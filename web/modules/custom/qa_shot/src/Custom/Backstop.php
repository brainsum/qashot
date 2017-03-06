<?php

namespace Drupal\qa_shot\Custom;

use Drupal\qa_shot\Entity\QAShotTestInterface;
use Drupal\qa_shot\Exception\BackstopAlreadyRunningException;
use Drupal\qa_shot\Exception\BackstopBaseException;
use Drupal\qa_shot\Exception\InvalidCommandException;
use Drupal\qa_shot\Exception\InvalidConfigurationException;
use Drupal\qa_shot\Exception\InvalidEntityException;
use Drupal\qa_shot\Exception\InvalidRunnerModeException;
use Drupal\qa_shot\Exception\InvalidRunnerOptionsException;
use Drupal\qa_shot\Exception\InvalidRunnerStageException;
use Drupal\qa_shot\Exception\ReferenceCommandFailedException;
use Drupal\qa_shot\Exception\TestCommandFailedException;

/**
 * Class Backstop, contains helper functions.
 *
 * @package Drupal\qa_shot\Custom
 *
 * @todo: Refactor Backstop into a service
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
   * @throws InvalidRunnerModeException
   * @throws InvalidRunnerStageException
   *
   * @return bool
   *   Whether the settings are valid.
   */
  public static function areRunnerSettingsValid($mode, $stage) {
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
  public static function getRunnerSettings() {
    return self::$runnerSettings;
  }

  /**
   * Checks whether Backstop is running or not.
   *
   * @throws BackstopAlreadyRunningException
   */
  public static function checkBackstopRunStatus() {
    $checkerCommand = escapeshellcmd('pgrep -f backstop -c');
    $res = exec($checkerCommand, $execOutput, $status);

    // > 1 is used since the pgrep command gets included as well.
    if (is_numeric($res) && (int) $res > 1) {
      throw new BackstopAlreadyRunningException('BackstopJS is already running.');
    }
  }

  /**
   * Check whether a command is valid/supported for BackstopJS.
   *
   * @param string $command
   *   The command.
   *
   * @return bool
   *   TRUE for valid, FALSE for invalid.
   */
  public static function isCommandValid($command) {
    return in_array($command, array('reference', 'test'), FALSE);
  }

  /**
   * Run a test according to the mode and stage.
   *
   * @param string $mode
   *   The test mode.
   * @param string $stage
   *   The test stage.
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $entity
   *   The entity.
   *
   * @throws \Drupal\qa_shot\Exception\InvalidRunnerOptionsException
   * @throws \Drupal\qa_shot\Exception\InvalidConfigurationException
   * @throws \Drupal\qa_shot\Exception\InvalidCommandException
   * @throws \Drupal\qa_shot\Exception\ReferenceCommandFailedException
   * @throws \Drupal\qa_shot\Exception\TestCommandFailedException
   * @throws \Drupal\qa_shot\Exception\InvalidEntityException
   * @throws \Drupal\qa_shot\Exception\BackstopAlreadyRunningException
   * @throws InvalidRunnerModeException
   * @throws InvalidRunnerStageException
   */
  public static function runTestBySettings($mode, $stage, QAShotTestInterface $entity) {
    if (!Backstop::areRunnerSettingsValid($mode, $stage)) {
      throw new InvalidRunnerOptionsException('The requested test mode or stage is invalid.');
    }

    if ('a_b' === $mode) {
      Backstop::runABTest($entity);
    }

    // @fixme
    if ('before_after' === $mode) {
      Backstop::prepareTest($entity);

      if ('before' === $stage) {
        Backstop::runReferenceCommand($entity);
      }
      else {
        Backstop::runTestCommand($entity);
      }
    }
  }

  /**
   * Preparations before running a test.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $entity
   *   The entity.
   *
   * @throws \Drupal\qa_shot\Exception\InvalidConfigurationException
   * @throws \Drupal\qa_shot\Exception\InvalidEntityException
   */
  public static function prepareTest(QAShotTestInterface $entity) {
    if (NULL === $entity) {
      drupal_set_message(t('Trying to run test on NULL.'), 'error');
      throw new InvalidEntityException('Entity is empty.');
    }

    try {
      /** @var \Drupal\qa_shot\Service\FileSystem $qasFileSystem */
      $qasFileSystem = \Drupal::service('qa_shot.file_system');
      $qasFileSystem->initializeEnvironment($entity);
    }
    catch (BackstopBaseException $exception) {
      drupal_set_message('Exception at environment init. ' . $exception->getMessage(), 'error');
    }

    if (empty($entity->getConfigurationPath())) {
      drupal_set_message('Configuration path not saved in entity.', 'error');
      throw new InvalidConfigurationException('Configuration path not saved in entity.');
    }
  }

  /**
   * Run an A/B test.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $entity
   *   The entity.
   *
   * @throws \Drupal\qa_shot\Exception\InvalidConfigurationException
   * @throws \Drupal\qa_shot\Exception\ReferenceCommandFailedException
   * @throws \Drupal\qa_shot\Exception\TestCommandFailedException
   * @throws \Drupal\qa_shot\Exception\InvalidEntityException
   */
  public static function runABTest(QAShotTestInterface $entity) {
    Backstop::prepareTest($entity);

    $command = 'reference';

    try {
      $referenceResult = Backstop::runReferenceCommand($entity);
    }
    catch (BackstopBaseException $e) {
      drupal_set_message($e->getMessage(), 'error');
      $referenceResult = FALSE;
    }

    if (FALSE === $referenceResult) {
      drupal_set_message("Running the $command command resulted in a failure.", 'error');
      throw new ReferenceCommandFailedException("Running the $command command resulted in a failure.");
    }

    $command = 'test';
    try {
      $testResult = Backstop::runTestCommand($entity);
    }
    catch (BackstopBaseException $e) {
      drupal_set_message($e->getMessage(), 'error');
      $testResult = FALSE;
    }

    if (FALSE === $testResult) {
      drupal_set_message("Running the $command command resulted in a failure.", 'error');
      throw new TestCommandFailedException("Running the $command command resulted in a failure.");
    }
  }

  /**
   * Run the 'Reference' command.
   *
   * Executes the reference BackstopJS command to create reference
   * screenshots according to the supplied configuration.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $entity
   *   The entity.
   *
   * @throws BackstopAlreadyRunningException
   *   When Backstop is already running.
   * @throws InvalidCommandException
   *   When the supplied command is not a valid BackstopJS command.
   * @throws InvalidEntityException
   * @throws InvalidConfigurationException
   *
   * @return bool
   *   TRUE on success, FALSE on failure.
   */
  public static function runReferenceCommand(QAShotTestInterface $entity) {
    return self::runCommand('reference', $entity->getConfigurationPath());
  }

  /**
   * Run the 'Test' command.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $entity
   *   The entity.
   *
   * @throws BackstopAlreadyRunningException
   *   When Backstop is already running.
   * @throws InvalidCommandException
   *   When the supplied command is not a valid BackstopJS command.
   * @throws InvalidEntityException
   * @throws InvalidConfigurationException
   *
   * @return bool
   *   TRUE on success, FALSE on failure.
   */
  public static function runTestCommand(QAShotTestInterface $entity) {
    return self::runCommand('test', $entity->getConfigurationPath());
  }

  /**
   * Command runner logic.
   *
   * @param string $command
   *   The command to be run.
   * @param string $configurationPath
   *   The path to the backstop config.
   *
   * @throws BackstopAlreadyRunningException
   *   When Backstop is already running.
   * @throws InvalidCommandException
   *   When the supplied command is not a valid BackstopJS command.
   *
   * @return bool
   *   TRUE on success.
   */
  private static function runCommand($command, $configurationPath) {
    // @todo: use exceptions instead of return bool
    if (!self::isCommandValid($command)) {
      throw new InvalidCommandException("The supplied command '$command' is not valid.");
    }

    self::checkBackstopRunStatus();

    // @todo: send this to the background, don't hold up UI
    // @todo: add some kind of semaphore to prevent running a test several times at the same time
    /*
     * @todo: real-time output:
     *    http://stackoverflow.com/questions/1281140/run-process-with-realtime-output-in-php
     *    http://stackoverflow.com/questions/20614557/php-shell-exec-update-output-as-script-is-running
     *    http://stackoverflow.com/questions/20107147/php-reading-shell-exec-live-output
     */

    // @todo: install script ending with "sudo -k" or "-K".
    // @todo: FIXME.
    // @todo: Get node version from .amazee.yml and add /var/www/drupal/.nvm/versions/node/v6.5.0/bin to path.
    // @todo: Add an admin form where the user can input the path.
    if (strpos(getenv("PATH"), "/var/www/drupal/.nvm/versions/node") === FALSE) {
      putenv("PATH=/var/www/drupal/.nvm/versions/node/v6.5.0/bin:" . getenv("PATH"));
    }

    $backstopCommand = escapeshellcmd('backstop ' . $command . ' --configPath=' . $configurationPath);
    exec($backstopCommand, $execOutput, $status);

    // dpm($status, "exec status");

    $results = [
      'passedTestCount' => NULL,
      'failedTestCount' => NULL,
      'bitmapGenerationSuccess' => FALSE,
    ];

    foreach ($execOutput as $line) {
      // Search for bitmap generation string.
      if (strpos($line, 'Bitmap file generation completed.') !== FALSE) {
        $results['bitmapGenerationSuccess'] = TRUE;
      }


      if (strpos($line, 'report |') !== FALSE) {
        // Search for the number of passed tests.
        if (strpos($line, 'Passed') !== FALSE) {
          $results['passedTestCount'] = preg_replace('/\D/', '', $line);
        }

        // Search for the number of failed tests.
        if (strpos($line, 'Failed') !== FALSE) {
          $results['failedTestCount'] = preg_replace('/\D/', '', $line);
        }
      }
    }

    if (!$results['bitmapGenerationSuccess']) {
      drupal_set_message('Bitmap generation failed.');
      dpm($execOutput);
      return FALSE;
    }

    // Should only be a real value for test.
    if (NULL !== $results['passedTestCount'] && NULL !== $results['failedTestCount']) {
      drupal_set_message(t('Test done. @passed test(s) passed, @failed test(s) failed.', [
        '@passed' => $results['passedTestCount'],
        '@failed' => $results['failedTestCount'],
      ]), 'status');
    }

    if ($status !== 0) {
      // @todo: Here what?
    }

    // dpm($execOutput, 'Output of exec.');

    return TRUE;
  }

}
