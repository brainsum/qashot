<?php

namespace Drupal\qa_shot\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
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
    $values += array(
      'user_id' => \Drupal::currentUser()->id(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    // @todo: Dependency inject.
    /** @var \Drupal\qa_shot\Service\FileSystem $qasFileSystem */
    $qasFileSystem = \Drupal::service('qa_shot.file_system');
    $pubRemoveRes = $qasFileSystem->removePublicData($this);
    $privRemoveRes = $qasFileSystem->removePrivateData($this);

    drupal_set_message(
      $privRemoveRes ? 'Private data folder removed' : 'Private data folder not removed',
      $privRemoveRes ? 'status' : 'error'
    );
    drupal_set_message(
      $pubRemoveRes ? 'Public data folder removed' : 'Public data folder not removed',
      $pubRemoveRes ? 'status' : 'error'
    );

    parent::delete();
  }

  /**
   * {@inheritdoc}
   */
  public function getType() {
    return $this->bundle();
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    $this->set('name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
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
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isPublished() {
    return (bool) $this->getEntityKey('status');
  }

  /**
   * {@inheritdoc}
   */
  public function setPublished($published) {
    $this->set('status', $published ? TRUE : FALSE);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldViewport() {
    return $this->get('viewport');
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldScenario() {
    return $this->get('field_scenario');
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
  public function setConfigurationPath($configurationPath) {
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
  public function setHtmlReportPath($htmlReportPath) {
    return $this->set('field_html_report_path', $htmlReportPath);
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

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setRequired(TRUE)
      ->setDescription(t('The name of the QAShot Test entity.'))
      ->setSettings(array(
        'max_length' => 50,
        'text_processing' => 0,
      ))
      ->setDefaultValue('')
      ->setDisplayOptions('view', array(
        'label' => 'above',
        'type' => 'string',
        'weight' => 0,
      ))
      ->setDisplayOptions('form', array(
        'type' => 'string_textfield',
        'weight' => 0,
      ))
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

    $fields['viewport'] = BaseFieldDefinition::create('qa_shot_viewport')
      ->setLabel(t('Viewport'))
      ->setRequired(TRUE)
      ->setDescription(t('Set a unique name and the desired resolution. Supported resolutions range from 480x320 up to 3840x2400.'))
      ->setCardinality(-1)
      ->setDisplayOptions('view', array(
        'label' => 'above',
        'type' => 'qa_shot_viewport',
        'weight' => 1,
      ))
      ->setDisplayOptions('form', array(
        'type' => 'qa_shot_viewport',
        'weight' => 1,
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // @todo: Metadata field
    // Metadata:
    //   Config path
    //   Html report path
    //   Run data
    //
    // Run data:
    //   date/time
    //   fails/passes
    //   run duration
    //
    // These are needed as custom field types.
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function validate() {
    // @todo: Add validations.
    return parent::validate();
  }

  /**
   * {@inheritdoc}
   */
  public function toBackstopConfigArray($privateDataPath, $publicDataPath, $withDebug = FALSE) {
    // @todo: get these field values global settings

    $mapConfigToArray = [
      // @todo: maybe id + revision id.
      'id' => $this->id(),
      'viewports' => [],
      'scenarios' => [],
      'paths' => [
        'bitmaps_reference' => $publicDataPath . '/reference',
        'bitmaps_test' => $publicDataPath . '/test',
        'casper_scripts' => $privateDataPath . '/casper_scripts',
        'html_report' => $publicDataPath . '/html_report',
        'ci_report' => $publicDataPath . '/ci_report',
      ],
      // 'onBeforeScript' => 'onBefore.js', //.
      // 'onReadyScript' => 'onReady.js', //.
      'engine' => 'phantomjs',
      'report' => [
        'browser',
      ],
      'casperFlags' => [
        '--ignore-ssl-errors=true',
        '--ssl-protocol=any',
      ],
      'debug' => FALSE,
    ];

    /** @var \Drupal\qa_shot\Plugin\Field\FieldType\Viewport $viewport */
    foreach ($this->getFieldViewport() as $viewport) {
      $mapConfigToArray['viewports'][] = $viewport->toBackstopViewportArray();
    }

    /** @var \Drupal\qa_shot\Plugin\Field\FieldType\Scenario $scenario */
    foreach ($this->getFieldScenario() as $scenario) {
      $mapConfigToArray['scenarios'][] = $scenario->toBackstopScenarioArray();
    }

    if ($withDebug === TRUE) {
      $mapConfigToArray['debug'] = TRUE;
      $mapConfigToArray['casperFlags'][] = '--verbose';
    }

    return $mapConfigToArray;
  }

}
