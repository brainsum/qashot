<?php

namespace Drupal\qa_shot\Plugin\QueueWorker;

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
      $container->get('qa_shot.test_notification')
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
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    TestBackendInterface $testBackend,
    TestQueueState $queueState,
    LoggerChannelFactoryInterface $loggerChannelFactory,
    TestNotification $notification
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
    $this->testBackend = $testBackend;
    $this->queueState = $queueState;
    $this->logger = $loggerChannelFactory->get('qa_shot');
    $this->notification = $notification;
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    /** @var \Drupal\qa_shot\Entity\QAShotTestInterface $entity */
    $entity = $data->entity;

    try {
      $this->queueState->setToRunning($entity->id());
      $this->testBackend->runTestBySettings($entity, $data->stage);
      $this->queueState->remove($entity->id());
      $this->notification->sendNotification($entity, 'qa_shot');
    }
    catch (QAShotBaseException $e) {
      $this->queueState->setToError($entity->id());
      $this->logger->error($e->getMessage());
      throw new RequeueException($e->getMessage(), $e->getCode(), $e);
    }
  }

}
