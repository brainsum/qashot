<?php

namespace Drupal\qa_shot;

use Drupal\Core\Messenger\MessengerTrait;
use Drupal\qa_shot\Entity\QAShotTestInterface;

/**
 * Base class for test abstractions.
 *
 * @package Drupal\qa_shot
 */
abstract class TestBackendBase implements TestBackendInterface {

  use MessengerTrait;

  /**
   * {@inheritdoc}
   */
  abstract public function runTestBySettings(QAShotTestInterface $entity, $stage);

  /**
   * {@inheritdoc}
   */
  abstract public function clearFiles(QAShotTestInterface $entity);

  /**
   * {@inheritdoc}
   */
  abstract public function removeUnusedFilesForTest(QAShotTestInterface $entity);

  /**
   * Run tests in Before/After mode.
   *
   * Use this function inside the runTestBySettings() method only.
   * Before/After should compare a single site with itself at two different
   * times.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $entity
   *   The test entity.
   * @param string $stage
   *   The provided stage.
   *
   * @return array
   *   The result array of the test run for the provided stage.
   */
  abstract protected function runBeforeAfterTest(QAShotTestInterface $entity, $stage): array;

  /**
   * Run tests in A/B mode.
   *
   * Use this function inside the runTestBySettings() method only.
   * A/B compares two URLs with each other.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $entity
   *   The test entity.
   *
   * @return array
   *   The result array of the test run.
   */
  abstract protected function runAbTest(QAShotTestInterface $entity): array;

}
