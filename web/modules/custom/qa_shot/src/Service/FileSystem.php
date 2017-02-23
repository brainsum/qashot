<?php

namespace Drupal\qa_shot\Service;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\qa_shot\Custom\Backstop;
use Drupal\qa_shot\Entity\QAShotTestInterface;
use Drupal\qa_shot\Exception\InvalidEntityException;

/**
 * Class FileSystem.
 *
 * @package Drupal\qa_shot\Service
 */
class FileSystem {

  /**
   * The entity data base path in the public and private filesystem.
   *
   * @var string
   */
  const DATA_BASE_FOLDER = 'qa_test_data';

  /**
   * The private files folder for QAShot Test Entities without a trailing /.
   *
   * @var string
   */
  private $privateFiles;

  /**
   * The public files folder for QAShot Test Entities without a trailing /.
   *
   * @var string
   */
  private $publicFiles;

  /**
   * FileSystem constructor.
   */
  public function __construct() {
    $this->privateFiles = PrivateStream::basePath() . '/' . $this::DATA_BASE_FOLDER;
    $this->publicFiles = PublicStream::basePath() . '/' . $this::DATA_BASE_FOLDER;
  }

  // @todo: use this
  # /** @var \Drupal\Core\File\FileSystem $fs */
  # $fs = \Drupal::service('file_system');

  /**
   * Creates a directory at the given path.
   *
   * @param string $dirToCreate
   *   Path of the directory to be created.
   *
   * @return bool
   *   Whether the folder exists or creating it succeeded.
   */
  public function createFolder($dirToCreate) {
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
  public function createFile($configurationPath, $jsonString) {
    // @todo: throw exceptions
    // @todo: check if file exists, if yes, check if it's the same as the new one.
    // if yes, skip
    if (($configFile = fopen($configurationPath, 'w')) === FALSE) {
      dpm('failed to open config file to write');
      return FALSE;
    }

    if (fwrite($configFile, $jsonString) === FALSE) {
      dpm('failed to write config file');
      return FALSE;
    }

    if (fclose($configFile) === FALSE) {
      dpm('failed to close config file');
      return FALSE;
    }

    dpm('config write success');

    return TRUE;
  }

  /**
   * @param $src
   * @param $target
   *
   * @return bool
   */
  public function copyTemplates($src, $target) {
    // @todo: use exceptions
    dpm($src, 'copy src');
    dpm($target, 'copy target');

    if (($fileList = scandir($src)) === FALSE) {
      dpm('scandir failed');
      return FALSE;
    }

    // @todo: scandir target, if file is there and they are the same, skip the file

    $result = TRUE;

    foreach ($fileList as $file) {
      if (strpos($file, '.js') === FALSE) {
        continue;
      }

      $result |= copy($src . '/' . $file, $target . '/' . $file);
    }

    return $result;
  }

  /**
   * Function that initializes a backstop configuration for the entity.
   *
   * Does the following:
   *    Creates the folder for the entity,
   *    Creates the backstop.json file,
   *    Copies template files to the proper directories,
   *    Saves some data to the entity.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $entity
   *   The QAShot Test entity.
   *
   * @throws \Drupal\qa_shot\Exception\InvalidEntityException
   * @throws \Exception
   */
  public function initializeEnvironment(QAShotTestInterface $entity) {
    if (NULL === $entity || $entity->getEntityTypeId() !== 'qa_shot_test') {
      throw new InvalidEntityException('The entity is empty or its type is not QAShot Test!');
    }

    // @todo: refactor
    // @todo: . "/" . revision id; to both paths.
    $privateEntityData = $this->privateFiles . '/' . $entity->id();
    $publicEntityData = $this->publicFiles . '/' . $entity->id();
    $templateFolder = $this->privateFiles . '/template';
    $configPath = $privateEntityData . '/backstop.json';

    $configAsArray = $entity->toBackstopConfigArray($privateEntityData, $publicEntityData, FALSE);
    $jsonConfig = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    $configAsJSON = json_encode($configAsArray, $jsonConfig);

    $privateCasperFolder = $configAsArray['paths']['casper_scripts'];
    $reportPath = $configAsArray['paths']['html_report'] . '/index.html';


    if (FALSE === $this->createFolder($privateEntityData)) {
      throw new \Exception('Creating the private base folder at ' . $privateEntityData . ' for the entity failed.');
    }

    if (FALSE === $this->createFile($configPath, $configAsJSON)) {
      throw new \Exception('Creating the configuration file at ' . $configPath . ' failed.');
    }

    if (FALSE === $this->createFolder($privateCasperFolder)) {
      throw new \Exception('Creating the folder for casper scripts at ' . $privateCasperFolder . ' failed.');
    }

    if (FALSE === $this->copyTemplates($templateFolder . '/casper_scripts', $configAsArray['paths']['casper_scripts'])) {
      throw new \Exception('Copying the template casper scripts failed.');
    }

    if (
      $entity->get('field_configuration_path')->value !== $configPath ||
      $entity->get('field_html_report_path')->value !== $reportPath
    ) {
      $entity->set('field_configuration_path', $configPath);
      $entity->set('field_html_report_path', $reportPath);
      $entity->save();
    }
  }

  /**
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $entity
   *   The QAShot Test entity.
   *
   * @return bool
   */
  public function removePublicData(QAShotTestInterface $entity) {
    $dir = $this->publicFiles . '/' . $entity->id();
    return $this->removeDirectory($dir);
  }

  /**
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $entity
   *   The QAShot Test entity.
   *
   * @return bool
   */
  public function removePrivateData(QAShotTestInterface $entity) {
    $dir = $this->privateFiles . '/' . $entity->id();
    return $this->removeDirectory($dir);
  }


  /**
   * @param string $dir
   *
   * @return bool
   */
  public function removeDirectory($dir) {
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
