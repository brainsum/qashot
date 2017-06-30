<?php

namespace Drupal\qa_shot\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\qa_shot\Service\TestNotification;
use Drupal\qa_shot\TestBackendInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class to implement TestRunner functionality.
 *
 * @package Drupal\qa_shot\Plugin\QueueWorker
 */
abstract class QAShotQueueWorkerBase extends QueueWorkerBase implements ContainerFactoryPluginInterface {

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
    TestNotification $notification,
    EntityTypeManagerInterface $entityTypeManager
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
    $this->testBackend = $testBackend;
    $this->notification = $notification;
    $this->testStorage = $entityTypeManager->getStorage('qa_shot_test');
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($item) {
    /** @var \Drupal\qa_shot\Entity\QAShotTestInterface $entity */
    $entity = $this->testStorage->load($item->tid);

    $this->testBackend->runTestBySettings($entity, $item->stage);
    $this->notification->sendNotification($entity, $item->origin);
    $this->testBackend->removeUnusedFilesForTest($entity);
  }

}
