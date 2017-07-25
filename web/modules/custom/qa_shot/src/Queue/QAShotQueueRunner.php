<?php

namespace Drupal\qa_shot\Queue;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\qa_shot\Entity\QAShotTestInterface;
use Drush\Log\LogLevel;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\Queue\SuspendQueueException;
use Drush\Queue\QueueException;

/**
 * Class QAShotQueueRunner.
 *
 * Modified version of Drush\Queue\Queue8.
 *
 * @package Drupal\qa_shot\Queue
 */
class QAShotQueueRunner {

  /**
   * The manager.
   *
   * @var \Drupal\qa_shot\Queue\QAShotQueueWorkerManager
   */
  protected $workerManager;

  /**
   * Queue factory.
   *
   * @var \Drupal\qa_shot\Queue\QAShotQueueFactory
   */
  protected $queueFactory;

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $testStorage;

  protected $logger;

  /**
   * Keep track of queue definitions.
   *
   * @var array
   */
  protected static $queues;

  /**
   * QAShotQueueRunner constructor.
   *
   * @param \Drupal\qa_shot\Queue\QAShotQueueWorkerManager $manager
   *   The queue worker manager.
   * @param \Drupal\qa_shot\Queue\QAShotQueueFactory $queueFactory
   *   The queue factory.
   */
  public function __construct(QAShotQueueWorkerManager $manager, QAShotQueueFactory $queueFactory, EntityTypeManagerInterface $entityTypeManager, LoggerChannelFactoryInterface $loggerFactory) {
    $this->workerManager = $manager;
    $this->queueFactory = $queueFactory;
    $this->testStorage = $entityTypeManager->getStorage('qa_shot_test');
    $this->logger = $loggerFactory->get('qa_shot_queue');
  }

  /**
   * Lists all available queues.
   */
  public function listQueues(): array {
    $result = array();
    foreach (array_keys($this->getQueues()) as $name) {
      $q = $this->getQueue($name);
      $result[$name] = array(
        'queue' => $name,
        'items' => $q->numberOfItems(),
        'class' => get_class($q),
      );
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function getInfo($name) {
    $queues = $this->getQueues();
    if (!isset($queues[$name])) {
      throw new \Exception(t('Could not find the !name queue.', ['!name' => $name]));
    }
    return $queues[$name];
  }

  /**
   * {@inheritdoc}
   */
  public function getQueues(): array {
    if (NULL === static::$queues) {
      static::$queues = [];
      foreach ($this->workerManager->getDefinitions() as $name => $info) {
        static::$queues[$name] = $info;
      }
    }
    return static::$queues;
  }

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\qa_shot\Queue\QAShotQueueInterface
   *   The queue instance.
   */
  public function getQueue($name): QAShotQueueInterface {
    return $this->queueFactory->get($name);
  }

  /**
   * Runs a given queue.
   *
   * Taken from Drush\Queue\Queue8.
   *
   * @param string $name
   *   The name of the queue to run.
   * @param int|null $timeLimit
   *   The maximum number of seconds that the queue can run. By default the
   *   queue will be run as long as possible.
   *
   * @return int
   *   The number of items successfully processed from the queue.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\Database\InvalidQueryException
   * @throws \Exception
   */
  public function run($name, $timeLimit = 0): int {
    // @todo: research this https://stackoverflow.com/questions/70855/how-can-one-use-multi-threading-in-php-applications
    $worker = $this->workerManager->createInstance($name);
    $end = time() + $timeLimit;
    $queue = $this->getQueue($name);
    $count = 0;

    while (time() < $end && ($item = $queue->claimItem($timeLimit))) {
      /** @var \Drupal\qa_shot\Entity\QAShotTestInterface $entity */
      $entity = $this->testStorage->load($item->tid);
      // If the entity has been deleted while queued remove it, log an error.
      if (NULL === $entity) {
        $queue->deleteItem($item);
        $this->logger->error('The entity with id ' . $item->tid . ' has been deleted while it was queued.');
        drupal_set_message('Cron: The entity with id ' . $item->tid . ' has been deleted while it was queued.', 'error');
        continue;
      }

      if ($queue->getItemStatus($item->tid) === QAShotQueue::QUEUE_STATUS_RUNNING) {
        drupal_set_message('Cron: The entity with id ' . $item->tid . ' tried to run, while it was already running.', 'error');
        continue;
      }

      // If there's already a running item, requeue.
      if ($queue->numberOfRunningItems() > 0) {
        drupal_set_message('Cron: The entity with id ' . $item->tid . ' tried to run while another test was already running.', 'error');
        // @todo: Update lease time maybe?
        continue;
      }

      // @todo: Maybe update 'expire' with the machine learning determined 'estimated run time'.

      // Set status to run.
      $this->updateEntityStatus(QAShotQueue::QUEUE_STATUS_RUNNING, $entity, $queue, $item);

      try {
        drupal_set_message(t('Processing test @id from @name queue.', ['@name' => $name, '@id' => $item->tid]));
        $worker->processItem($item, $entity);
        $queue->deleteItem($item);
        $count++;

        // Set entity status to idle.
        $this->updateEntityStatus(QAShotQueue::QUEUE_STATUS_IDLE, $entity);
      }
      catch (RequeueException $e) {
        // @todo: Set item to error
        $this->logger->warning($e->getMessage());
        // The worker requested the task to be immediately requeued.
        $item->status = QAShotQueue::QUEUE_STATUS_WAITING;
        $queue->releaseItem($item);
        $this->updateEntityStatus(QAShotQueue::QUEUE_STATUS_WAITING, $entity);
      }
      catch (SuspendQueueException $e) {
        // @todo: Set item to error
        $this->logger->error($e->getMessage());
        // If the worker indicates there is a problem with the whole queue,
        // release the item and skip to the next queue.
        $item->status = QAShotQueue::QUEUE_STATUS_ERROR;
        $queue->releaseItem($item);
        $this->updateEntityStatus(QAShotQueue::QUEUE_STATUS_ERROR, $entity);
        continue;
      }
      catch (\Exception $e) {
        $this->logger->error($e->getMessage());
        // In case of any other kind of exception, log it and leave the item
        // in the queue to be processed again later.
        // Set entity status to error.
        $this->updateEntityStatus(QAShotQueue::QUEUE_STATUS_ERROR, $entity, $queue, $item);
        continue;
      }
    }

    return $count;
  }

  /**
   * Updates the entity and the queue item status.
   *
   * @param string $status
   *   The new status.
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $entity
   *   The entity.
   * @param \Drupal\qa_shot\Queue\QAShotQueueInterface|null $queue
   *   The queue, if update is necessary.
   *   If queue is not NULL pass the item as well.
   * @param \stdClass|null $item
   *   The queue item, if update is necessary.
   *   If item is not NULL pass the queue as well.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function updateEntityStatus($status, QAShotTestInterface $entity, QAShotQueueInterface $queue = NULL, \stdClass $item = NULL) {
    // Set entity status to running.
    try {
      if (NULL !== $item && NULL !== $queue) {
        $item->status = $status;
        $queue->updateItemStatus($item);
      }

      $this->logger->info(
        t('Test with ID #@testID status changed to @status.', [
          '@testID' => $entity->id(),
          '@status' => $status,
        ])
      );

    }
    catch (EntityStorageException $e) {
      $this->logger->warning('Updating the test with ID #@testID status to "@status" failed. @msg', [
        '@testID' => $entity->id(),
        '@status' => $status,
        '@msg' => $e->getMessage(),
      ]);
      if (NULL !== $item && NULL !== $queue) {
        // If updating the entity fails, remove it from the queue.
        $queue->deleteItem($item);
      }

      // Throw the caught exception again.
      throw $e;
    }
  }

}
