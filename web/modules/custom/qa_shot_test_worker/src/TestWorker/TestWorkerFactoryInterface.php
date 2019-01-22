<?php

namespace Drupal\qa_shot_test_worker\TestWorker;

use Drupal\Component\Plugin\Factory\FactoryInterface;

/**
 * Interface TestWorkerFactoryInterface.
 *
 * @package Drupal\qa_shot_test_worker\TestWorker
 */
interface TestWorkerFactoryInterface extends FactoryInterface {

  /**
   * Get a worker by ID.
   *
   * @param string $workerId
   *   Test Worker ID.
   *
   * @return \Drupal\qa_shot_test_worker\TestWorker\TestWorkerInterface
   *   Test Worker instance.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function get($workerId): TestWorkerInterface;

}
