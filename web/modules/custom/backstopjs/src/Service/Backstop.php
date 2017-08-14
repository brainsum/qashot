<?php

namespace Drupal\backstopjs\Service;

use Drupal\backstopjs\Component\LocalBackstopJS;
use Drupal\backstopjs\Component\RemoteBackstopJS;
use Drupal\backstopjs\Exception\EmptyResultsException;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\qa_shot\Entity\QAShotTestInterface;
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
   * The Local or Remote BackstopJS.
   *
   * @var \Drupal\backstopjs\Component\BackstopJSInterface
   */
  protected $backstop;

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

    $local = TRUE;
    $this->backstop = (TRUE === $local)
      ? new LocalBackstopJS($backstopFileSystem, $loggerChannelFactory)
      : new RemoteBackstopJS($backstopFileSystem, $loggerChannelFactory);
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
      // @todo: Save as timestamp.
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
  public function parseScreenshots(QAShotTestInterface $entity): array {
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

    // @todo: Load paragraphs, match screenshot names for security.
    // $paragraphsStorage = \Drupal::entityTypeManager()->getStorage('paragraph');
    $scenarioIds = array_map(function ($item) {
      return $item['target_id'];
    }, $entity->getFieldScenario()->getValue(TRUE));
    $viewportIds = array_map(function ($item) {
      return $item['target_id'];
    }, $entity->getFieldViewport()->getValue(TRUE));

//    reset($viewportIds);
//    reset($scenarioIds);

    foreach ($screenshotConfigData['tests'] as $screenshot) {
      $screenshots[] = [
        'scenario_id' => (int) current($scenarioIds),
        'viewport_id' => (int) current($viewportIds),
        'reference' => str_replace('../', $entity->id() . '/', $screenshot['pair']['reference']),
        'test' => str_replace('../', $entity->id() . '/', $screenshot['pair']['test']),
        'diff' => isset($screenshot['pair']['diffImage']) ? str_replace('../', $entity->id() . '/', $screenshot['pair']['diffImage']) : '',
        'success' => $screenshot['status'] === 'pass',
      ];

      // When we reach the last viewport, we have to start over.
      // We also have to get the next scenario.
      if (FALSE === next($viewportIds)) {
        next($scenarioIds);
        reset($viewportIds);
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
   * @return array
   *   Array with data from the command run.
   *
   * @throws BackstopAlreadyRunningException
   *   When Backstop is already running.
   * @throws InvalidCommandException
   *   When the supplied command is not a valid BackstopJS command.
   * @throws \Drupal\backstopjs\Exception\FileWriteException
   * @throws \Drupal\backstopjs\Exception\FolderCreateException
   * @throws \Drupal\backstopjs\Exception\FileOpenException
   */
  private function runCommand($command, QAShotTestInterface $entity): array {
    if (!$this->isCommandValid($command)) {
      throw new InvalidCommandException("The supplied command '$command' is not valid.");
    }

    $this->backstop->checkRunStatus();

    $testerEngine = 'phantomjs';
    if ($engine = $entity->getTestEngine()) {
      $testerEngine = $engine;
    }

    return $this->backstop->run($testerEngine, $command, $entity);
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
    return in_array($command, ['reference', 'test'], FALSE);
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

  /**
   * Returns the status of backstopjs.
   *
   * @return string
   *   The status as string.
   */
  public function getStatus(): string {
    return $this->backstop->getStatus();
  }

}
