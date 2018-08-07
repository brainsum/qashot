<?php

namespace Drupal\qa_shot\Entity;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList;
use Drupal\qa_shot\Exception\QAShotBaseException;
use Drupal\qa_shot\Plugin\DataType\ComputedLastRunMetadata;
use Drupal\qa_shot\Queue\QAShotQueue;
use Drupal\user\UserInterface;

/**
 * Defines the QAShot Test entity.
 *
 * @ingroup qa_shot
 *
 * @ContentEntityType(
 *   id = "qa_shot_test",
 *   label = @Translation("QAShot Test"),
 *   bundle_label = @Translation("QAShot Test type"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\qa_shot\QAShotTestListBuilder",
 *     "views_data" = "Drupal\qa_shot\Entity\QAShotTestViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\qa_shot\Form\QAShotTestForm",
 *       "add" = "Drupal\qa_shot\Form\QAShotTestForm",
 *       "edit" = "Drupal\qa_shot\Form\QAShotTestForm",
 *       "delete" = "Drupal\qa_shot\Form\QAShotTestDeleteForm",
 *     },
 *     "access" = "Drupal\qa_shot\QAShotTestAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\qa_shot\QAShotTestHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "qa_shot_test",
 *   admin_permission = "administer qashot test entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "bundle" = "type",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *   },
 *   links = {
 *     "canonical" = "/qa_shot_test/{qa_shot_test}",
 *     "add-page" = "/qa_shot_test/add",
 *     "add-form" = "/qa_shot_test/add/{qa_shot_test_type}",
 *     "edit-form" = "/qa_shot_test/{qa_shot_test}/edit",
 *     "delete-form" = "/qa_shot_test/{qa_shot_test}/delete",
 *     "collection" = "/qa_shot_test",
 *   },
 *   bundle_entity_type = "qa_shot_test_type",
 *   field_ui_base_route = "entity.qa_shot_test_type.edit_form"
 * )
 */
class QAShotTest extends ContentEntityBase implements QAShotTestInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $entityStorage, array &$values) {
    parent::preCreate($entityStorage, $values);
    $values += [
      'user_id' => \Drupal::currentUser()->id(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    // @todo: don't allow if it's running.
    // TODO: Implement preDelete() method.
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    // @todo: Remove from the queue.
    // @todo: Generalize.
    /** @var \Drupal\qa_shot\TestBackendInterface $testBackend */
    $testBackend = \Drupal::service('backstopjs.backstop');
    $testBackend->clearFiles($this);

    parent::delete();
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): string {
    return $this->bundle();
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name): QAShotTestInterface {
    $this->set('name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime(): int {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp): QAShotTestInterface {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner(): UserInterface {
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account): QAShotTestInterface {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getInitiator(): UserInterface {
    return $this->get('initiator_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setInitiator(UserInterface $account): QAShotTestInterface {
    $this->set('initiator_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getInitiatorId() {
    return $this->get('initiator_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setInitiatorId($uid): QAShotTestInterface {
    $this->set('initiator_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getInitiatedTime(): int {
    return $this->get('initiated')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setInitiatedTime($timestamp): QAShotTestInterface {
    $this->set('initiated', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isPublished(): bool {
    return (bool) $this->getEntityKey('status');
  }

  /**
   * {@inheritdoc}
   */
  public function setPublished($published): QAShotTestInterface {
    $this->set('status', $published ? TRUE : FALSE);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldViewport(): EntityReferenceRevisionsFieldItemList {
    return $this->get('field_viewport');
  }

  /**
   * {@inheritdoc}
   */
  public function getViewportCount(): int {
    return $this->getFieldViewport()->count();
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldScenario(): EntityReferenceRevisionsFieldItemList {
    return $this->get('field_scenario');
  }

  /**
   * {@inheritdoc}
   */
  public function getScenarioCount(): int {
    return $this->getFieldScenario()->count();
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigurationPath() {
    return $this->get('field_configuration_path')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfigurationPath($configurationPath): QAShotTestInterface {
    return $this->set('field_configuration_path', $configurationPath);
  }

  /**
   * {@inheritdoc}
   */
  public function getHtmlReportPath() {
    return $this->get('field_html_report_path')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setHtmlReportPath($htmlReportPath): QAShotTestInterface {
    return $this->set('field_html_report_path', $htmlReportPath);
  }

  /**
   * {@inheritdoc}
   */
  public function addMetadata(array $metadata): QAShotTestInterface {
    $this->get('metadata_lifetime')->appendItem($metadata);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getLastRunMetadataValue(): array {
    return $this->get('metadata_last_run')->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function getLifetimeMetadataValue(): array {
    return $this->get('metadata_lifetime')->getValue();
  }

  /**
   * This function will return the last run times.
   *
   * @return array
   *   It contains the last runtimes in this order:
   *   last_run_time, last_reference_run_time, last_test_run_time.
   *
   * @throws \Drupal\qa_shot\Exception\QAShotBaseException
   */
  public function getLastRunTimes(): array {
    $last_run_time = $last_reference_run_time = $last_test_run_time = NULL;
    $type = $this->getType();

    if ($type == "before_after") {
      $metadatas = $this->get('metadata_lifetime')->getValue();
      foreach ($metadatas as $metadata) {
        if ($metadata['stage'] == "before") {
          $last_reference_run_time = $metadata['datetime'];
        }
        elseif ($metadata['stage'] == "after") {
          $last_test_run_time = $metadata['datetime'];
        }
      }
    }
    elseif ($type == "a_b") {
      $last_run_time = $this->getLastRunMetadataValue()[0]['datetime'] ?? NULL;
    }
    else {
      throw new QAShotBaseException(t("This function doesn't support this type: @type", ["@type" => $type]));
    }

    return [
      "last_run_time" => $last_run_time,
      "last_reference_run_time" => $last_reference_run_time,
      "last_test_run_time" => $last_test_run_time,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getResultValue(): array {
    return $this->get('result')->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function getSelectorsToHide(): array {
    /** @var \Drupal\Core\Field\FieldItemList $field */
    $field = $this->get('selectors_to_hide')->getValue();

    $fieldValue = [];

    /** @var array $item */
    foreach ($field as $item) {
      $fieldValue[] = $item['value'];
    }

    return $fieldValue;

  }

  /**
   * {@inheritdoc}
   */
  public function setSelectorsToHide(array $selectors): QAShotTestInterface {
    return $this->set('selectors_to_hide', $selectors);
  }

  /**
   * {@inheritdoc}
   */
  public function getSelectorsToRemove(): array {
    /** @var \Drupal\Core\Field\FieldItemList $field */
    $field = $this->get('selectors_to_remove')->getValue();

    $fieldValue = [];

    /** @var array $item */
    foreach ($field as $item) {
      $fieldValue[] = $item['value'];
    }

    return $fieldValue;

  }

  /**
   * {@inheritdoc}
   */
  public function setSelectorsToRemove(array $selectors): QAShotTestInterface {
    return $this->set('selectors_to_remove', $selectors);
  }

  /**
   * {@inheritdoc}
   */
  public function setResult(array $result): QAShotTestInterface {
    $this->get('result')->setValue($result);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFrontendUrl() {
    return $this->get('frontend_url')->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function setFrontendUrl($url): QAShotTestInterface {
    $this->set('frontend_url', $url);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getQueueStatus(): array {
    /** @var \Drupal\qa_shot\Service\QAShotQueueData $queueData */
    $queueData = \Drupal::service('qa_shot.queue_data')->getDataFromQueue($this->id());

    if (empty($queueData)) {
      return [QAShotQueue::QUEUE_STATUS_IDLE];
    }

    return [$queueData->status];
  }

  /**
   * {@inheritdoc}
   */
  public function getHumanReadableQueueStatus(): string {
    /** @var \Drupal\qa_shot\Service\QAShotQueueData $queueData */
    $queueData = \Drupal::service('qa_shot.queue_data')->getDataFromQueue($this->id());

    if (empty($queueData)) {
      return t('Idle');
    }

    switch ($queueData->status) {
      case QAShotQueue::QUEUE_STATUS_WAITING:
        if (!empty($queueData->stage)) {
          $runStateType = $queueData->stage === 'before' ? 'for reference pictures' : 'for test pictures';
          return t('Queued to run (@type)', ['@type' => $runStateType]);
        }

        return t('Queued to run');

      case QAShotQueue::QUEUE_STATUS_RUNNING:
        return t('Running, please be patient...');

      case QAShotQueue::QUEUE_STATUS_ERROR:
        return t('There was an error!');

      default:
        throw new QAShotBaseException(t('Unknown queue state!'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getBrowser(): string {
    return $this->get('field_browser')->value;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entityType) {
    $fields = parent::baseFieldDefinitions($entityType);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the QAShot Test entity.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE);

    $fields['initiator_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Initiated by'))
      ->setDescription(t('The user ID of who started the previous test run.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setRequired(TRUE)
      ->setDescription(t('The name of the QAShot Test entity.'))
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Publishing status'))
      ->setDescription(t('A boolean indicating whether the QAShot Test is published.'))
      ->setDefaultValue(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    $fields['initiated'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Initiated'))
      ->setDescription(t('The time that the entity was last initiated.'));

    $fields['selectors_to_hide'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Selectors to hide'))
      ->setDescription(t('Selectors that should be visually hidden. Can be an element ID (#my-id), Class (.my-class) or XPath.'))
      ->setCardinality(-1)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 10,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 10,
      ])
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['selectors_to_remove'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Selectors to remove'))
      ->setDescription(t('Selectors that should be removed from the DOM. Can be an element ID (#my-id), Class (.my-class) or XPath.'))
      ->setCardinality(-1)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 11,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 11,
      ])
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // This field stores metadata for the last run.
    // Last run means a full test run, so every stage that's required.
    // E.g Before/After has 2 stages, before and after.
    $fields['metadata_last_run'] = BaseFieldDefinition::create('qa_shot_test_metadata')
      ->setLabel(t('Metadata (Last run)'))
      ->setDescription(t('Metadata for the last run.'))
      ->setComputed(TRUE)
      ->setClass(ComputedLastRunMetadata::class)
      ->setSetting('data source', 'metadata_lifetime');

    // This field stores metadata for every run.
    $fields['metadata_lifetime'] = BaseFieldDefinition::create('qa_shot_test_metadata')
      ->setLabel(t('Metadata (Lifetime)'))
      ->setDescription(t('Stores metadata for the entity.'))
      ->setCardinality(-1);

    // This field stores results (links to the individual screenshots).
    $fields['result'] = BaseFieldDefinition::create('qa_shot_test_result')
      ->setLabel(t('Result'))
      ->setDescription(t('Stores the results for the last run.'))
      ->setCardinality(-1);

    $fields['frontend_url'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Frontend URL'))
      ->setDescription(t('Stores the frontend URL.'))
      ->setSettings([
        'max_length' => 2000,
        'text_processing' => 0,
      ])
      ->setCardinality(1);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function queue($stage, $origin = 'drupal'): string {
    /** @var \Drupal\qa_shot\Queue\QAShotQueueFactory $queueFactory */
    $queueFactory = \Drupal::service('qa_shot.test_queue_factory');
    // Get our QueueWorker.
    /** @var \Drupal\qa_shot\Queue\QAShotQueueInterface $testQueue */
    $testQueue = $queueFactory->get('cron_run_qa_shot_test');

    // Add the test entity and the requested stage to the item.
    // @todo: Change?
    $queueItem = new \stdClass();
    $queueItem->tid = $this->id();
    $queueItem->stage = $stage;
    $queueItem->origin = $origin;
    $queueItem->data = NULL;

    // @todo: Cleanup.
    // Remote worker integration.
    // @note: This has to be cleaned up properly.
    /** @var \Drupal\backstopjs\Backstopjs\BackstopjsWorkerFactory $workerFactory */
    $workerFactory = \Drupal::service('backstopjs.worker_factory');
    /** @var \Drupal\backstopjs\Backstopjs\BackstopjsWorkerInterface $remoteWorker */
    $remoteWorker = $workerFactory->get('remote');
    $remoteWorker->run('chrome', 'reference', $this);
    // End of remote worker integration.
    //
    // Try to add the test to the drupal queue.
    if (FALSE !== $testQueue->createItem($queueItem)) {
      try {
        // If we successfully added it to the queue, we set
        // the current user as the initiator and also save the current time.
        $currentUser = \Drupal::currentUser();
        $this->setInitiatorId($currentUser->id());
        $this->setInitiatedTime(\Drupal::time()->getRequestTime());
        $this->save();
      }
      catch (EntityStorageException $e) {
        \Drupal::logger('qa_shot')->warning('Saving the entity data in QAShot::run failed. @msg', [
          '@msg' => $e->getMessage(),
        ]);
        // If updating the entity fails, remove it from the queue.
        $testQueue->deleteItem($queueItem);
        // Throw the caught exception again.
        throw $e;
      }

      drupal_set_message(t('The test has been queued to run. Check back later for the results.'), 'info');
      return 'added_to_queue';
    }

    // If we can't create, it's already in the queue.
    return 'already_in_queue';
  }

}
