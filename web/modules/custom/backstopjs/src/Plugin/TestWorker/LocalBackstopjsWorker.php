<?php

namespace Drupal\backstopjs\Plugin\TestWorker;

use Drupal\backstopjs\Backstopjs\BackstopjsWorkerBase;
use Drupal\backstopjs\Exception\BackstopAlreadyRunningException;
use Drupal\backstopjs\Exception\FileOpenException;
use Drupal\backstopjs\Exception\FileWriteException;
use Drupal\backstopjs\Exception\FolderCreateException;
use Drupal\qa_shot\Entity\QAShotTestInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
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
   * @throws \Drupal\backstopjs\Exception\FileWriteException
   * @throws \Drupal\backstopjs\Exception\FolderCreateException
   * @throws \Drupal\backstopjs\Exception\FileOpenException
   */
  public function run(string $browser, string $command, QAShotTestInterface $entity): array {
    // @todo: Add an admin form where the user can input the path of binaries.
    // @todo: What if local install, not docker/server?
    switch ($browser) {
      case 'firefox':
        $engine = 'slimerjs';
        break;

      case 'chrome':
        $engine = 'puppeteer';
        break;

      case 'phantomjs':
        $engine = 'phantomjs';
        break;

      default:
        throw new \RuntimeException('Invalid browser (' . $browser . ').');
    }

    $testId = $entity->id();
    // With slimerjs we have to use xvfb-run.
    $xvfb = '';
    if ($browser === 'firefox') {
      $xvfb = 'xvfb-run -a ';
    }

    // @todo: Dep.Inj
    /** @var \Drupal\Core\File\FileSystemInterface $filesystem */
    $filesystem = \Drupal::service('file_system');
    $arguments = ' ' . $command . ' --configPath=' . $filesystem->realpath($entity->getConfigurationPath());

    $results = [
      'result' => TRUE,
      'passedTestCount' => NULL,
      'failedTestCount' => NULL,
      'bitmapGenerationSuccess' => FALSE,
      'engine' => $engine,
      'browser' => $browser,
    ];

    $path = $this->config->get('suite.binary_path');
    $executable = $path ? $path . 'backstop' : 'backstop';

    $backstopCommand = \escapeshellcmd($xvfb . $executable . $arguments);
    $this->messenger()->addStatus("Starting '$command' for '$testId'..");

    // @requires proc_open.
    $process = new Process($backstopCommand);
    // 10 mins should be enough.
    $process->setTimeout(600);
    $process->enableOutput();

    try {
      $process->mustRun(function ($type, $data) use (&$results) {
        // Search for bitmap generation string.
        if (
          \strpos($data, 'Bitmap file generation completed.') !== FALSE
          || \strpos($data, 'Command `reference` successfully executed') !== FALSE
        ) {
          $results['bitmapGenerationSuccess'] = TRUE;
        }
        // @todo: Command `{$command}` sucessfully executed in [{$float}s]
        // @todo: Command `{$command}` ended with an error after [{$float}s]
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
    }
    catch (ProcessFailedException $exception) {
      $this->messenger()->addError("The '$command' for '$testId' failed. Error: " . $exception->getMessage());

      $results = [
        'result' => FALSE,
        'passedTestCount' => NULL,
        'failedTestCount' => NULL,
        'bitmapGenerationSuccess' => FALSE,
        'engine' => $engine,
        'browser' => $browser,
      ];
    }

    $execOutput = $process->getOutput();

    try {
      $debugPath = $this->backstopFileSystem->getPrivateFiles() . "/$testId/debug";
      $debugFile = \time() . '.debug.txt';
      $this->backstopFileSystem->createFolder($debugPath);
      $this->backstopFileSystem->createConfigFile($debugPath . '/' . $debugFile, \var_export($execOutput, TRUE));
    }
    catch (\Exception $e) {
      $log = $e instanceof FolderCreateException || $e instanceof FileWriteException || $e instanceof FileOpenException;
      if ($log) {
        $this->logger->debug("Could not create debug files for entity $testId.");
      }
      else {
        throw $e;
      }
    }

    if ($results['bitmapGenerationSuccess'] === FALSE) {
      $results['result'] = FALSE;
      $this->messenger()->addError($this->t('Bitmap generation failed.'));
      return $results;
    }

    return $results;
  }

}
