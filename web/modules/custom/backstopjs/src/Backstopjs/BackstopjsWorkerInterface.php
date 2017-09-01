<?php

namespace Drupal\backstopjs\Backstopjs;

use Drupal\qa_shot\Entity\QAShotTestInterface;

/**
 * Interface BackstopJSInterface.
 *
 * @package Drupal\backstopjs\Backstopjs
 */
interface BackstopjsWorkerInterface {

  /**
   * Returns the status of backstopjs.
   *
   * @return string
   *   The status as JSON encoded string.
   */
  public function getStatus(): string;

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
   * @param string $engine
   *   The requested engine; phantomjs or slimerjs.
   * @param string $command
   *   The command; reference or test.
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $entity
   *   The test entity.
   *
   * @return array
   *   The result array.
   */
  public function run(string $engine, string $command, QAShotTestInterface $entity): array;

}
