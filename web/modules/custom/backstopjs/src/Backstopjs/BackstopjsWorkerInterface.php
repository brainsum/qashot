<?php

namespace Drupal\backstopjs\Backstopjs;

use Drupal\qa_shot\Entity\QAShotTestInterface;
use Drupal\qa_shot_test_worker\TestWorker\TestWorkerInterface;

/**
 * Interface BackstopJSInterface.
 *
 * @package Drupal\backstopjs\Backstopjs
 */
interface BackstopjsWorkerInterface extends TestWorkerInterface {

  /**
   * Checks whether Backstop is running or not.
   *
   * @throws \Drupal\backstopjs\Exception\BackstopAlreadyRunningException
   *   If BackstopJS is already running, throw an exception.
   */
  public function checkRunStatus();

  /**
   * Run backstop with local binaries.
   *
   * @param string $browser
   *   The requested browser; phantomjs, firefox or chrome.
   * @param string $command
   *   The command; reference or test.
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $entity
   *   The test entity.
   *
   * @return array
   *   The result array.
   */
  public function run(string $browser, string $command, QAShotTestInterface $entity): array;

}
