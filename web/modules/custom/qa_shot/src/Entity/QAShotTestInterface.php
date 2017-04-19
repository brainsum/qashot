<?php

namespace Drupal\qa_shot\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\UserInterface;

/**
 * Provides an interface for defining QAShot Test entities.
 *
 * @ingroup qa_shot
 */
interface QAShotTestInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  /**
   * Gets the QAShot Test type.
   *
   * @return string
   *   The QAShot Test type.
   */
  public function getType();

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
   * Get the user who initiated the test.
   *
   * @return \Drupal\user\UserInterface|null
   *   The user object or NULL, if it has not been run yet.
   */
  public function getInitiator();

  /**
   * Set the user who initiated the test.
   *
   * @param \Drupal\user\UserInterface $account
   *   The account.
   *
   * @return \Drupal\qa_shot\Entity\QAShotTestInterface
   *   The test object for chaining.
   */
  public function setInitiator(UserInterface $account);

  /**
   * Get the Id of the user who initiated the test.
   *
   * @return int|string|null
   *   The user ID.
   */
  public function getInitiatorId();

  /**
   * Set the user who initiated the test by ID.
   *
   * @param string|int $uid
   *   The user ID.
   *
   * @return \Drupal\qa_shot\Entity\QAShotTestInterface
   *   The test object for chaining.
   */
  public function setInitiatorId($uid);

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
   * Gets the QAShot Test run initiation timestamp.
   *
   * @return int
   *   Initiation timestamp of the QAShot Test.
   */
  public function getInitiatedTime();

  /**
   * Sets the QAShot Test run initiation timestamp.
   *
   * @param int $timestamp
   *   The QAShot Test initiation timestamp.
   *
   * @return \Drupal\qa_shot\Entity\QAShotTestInterface
   *   The called QAShot Test entity.
   */
  public function setInitiatedTime($timestamp);

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
   *   TRUE to set this QAShot Test to published,
   *   FALSE to set it to unpublished.
   *
   * @return \Drupal\qa_shot\Entity\QAShotTestInterface
   *   The called QAShot Test entity.
   */
  public function setPublished($published);

  /**
   * Return the viewport field.
   *
   * @return \Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList
   *   The Viewport field.
   */
  public function getFieldViewport();

  /**
   * Return the count of the viewports.
   *
   * @return int
   *   The count of viewports.
   */
  public function getViewportCount();

  /**
   * Return the scenario field.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface
   *   The Scenario field.
   */
  public function getFieldScenario();

  /**
   * Return the count of the scenarios.
   *
   * @return int
   *   The count of scenarios.
   */
  public function getScenarioCount();

  /**
   * Return the path to the backstop.json configuration.
   *
   * @return string
   *   The path to the config.
   */
  public function getConfigurationPath();

  /**
   * Set the configuration path.
   *
   * @param string $configurationPath
   *   The path to the config.
   *
   * @return \Drupal\qa_shot\Entity\QAShotTestInterface
   *   The called QAShot Test entity.
   */
  public function setConfigurationPath($configurationPath);

  /**
   * Return the path to the backstop-generated report.
   *
   * @return string
   *   The path to the Backstop-generated report.
   */
  public function getHtmlReportPath();

  /**
   * Set the report path.
   *
   * @param string $htmlReportPath
   *   The path to the report.
   *
   * @return \Drupal\qa_shot\Entity\QAShotTestInterface
   *   The called QAShot Test entity.
   */
  public function setHtmlReportPath($htmlReportPath);

  /**
   * Adds metadata to the entity.
   *
   * Each entity features two metadata fields:
   *  metadata_last_run
   *  metadata_lifetime
   * This function adds the metadata to the beginning of the lifetime field,
   * and updates the last_run value following this rule:
   *   If the stage defined in the metadata already exists, then it's
   *   overwritten, otherwise it's added.
   *
   * @param array $metadata
   *   The array with the metadata.
   *
   * @return \Drupal\qa_shot\Entity\QAShotTestInterface
   *   The called QAShot Test entity.
   */
  public function addMetadata(array $metadata);

  /**
   * Returns the lifetime metadata.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface
   *   The metadata field.
   */
  public function getLifetimeMetadataValue();

  /**
   * Returns the last run metadata.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface
   *   The metadata field.
   */
  public function getLastRunMetadataValue();

  /**
   * Sets the result.
   *
   * @param array $result
   *   The array with the results.
   *
   * @return \Drupal\qa_shot\Entity\QAShotTestInterface
   *   The called QAShot Test entity.
   */
  public function setResult(array $result);

  /**
   * Returns the result.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface
   *   The result field.
   */
  public function getResultValue();

  /**
   * Set the frontend URL.
   *
   * @param string $url
   *   The frontend url of the entity.
   *
   * @return \Drupal\qa_shot\Entity\QAShotTestInterface
   *   The called QAShot Test entity.
   */
  public function setFrontendUrl($url);

  /**
   * Get the frontend URL.
   *
   * @return string|null
   *   The url or NULL.
   */
  public function getFrontendUrl();

  /**
   * Get the 'selectors_to_hide' field value.
   *
   * @return array
   *   The field value as array.
   */
  public function getSelectorsToHide();

  /**
   * Set the 'selectors_to_hide' field value.
   *
   * @param string[] $selectors
   *   The list of selectors.
   *
   * @return \Drupal\qa_shot\Entity\QAShotTestInterface
   *   The entity for chaining.
   */
  public function setSelectorsToHide(array $selectors);

  /**
   * Run the test entity.
   *
   * @param string|null $stage
   *   The stage of the run.
   * @param string $origin
   *   The origin of the run request. Can be 'drupal' or 'api'.
   */
  public function run($stage, $origin = 'drupal');

  /**
   * Return the current queue status of the entity.
   *
   * @return string
   *   The status.
   */
  public function statusInQueue();

}
