<?php

namespace Drupal\qa_shot_test_worker\TestWorker;

/**
 * Class TestWorkerFactory.
 *
 * @package Drupal\qa_shot_test_worker\TestWorker
 */
class TestWorkerFactory implements TestWorkerFactoryInterface {

  /**
   * Array of worker instances.
   *
   * @var \Drupal\qa_shot_test_worker\TestWorker\TestWorkerInterface[]
   */
  protected $workers;

  /**
   * The Test Worker manager instance.
   *
   * @var \Drupal\qa_shot_test_worker\TestWorker\TestWorkerManagerInterface
   */
  protected $workerManager;

  /**
   * TestWorkerFactory constructor.
   *
   * @param \Drupal\qa_shot_test_worker\TestWorker\TestWorkerManagerInterface $workerManager
   *   The Test Worker manager instance.
   */
  public function __construct(
    TestWorkerManagerInterface $workerManager
  ) {
    $this->workerManager = $workerManager;
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance(
    $pluginId,
    array $configuration = []
  ): TestWorkerInterface {
    if (empty($this->workers[$pluginId])) {
      $this->workers[$pluginId] = $this->workerManager->createInstance($pluginId, $configuration);
    }

    return $this->workers[$pluginId];
  }

  /**
   * {@inheritdoc}
   */
  public function get($workerId): TestWorkerInterface {
    return $this->createInstance($workerId);
  }

}
