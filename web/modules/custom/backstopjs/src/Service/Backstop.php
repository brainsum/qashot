<?php

namespace Drupal\backstopjs\Service;

use Drupal\backstopjs\Exception\EmptyResultsException;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
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
  protected $backstopFileSystem;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Backstop constructor.
   *
   * @param \Drupal\backstopjs\Service\FileSystem $backstopFileSystem
   *   The BackstopJS file system service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger channel factory.
   */
  public function __construct(
    FileSystem $backstopFileSystem,
    LoggerChannelFactoryInterface $loggerChannelFactory
  ) {
    $this->backstopFileSystem = $backstopFileSystem;
    $this->logger = $loggerChannelFactory->get('backstopjs');
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
   * @throws \Drupal\backstopjs\Exception\EmptyResultsException
   * @throws \Drupal\Core\Entity\EntityStorageException
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
      $results = $this->runAbTest($entity);
      $containsResults = TRUE;
    }
    elseif ('before_after' === $mode) {
      $results = $this->runBeforeAfterTest($entity, $stage);
      $containsResults = $stage === 'after';
    }
    else {
      throw new InvalidRunnerOptionsException('The test mode is invalid or the app was not prepared for it.');
    }

    $endTime = microtime(TRUE);

    if (NULL === $results) {
      throw new EmptyResultsException('Test results are empty. Contact the site administrator!');
    }

    $passRate = 0;
    // Should only be a real value for the 'test' command.
    if (NULL !== $results['passedTestCount'] && NULL !== $results['failedTestCount']) {
      drupal_set_message(t('Test done. @passed test(s) passed, @failed test(s) failed.', [
        '@passed' => $results['passedTestCount'],
        '@failed' => $results['failedTestCount'],
      ]));

      $testCount = (int) $results['passedTestCount'] + (int) $results['failedTestCount'];
      if ($testCount === 0) {
        $this->logger->notice('Test count is 0. Is this ok? Test ID: ' . $entity->id());
        $passRate = 0;
      }
      else {
        $passRate = ((int) $results['passedTestCount']) / $testCount;
      }
    }

    // @todo: Maybe return result and metadata and save elsewhere,
    // more specifically in the qa_shot module.
    // Gather and persist metadata.
    $metadata = [
      'stage' => empty($stage) ? NULL : $stage,
      'backstop_engine' => $results['backstopEngine'],
      'viewport_count' => $entity->getViewportCount(),
      'scenario_count' => $entity->getScenarioCount(),
      'datetime' => (new \DateTime())->format('Y-m-d H:i:s'),
      'duration' => $endTime - $startTime,
      'passed_count' => $results['passedTestCount'],
      'failed_count' => $results['failedTestCount'],
      'pass_rate' => (float) $passRate,
      'contains_result' => $containsResults,
      'success' => 0 === $results['failedTestCount'] && NULL !== $results['passedTestCount'],
    ];

    $result = $this->parseScreenshots($entity);

    $this->logger->debug(var_export([
      'metadata' => $metadata,
      'result' => $result,
    ], TRUE));

    // @todo: Save this as well.
    unset($metadata['backstop_engine']);

    $entity->addMetadata($metadata);
    $entity->setResult($result);
    $entity->save();
  }

  /**
   * Get the result screenshots.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $entity
   *   The entity.
   *
   * @todo: Change, so this is stored in the new field_viewport.
   *
   * @return array
   *   The screenshots.
   */
  private function parseScreenshots(QAShotTestInterface $entity): array {
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

    // @todo: Change.
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
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function prepareTest(QAShotTestInterface $entity) {
    if (NULL === $entity) {
      drupal_set_message(t('Trying to run test on NULL.'), 'error');
      throw new InvalidEntityException('Entity is empty.');
    }

    try {
      $this->backstopFileSystem->initializeEnvironment($entity);
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
   * Callback for running the 'Before/After' test type.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $entity
   *   The test.
   * @param string $stage
   *   The stage; before or after.
   *
   * @throws \Drupal\backstopjs\Exception\InvalidEntityException
   * @throws \Drupal\backstopjs\Exception\InvalidConfigurationException
   * @throws \Drupal\backstopjs\Exception\InvalidCommandException
   * @throws \Drupal\backstopjs\Exception\BackstopAlreadyRunningException
   *
   * @return array
   *   Test results.
   */
  protected function runBeforeAfterTest(QAShotTestInterface $entity, $stage): array {
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
   *   The test results.
   */
  protected function runAbTest(QAShotTestInterface $entity): array {
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
  private function runReferenceCommand(QAShotTestInterface $entity): array {
    return $this->runCommand('reference', $entity);
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
  private function runTestCommand(QAShotTestInterface $entity): array {
    return $this->runCommand('test', $entity);
  }

  /**
   * Command runner logic.
   *
   * @param string $command
   *   The command to be run.
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $entity
   *   The entity.
   *
   * @throws BackstopAlreadyRunningException
   *   When Backstop is already running.
   * @throws InvalidCommandException
   *   When the supplied command is not a valid BackstopJS command.
   *
   * @return array
   *   Array with data from the command run.
   */
  private function runCommand($command, QAShotTestInterface $entity): array {
    if (!$this->isCommandValid($command)) {
      throw new InvalidCommandException("The supplied command '$command' is not valid.");
    }

    $this->checkBackstopRunStatus();
    /*
     * @todo: real-time output @see: QAS-90.
     */
    // @todo: Add separate function.
    $testerEngine = 'phantomjs';
    if ($engine = $entity->getTestEngine()) {
      $testerEngine = $engine;
    }

    // @todo: Add an admin form where the user can input the path of binaries.
    // @todo: What if local install, not docker/server?
    // With slimerjs we have to use xvfb-run.
    $xvfb = '';
    if ($testerEngine === 'slimerjs') {
      $xvfb = 'xvfb-run -a ';
    }

    $backstopCommand = escapeshellcmd($xvfb . 'backstop ' . $command . ' --configPath=' . $entity->getConfigurationPath());
    /** @var array $execOutput */
    /** @var int $status */
    exec($backstopCommand, $execOutput, $status);

    $results = [
      'result' => TRUE,
      'passedTestCount' => NULL,
      'failedTestCount' => NULL,
      'bitmapGenerationSuccess' => FALSE,
      'backstopEngine' => $testerEngine,
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
        if ($results['passedTestCount'] === NULL && strpos($line, 'Passed') !== FALSE) {
          $results['passedTestCount'] = (int) preg_replace('/\D/', '', $line);
        }

        // Search for the number of failed tests.
        if ($results['failedTestCount'] === NULL && strpos($line, 'Failed') !== FALSE) {
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

    /*
    if ($status !== 0) {
    // @todo: Here what?
    }
     */
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
      $this->logger->warning('BackstopJS is already running.');
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
  private function isCommandValid($command): bool {
    return in_array($command, array('reference', 'test'), FALSE);
  }

  /**
   * Clean up the stored files for an entity.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $entity
   *   The entity.
   */
  public function clearFiles(QAShotTestInterface $entity) {
    $this->backstopFileSystem->clearFiles($entity);
  }

  /**
   * Clean up the unused files for an entity.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $entity
   *   The entity.
   */
  public function removeUnusedFilesForTest(QAShotTestInterface $entity) {
    $this->backstopFileSystem->removedUnusedFilesForTest($entity);
  }

}
