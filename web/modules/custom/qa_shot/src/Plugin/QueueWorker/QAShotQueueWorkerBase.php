<?php

namespace Drupal\qa_shot\Plugin\QueueWorker;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\qa_shot\Entity\QAShotTestInterface;
use Drupal\qa_shot\Queue\QAShotQueueWorkerInterface;
use Drupal\qa_shot\Service\TestNotification;
use Drupal\qa_shot\TestBackendInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class to implement TestRunner functionality.
 *
 * @package Drupal\qa_shot\Plugin\QueueWorker
 */
abstract class QAShotQueueWorkerBase extends PluginBase implements QAShotQueueWorkerInterface, ContainerFactoryPluginInterface {

  /**
   * TestBackend service.
   *
   * @var \Drupal\qa_shot\TestBackendInterface
   */
  protected $testBackend;

  /**
   * The QAShot Test Notification service.
   *
   * @var \Drupal\qa_shot\Service\TestNotification
   */
  protected $notification;

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
   * @param \Drupal\qa_shot\Service\TestNotification $notification
   *   The notification service.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    TestBackendInterface $testBackend,
    TestNotification $notification
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
    $this->testBackend = $testBackend;
    $this->notification = $notification;
  }

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
      $container->get('qa_shot.test_notification')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($item, QAShotTestInterface $entity) {
    $this->testBackend->runTestBySettings($entity, $item->stage);
    $this->notification->sendNotification($entity, $item->origin);
    $this->testBackend->removeUnusedFilesForTest($entity);
  }

}
