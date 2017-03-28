<?php

namespace Drupal\backstopjs\Service;

use Drupal\qa_shot\Entity\QAShotTestInterface;
use Drupal\backstopjs\Exception\BackstopAlreadyRunningException;
use Drupal\qa_shot\Exception\QAShotBaseException;
use Drupal\backstopjs\Exception\InvalidCommandException;
use Drupal\backstopjs\Exception\InvalidConfigurationException;
use Drupal\backstopjs\Exception\InvalidEntityException;
use Drupal\backstopjs\Exception\InvalidRunnerOptionsException;
use Drupal\backstopjs\Custom\Backstop as CustomBackstop;
use Drupal\backstopjs\Exception\ReferenceCommandFailedException;
use Drupal\backstopjs\Exception\TestCommandFailedException;
use Drupal\qa_shot\TestBackendBase;

/**
 * Class Backstop.
 *
 * BackstopJS abstraction class.
 *
 * @package Drupal\qa_shot\Service
 */
class Backstop extends TestBackendBase {

  /**
   * The QAShot specific filesystem service.
   *
   * @var \Drupal\backstopjs\Service\FileSystem
   */
  protected $qasFileSystem;

  /**
   * TestBase constructor.
   */
  public function __construct() {
    // @todo: Dependency injection.
    $this->qasFileSystem = \Drupal::service('backstopjs.file_system');
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\backstopjs\Exception\InvalidRunnerOptionsException
   * @throws \Drupal\backstopjs\Exception\InvalidConfigurationException
   * @throws \Drupal\backstopjs\Exception\InvalidCommandException
   * @throws \Drupal\backstopjs\Exception\ReferenceCommandFailedException
   * @throws \Drupal\backstopjs\Exception\TestCommandFailedException
   * @throws \Drupal\backstopjs\Exception\InvalidEntityException
   * @throws \Drupal\backstopjs\Exception\BackstopAlreadyRunningException
   * @throws \Drupal\backstopjs\Exception\InvalidRunnerModeException
   * @throws \Drupal\backstopjs\Exception\InvalidRunnerStageException
   */
  public function runTestBySettings(QAShotTestInterface $entity, $stage) {

    $mode = $entity->bundle();
    // Validate the settings.
    if (!CustomBackstop::areRunnerSettingsValid($mode, $stage)) {
      throw new InvalidRunnerOptionsException('The requested test mode or stage is invalid.');
    }

    // Prepare the test environment.
    $this->prepareTest($entity);

    $startTime = microtime(TRUE);
    $results = NULL;

    // Run the test.
    if ('a_b' === $mode) {
      $results = $this->runABTest($entity);
    }
    elseif ('before_after' === $mode) {
      $results = $this->runBeforeAfterTest($entity, $stage);
    }
    else {
      throw new InvalidRunnerOptionsException('The test mode is invalid or the app was not prepared for it.');
    }

    $endTime = microtime(TRUE);

    if (NULL === $results) {
      // @todo: More specific exception.
      throw new \Exception('Test results are empty. Contact the site administrator!');
    }

    // Should only be a real value for test.
    if (NULL !== $results['passedTestCount'] && NULL !== $results['failedTestCount']) {
      drupal_set_message(t('Test done. @passed test(s) passed, @failed test(s) failed.', [
        '@passed' => $results['passedTestCount'],
        '@failed' => $results['failedTestCount'],
      ]), 'status');
    }

    // Gather and persist metadata.
    $metadata = [
      'stage' => empty($stage) ? NULL : $stage,
      'viewport_count' => $entity->getViewportCount(),
      'scenario_count' => $entity->getScenarioCount(),
      'datetime' => (new \DateTime())->format('Y-m-d H:i:s'),
      'duration' => $endTime - $startTime,
      'passed_count' => $results['passedTestCount'],
      'failed_count' => $results['failedTestCount'],
      'success' => 0 === $results['failedTestCount'] && NULL !== $results['passedTestCount'],
    ];

    $entity->addMetadata($metadata);
    $entity->setResult($this->parseScreenshots($entity));
    $entity->save();

    kint('lifetime metadata', $entity->getLifetimeMetadataValue());
    kint('last run metadata', $entity->getLastRunMetadataValue());
    kint('last run result', $entity->getResultValue());

  }

  /**
   * Get the result screenshots.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $entity
   *   The entity.
   *
   * @return array
   *   The screenshots.
   */
  private function parseScreenshots(QAShotTestInterface $entity) {
    $screenshots = [];

    $reportBasePath = str_replace('/html_report/index.html', '', $entity->getHtmlReportPath());
    $screenshotConfigPath = $reportBasePath . '/html_report/config.js';
    $screenshotConfig = file_get_contents($screenshotConfigPath);
    if (FALSE === $screenshotConfig) {
      dpm('Config file not found at ' . $screenshotConfigPath);
      return $screenshots;
    }

    // Report config is a json wrapped in a report() function,
    // so we replace that with ''. Then, we turn the json into an array.
    /** @var array[] $screenshotConfigData */
    $screenshotConfigData = json_decode(str_replace(['report(', ');'], '', $screenshotConfig), TRUE);

    $scenarioIndex = 0;
    $totalViewportLimit = $entity->getViewportCount() - 1;
    $viewportIndex = 0;

    foreach ($screenshotConfigData['tests'] as $screenshot) {
      $screenshots[] = [
        'scenario_delta' => $scenarioIndex,
        'viewport_delta' => $viewportIndex,
        'reference' => str_replace('../', $entity->id() . '/', $screenshot['pair']['reference']),
        'test' => str_replace('../', $entity->id() . '/', $screenshot['pair']['test']),
        'diff' => isset($screenshot['pair']['diffImage']) ? str_replace('../', $entity->id() . '/', $screenshot['pair']['diffImage']) : '',
        'success' => $screenshot['status'] === 'pass',
      ];

      // When the viewportIndex reaches the limit, we have to reset it to 0.
      // We also have to increase the scenarioIndex.
      if ($viewportIndex === $totalViewportLimit) {
        ++$scenarioIndex;
        $viewportIndex = 0;
      }
      else {
        ++$viewportIndex;
      }
    }

    return $screenshots;
  }

  /**
   * Preparations before running a test.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $entity
   *   The entity.
   *
   * @throws \Drupal\backstopjs\Exception\InvalidConfigurationException
   * @throws \Drupal\backstopjs\Exception\InvalidEntityException
   */
  private function prepareTest(QAShotTestInterface $entity) {
    if (NULL === $entity) {
      drupal_set_message(t('Trying to run test on NULL.'), 'error');
      throw new InvalidEntityException('Entity is empty.');
    }

    try {
      $this->qasFileSystem->initializeEnvironment($entity);
    }
    catch (QAShotBaseException $exception) {
      drupal_set_message('Exception at environment init. ' . $exception->getMessage(), 'error');
    }

    if (empty($entity->getConfigurationPath())) {
      drupal_set_message('Configuration path not saved in entity.', 'error');
      throw new InvalidConfigurationException('Configuration path not saved in entity.');
    }
  }

  /**
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $entity
   * @param $stage
   *
   * @return array
   */
  protected function runBeforeAfterTest(QAShotTestInterface $entity, $stage) {
    return ('before' === $stage) ? $this->runReferenceCommand($entity) : $this->runTestCommand($entity);
  }

  /**
   * Run an A/B test.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $entity
   *   The entity.
   *
   * @throws \Drupal\backstopjs\Exception\InvalidConfigurationException
   * @throws \Drupal\backstopjs\Exception\ReferenceCommandFailedException
   * @throws \Drupal\backstopjs\Exception\TestCommandFailedException
   * @throws \Drupal\backstopjs\Exception\InvalidEntityException
   *
   * @return array
   */
  protected function runABTest(QAShotTestInterface $entity) {
    $command = 'reference';
    try {
      $referenceResult = $this->runReferenceCommand($entity);
    }
    catch (QAShotBaseException $e) {
      drupal_set_message($e->getMessage(), 'error');
      $referenceResult['result'] = FALSE;
    }

    if (FALSE === $referenceResult['result']) {
      drupal_set_message("Running the $command command resulted in a failure.", 'error');
      throw new ReferenceCommandFailedException("Running the $command command resulted in a failure.");
    }

    $command = 'test';
    try {
      $testResult = $this->runTestCommand($entity);
    }
    catch (QAShotBaseException $e) {
      drupal_set_message($e->getMessage(), 'error');
      $testResult['result'] = FALSE;
    }

    if (FALSE === $testResult['result']) {
      drupal_set_message("Running the $command command resulted in a failure.", 'error');
      throw new TestCommandFailedException("Running the $command command resulted in a failure.");
    }

    return $testResult;
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
   * @return array
   *   Array with data from the command run.
   */
  private function runReferenceCommand(QAShotTestInterface $entity) {
    return $this->runCommand('reference', $entity->getConfigurationPath());
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
   * @return array
   *   Array with data from the command run.
   */
  private function runTestCommand(QAShotTestInterface $entity) {
    return $this->runCommand('test', $entity->getConfigurationPath());
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
   * @return array
   *   Array with data from the command run.
   */
  private function runCommand($command, $configurationPath) {
    // @todo: Either make the entity as the parameter, or return an array.
    // @todo: use exceptions instead of return bool
    if (!$this->isCommandValid($command)) {
      throw new InvalidCommandException("The supplied command '$command' is not valid.");
    }

    $this->checkBackstopRunStatus();

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
      'result' => TRUE,
      'passedTestCount' => NULL,
      'failedTestCount' => NULL,
      'bitmapGenerationSuccess' => FALSE,
    ];

    foreach ($execOutput as $line) {
      // Search for bitmap generation string.
      if (strpos($line, 'Bitmap file generation completed.') !== FALSE) {
        $results['bitmapGenerationSuccess'] = TRUE;
        continue;
      }

      // Search for the reports.
      if (strpos($line, 'report |') !== FALSE) {
        // Search for the number of passed tests.
        if (strpos($line, 'Passed') !== FALSE) {
          $results['passedTestCount'] = (int) preg_replace('/\D/', '', $line);
        }

        // Search for the number of failed tests.
        if (strpos($line, 'Failed') !== FALSE) {
          $results['failedTestCount'] = (int) preg_replace('/\D/', '', $line);
        }
      }
    }

    dpm($execOutput);
    if (!$results['bitmapGenerationSuccess']) {
      $results['result'] = FALSE;
      drupal_set_message('Bitmap generation failed.');
      return $results;
    }

//    if ($status !== 0) {
//      // @todo: Here what?
//    }

    // dpm($execOutput, 'Output of exec.');

    return $results;
  }

  /**
   * Checks whether Backstop is running or not.
   *
   * @throws BackstopAlreadyRunningException
   */
  private function checkBackstopRunStatus() {
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
  private function isCommandValid($command) {
    return in_array($command, array('reference', 'test'), FALSE);
  }

  /**
   * Clean up the stored files for an entity.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $entity
   *   The entity.
   */
  public function clearFiles(QAShotTestInterface $entity) {
    $this->qasFileSystem->clearFiles($entity);
  }

}
