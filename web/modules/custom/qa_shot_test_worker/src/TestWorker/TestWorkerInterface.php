<?php

namespace Drupal\qa_shot_test_worker\TestWorker;

/**
 * Interface TestWorkerInterface.
 *
 * @package Drupal\qa_shot_test_worker\TestWorker
 *
 * @todo: startTest()
 * @todo: stopTest()
 * @todo: Determine a sane API that:
 * - allows for both remote and local executions
 * - allows test steps to be executed separately.
 */
interface TestWorkerInterface {

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
  public function status();

}
