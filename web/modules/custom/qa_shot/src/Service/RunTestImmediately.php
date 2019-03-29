<?php

namespace Drupal\qa_shot\Service;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\qa_shot\Entity\QAShotTestInterface;
use Drupal\qa_shot\Exception\QAShotBaseException;
use Drupal\qa_shot\Queue\QAShotQueue;
use Drupal\qa_shot\Queue\QAShotQueueFactory;
use Drupal\qa_shot\Queue\QAShotQueueInterface;
use Drupal\qa_shot\Queue\QAShotQueueWorkerManager;
use Exception;
use stdClass;

/**
 * Class RunTestImmediately.
 *
 * @package Drupal\qa_shot\Service
 */
class RunTestImmediately {

  use StringTranslationTrait;

  /**
   * Keep track of queue definitions.
   *
   * @var array
   */
  protected static $queues;

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
   * The test storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $testStorage;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * QAShotQueueRunner constructor.
   *
   * @param \Drupal\qa_shot\Queue\QAShotQueueWorkerManager $manager
   *   The queue worker manager.
   * @param \Drupal\qa_shot\Queue\QAShotQueueFactory $queueFactory
   *   The queue factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(QAShotQueueWorkerManager $manager, QAShotQueueFactory $queueFactory, EntityTypeManagerInterface $entityTypeManager, LoggerChannelFactoryInterface $loggerFactory) {
    $this->workerManager = $manager;
    $this->queueFactory = $queueFactory;
    $this->testStorage = $entityTypeManager->getStorage('qa_shot_test');
    $this->logger = $loggerFactory->get('qa_shot_queue');
  }

  /**
   * Runs a given queue.
   *
   * Taken from Drush\Queue\Queue8.
   *
   * @param string $id
   *   Task which you want to run now.
   *
   * @return int
   *   The number of items successfully processed from the queue.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\Database\InvalidQueryException
   * @throws \Exception
   * @throws \Drupal\qa_shot\Exception\QAShotBaseException
   */
  public function run($id): int {
    $name = 'cron_run_qa_shot_test';
    $worker = $this->workerManager->createInstance($name);
    $queue = $this->getQueue($name);
    $item = $queue->getItem($id);

    /** @var \Drupal\qa_shot\Entity\QAShotTestInterface $entity */
    $entity = $this->testStorage->load($item->tid);
    // If the entity has been deleted while queued remove it, log an error.
    if (NULL === $entity) {
      $queue->deleteItem($item);
      $this->logger->error('The entity with id ' . $item->tid . ' has been deleted while it was queued.');
      throw new QAShotBaseException('Cron: The entity with id ' . $item->tid . ' has been deleted while it was queued.');
    }

    if ($queue->getItemStatus($item->tid) === QAShotQueue::QUEUE_STATUS_RUNNING) {
      throw new QAShotBaseException('Cron: The entity with id ' . $item->tid . ' tried to run, while it was already running.');
    }

    // @todo: Maybe update 'expire' with the machine learning determined 'estimated run time'.
    // Set status to run.
    $this->updateEntityStatus(QAShotQueue::QUEUE_STATUS_RUNNING, $entity, $queue, $item);

    try {
      $worker->processItem($item, $entity);
      $queue->deleteItem($item);

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
      throw new QAShotBaseException($e->getMessage());
    }
    catch (SuspendQueueException $e) {
      // @todo: Set item to error
      $this->logger->error($e->getMessage());
      // If the worker indicates there is a problem with the whole queue,
      // release the item and skip to the next queue.
      $item->status = QAShotQueue::QUEUE_STATUS_ERROR;
      $queue->releaseItem($item);
      $this->updateEntityStatus(QAShotQueue::QUEUE_STATUS_ERROR, $entity);
      throw new QAShotBaseException($e->getMessage());
    }
    catch (Exception $e) {
      $this->logger->error($e->getMessage());
      // In case of any other kind of exception, log it and leave the item
      // in the queue to be processed again later.
      // Set entity status to error.
      $this->updateEntityStatus(QAShotQueue::QUEUE_STATUS_ERROR, $entity, $queue, $item);
      throw new QAShotBaseException($e->getMessage());
    }

    return 1;
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
  private function updateEntityStatus($status, QAShotTestInterface $entity, QAShotQueueInterface $queue = NULL, stdClass $item = NULL): void {
    // Set entity status to running.
    try {
      if (NULL !== $item && NULL !== $queue) {
        $item->status = $status;
        $queue->updateItemStatus($item);
      }

      $this->logger->info(
        $this->t('Test with ID #@testID status changed to @status.', [
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
