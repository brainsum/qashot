<?php

namespace Drupal\qa_shot_test_worker\TestWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class TestWorkerBase.
 *
 * @package Drupal\qa_shot_test_worker\TestWorker
 */
abstract class TestWorkerBase extends PluginBase implements TestWorkerInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $pluginId,
    $pluginDefinition
  ) {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition
    );
  }

  /**
   * Check the worker status.
   *
   * E.g: Remote -> API call to the remote.
   * E.g: Local -> check if the process is running.
   *
   * @todo: Add an enum instead of the string.
   * @todo: Maybe return a more complex status, e.g with current test id, etc.
   *
   * @return mixed
   *   The status.
   */
  abstract public function status();

}
