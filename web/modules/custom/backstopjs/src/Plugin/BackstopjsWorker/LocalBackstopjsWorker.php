<?php

namespace Drupal\backstopjs\Plugin\BackstopjsWorker;

use Drupal\backstopjs\Backstopjs\BackstopjsWorkerBase;
use Drupal\backstopjs\Exception\BackstopAlreadyRunningException;
use Drupal\backstopjs\Exception\FileOpenException;
use Drupal\backstopjs\Exception\FileWriteException;
use Drupal\backstopjs\Exception\FolderCreateException;
use Drupal\qa_shot\Entity\QAShotTestInterface;

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
 * @BackstopjsWorker(
 *   id = "local",
 *   title = @Translation("Local binaries"),
 *   description = @Translation("Worker for local binaries")
 * )
 */
class LocalBackstopjsWorker extends BackstopjsWorkerBase {

  const COMMAND_CHECK_STATUS = 'pgrep -f backstop -c';
  const COMMAND_GET_STATUS = 'pgrep -l -a -f backstop';

  /**
   * {@inheritdoc}
   */
  public function getStatus(): string {
    $checkerCommand = escapeshellcmd(self::COMMAND_GET_STATUS);
    // @todo: Refactor and use \Symfony\Component\Process\Process.
    exec($checkerCommand, $execOutput, $status);

    $result = array_filter($execOutput, function ($row) use ($checkerCommand) {
      return strpos($row, $checkerCommand) === FALSE;
    });

    return json_encode(['output' => $result, 'status' => $status]);
  }

  /**
   * {@inheritdoc}
   */
  public function checkRunStatus() {
    $checkerCommand = escapeshellcmd(self::COMMAND_CHECK_STATUS);
    // @todo: Refactor and use \Symfony\Component\Process\Process.
    $res = exec($checkerCommand);

    // > 1 is used since the pgrep command gets included as well.
    if (is_numeric($res) && (int) $res > 1) {
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
    // With slimerjs we have to use xvfb-run.
    $xvfb = '';
    if ($browser === 'firefox') {
      $xvfb = 'xvfb-run -a ';
    }

    $path = $this->config->get('suite.binary_path');
    $executable = $path ? $path . 'backstop' : 'backstop';
    $arguments = ' ' . $command . ' --configPath=' . $entity->getConfigurationPath();

    $backstopCommand = escapeshellcmd($xvfb . $executable . $arguments);
    /** @var array $execOutput */
    /** @var int $status */

    // @todo: Refactor and use \Symfony\Component\Process\Process.
    // @see: http://symfony.com/doc/2.8/components/process.html
    // @see: QAS-10
    exec($backstopCommand, $execOutput);

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

    $results = [
      'result' => TRUE,
      'passedTestCount' => NULL,
      'failedTestCount' => NULL,
      'bitmapGenerationSuccess' => FALSE,
      'engine' => $engine,
      'browser' => $browser,
    ];

    foreach ($execOutput as $line) {
      // Search for bitmap generation string.
      if (
        strpos($line, 'Bitmap file generation completed.') !== FALSE
        // @todo: Check https://github.com/garris/BackstopJS/issues/567
        || strpos($line, 'Command `reference` sucessfully executed') !== FALSE
        || strpos($line, 'Command `reference` successfully executed') !== FALSE
      ) {
        $results['bitmapGenerationSuccess'] = TRUE;
        continue;
      }

      // @todo: Command `{$command}` sucessfully executed in [{$float}s]
      // @todo: Command `{$command}` ended with an error after [{$float}s]
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

    try {
      $debugPath = $this->backstopFileSystem->getPrivateFiles() . '/' . $entity->id() . '/debug';
      $debugFile = time() . '.debug.txt';
      $this->backstopFileSystem->createFolder($debugPath);
      $this->backstopFileSystem->createConfigFile($debugPath . '/' . $debugFile, var_export($execOutput, TRUE));
    }
    catch (\Exception $e) {
      $log = $e instanceof FolderCreateException || $e instanceof FileWriteException || $e instanceof FileOpenException;
      if ($log) {
        $this->logger->debug('Could not create debug files for entity ' . $entity->id() . '.');
      }
      else {
        throw $e;
      }

    }

    if (!$results['bitmapGenerationSuccess']) {
      $results['result'] = FALSE;
      drupal_set_message($this->t('Bitmap generation failed.'));
      return $results;
    }

    /*
    if ($status !== 0) {
    // @todo: Here what?
    }
     */

    return $results;
  }

}
