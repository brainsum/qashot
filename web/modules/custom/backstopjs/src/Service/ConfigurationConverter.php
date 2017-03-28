<?php

namespace Drupal\backstopjs\Service;

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

  public function entityToArray(QAShotTestInterface $entity) {
    // return $entity->toBackstopConfigArray('', '');
  }

  /**
   * Instantiates a new QAShotEntity from the array. Optionally saves it.
   *
   * @param array $config
   *   The entity as an array.
   * @param bool $saveEntity
   *   Whether to persist the entity as well.
   *
   * @return QAShotTestInterface|\Drupal\Core\Entity\EntityInterface
   *   The entity object.
   */
  public function arrayToEntity(array $config, $saveEntity = FALSE) {
    $testEntity = \Drupal::entityTypeManager()->getStorage('qa_shot_test')->create($config);

    if ($saveEntity) {
      $testEntity->save();
    }

    return $testEntity;
  }

  /**
   * Instantiates a new QAShotEntity from the json string. Optionally saves it.
   *
   * @param string $json
   *   The backstop.json config file as a string.
   * @param bool $saveEntity
   *   Whether to persist the entity as well.
   *
   * @return QAShotTestInterface
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
   *
   * @param string $jsonFile
   *   The backstop.json config file path as a string.
   * @param bool $saveEntity
   *   Whether to persist the entity as well.
   *
   * @return QAShotTestInterface
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
