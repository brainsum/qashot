<?php

namespace Drupal\backstopjs\Component;

use Drupal\backstopjs\Form\BackstopjsSettingsForm;
use Drupal\backstopjs\Service\FileSystem;
use Drupal\Component\Render\HtmlEscapedText;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Class BackstopJsFactory.
 *
 * @package Drupal\backstopjs\Component
 */
class BackstopJsFactory {

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
   * BackstopJsFactory constructor.
   *
   * @param \Drupal\backstopjs\Service\FileSystem $backstopFileSystem
   *   BackstopJS FileSystem.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   Log channel factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The site config factory.
   */
  public function __construct(
    FileSystem $backstopFileSystem,
    LoggerChannelFactoryInterface $loggerChannelFactory,
    ConfigFactoryInterface $configFactory
  ) {
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
   * @return \Drupal\backstopjs\Component\BackstopJSInterface
   *   The BackstopJS worker instance.
   *
   * @throws \InvalidArgumentException
   */
  public function get($name): BackstopJSInterface {
    if (!isset($this->backstopWorkers[$name])) {
      switch ($name) {
        case BackstopjsSettingsForm::LOCAL_SUITE:
          $this->backstopWorkers[$name] = new LocalBackstopJS(
            $this->backstopFileSystem,
            $this->loggerChannelFactory,
            $this->configFactory
          );
          break;

        case BackstopjsSettingsForm::REMOTE_SUITE:
          $this->backstopWorkers[$name] = new RemoteBackstopJS(
            $this->backstopFileSystem,
            $this->loggerChannelFactory,
            $this->configFactory
          );
          break;

        default:
          throw new \InvalidArgumentException('The ' . new HtmlEscapedText($name) . ' is not a valid BackstopJS worker name.');
      }
    }
    return $this->backstopWorkers[$name];
  }

}
