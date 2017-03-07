<?php

namespace Drupal\qa_shot\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/*
 * Plugin implementation for the qa_shot_test_metadata field type.
 *
 * FieldType(
 *   id = "qa_shot_test_metadata",
 *   label = @Translation("Test Metadata"),
 *   description = @Translation("Test Metadata field type for QA Shot tests."),
 *   default_widget = "qa_shot_test_metadata",
 *   default_formatter = "qa_shot_test_metadata"
 * )
 */
class TestMetadata extends FieldItemBase {

  /*
   * @todo: Add these fields:
   *   Last run date
   *   Last run fails
   *   Last run passes
   *   Last run report
   *   Last run idk
   */

  /**
   * {@inheritdoc}
   */
  public static function schema(
    FieldStorageDefinitionInterface $fieldDefinition
  ) {
    $schema = [
      'columns' => [
        'datetime' => [
          'description' => 'Time of the run.',
          'type' => 'varchar',
          'length' => 20,
        ],
        'duration' => [
          'description' => 'The duration of the run.',
        ],
        'passed' => [
          'description' => 'The amount of passed tests.',
          'type' => 'int',
        ],
        'failed' => [
          'description' => 'The amount of failed tests.',
          'type' => 'int',
        ],
      ],
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(
    FieldStorageDefinitionInterface $field_definition
  ) {
    // Prevent early t() calls by using the TranslatableMarkup.
    $properties['label'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Label'))
      ->setDescription("A label to help identify the scenario.")
      ->addConstraint(
        'Length',
        [
          'max' => $field_definition->getSetting('max_label_length'),
        ]
      )
      ->setRequired(TRUE);

    // Prevent early t() calls by using the TranslatableMarkup.
    $properties['referenceUrl'] = DataDefinition::create('uri')
      ->setLabel(new TranslatableMarkup('Reference URL'))
      ->setDescription("The URL of the reference site.")
      ->addConstraint(
        'Length',
        [
          'max' => $field_definition->getSetting('max_url_length'),
        ]
      )
      ->setRequired(TRUE);

    // Prevent early t() calls by using the TranslatableMarkup.
    $properties['testUrl'] = DataDefinition::create('uri')
      ->setLabel(new TranslatableMarkup('Test URL'))
      ->setDescription("The URL of the site to test.")
      ->addConstraint(
        'Length',
        [
          'max' => $field_definition->getSetting('max_url_length'),
        ]
      )
      ->setRequired(TRUE);

    return $properties;
  }

//  /**
//   * {@inheritdoc}
//   */
//  public function isEmpty() {
//    $referenceUrl = $this->get('referenceUrl')->getValue();
//    $testUrl = $this->get('testUrl')->getValue();
//    $label = $this->get('label')->getValue();
//
//    return $referenceUrl === NULL || $referenceUrl === '' ||
//      $label === NULL || $label === '' ||
//      $testUrl === NULL || $testUrl === '';
//  }

}
