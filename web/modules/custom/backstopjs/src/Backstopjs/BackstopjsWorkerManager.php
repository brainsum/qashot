<?php

namespace Drupal\backstopjs\Backstopjs;

use Drupal\backstopjs\Annotation\BackstopjsWorker;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Class BackstopjsWorkerManager.
 *
 * @package Drupal\backstopjs\Backstopjs
 *
 * @see \Drupal\backstopjs\Backstopjs\BackstopjsWorkerInterface
 * @see \Drupal\backstopjs\Backstopjs\BackstopjsWorkerBase
 * @see \Drupal\backstopjs\Annotation\BackstopjsWorker
 * @see plugin_api
 */
class BackstopjsWorkerManager extends DefaultPluginManager {

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
      'Plugin/BackstopjsWorker',
      $namespaces,
      $moduleHandler,
      BackstopjsWorkerInterface::class,
      BackstopjsWorker::class
    );

    $this->setCacheBackend($cacheBackend, 'backstopjs_worker_plugins');
    $this->alterInfo('backstopjs_worker_info');
  }

}
