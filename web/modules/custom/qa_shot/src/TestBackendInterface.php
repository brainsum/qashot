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

}
