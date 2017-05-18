<?php

namespace Drupal\qa_shot\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\RequeueException;
use Drupal\qa_shot\Exception\QAShotBaseException;
use Drupal\qa_shot\Service\TestNotification;
use Drupal\qa_shot\Service\TestQueueState;
use Drupal\qa_shot\TestBackendInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class to implement TestRunner functionality.
 *
 * @package Drupal\qa_shot\Plugin\QueueWorker
 */
abstract class TestRunnerBase extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * TestBackend service.
   *
   * @var \Drupal\qa_shot\TestBackendInterface
   */
  protected $testBackend;

  /**
   * The custom queue state service.
   *
   * @var \Drupal\qa_shot\Service\TestQueueState
   */
  protected $queueState;

  /**
   * Logger service for the qa_shot logging channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The QAShot Test Notification service.
   *
   * @var \Drupal\qa_shot\Service\TestNotification
   */
  protected $notification;

  /**
   * QAShot Test storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $testStorage;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('backstopjs.backstop'),
      $container->get('qa_shot.test_queue_state'),
      $container->get('logger.factory'),
      $container->get('qa_shot.test_notification'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * TestRunBase constructor.
   *
   * @param array $configuration
   *   Config.
   * @param string $pluginId
   *   Plugin ID.
   * @param mixed $pluginDefinition
   *   Plugin def.
   * @param \Drupal\qa_shot\TestBackendInterface $testBackend
   *   The test backend (e.g BackstopJS).
   * @param \Drupal\qa_shot\Service\TestQueueState $queueState
   *   The queue state service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger channel factory service.
   * @param \Drupal\qa_shot\Service\TestNotification $notification
   *   The notification service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    TestBackendInterface $testBackend,
    TestQueueState $queueState,
    LoggerChannelFactoryInterface $loggerChannelFactory,
    TestNotification $notification,
    EntityTypeManagerInterface $entityTypeManager
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
    $this->testBackend = $testBackend;
    $this->queueState = $queueState;
    $this->logger = $loggerChannelFactory->get('qa_shot');
    $this->notification = $notification;
    $this->testStorage = $entityTypeManager->getStorage('qa_shot_test');
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    drupal_set_message('Cron: starting test with ID ' . $data->entityId);
    /** @var \Drupal\qa_shot\Entity\QAShotTestInterface $entity */
    $entity = $this->testStorage->load($data->entityId);

    // If the entity has been deleted while queued remove it, log an error
    // and return, so it gets removed from the DB queue as well.
    if (NULL === $entity) {
      $this->queueState->remove($data->entityId);
      $this->logger->error('The entity with id ' . $data->entityId . ' has been deleted while it was queued.');
      drupal_set_message('Cron: The entity with id ' . $data->entityId . ' has been deleted while it was queued.', 'error');
      return;
    }

    // @todo: Maybe add a custom queue processor.
    // If there's already a running item, requeue.
    if ($this->queueState->hasRunningItem()) {
      drupal_set_message('Cron: The entity with id ' . $data->entityId . ' tried to run while another test was already running.', 'error');
      throw new RequeueException('The entity with id ' . $data->entityId . ' tried to run while another test was already running.');
    }

    try {
      $this->queueState->setToRunning($data->entityId);
      $this->testBackend->runTestBySettings($entity, $data->stage);
      // @todo: Don't remove, just set to IDLE?
      // Remove only when entity is deleted.
      // Check the queue table in the DB for inconsistencies.
      $this->queueState->remove($data->entityId);
      $this->notification->sendNotification($entity, $data->origin, 'qa_shot');
      $this->testBackend->removeUnusedFilesForTest($entity);
    }
    catch (QAShotBaseException $e) {
      $this->queueState->setToError($data->entityId);
      $this->logger->alert($e->getMessage());
      return;
    }
    catch (\Exception $e) {
      // If we get any other errors, just remove the item from core queue
      // and our custom one as well.
      $this->queueState->setToError($data->entityId);
      $this->logger->error($e->getMessage());
      return;
    }
  }

}
