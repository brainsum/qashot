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
   * @return mixed
   *   The status.
   *
   * @todo: Maybe return a more complex status, e.g with current test id, etc.
   *
   * @todo: Add an enum instead of the string.
   */
  abstract public function status();

}
