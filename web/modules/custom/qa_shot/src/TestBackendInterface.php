<?php

namespace Drupal\qa_shot;

use Drupal\qa_shot\Entity\QAShotTestInterface;

/**
 * Interface for QAShot tests.
 *
 * Implement this in regression testing tool abstractions.
 *
 * @package Drupal\qa_shot
 */
interface TestBackendInterface {

  /**
   * Use this function as a glue for the different test modes, telemetry, etc.
   *
   * This function is only used in QAShotTest::run().
   * Throw exception, when something unwanted happens.
   *
   * This function should do the following:
   * - Test if the stage is valid,
   * - Prepare the environment (FileSystem, etc.)
   * - Run the proper test mode at the proper test stage
   * - Persist metadata and results in the entity.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $entity
   *   The QAShot Test entity.
   * @param string $stage
   *   The stage of the test.
   */
  public function runTestBySettings(QAShotTestInterface $entity, $stage);

  /**
   * Remove files belonging to the test.
   *
   * Removal should include both the private and public file system.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $entity
   *   The entity.
   */
  public function clearFiles(QAShotTestInterface $entity);

  /**
   * Remove unused files belonging to the test.
   *
   * Removal should include only public files.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $entity
   *   The entity.
   */
  public function removeUnusedFilesForTest(QAShotTestInterface $entity);

  /**
   * Returns the status of backstopjs.
   *
   * @return string
   *   The status as string.
   */
  public function getStatus(): string;

}
