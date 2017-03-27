<?php

namespace Drupal\qa_shot;

use Drupal\qa_shot\Entity\QAShotTestInterface;

/**
 * Base class for test abstractions.
 *
 * @package Drupal\qa_shot
 */
abstract class TestBase implements TestInterface {

  /**
   * {@inheritdoc}
   */
  abstract public function runTestBySettings(QAShotTestInterface $entity, $stage);

  /**
   * Run tests in Before/After mode.
   *
   * Use this function inside the runTestBySettings() method only.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $entity
   *   The test entity.
   * @param string $stage
   *   The provided stage.
   *
   * @return array
   *   The result array of the test run for the provided stage.
   */
  abstract protected function runBeforeAfterTest(QAShotTestInterface $entity, $stage);

  /**
   * Run tests in A/B mode.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $entity
   *   The test entity.
   *
   * @return array
   *   The result array of the test run.
   */
  abstract protected function runABTest(QAShotTestInterface $entity);

}
