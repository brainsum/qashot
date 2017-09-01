<?php

namespace Drupal\backstopjs\Backstopjs;

use Drupal\backstopjs\Service\FileSystem;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Class BackstopJsFactory.
 *
 * @package Drupal\backstopjs\Backstopjs
 */
class BackstopjsWorkerFactory {

  /**
   * Instantiated backstopWorkers, keyed by name.
   *
   * @var array
   */
  protected $backstopWorkers = [];

  /**
   * The backstop file system to be used in the worker instance.
   *
   * @var \Drupal\backstopjs\Service\FileSystem
   */
  protected $backstopFileSystem;

  /**
   * The logger factory to be used in the worker instance.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerChannelFactory;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * BackstopJS worker manager.
   *
   * @var \Drupal\backstopjs\Backstopjs\BackstopjsWorkerManager
   */
  protected $workerManager;

  /**
   * BackstopJsFactory constructor.
   *
   * @param \Drupal\backstopjs\Backstopjs\BackstopjsWorkerManager $workerManager
   *   Plugin manager for Backstopjs Workers.
   * @param \Drupal\backstopjs\Service\FileSystem $backstopFileSystem
   *   BackstopJS FileSystem.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   Log channel factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The site config factory.
   */
  public function __construct(
    BackstopjsWorkerManager $workerManager,
    FileSystem $backstopFileSystem,
    LoggerChannelFactoryInterface $loggerChannelFactory,
    ConfigFactoryInterface $configFactory
  ) {
    $this->workerManager = $workerManager;

    $this->backstopFileSystem = $backstopFileSystem;
    $this->loggerChannelFactory = $loggerChannelFactory;
    $this->configFactory = $configFactory;
  }

  /**
   * Constructs a new BackstopJS worker.
   *
   * @param string $name
   *   The name of the queue to work with.
   *
   * @return \Drupal\backstopjs\Backstopjs\BackstopjsWorkerInterface
   *   The BackstopJS worker instance.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function get($name): BackstopjsWorkerInterface {
    if (!isset($this->backstopWorkers[$name])) {
      $this->backstopWorkers[$name] = $this->workerManager->createInstance($name);
    }
    return $this->backstopWorkers[$name];
  }

}
