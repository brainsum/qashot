<?php

namespace Drupal\backstopjs\Component;

use Drupal\backstopjs\Service\FileSystem;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\qa_shot\Entity\QAShotTestInterface;

/**
 * Class BackstopJSBase.
 *
 * Base class for BackstopJS.
 *
 * @package Drupal\backstopjs\Component
 */
abstract class BackstopJSBase implements BackstopJSInterface {

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
   * LocalBackstopJS constructor.
   *
   * @param \Drupal\backstopjs\Service\FileSystem $backstopFileSystem
   *   The BackstopJS file system service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger channel factory.
   */
  public function __construct(FileSystem $backstopFileSystem, LoggerChannelFactoryInterface $loggerChannelFactory) {
    $this->backstopFileSystem = $backstopFileSystem;
    $this->logger = $loggerChannelFactory->get('backstopjs');
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
  abstract public function run(string $engine, string $command, QAShotTestInterface $entity): array;

}
