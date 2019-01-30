<?php

namespace Drupal\backstopjs\Plugin\TestWorker;

use Drupal\backstopjs\Backstopjs\BackstopjsWorkerBase;
use Drupal\backstopjs\Exception\BackstopAlreadyRunningException;
use Drupal\backstopjs\Exception\FileCloseException;
use Drupal\Component\Utility\Html;
use Drupal\qa_shot\Entity\QAShotTestInterface;
use Symfony\Component\Process\Process;

/**
 * Class LocalBackstopJS.
 *
 * Implements running BackstopJS from a local binary.
 *
 * @todo: Move 'Local' related functions here from web/modules/custom/backstopjs/src/Form/BackstopjsSettingsForm.php
 * @todo: Refactor exec-s and etc to use the new functions
 *
 * @package Drupal\backstopjs\Plugin\BackstopjsWorker
 *
 * @TestWorker(
 *   id = "backstopjs.local",
 *   backend = "backstopjs",
 *   type = "local",
 *   label = @Translation("Local binaries"),
 *   description = @Translation("Worker for local binaries")
 * )
 *
 * @backstopjs v3.8.8
 */
class LocalBackstopjsWorker extends BackstopjsWorkerBase {

  public const COMMAND_CHECK_STATUS = 'pgrep -f backstop -c';
  public const COMMAND_GET_STATUS = 'pgrep -l -a -f backstop';

  /**
   * {@inheritdoc}
   */
  public function status() {
    $checkerCommand = escapeshellcmd(self::COMMAND_GET_STATUS);
    // @todo: Refactor and use \Symfony\Component\Process\Process.
    \exec($checkerCommand, $execOutput, $status);

    $result = \array_filter($execOutput, function ($row) use ($checkerCommand) {
      return \strpos($row, $checkerCommand) === FALSE;
    });

    return \json_encode(['output' => $result, 'status' => $status]);
  }

  /**
   * {@inheritdoc}
   */
  public function checkRunStatus() {
    $checkerCommand = \escapeshellcmd(self::COMMAND_CHECK_STATUS);
    // @todo: Refactor and use \Symfony\Component\Process\Process.
    $res = \exec($checkerCommand);

    // > 1 is used since the pgrep command gets included as well.
    if (\is_numeric($res) && (int) $res > 1) {
      $this->logger->warning('BackstopJS is already running.');
      throw new BackstopAlreadyRunningException('BackstopJS is already running.');
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\backstopjs\Exception\FolderCreateException
   * @throws \Drupal\backstopjs\Exception\FileCloseException
   */
  public function run(string $browser, string $command, QAShotTestInterface $entity): array {
    $testId = $entity->id();
    $configPath = $entity->getConfigurationPath();
    $configArray = \json_decode(\file_get_contents($configPath), TRUE);

    // @todo: Detect, if the process failed.
    // Backstop tends to return 0/1 exit code, might be enough to check that.
    $results = [
      'result' => TRUE,
      'passedTestCount' => NULL,
      'failedTestCount' => NULL,
      'bitmapGenerationSuccess' => FALSE,
      'engine' => $configArray['engine'],
      'browser' => $browser,
    ];

    $path = $this->config->get('suite.binary_path');
    $executable = $path ? $path . 'backstop' : 'backstop';

    $backstopCommand = \escapeshellcmd("{$executable} {$command} --configPath={$configPath}");
    $this->messenger()->addStatus("Starting '$command' for '$testId'..");

    /*
     * @todo: Update debug file list (currently, timestamp is displayed).
     * @todo: Move debug file to a "debug" tab from "view" [entity].
     */
    $debugPath = $this->backstopFileSystem->getPrivateFiles() . "/$testId/debug";
    $this->backstopFileSystem->createFolder($debugPath);
    $debugFileName = \time() . ".$command.$testId.debug.txt";
    $debugFile = \fopen($debugPath . '/' . $debugFileName, 'wb');
    // @todo: if ($debugFile === FALSE) { exception? message? }
    // @todo: \fwrite() === FALSE cases.
    $process = new Process($backstopCommand);
    // Default MariaDB timeout is 420 for the wodby image, use something lower.
    // 5 mins should be plenty.
    $process->setTimeout(300);
    $process->enableOutput();

    $process->run(function ($type, $data) use (&$results, $debugFile) {
      // Log output to the debug file.
      \fwrite($debugFile, Html::escape($data));

      // Search for bitmap generation string.
      if (
        \strpos($data, 'Bitmap file generation completed.') !== FALSE
        || \strpos($data, 'Command "reference" successfully executed') !== FALSE
        // If reports are executing, we can be certain, that bitmapgen was OK.
        || \strpos($data, 'Executing core for "report"') !== FALSE
      ) {
        $results['bitmapGenerationSuccess'] = TRUE;
      }
      // @todo: Command `{$command}` sucessfully executed in [{$float}s]
      // @todo: Command `{$command}` ended with an error after [{$float}s]
      // @todo: Command "test" ended with an error after [30.174s]
      // Search for the reports.
      if (\strpos($data, 'report |') !== FALSE) {
        // Search for the number of passed tests.
        $passedMatches = [];
        if (
          $results['passedTestCount'] === NULL
          && \strpos($data, 'Passed') !== FALSE
          && \preg_match('/report \| (\d+) Passed/', $data, $passedMatches) === 1
        ) {
          // Due to the (\d+) capture group, 1 has the count.
          // FFR, 0 is the exact match, e.g "report | 4 Passed".
          $results['passedTestCount'] = (int) $passedMatches[1];
        }
        unset($passedMatches);

        // Search for the number of failed tests.
        $failedMatches = [];
        if (
          $results['failedTestCount'] === NULL
          && \strpos($data, 'Failed') !== FALSE
          && \preg_match('/report \| (\d+) Failed/', $data, $failedMatches) === 1
        ) {
          $results['failedTestCount'] = (int) $failedMatches[1];
        }
        unset($failedMatches);
      }
    });

    // @todo: Check if the process timed out.
    // @todo: Check if the process finished.
    // @todo: Kill backstop processes (belonging to this process).
    // @todo: -- $this->backstop->getStatus() returns pids, kill them manually.
    // @todo: Add "local"/"remote" to debug file name.
    // @todo: Add additional info to debug file.
    // @todo: Merge debugging solution with remote worker.
    $stringResults = \json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $configJson = \json_encode($configArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $isRunning = $process->isRunning() ? 'Yes' : 'No';
    $isTerminated = $process->isTerminated() ? 'Yes' : 'No';
    $isSuccessful = $process->isSuccessful() ? 'Yes' : 'No';
    $summaryMessage = <<<EOD


SUMMARY
---------------------------------------------------
Run:
- Test ID: {$testId}
- Command: {$command}

Process:
- Running: {$isRunning}
- Terminated: {$isTerminated}
- Successful: {$isSuccessful}
- Exit code: {$process->getExitCode()} ({$process->getExitCodeText()})

Results array:
{$stringResults}

BackstopJS configuration:
{$configJson}
EOD;

    \fwrite($debugFile, $summaryMessage);

    if (\fclose($debugFile) === FALSE) {
      $message = "Closing the debug file failed for test '$testId', path: $debugPath/$debugFileName.";
      $this->logger->debug($message);
      throw new FileCloseException($message);
    }

    if ($results['bitmapGenerationSuccess'] === FALSE) {
      $results['result'] = FALSE;
      $this->messenger()->addError($this->t('Bitmap generation failed.'));
      return $results;
    }

    $this->messenger()->addStatus("Ending '$command' for '$testId'..");

    return $results;
  }

}
