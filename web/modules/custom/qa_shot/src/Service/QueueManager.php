<?php

namespace Drupal\qa_shot\Service;

use Drupal\backstopjs\Backstopjs\BackstopjsWorkerFactory;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\qa_shot\Entity\QAShotTestInterface;
use Drupal\qa_shot\Queue\QAShotQueueFactory;

/**
 * Class QueueManager.
 *
 * @package Drupal\qa_shot\Service
 */
class QueueManager {

  use StringTranslationTrait;

  /**
   * The worker factory for BackstopJS.
   *
   * @var \Drupal\backstopjs\Backstopjs\BackstopjsWorkerFactory
   */
  protected $backstopWorkerFactory;

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $user;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Worker for local queue.
   *
   * @var \Drupal\qa_shot\Queue\QAShotQueueInterface
   */
  protected $localQueue;

  /**
   * Worker for remote queue.
   *
   * @var \Drupal\qa_shot\Queue\QAShotQueueInterface
   */
  protected $remoteQueue;

  /**
   * The current QAShot config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   *
   * @note: Yes, currently this is the backstopjs config.
   * @todo: Refactor me.
   */
  protected $config;

  /**
   * QueueManager constructor.
   *
   * @param \Drupal\qa_shot\Queue\QAShotQueueFactory $queueFactory
   *   QAShot Queue factory.
   * @param \Drupal\backstopjs\Backstopjs\BackstopjsWorkerFactory $backstopjsWorkerFactory
   *   QAShot BackstopJS factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   Logger Channel factory.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    QAShotQueueFactory $queueFactory,
    BackstopjsWorkerFactory $backstopjsWorkerFactory,
    LoggerChannelFactoryInterface $loggerChannelFactory,
    MessengerInterface $messenger,
    AccountProxyInterface $currentUser,
    TimeInterface $time,
    ConfigFactoryInterface $configFactory
  ) {
    $this->backstopWorkerFactory = $backstopjsWorkerFactory;
    $this->logger = $loggerChannelFactory;
    $this->messenger = $messenger;
    $this->user = $currentUser;
    $this->time = $time;
    $this->config = $configFactory->get('backstopjs.settings');

    $this->localQueue = $queueFactory->get('cron_run_qa_shot_test');
    $this->remoteQueue = $queueFactory->get('qa_shot_remote_queue');
  }

  /**
   * Add a test to the queue.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $test
   *   The QAShot test.
   * @param string $stage
   *   The test stage.
   * @param string $origin
   *   The test origin.
   *
   * @return string
   *   Status string.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function addTest(QAShotTestInterface $test, string $stage = NULL, string $origin = 'drupal'): string {
    // Add the test entity and the requested stage to the item.
    // @todo: Change?
    $queueItem = new \stdClass();
    $queueItem->tid = $test->id();
    $queueItem->stage = $stage;
    $queueItem->origin = $origin;
    $queueItem->data = NULL;

    if ('remote' === $this->config->get('suite.location')) {
      return $this->addRemoteTest($test, $queueItem);
    }

    return $this->addLocalTest($test, $queueItem);
  }

  /**
   * Add test to the local queue.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $test
   *   The QAShot test.
   * @param \stdClass $item
   *   The prepared queue item.
   *
   * @return string
   *   Status string.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function addLocalTest(QAShotTestInterface $test, \stdClass $item): string {
    // Try to add the test to the drupal queue.
    if (FALSE !== $this->localQueue->createItem($item)) {
      try {
        // If we successfully added it to the queue, we set
        // the current user as the initiator and also save the current time.
        $test->setInitiatorId($this->user->id());
        $test->setInitiatedTime($this->time->getRequestTime());
        $test->save();
      }
      catch (EntityStorageException $e) {
        $this->logger->get('qa_shot')->warning('Saving the entity data in the QueueManager failed. @msg', [
          '@msg' => $e->getMessage(),
        ]);
        // If updating the entity fails, remove it from the queue.
        $this->localQueue->deleteItem($item);
        // Throw the caught exception again.
        throw $e;
      }

      $this->messenger->addMessage($this->t('The test has been queued to run. Check back later for the results.'), 'info');
      return 'added_to_queue';
    }

    // If we can't create, it's already in the queue.
    return 'already_in_queue';
  }

  /**
   * Add test to the remote queue.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $test
   *   The QAShot test.
   * @param \stdClass $item
   *   The prepared queue item.
   *
   * @return string
   *   Status string.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function addRemoteTest(QAShotTestInterface $test, \stdClass $item): string {
    // @todo: Move to remote queue.
    /** @var \Drupal\backstopjs\Backstopjs\BackstopjsWorkerInterface $remoteWorker */
    $remoteWorker = $this->backstopWorkerFactory->get('remote');
    $remoteWorker->run('chrome', 'reference', $test);

    // Try to add the test to the drupal queue.
    if (FALSE !== $this->remoteQueue->createItem($item)) {
      try {
        // If we successfully added it to the queue, we set
        // the current user as the initiator and also save the current time.
        $test->setInitiatorId($this->user->id());
        $test->setInitiatedTime($this->time->getRequestTime());
        $test->save();
      }
      catch (EntityStorageException $e) {
        $this->logger->get('qa_shot')->warning('Saving the entity data in the QueueManager failed. @msg', [
          '@msg' => $e->getMessage(),
        ]);
        // If updating the entity fails, remove it from the queue.
        $this->remoteQueue->deleteItem($item);
        // Throw the caught exception again.
        throw $e;
      }

      $this->messenger->addMessage($this->t('The test has been queued to run. Check back later for the results.'), 'info');
      return 'added_to_queue';
    }

    // If we can't create, it's already in the queue.
    return 'already_in_queue';
  }

}
