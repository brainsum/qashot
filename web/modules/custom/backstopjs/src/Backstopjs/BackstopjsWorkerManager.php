<?php

namespace Drupal\backstopjs\Backstopjs;

use function array_filter;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\qa_shot_test_worker\TestWorker\TestWorkerInterface;
use Drupal\qa_shot_test_worker\TestWorker\TestWorkerManager;
use Traversable;

/**
 * Class BackstopjsWorkerManager.
 *
 * @package Drupal\backstopjs\Backstopjs
 *
 * @see \Drupal\backstopjs\Backstopjs\BackstopjsWorkerInterface
 * @see \Drupal\backstopjs\Backstopjs\BackstopjsWorkerBase
 * @see \Drupal\qa_shot_test_worker\Annotation\TestWorker
 * @see plugin_api
 */
class BackstopjsWorkerManager extends TestWorkerManager {

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
  public function __construct(
    Traversable $namespaces,
    CacheBackendInterface $cacheBackend,
    ModuleHandlerInterface $moduleHandler
  ) {
    parent::__construct(
      $namespaces,
      $cacheBackend,
      $moduleHandler
    );

    $this->setCacheBackend($cacheBackend, 'backstopjs_worker_plugins');
    $this->alterInfo('backstopjs_worker_info');
  }

  /**
   * Creates a pre-configured instance of a Test Worker.
   *
   * @param string $pluginId
   *   The ID of the plugin being instantiated.
   * @param array $configuration
   *   An array of configuration relevant to the plugin instance.
   *
   * @return \Drupal\qa_shot_test_worker\TestWorker\TestWorkerInterface
   *   A fully configured plugin instance.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If the instance cannot be created, such as if the ID is invalid.
   */
  public function createInstance($pluginId, array $configuration = []): TestWorkerInterface {
    $definition = $this->getDefinition($pluginId);

    // @todo: This is likely not needed and the module should only define
    // the workers, not use them.
    if (
      ($provider = $definition['provider'])
      && $provider !== 'backstopjs'
    ) {
      throw new PluginException("Constructing a new '$pluginId' worker is not allowed here.");
    }

    /** @var \Drupal\qa_shot_test_worker\TestWorker\TestWorkerInterface $instance */
    $instance = parent::createInstance($pluginId, $configuration);
    return $instance;
  }

  /**
   * Gets the definition of all plugins for this module.
   *
   * @return array
   *   The definitions for this module.
   */
  public function getDefinitions(): array {
    $definitions = parent::getDefinitions() ?? [];

    return array_filter($definitions, function ($definition) {
      return $definition['provider'] === 'backstopjs';
    });
  }

}
