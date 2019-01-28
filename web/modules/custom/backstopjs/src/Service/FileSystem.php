<?php

namespace Drupal\backstopjs\Service;

use Drupal\backstopjs\Exception\FileCopyException;
use Drupal\backstopjs\Exception\FileOpenException;
use Drupal\backstopjs\Exception\FileWriteException;
use Drupal\backstopjs\Exception\FolderCreateException;
use Drupal\backstopjs\Exception\InvalidEntityException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\qa_shot\Entity\QAShotTestInterface;

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
  public const DATA_BASE_FOLDER = 'qa_test_data';

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
   * QAShot Test entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private $testStorage;

  /**
   * FileSystem constructor.
   *
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   File system service.
   * @param \Drupal\backstopjs\Service\ConfigurationConverter $configConverter
   *   The configuration converter.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    FileSystemInterface $fileSystem,
    ConfigurationConverter $configConverter,
    EntityTypeManagerInterface $entityTypeManager
  ) {
    $this->privateFiles = PrivateStream::basePath() . '/' . $this::DATA_BASE_FOLDER;
    $this->publicFiles = PublicStream::basePath() . '/' . $this::DATA_BASE_FOLDER;

    $this->fileSystem = $fileSystem;
    $this->configConverter = $configConverter;
    $this->testStorage = $entityTypeManager->getStorage('qa_shot_test');
  }

  /**
   * Return the public file location.
   *
   * @return string
   *   Public files.
   */
  public function getPublicFiles(): string {
    return $this->publicFiles;
  }

  /**
   * Return the private file location.
   *
   * @return string
   *   Private files.
   */
  public function getPrivateFiles(): string {
    return $this->privateFiles;
  }

  /**
   * Creates a directory at the given path.
   *
   * @param string $dirToCreate
   *   Path of the directory to be created.
   *
   * @throws \Drupal\backstopjs\Exception\FolderCreateException
   */
  public function createFolder($dirToCreate): void {
    if (\is_dir($dirToCreate)) {
      return;
    }

    // Create directory and parents as well.
    if (!$this->fileSystem->mkdir($dirToCreate, 0775, TRUE) && !\is_dir($dirToCreate)) {
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
  public function createConfigFile($configurationPath, $jsonString): void {
    // @todo: check if file exists, if yes, check if it's the same as the new one.
    if (($configFile = \fopen($configurationPath, 'wb')) === FALSE) {
      throw new FileOpenException("Opening the configuration file at $configurationPath failed.");
    }

    if (\fwrite($configFile, $jsonString) === FALSE) {
      throw new FileWriteException("Writing the configuration file at $configurationPath failed.");
    }

    if (\fclose($configFile) === FALSE) {
      throw new FileWriteException("Closing the configuration file at $configurationPath failed.");
    }

    $this->setFilePermissions($configurationPath);
  }

  /**
   * Set permissions for files.
   *
   * @param string $filename
   *   The filename.
   *
   * @throws \Drupal\backstopjs\Exception\FileWriteException
   */
  private function setFilePermissions($filename): void {
    if ($this->fileSystem->chmod($filename, 0664) === FALSE) {
      throw new FileWriteException("Operation 'chmod' on file '$filename' failed.");
    }
    if (\chown($filename, 'www-data') === FALSE) {
      throw new FileWriteException("Operation 'chown' on file '$filename' failed.");
    }

    if (\chgrp($filename, 'www-data') === FALSE) {
      throw new FileWriteException("Operation 'chgrp' on file '$filename' failed.");
    }
  }

  /**
   * Compare two files.
   *
   * @param string $firstFilename
   *   File name for the first file.
   * @param string $secondFilename
   *   File name for the second file.
   *
   * @return bool
   *   TRUE, if they are the same.
   *
   * @see https://jonlabelle.com/snippets/view/php/quickly-check-if-two-files-are-identical
   * @see http://php.net/manual/en/function.md5-file.php#94494
   */
  private function filesAreIdentical(string $firstFilename, string $secondFilename): bool {
    if (
      \file_exists($firstFilename) === FALSE
      || \file_exists($secondFilename) === FALSE
    ) {
      return FALSE;
    }

    if (\filetype($firstFilename) !== \filetype($secondFilename)) {
      return FALSE;
    }

    if (\filesize($firstFilename) !== \filesize($secondFilename)) {
      return FALSE;
    }

    if (!($fpFirst = \fopen($firstFilename, 'rb'))) {
      return FALSE;
    }

    if (!($fpSecond = \fopen($secondFilename, 'rb'))) {
      \fclose($fpFirst);
      return FALSE;
    }

    $isSame = TRUE;

    while (!\feof($fpFirst) and !\feof($fpSecond)) {
      if (\fread($fpFirst, 8192) !== \fread($fpSecond, 8192)) {
        $isSame = FALSE;
        break;
      }
    }

    if (\feof($fpFirst) !== \feof($fpSecond)) {
      $isSame = FALSE;
    }

    \fclose($fpFirst);
    \fclose($fpSecond);

    return $isSame;
  }

  /**
   * Copy the required template files into the target folder.
   *
   * @param string $src
   *   Template folder.
   * @param string $target
   *   Target folder.
   *
   * @throws \Drupal\backstopjs\Exception\FileCopyException
   * @throws \Drupal\backstopjs\Exception\FileWriteException
   */
  private function copyTemplates($src, $target): void {
    if (($fileList = \scandir($src, NULL)) === FALSE) {
      throw new FileCopyException("Opening script template directory '$src' failed.");
    }

    $result = TRUE;

    foreach ($fileList as $file) {
      if (\strpos($file, '.js') === FALSE) {
        continue;
      }

      $srcFile = $src . '/' . $file;
      $targetFile = $target . '/' . $file;

      if ($this->filesAreIdentical($srcFile, $targetFile) === TRUE) {
        continue;
      }

      $couldCopy = \copy($srcFile, $targetFile);
      if ($couldCopy === FALSE) {
        throw new FileCopyException("Making a copy of the '$srcFile' template failed (target: '$targetFile').");
      }

      $this->setFilePermissions($targetFile);

      $result |= $couldCopy;
    }

    if ($result === FALSE) {
      throw new FileCopyException("Copying script templates from '$src' failed.");
    }
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
   * @throws \Drupal\backstopjs\Exception\FileCopyException
   * @throws \Drupal\backstopjs\Exception\FolderCreateException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \InvalidArgumentException
   */
  public function initializeEnvironment(QAShotTestInterface $entity): void {
    if (NULL === $entity || $entity->getEntityTypeId() !== 'qa_shot_test') {
      throw new InvalidEntityException('The entity is empty or its type is not QAShot Test!');
    }

    // @todo: refactor
    // @todo: . "/" . revision id; to both paths.
    $privateEntityData = $this->privateFiles . '/' . $entity->id();
    $templateBaseFolder = $this->privateFiles . '/template';
    $configPath = $privateEntityData . '/backstop.json';

    $configAsArray = $this->configConverter->entityToArray($entity);
    $jsonEncodeSettings = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    $configAsJSON = \json_encode($configAsArray, $jsonEncodeSettings);

    $reportPath = $configAsArray['paths']['html_report'] . '/index.html';

    $this->createFolder($privateEntityData);
    $this->createFolder($configAsArray['paths']['html_report']);
    $this->createFolder($configAsArray['paths']['ci_report']);
    $this->createFolder($privateEntityData . '/tmp');
    $this->createConfigFile($configPath, $configAsJSON);

    $engineScriptPath = \explode('/', $configAsArray['paths']['engine_scripts']);
    $templateFolder = \end($engineScriptPath);
    $this->createFolder($configAsArray['paths']['engine_scripts']);
    $this->copyTemplates($templateBaseFolder . '/' . $templateFolder, $configAsArray['paths']['engine_scripts']);

    // If the paths changed we save them.
    // @todo: Re-visit this and make it not clash with remote.
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
  public function clearFiles(QAShotTestInterface $entity): void {
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
  private function removePublicData(QAShotTestInterface $entity): bool {
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
  private function removePrivateData(QAShotTestInterface $entity): bool {
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
  private function removeDirectory($dir): bool {
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
    $testIds = array_keys($this->testStorage->loadMultiple());

    foreach ($testIds as $id) {
      $this->removedUnusedFilesByTestId($id);
    }

    // Remove stuck folders.
    $folders = array_values(array_diff(scandir($this->publicFiles, SCANDIR_SORT_DESCENDING), [
      '.',
      '..',
    ]));
    // Get the folders where the test entity is missing.
    $stuckTestData = array_diff($folders, $testIds);

    // Remove data for the missing entities.
    foreach ($stuckTestData as $data) {
      $this->removeDirectory($this->publicFiles . '/' . $data);
      $this->removeDirectory($this->privateFiles . '/' . $data);
    }
  }

  /**
   * Remove unused data for a specific test by its ID.
   *
   * @param string|int $testId
   *   The test entity ID.
   */
  public function removedUnusedFilesByTestId($testId) {
    $dataFolder = $this->publicFiles . '/' . $testId . '/test';

    if (!is_dir($dataFolder)) {
      return;
    }

    // Get the folders without the . and .. ones in reverse order.
    $folders = array_values(array_diff(scandir($dataFolder, SCANDIR_SORT_DESCENDING), [
      '.',
      '..',
    ]));

    // If there are more than one items in it, remove the first.
    // This should mean the removal of the latest item.
    if (count($folders) > 0) {
      unset($folders[0]);
    }

    foreach ($folders as $folder) {
      $this->removeDirectory($dataFolder . '/' . $folder);
    }
  }

  /**
   * Remove unused data for a specific test.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $test
   *   The test entity.
   */
  public function removedUnusedFilesForTest(QAShotTestInterface $test) {
    $this->removedUnusedFilesByTestId($test->id());
  }

}
