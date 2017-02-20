<?php

namespace Drupal\qa_shot\Custom;

use \Drupal\Core\Entity\EntityInterface;
use Drupal\qa_shot\Exception\BackstopAlreadyRunningException;
use Drupal\qa_shot\Exception\InvalidCommandException;
use Drupal\qa_shot\Plugin\Field\FieldType\Viewport;
use Drupal\qa_shot\Plugin\Field\FieldType\Scenario;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\Core\StreamWrapper\PublicStream;

/**
 * Class Backstop, contains helper functions.
 *
 * @package Drupal\qa_shot\Custom
 */
class Backstop {
  // @todo: Reorder functions, publics first.

  /**
   * The entity data base path in the public and private filesystem.
   *
   * @var string
   */
  private static $customDataBase = "qa_test_data";

  /**
   * Debug variable.
   *
   * @var bool
   */
  private static $debugMode = FALSE;

  // @todo: use this
  # /** @var \Drupal\Core\File\FileSystem $fs */
  # $fs = \Drupal::service('file_system');

  /**
   * Function that initializes a backstop configuration for the entity.
   *
   * Does the following:
   *    Creates the folder for the entity,
   *    Creates the backstop.json file,
   *    Copies template files to the proper directories,
   *    Saves some data to the entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The QAShot Test entity.
   *
   * @throws \Exception
   */
  public static function initializeEnvironment(EntityInterface &$entity) {
    if (NULL === $entity || $entity->getEntityTypeId() !== 'qa_shot_test') {
      throw new \Exception('The entity is empty or its type is not QAShot Test!');
    }

    // @todo: refactor
    // @todo: . "/" . revision id; to both paths.
    $privateEntityData = PrivateStream::basePath() . "/" . self::$customDataBase . "/" . $entity->id();
    $publicEntityData = PublicStream::basePath() . "/" . self::$customDataBase . "/" . $entity->id();
    $templateFolder = PrivateStream::basePath() . "/" . self::$customDataBase . "/template";
    $configPath = $privateEntityData . "/backstop.json";

    $configAsArray = self::mapEntityToArray($entity, $privateEntityData, $publicEntityData);
    $jsonConfig = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    $configAsJSON = json_encode($configAsArray, $jsonConfig);

    $privateCasperFolder = $configAsArray["paths"]["casper_scripts"];
    $reportPath = $configAsArray["paths"]["html_report"] . "/index.html";

    if (FALSE === self::createFolder($privateEntityData)) {
      throw new \Exception("Creating the private base folder at " . $privateEntityData . " for the entity failed.");
    }

    if (FALSE === self::createFile($configPath, $configAsJSON)) {
      throw new \Exception("Creating the configuration file at " . $configPath . " failed.");
    }

    if (FALSE === self::createFolder($privateCasperFolder)) {
      throw new \Exception("Creating the folder for casper scripts at " . $privateCasperFolder . " failed.");
    }

    if (FALSE === self::copyTemplates($templateFolder . "/casper_scripts", $configAsArray["paths"]["casper_scripts"])) {
      throw new \Exception("Copying the template casper scripts failed.");
    }

    if (
      $entity->get("field_configuration_path")->value !== $configPath ||
      $entity->get("field_html_report_path")->value !== $reportPath
    ) {
      $entity->set("field_configuration_path", $configPath);
      $entity->set("field_html_report_path", $reportPath);
      $entity->save();
    }
  }

  /**
   * Helper function which maps the entity fields to an array.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The QAShot Test entity.
   * @param string $privateDataPath
   *   Path to the private filesystem.
   * @param string $publicDataPath
   *   Path to the public filesystem.
   *
   * @return array
   *   A backstop config as an array.
   */
  private static function mapEntityToArray(EntityInterface $entity, $privateDataPath, $publicDataPath) {
    // @todo: get these field values global settings

    $mapConfigToArray = [
      // @todo: maybe id + revision id.
      "id" => $entity->id(),
      "viewports" => [],
      "scenarios" => [],
      "paths" => [
        "bitmaps_reference" => $publicDataPath . "/reference",
        "bitmaps_test" => $publicDataPath . "/test",
        "casper_scripts" => $privateDataPath . "/casper_scripts",
        "html_report" => $publicDataPath . "/html_report",
        "ci_report" => $publicDataPath . "/ci_report",
      ],
      // "onBeforeScript" => "onBefore.js", //.
      // "onReadyScript" => "onReady.js", //.
      "engine" => "phantomjs",
      "report" => [
        "browser",
      ],
      "casperFlags" => [
        "--ignore-ssl-errors=true",
        "--ssl-protocol=any",
      ],
      "debug" => FALSE,
    ];

    foreach ($entity->get("field_viewport") as $viewport) {
      $mapConfigToArray["viewports"][] = self::mapViewportToArray($viewport);
    }

    foreach ($entity->get("field_scenario") as $scenario) {
      $mapConfigToArray["scenarios"][] = self::mapScenarioToArray($scenario);
    }

    if (self::$debugMode === TRUE) {
      $mapConfigToArray['debug'] = TRUE;
      $mapConfigToArray['casperFlags'][] = "--verbose";
    }

    return $mapConfigToArray;
  }

  /**
   * Maps the values of a scenario field to an array.
   *
   * @param Scenario $scenario
   *
   * @return array
   */
  private static function mapScenarioToArray(Scenario $scenario) {
    return array(
      "label" => (string) $scenario->get("label")->getValue(),
      "referenceUrl" => (string) $scenario->get("referenceUrl")->getValue(),
      "url" => (string) $scenario->get("testUrl")->getValue(),
      "readyEvent" => NULL,
      "delay" => 5000,
      "misMatchThreshold" => 0.0,
      "selectors" => [
        "document",
      ],
      "removeSelectors" => [
        "#twitter-widget-0",
        "#twitter-widget-1",
        ".captcha",
        "#sliding-popup",
      ],
      "hideSelectors" => [],
      "onBeforeScript" => "onBefore.js",
      "onReadyScript" => "onReady.js",
    );
  }

  /**
   * Maps a viewport field to an array.
   *
   * @param Viewport $viewport
   *
   * @return array
   */
  private static function mapViewportToArray(Viewport $viewport) {
    return array(
      "name" => (string) $viewport->get("name")->getValue(),
      "width" => (int) $viewport->get("width")->getValue(),
      "height" => (int) $viewport->get("height")->getValue(),
    );
  }

  /**
   * Creates a directory at the given path.
   *
   * @param string $dirToCreate
   *   Path of the directory to be created.
   *
   * @return bool
   *   Whether the folder exists or creating it succeeded.
   */
  private static function createFolder($dirToCreate) {
    if (is_dir($dirToCreate)) {
      return TRUE;
    }

    // Create directory and parents as well.
    if (!mkdir($dirToCreate, 0775, TRUE) && !is_dir($dirToCreate)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * @param $configurationPath
   * @param $jsonString
   *
   * @return bool
   */
  private static function createFile($configurationPath, $jsonString) {
    // @todo: throw exceptions
    // @todo: check if file exists, if yes, check if it's the same as the new one.
    // if yes, skip
    if (($configFile = fopen($configurationPath, "w")) === FALSE) {
      dpm("failed to open config file to write");
      return FALSE;
    }

    if (fwrite($configFile, $jsonString) === FALSE) {
      dpm("failed to write config file");
      return FALSE;
    }

    if (fclose($configFile) === FALSE) {
      dpm("failed to close config file");
      return FALSE;
    }

    dpm("config write success");

    return TRUE;
  }

  /**
   * @param $src
   * @param $target
   *
   * @return bool
   */
  private static function copyTemplates($src, $target) {
    // @todo: use exceptions
    dpm($src, "copy src");
    dpm($target, "copy target");

    if (($fileList = scandir($src)) === FALSE) {
      dpm("scandir failed");
      return FALSE;
    }

    // @todo: scandir target, if file is there and they are the same, skip the file

    $result = TRUE;

    foreach ($fileList as $file) {
      if (strpos($file, ".js") === FALSE) {
        continue;
      }

      $result |= copy($src . "/" . $file, $target . "/" . $file);
    }

    return $result;
  }

  /**
   * Checks whether Backstop is running or not.
   *
   * @return bool
   *   TRUE, if a BackstopJS process is already running. FALSE otherwise.
   */
  public static function isRunning() {
    $checkerCommand = escapeshellcmd('pgrep -f backstop -c');
    $res = exec($checkerCommand, $execOutput, $status);

    // > 1 is used since the pgrep command gets included as well.
    return (is_numeric($res) && (int) $res > 1);
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
   * Run the 'Reference' command.
   *
   * Executes the reference BackstopJS command to create reference
   * screenshots according to the supplied configuration.
   *
   * @param string $configurationPath
   *   Path to the configuration.
   *
   * @throws BackstopAlreadyRunningException
   *   When Backstop is already running.
   * @throws InvalidCommandException
   *   When the supplied command is not a valid BackstopJS command.
   *
   * @return bool
   *   TRUE on success, FALSE on failure.
   */
  public static function runReference($configurationPath) {
    return self::runCommand('reference', $configurationPath);
  }

  /**
   * Run the 'Test' command.
   *
   * @param string $configurationPath
   *   Path to the configuration.
   *
   * @throws BackstopAlreadyRunningException
   *   When Backstop is already running.
   * @throws InvalidCommandException
   *   When the supplied command is not a valid BackstopJS command.
   *
   * @return bool
   *   TRUE on success, FALSE on failure.
   */
  public static function runTest($configurationPath) {
    return self::runCommand('test', $configurationPath);
  }

  /**
   * Command runner logic.
   *
   * @param $command
   * @param $configurationPath
   *
   * @throws BackstopAlreadyRunningException
   *   When Backstop is already running.
   * @throws InvalidCommandException
   *   When the supplied command is not a valid BackstopJS command.
   *
   * @return bool
   */
  private static function runCommand($command, $configurationPath) {
    // @todo: use exceptions instead of return bool
    if (!self::isCommandValid($command)) {
      throw new InvalidCommandException("The supplied command '$command' is not valid.");
    }

    if (self::isRunning()) {
      throw new BackstopAlreadyRunningException('BackstopJS is already running.');
    }

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

    dpm($status, "exec status");

    if ($status !== 0) {
      // @todo: Test command gives exit 1 if the compare fails.
      // Search this line: "Bitmap file generation completed."
      // And/Or this line: "COMMAND | Executing core for `report`".
      dpm($execOutput, "Output of exec.");

      return FALSE;
    }

    return TRUE;
  }

  /**
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return bool
   */
  public static function removePublicData(EntityInterface $entity) {
    $dir = PublicStream::basePath() . "/" . self::$customDataBase . "/" . $entity->id();
    return self::removeDirectory($dir);
  }

  /**
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return bool
   */
  public static function removePrivateData(EntityInterface $entity) {
    $dir = PrivateStream::basePath() . "/" . self::$customDataBase . "/" . $entity->id();
    return self::removeDirectory($dir);
  }

  /**
   * @param string $dir
   *
   * @return bool
   */
  private static function removeDirectory($dir) {
    if (!is_dir($dir)) {
      return TRUE;
    }

    $iterator = new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new \RecursiveIteratorIterator($iterator,
      \RecursiveIteratorIterator::CHILD_FIRST);

    $result = TRUE;

    foreach ($files as $file) {
      if ($file->isDir()) {
        $result |= rmdir($file->getRealPath());
      }
      else {
        $result |= unlink($file->getRealPath());
      }
    }
    $result |= rmdir($dir);

    return $result;
  }

}
