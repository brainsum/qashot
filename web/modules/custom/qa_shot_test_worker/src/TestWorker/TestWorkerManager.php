<?php

namespace Drupal\qa_shot_test_worker\TestWorker;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\qa_shot_test_worker\Annotation\TestWorker;
use Traversable;

/**
 * Plugin manager for TestWorkers.
 *
 * @package Drupal\qa_shot_test_worker\TestWorker
 *
 * @see \Drupal\qa_shot_test_worker\TestWorker\TestWorkerInterface
 * @see \Drupal\qa_shot_test_worker\TestWorker\TestWorkerBase
 * @see \Drupal\qa_shot_test_worker\TestWorker\TestWorkerFactory
 * @see \Drupal\qa_shot_test_worker\Annotation\TestWorker
 * @see plugin_api
 */
class TestWorkerManager extends DefaultPluginManager implements TestWorkerManagerInterface {

  /**
   * TestWorkerManager constructor.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(
    Traversable $namespaces,
    CacheBackendInterface $cacheBackend,
    ModuleHandlerInterface $moduleHandler
  ) {
    parent::__construct(
      'Plugin/TestWorker',
      $namespaces,
      $moduleHandler,
      TestWorkerInterface::class,
      TestWorker::class
    );

    $this->alterInfo('test_worker_info');
    $this->setCacheBackend($cacheBackend, 'test_worker_plugins');
  }

}
