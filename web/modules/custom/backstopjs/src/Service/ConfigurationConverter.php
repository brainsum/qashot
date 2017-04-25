<?php

namespace Drupal\backstopjs\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList;
use Drupal\qa_shot\Entity\QAShotTestInterface;

/**
 * Class ConfigurationConverter.
 *
 * Provides various functions to create a QAShotTest instance.
 * Saving the new entity is optional.
 *
 * @package Drupal\backstopjs\Service
 */
class ConfigurationConverter {

  /**
   * The private files folder for QAShot Test Entities without a trailing /.
   *
   * @var string
   */
  private $privateDataPath;

  /**
   * The public files folder for QAShot Test Entities without a trailing /.
   *
   * @var string
   */
  private $publicDataPath;

  /**
   * QAShotTest entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private $testStorage;

  /**
   * Paragraphs entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private $paragraphStorage;

  /**
   * ConfigurationConverter constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   EntityTypeManager service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->privateDataPath = PrivateStream::basePath() . DIRECTORY_SEPARATOR . FileSystem::DATA_BASE_FOLDER;
    $this->publicDataPath = PublicStream::basePath() . DIRECTORY_SEPARATOR . FileSystem::DATA_BASE_FOLDER;

    $this->testStorage = $entityTypeManager->getStorage('qa_shot_test');
    $this->paragraphStorage = $entityTypeManager->getStorage('paragraph');
  }

  /**
   * Map the current entity to the array representation of a BackstopJS config.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $entity
   *   The test entity.
   * @param bool $withDebug
   *   Whether we should add CasperJS debug options.
   *
   * @return array
   *   The entity as a BackstopJS config array.
   */
  public function entityToArray(QAShotTestInterface $entity, $withDebug = FALSE) {
    // @todo: get some field values global settings

    $entityId = $entity->id();
    $private = $this->privateDataPath . DIRECTORY_SEPARATOR . $entityId;
    $public = $this->publicDataPath . DIRECTORY_SEPARATOR . $entityId;

    $mapConfigToArray = [
      // @todo: maybe id + revision id.
      'id' => $entityId,
      'viewports' => [],
      'scenarios' => [],
      'paths' => [
        'bitmaps_reference' => $public . '/reference',
        'bitmaps_test' => $public . '/test',
        'casper_scripts' => $private . '/casper_scripts',
        'html_report' => $public . '/html_report',
        'ci_report' => $public . '/ci_report',
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
      'resembleOutputOptions' => [
        'errorColor' => [
          'red' => 255,
          'green' => 0,
          'blue' => 255,
        ],
        // Can be 'flat' or 'movement'.
        // Movement: Merges error color with base image
        // which makes it a little easier to spot movement.
        'errorType' => 'movement',
        // Fade unchanged areas to make changed areas more apparent.
        'transparency' => 0.3,
        // Set as 0 to disable.
        'largeImageThreshold' => 1200,
        // Set to FALSE for DATA URIs.
        // @see: https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/Data_URIs
        // Note: This shouldn't matter here.
        'useCrossOrigin' => TRUE,
      ],
      'asyncCompareLimit' => 10,
      'debug' => FALSE,
    ];

    $mapConfigToArray['viewports'] = $this->viewportToArray($entity->getFieldViewport());
    $mapConfigToArray['scenarios'] = $this->scenarioToArray($entity->getFieldScenario(), $entity->getSelectorsToHide());

    if ($withDebug === TRUE) {
      $mapConfigToArray['debug'] = TRUE;
      $mapConfigToArray['casperFlags'][] = '--verbose';
    }

    return $mapConfigToArray;
  }

  /**
   * Convert the viewport field so it can be used in a BackstopJS config array.
   *
   * @param \Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList $viewportField
   *   The viewport field.
   *
   * @return array
   *   Array representation of the viewport field.
   */
  private function viewportToArray(EntityReferenceRevisionsFieldItemList $viewportField) {
    // Flatten the field values from target_id + revision_target_id
    // to target_id only.
    $ids = array_map(function ($item) {
      return $item['target_id'];
    }, $viewportField->getValue());

    $viewports = $this->paragraphStorage->loadMultiple($ids);

    $viewportData = [];
    /** @var \Drupal\qa_shot\Plugin\Field\FieldType\Viewport $viewport */
    foreach ($viewports as $viewport) {
      $viewportData[] = [
        'name' => (string) $viewport->get('field_name')->value,
        'width' => (int) $viewport->get('field_width')->value,
        'height' => (int) $viewport->get('field_height')->value,
      ];
    }
    return $viewportData;
  }

  /**
   * Convert the scenario field so it can be used in a BackstopJS config array.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $scenarioField
   *   The scenario field.
   * @param string[] $selectorsToHide
   *   An array of selectors that should be visually hidden.
   *   The values are merged with the default ones.
   *
   * @return array
   *   Array representation of the scenario field.
   */
  private function scenarioToArray(FieldItemListInterface $scenarioField, array $selectorsToHide) {
    $scenarioData = [];

    /** @var \Drupal\qa_shot\Plugin\Field\FieldType\Scenario $scenario */
    foreach ($scenarioField as $scenario) {
      $currentScenario = [];
      $currentScenario['label'] = (string) $scenario->get('label')->getValue();

      if ($referenceUrl = $scenario->get('referenceUrl')->getValue()) {
        $currentScenario['referenceUrl'] = (string) $referenceUrl;
      }

      $currentScenario += [
        'url' => (string) $scenario->get('testUrl')->getValue(),
        'readyEvent' => NULL,
        'delay' => 5000,
        'misMatchThreshold' => 0.0,
        'selectors' => [
          'document',
        ],
        'removeSelectors' => [],
        'hideSelectors' => $selectorsToHide,
        'onBeforeScript' => 'onBefore.js',
        'onReadyScript' => 'onReady.js',
      ];

      $scenarioData[] = $currentScenario;
    }

    return $scenarioData;
  }

  /**
   * Instantiates a new QAShotEntity from the array. Optionally saves it.
   *
   * @deprecated
   *
   * @param array $config
   *   The entity as an array.
   * @param bool $saveEntity
   *   Whether to persist the entity as well.
   *
   * @return \Drupal\qa_shot\Entity\QAShotTestInterface|\Drupal\Core\Entity\EntityInterface
   *   The entity object.
   */
  public function arrayToEntity(array $config, $saveEntity = FALSE) {
    $testEntity = $this->testStorage->create($config);

    if ($saveEntity) {
      $testEntity->save();
    }

    return $testEntity;
  }

  /**
   * Instantiates a new QAShotEntity from the json string. Optionally saves it.
   * @deprecated
   * @param string $json
   *   The backstop.json config file as a string.
   * @param bool $saveEntity
   *   Whether to persist the entity as well.
   *
   * @return \Drupal\qa_shot\Entity\QAShotTestInterface
   *   The entity object.
   */
  public function jsonStringToEntity($json, $saveEntity = FALSE) {
    /** @var array $rawData */
    $rawData = json_decode($json, TRUE);

    $entityData = [
      'name' => 'Import for backstop.json with id #' . $rawData['id'],
      'type' => 'a_b',
      'viewport' => $rawData['viewports'],
      'field_scenario' => [],
    ];

    foreach ($rawData['scenarios'] as $scenario) {
      $entityData['field_scenario'][] = [
        'label' => $scenario['label'],
        'referenceUrl' => $scenario['referenceUrl'],
        'testUrl' => $scenario['url'],
      ];
    }

    return $this->arrayToEntity($entityData, $saveEntity);
  }

  /**
   * Instantiates a new QAShotEntity from the json file. Optionally saves it.
   * @deprecated
   * @param string $jsonFile
   *   The backstop.json config file path as a string.
   * @param bool $saveEntity
   *   Whether to persist the entity as well.
   *
   * @return \Drupal\qa_shot\Entity\QAShotTestInterface
   *   The entity object.
   */
  public function jsonFileToEntity($jsonFile, $saveEntity = FALSE) {
    $data = file_get_contents($jsonFile);
    return $this->jsonStringToEntity($data, $saveEntity);
  }

  /**
   * Example function on how to use this class.
   *
   * @internal
   * @deprecated
   */
  public function example() {
    $basePath = \Drupal\Core\StreamWrapper\PrivateStream::basePath() . '/qa_test_data';

    $ids = [
      '1',
    ];

    foreach ($ids as $id) {
      $this->jsonFileToEntity($basePath . "/$id/backstop.json", FALSE);
    }
  }

  /**
   * Example function on how to use the ideas behind this class.
   *
   * This is intended to be copy-pasteable into devel/php as a rudimentary
   * import script.
   *
   * @internal
   * @deprecated
   */
  public function copyPasteableExample() {
    $basePath = \Drupal\Core\StreamWrapper\PrivateStream::basePath() . '/qa_test_data';

    $ids = [
      '1',
    ];

    foreach ($ids as $id) {
      /** @var string $fileData */
      $fileData = file_get_contents($basePath . "/$id/backstop.json");
      /** @var array $jsonData */
      $jsonData = json_decode($fileData, TRUE);

      $entityData = [
        'name' => 'Import for backstop.json with id #' . $jsonData['id'],
        'type' => 'a_b',
        'viewport' => $jsonData['viewports'],
        'field_scenario' => [],
      ];

      foreach ($jsonData['scenarios'] as $scenario) {
        $entityData['field_scenario'][] = [
          'label' => $scenario['label'],
          'referenceUrl' => $scenario['referenceUrl'],
          'testUrl' => $scenario['url'],
        ];
      }

      /** @var QAShotTestInterface $newTest */
      $newTest = \Drupal::entityTypeManager()->getStorage('qa_shot_test')->create($entityData);
      kint($newTest);
      $newTest->save();
    }
  }

}
