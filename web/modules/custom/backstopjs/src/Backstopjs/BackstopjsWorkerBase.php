<?php

namespace Drupal\backstopjs\Backstopjs;

use Drupal\backstopjs\Service\FileSystem;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\qa_shot\Entity\QAShotTestInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class BackstopJSBase.
 *
 * Base class for BackstopJS.
 *
 * @package Drupal\backstopjs\Backstopjs
 */
abstract class BackstopjsWorkerBase extends PluginBase implements BackstopjsWorkerInterface, ContainerFactoryPluginInterface {

  use ContainerAwareTrait;

  /**
   * Backstop FileSystem.
   *
   * @var \Drupal\backstopjs\Service\FileSystem
   */
  protected $backstopFileSystem;

  /**
   * Logger for backstopjs.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The backstopjs module config.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * {@inheritdoc}
   *
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('backstopjs.file_system'),
      $container->get('logger.factory'),
      $container->get('config.factory')
    );
  }

  /**
   * LocalBackstopJS constructor.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \Drupal\backstopjs\Service\FileSystem $backstopFileSystem
   *   The BackstopJS file system service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger channel factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The site config factory.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    array $plugin_definition,
    FileSystem $backstopFileSystem,
    LoggerChannelFactoryInterface $loggerChannelFactory,
    ConfigFactoryInterface $configFactory
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->backstopFileSystem = $backstopFileSystem;
    $this->logger = $loggerChannelFactory->get('backstopjs');
    $this->config = $configFactory->get('backstopjs.settings');
  }

  /**
   * {@inheritdoc}
   */
  abstract public function checkRunStatus();

  /**
   * {@inheritdoc}
   */
  abstract public function getStatus(): string;

  /**
   * {@inheritdoc}
   */
  abstract public function run(string $browser, string $command, QAShotTestInterface $entity): array;

}
