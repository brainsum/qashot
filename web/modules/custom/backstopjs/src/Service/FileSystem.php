<?php

namespace Drupal\backstopjs\Service;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\qa_shot\Entity\QAShotTestInterface;
use Drupal\backstopjs\Exception\FileCopyException;
use Drupal\backstopjs\Exception\FileOpenException;
use Drupal\backstopjs\Exception\FileWriteException;
use Drupal\backstopjs\Exception\FolderCreateException;
use Drupal\backstopjs\Exception\InvalidEntityException;

/**
 * Class FileSystem.
 *
 * @package Drupal\backstopjs\Service
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
   * File system service from core.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  private $fileSystem;

  /**
   * The configuration converter service.
   *
   * @var \Drupal\backstopjs\Service\ConfigurationConverter
   */
  private $configConverter;

  /**
   * FileSystem constructor.
   *
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   File system service.
   * @param \Drupal\backstopjs\Service\ConfigurationConverter $configConverter
   *   The configuration converter.
   */
  public function __construct(FileSystemInterface $fileSystem, ConfigurationConverter $configConverter) {
    $this->privateFiles = PrivateStream::basePath() . DIRECTORY_SEPARATOR . $this::DATA_BASE_FOLDER;
    $this->publicFiles = PublicStream::basePath() . DIRECTORY_SEPARATOR . $this::DATA_BASE_FOLDER;

    $this->fileSystem = $fileSystem;
    $this->configConverter = $configConverter;
  }

  /**
   * Creates a directory at the given path.
   *
   * @param string $dirToCreate
   *   Path of the directory to be created.
   *
   * @throws \Drupal\backstopjs\Exception\FolderCreateException
   */
  private function createFolder($dirToCreate) {
    if (is_dir($dirToCreate)) {
      return;
    }

    // Create directory and parents as well.
    if (!$this->fileSystem->mkdir($dirToCreate, 0775, TRUE) && !is_dir($dirToCreate)) {
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
  private function createConfigFile($configurationPath, $jsonString) {
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
   * Copy the required template files into the target folder.
   *
   * @param string $src
   *   Template folder.
   * @param string $target
   *   Target folder.
   *
   * @return bool
   *   Whether the copy was a success or not.
   */
  private function copyTemplates($src, $target) {
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
   * @throws \Drupal\backstopjs\Exception\InvalidEntityException
   * @throws \Drupal\backstopjs\Exception\FileOpenException
   * @throws \Drupal\backstopjs\Exception\FileWriteException
   * @throws \Drupal\backstopjs\Exception\FileCloseException
   * @throws \Drupal\backstopjs\Exception\FileCopyException
   * @throws \Drupal\backstopjs\Exception\FolderCreateException
   */
  public function initializeEnvironment(QAShotTestInterface $entity) {
    if (NULL === $entity || $entity->getEntityTypeId() !== 'qa_shot_test') {
      throw new InvalidEntityException('The entity is empty or its type is not QAShot Test!');
    }

    // @todo: refactor
    // @todo: . "/" . revision id; to both paths.
    $privateEntityData = $this->privateFiles . '/' . $entity->id();
    $templateFolder = $this->privateFiles . '/template';
    $configPath = $privateEntityData . '/backstop.json';

    $configAsArray = $this->configConverter->entityToArray($entity, FALSE);
    $jsonEncodeSettings = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    $configAsJSON = json_encode($configAsArray, $jsonEncodeSettings);

    $privateCasperFolder = $configAsArray['paths']['casper_scripts'];
    $reportPath = $configAsArray['paths']['html_report'] . '/index.html';

    $this->createFolder($privateEntityData);
    $this->createFolder($configAsArray['paths']['html_report']);
    $this->createFolder($privateEntityData . '/tmp');
    $this->createConfigFile($configPath, $configAsJSON);
    $this->createFolder($privateCasperFolder);

    if (FALSE === $this->copyTemplates($templateFolder . '/casper_scripts', $configAsArray['paths']['casper_scripts'])) {
      throw new FileCopyException('Copying the casper script templates failed.');
    }

    // If the paths changed we save them.
    if (
      $entity->getConfigurationPath() !== $configPath ||
      $entity->getHtmlReportPath() !== $reportPath
    ) {
      $entity->setConfigurationPath($configPath);
      $entity->setHtmlReportPath($reportPath);
      $entity->save();
    }
  }

  /**
   * Clean up the stored files for an entity.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $entity
   *   The entity.
   */
  public function clearFiles(QAShotTestInterface $entity) {
    $pubRemoveRes = $this->removePublicData($entity);
    $privRemoveRes = $this->removePrivateData($entity);

    drupal_set_message(
      $privRemoveRes ? 'Private data folder removed' : 'Private data folder not removed',
      $privRemoveRes ? 'status' : 'error'
    );
    drupal_set_message(
      $pubRemoveRes ? 'Public data folder removed' : 'Public data folder not removed',
      $pubRemoveRes ? 'status' : 'error'
    );
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
  private function removePublicData(QAShotTestInterface $entity) {
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
  private function removePrivateData(QAShotTestInterface $entity) {
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
  private function removeDirectory($dir) {
    if (!is_dir($dir)) {
      return TRUE;
    }

    $iterator = new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new \RecursiveIteratorIterator($iterator,
      \RecursiveIteratorIterator::CHILD_FIRST);

    $result = TRUE;

    foreach ($files as $file) {
      if ($file->isDir()) {
        $result |= $this->fileSystem->rmdir($file->getRealPath());
      }
      else {
        $result |= $this->fileSystem->unlink($file->getRealPath());
      }
    }
    $result |= $this->fileSystem->rmdir($dir);

    return $result;
  }

  /**
   * Remove unused public files and folders.
   */
  public function removeUnusedFiles() {
    $tests = \Drupal::entityTypeManager()->getStorage('qa_shot_test')->loadMultiple();
    foreach ($tests as $test) {
      $dataFolder = $this->publicFiles . DIRECTORY_SEPARATOR . $test->id() . DIRECTORY_SEPARATOR . 'test';

      if (!is_dir($dataFolder)) {
        continue;
      }

      // Get the folders without the . and .. ones in reverse order.
      $folders = array_values(array_diff(scandir($dataFolder, SCANDIR_SORT_DESCENDING), ['.', '..']));

      // If there are more than one items in it, remove the first.
      // This should mean the removal of the latest item.
      if (count($folders) > 0) {
        unset($folders[0]);
      }

      foreach ($folders as $folder) {
        $this->removeDirectory($dataFolder . DIRECTORY_SEPARATOR . $folder);
      }
    }
  }

}
