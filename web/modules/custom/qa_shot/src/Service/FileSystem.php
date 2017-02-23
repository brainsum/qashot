<?php

namespace Drupal\qa_shot\Service;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\qa_shot\Custom\Backstop;
use Drupal\qa_shot\Entity\QAShotTestInterface;
use Drupal\qa_shot\Exception\FileCopyException;
use Drupal\qa_shot\Exception\FileCreateException;
use Drupal\qa_shot\Exception\FileOpenException;
use Drupal\qa_shot\Exception\FileWriteException;
use Drupal\qa_shot\Exception\FolderCreateException;
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
   * @throws \Drupal\qa_shot\Exception\FolderCreateException
   */
  public function createFolder($dirToCreate) {
    if (is_dir($dirToCreate)) {
      return;
    }

    // Create directory and parents as well.
    if (!mkdir($dirToCreate, 0775, TRUE) && !is_dir($dirToCreate)) {
      throw new FolderCreateException("Creating the $dirToCreate folder failed.");
    }
  }

  /**
   * Create a new BackstopJS configuration file for the entity.
   *
   * @param string $configurationPath
   *   Path to the BackstopJS configuration file.
   * @param string $jsonString
   *   The json data to be written.
   *
   * @throws FileWriteException
   * @throws FileOpenException
   */
  public function createConfigFile($configurationPath, $jsonString) {
    // @todo: check if file exists, if yes, check if it's the same as the new one.
    if (($configFile = fopen($configurationPath, 'w')) === FALSE) {
      throw new FileOpenException("Opening the configuration file at $configurationPath failed.");
    }

    if (fwrite($configFile, $jsonString) === FALSE) {
      throw new FileWriteException("Writing the configuration file at $configurationPath failed.");
    }

    if (fclose($configFile) === FALSE) {
      throw new FileWriteException("Closing the configuration file at $configurationPath failed.");
    }
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
   * @throws \Drupal\qa_shot\Exception\FileOpenException
   * @throws \Drupal\qa_shot\Exception\FileWriteException
   * @throws \Drupal\qa_shot\Exception\FileCloseException
   * @throws \Drupal\qa_shot\Exception\FileCopyException
   * @throws \Drupal\qa_shot\Exception\FolderCreateException
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
    $jsonEncodeSettings = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    $configAsJSON = json_encode($configAsArray, $jsonEncodeSettings);

    $privateCasperFolder = $configAsArray['paths']['casper_scripts'];
    $reportPath = $configAsArray['paths']['html_report'] . '/index.html';


    $this->createFolder($privateEntityData);
    $this->createConfigFile($configPath, $configAsJSON);
    $this->createFolder($privateCasperFolder);

    if (FALSE === $this->copyTemplates($templateFolder . '/casper_scripts', $configAsArray['paths']['casper_scripts'])) {
      throw new FileCopyException('Copying the casper script templates failed.');
    }

    // If the paths changed we save them.
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
   * Remove the stored public data of the entity from the filesystem.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $entity
   *   The QAShot Test entity.
   *
   * @return bool
   *   Whether the removal was a success or not.
   */
  public function removePublicData(QAShotTestInterface $entity) {
    $dir = $this->publicFiles . '/' . $entity->id();
    return $this->removeDirectory($dir);
  }

  /**
   * Remove the stored private data of the entity from the filesystem.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $entity
   *   The QAShot Test entity.
   *
   * @return bool
   *   Whether the removal was a success or not.
   */
  public function removePrivateData(QAShotTestInterface $entity) {
    $dir = $this->privateFiles . '/' . $entity->id();
    return $this->removeDirectory($dir);
  }

  /**
   * Recursively remove a directory.
   *
   * @param string $dir
   *   The path of directory to be removed.
   *
   * @return bool
   *   Whether the removal was a success or not.
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
