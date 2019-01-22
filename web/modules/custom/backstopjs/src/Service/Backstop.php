<?php

namespace Drupal\backstopjs\Service;

use Drupal\backstopjs\Exception\EmptyResultsException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
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
use Drupal\qa_shot_test_worker\TestWorker\TestWorkerFactoryInterface;

/**
 * Class Backstop.
 *
 * BackstopJS abstraction class.
 *
 * @package Drupal\qa_shot\Service
 */
class Backstop extends TestBackendBase {

  use StringTranslationTrait;

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
   * @var \Drupal\backstopjs\Backstopjs\BackstopjsWorkerInterface
   */
  protected $backstop;

  /**
   * Backstop constructor.
   *
   * @param \Drupal\backstopjs\Service\FileSystem $backstopFileSystem
   *   The BackstopJS file system service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger channel factory.
   * @param \Drupal\qa_shot_test_worker\TestWorker\TestWorkerFactoryInterface $backstopJsFactory
   *   BackstopJS Worker Factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The site configuration.
   *
   * @throws \InvalidArgumentException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function __construct(
    FileSystem $backstopFileSystem,
    LoggerChannelFactoryInterface $loggerChannelFactory,
    TestWorkerFactoryInterface $backstopJsFactory,
    ConfigFactoryInterface $configFactory
  ) {
    $this->backstopFileSystem = $backstopFileSystem;
    $this->logger = $loggerChannelFactory->get('backstopjs');
    $this->backstop = $backstopJsFactory->get($configFactory->get('backstopjs.settings')->get('suite.location'));
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
   * @throws \Drupal\backstopjs\Exception\FileOpenException
   * @throws \Drupal\backstopjs\Exception\FileWriteException
   * @throws \Drupal\backstopjs\Exception\FolderCreateException
   * @throws \InvalidArgumentException
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
      drupal_set_message($this->t('Test done. @passed test(s) passed, @failed test(s) failed.', [
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
      'mode' => $mode,
      'stage' => empty($stage) ? NULL : $stage,
      'tool' => 'backstopjs',
      'browser' => $results['browser'],
      'engine' => $results['engine'],
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

    // @todo: Save these as well.
    unset($metadata['mode'], $metadata['tool'], $metadata['browser'], $metadata['engine']);

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
      $this->logger->notice('Config file not found at ' . $screenshotConfigPath . ' for QAShot test with ID ' . $entity->id());
      return $screenshots;
    }

    // Report config is a json wrapped in a report() function,
    // so we replace that with ''. Then, we turn the json into an array.
    /** @var array[] $screenshotConfigData */
    $screenshotConfigData = json_decode(str_replace(['report(', ');'], '', $screenshotConfig), TRUE);

    // @todo: Load paragraphs, match screenshot names for security.
    // $paragraphsStorage =
    // \Drupal::entityTypeManager()->getStorage('paragraph');
    $scenarioIds = array_map(function ($item) {
      return $item['target_id'];
    }, $entity->getFieldScenario()->getValue(TRUE));
    $viewportIds = array_map(function ($item) {
      return $item['target_id'];
    }, $entity->getFieldViewport()->getValue(TRUE));

    foreach ($screenshotConfigData['tests'] as $screenshot) {
      $screenshots[] = [
        'scenario_id' => (int) current($scenarioIds),
        'viewport_id' => (int) current($viewportIds),
        'reference' => str_replace('..' . DIRECTORY_SEPARATOR, $entity->id() . '/', $screenshot['pair']['reference']),
        'test' => str_replace('..' . DIRECTORY_SEPARATOR, $entity->id() . '/', $screenshot['pair']['test']),
        'diff' => isset($screenshot['pair']['diffImage']) ? str_replace('..' . DIRECTORY_SEPARATOR, $entity->id() . '/', $screenshot['pair']['diffImage']) : '',
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
   * @throws \InvalidArgumentException
   */
  public function prepareTest(QAShotTestInterface $entity) {
    if (NULL === $entity) {
      drupal_set_message($this->t('Trying to run test on NULL.'), 'error');
      throw new InvalidEntityException('Entity is empty.');
    }

    try {
      $this->backstopFileSystem->initializeEnvironment($entity);
    }
    catch (QAShotBaseException $exception) {
      drupal_set_message($this->t('Exception at environment init. @msg', [
        '@msg' => $exception->getMessage(),
      ]), 'error');
    }

    if (empty($entity->getConfigurationPath())) {
      drupal_set_message($this->t('Configuration path not saved in entity.'), 'error');
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
   * @return array
   *   Test results.
   *
   * @throws \Drupal\backstopjs\Exception\InvalidEntityException
   * @throws \Drupal\backstopjs\Exception\InvalidConfigurationException
   * @throws \Drupal\backstopjs\Exception\InvalidCommandException
   * @throws \Drupal\backstopjs\Exception\BackstopAlreadyRunningException
   * @throws \Drupal\backstopjs\Exception\FolderCreateException
   * @throws \Drupal\backstopjs\Exception\FileWriteException
   * @throws \Drupal\backstopjs\Exception\FileOpenException
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
   * @return array
   *   Array with data from the command run.
   *
   * @throws \Drupal\backstopjs\Exception\BackstopAlreadyRunningException
   *   When Backstop is already running.
   * @throws InvalidCommandException
   *   When the supplied command is not a valid BackstopJS command.
   * @throws InvalidEntityException
   * @throws InvalidConfigurationException
   * @throws \Drupal\backstopjs\Exception\FolderCreateException
   * @throws \Drupal\backstopjs\Exception\FileWriteException
   * @throws \Drupal\backstopjs\Exception\FileOpenException
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
   * @return array
   *   Array with data from the command run.
   *
   * @throws \Drupal\backstopjs\Exception\BackstopAlreadyRunningException
   *   When Backstop is already running.
   * @throws InvalidCommandException
   *   When the supplied command is not a valid BackstopJS command.
   * @throws InvalidEntityException
   * @throws InvalidConfigurationException
   * @throws \Drupal\backstopjs\Exception\FolderCreateException
   * @throws \Drupal\backstopjs\Exception\FileWriteException
   * @throws \Drupal\backstopjs\Exception\FileOpenException
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
   * @throws \Drupal\backstopjs\Exception\BackstopAlreadyRunningException
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

    $browser = 'chrome';
    if ($browserFromTest = $entity->getBrowser()) {
      $browser = $browserFromTest;
    }

    return $this->backstop->run($browser, $command, $entity);
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
    return \in_array($command, ['reference', 'test'], FALSE);
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
   * {@inheritdoc}
   */
  public function getStatus(): string {
    return $this->backstop->status();
  }

}
