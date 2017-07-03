<?php

namespace Drupal\qa_shot\Queue;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\qa_shot\Annotation\QAShotQueueWorker;

/**
 * Defines the queue worker manager for QAShot.
 *
 * Modified version of Drupal\Core\Queue\QueueWorkerManager.
 * It only searches for QAShotQueueWorkers.
 *
 * @see \Drupal\Core\Queue\QueueWorkerInterface
 * @see \Drupal\Core\Queue\QueueWorkerBase
 * @see \Drupal\Core\Annotation\QueueWorker
 * @see plugin_api
 */
class QAShotQueueWorkerManager extends DefaultPluginManager implements QueueWorkerManagerInterface {

  /**
   * Constructs an QueueWorkerManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cacheBackend, ModuleHandlerInterface $moduleHandler) {
    parent::__construct(
      'Plugin/QueueWorker',
      $namespaces,
      $moduleHandler,
      QAShotQueueWorkerInterface::class,
      QAShotQueueWorker::class
    );

    $this->setCacheBackend($cacheBackend, 'qa_shot_queue_plugins');
    $this->alterInfo('qa_shot_queue_info');
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $pluginId) {
    parent::processDefinition($definition, $pluginId);

    // Assign a default time if a cron is specified.
    if (isset($definition['cron'])) {
      $definition['cron'] += [
        'time' => 15,
      ];
    }
  }

}
