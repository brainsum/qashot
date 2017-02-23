<?php

namespace Drupal\qa_shot\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining QAShot Test entities.
 *
 * @ingroup qa_shot
 */
interface QAShotTestInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  /**
   * Gets the QAShot Test name.
   *
   * @return string
   *   Name of the QAShot Test.
   */
  public function getName();

  /**
   * Sets the QAShot Test name.
   *
   * @param string $name
   *   The QAShot Test name.
   *
   * @return \Drupal\qa_shot\Entity\QAShotTestInterface
   *   The called QAShot Test entity.
   */
  public function setName($name);

  /**
   * Gets the QAShot Test creation timestamp.
   *
   * @return int
   *   Creation timestamp of the QAShot Test.
   */
  public function getCreatedTime();

  /**
   * Sets the QAShot Test creation timestamp.
   *
   * @param int $timestamp
   *   The QAShot Test creation timestamp.
   *
   * @return \Drupal\qa_shot\Entity\QAShotTestInterface
   *   The called QAShot Test entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the QAShot Test published status indicator.
   *
   * Unpublished QAShot Test are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the QAShot Test is published.
   */
  public function isPublished();

  /**
   * Sets the published status of a QAShot Test.
   *
   * @param bool $published
   *   TRUE to set as published, FALSE to set as unpublished.
   *
   * @return \Drupal\qa_shot\Entity\QAShotTestInterface
   *   The called QAShot Test entity.
   */
  public function setPublished($published);

  /**
   * {@inheritdoc}
   */
  public function validate();

  /**
   * Map the current entity to the array representation of a BackstopJS config.
   *
   * @param string $publicDataPath
   *   The path to the public file system.
   * @param string $privateDataPath
   *   The path to the private file system.
   * @param bool $withDebug
   *   Whether we should add CasperJS debug options.
   *
   * @return array
   *   The entity as a BackstopJS config array.
   */
  public function toBackstopConfigArray($publicDataPath, $privateDataPath, $withDebug = FALSE);

}
