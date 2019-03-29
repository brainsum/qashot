<?php

namespace Drupal\backstopjs\Custom;

use Drupal\backstopjs\Exception\InvalidRunnerModeException;
use Drupal\backstopjs\Exception\InvalidRunnerStageException;

/**
 * Class Backstop, contains helper functions.
 *
 * @package Drupal\backstopjs\Custom
 *
 * @todo: Refactor RunnerOptions into a service
 */
class Backstop {

  /**
   * Map of settings which describes the available run modes and stages.
   *
   * Associative array of arrays.
   * Keys: test modes.
   * Values: arrays of test stages.
   *
   * Test mode: How the test is going to run.
   * Test stage: Which part of the test to run.
   *
   * @var array
   */
  private static $runnerSettings = [
    'a_b' => '',
    'before_after' => [
      'before',
      'after',
    ],
  ];

  /**
   * Check if the mode and stage are valid.
   *
   * Only returns TRUE for exact matches.
   *
   * @param string $mode
   *   The runner mode.
   * @param string $stage
   *   The run stage.
   *
   * @return bool
   *   Whether the settings are valid.
   *
   * @throws InvalidRunnerStageException
   * @throws InvalidRunnerModeException
   */
  public static function areRunnerSettingsValid($mode, $stage): bool {
    // When not a valid mode, return FALSE.
    if (!array_key_exists($mode, self::$runnerSettings)) {
      throw new InvalidRunnerModeException("The mode '$mode' is not valid.");
    }

    $stages = self::getRunnerSettings()[$mode];
    // When stage is null, but there are stages, return FALSE.
    if (empty($stage) && !empty($stages)) {
      throw new InvalidRunnerStageException("The stage '$stage' is not valid.");
    }

    // If stage is invalid, return FALSE.
    if (is_array($stages) && !in_array($stage, $stages, FALSE)) {
      throw new InvalidRunnerStageException("The stage '$stage' is not valid for the '$mode' mode.");
    }

    return TRUE;
  }

  /**
   * Return the runner settings.
   *
   * @return array
   *   The settings as an array.
   */
  public static function getRunnerSettings(): array {
    return self::$runnerSettings;
  }

}
