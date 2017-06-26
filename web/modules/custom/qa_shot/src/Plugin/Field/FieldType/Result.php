<?php

namespace Drupal\qa_shot\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\qa_shot\Plugin\DataType\ComputedScreenshotPath;

/**
 * Plugin implementation for the qa_shot_test_result field type.
 *
 * @FieldType(
 *   id = "qa_shot_test_result",
 *   label = @Translation("Test Result"),
 *   description = @Translation("Test Result field type for QA Shot tests."),
 *   category = @Translation("QAShot")
 * )
 */
class Result extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings(): array {
    return [
      'max_url_length' => 255,
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $fieldDefinition): array {
    $schema = [
      'columns' => [
        'id' => [
          'description' => 'The primary identifier for a result.',
          'type' => 'serial',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ],
        'scenario_id' => [
          'description' => 'The delta of the scenario.',
          'type' => 'int',
          'size' => 'normal',
          'unsigned' => TRUE,
        ],
        'viewport_id' => [
          'description' => 'The delta of the viewport.',
          'type' => 'int',
          'size' => 'normal',
          'unsigned' => TRUE,
        ],
        'reference' => [
          'description' => 'The path of the reference screenshot.',
          'type' => 'varchar',
          'length' => $fieldDefinition->getSetting('max_url_length'),
        ],
        'test' => [
          'description' => 'The path of the test screenshot.',
          'type' => 'varchar',
          'length' => $fieldDefinition->getSetting('max_url_length'),
        ],
        'diff' => [
          'description' => 'The path of the diff screenshot.',
          'type' => 'varchar',
          'length' => $fieldDefinition->getSetting('max_url_length'),
        ],
        'success' => [
          'description' => 'Flag indicating whether a test case is considered a success.',
          'type' => 'int',
          'size' => 'tiny',
          'not null' => TRUE,
          'default' => 0,
        ],
      ],
      'primary key' => [
        'id',
      ],
      'indexes' => [
        'result_id' => ['id'],
      ],
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $fieldDefinition) {
    $properties['id'] = DataDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('ID'))
      ->setDescription('The result ID.');

    $properties['scenario_id'] = DataDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Scenario (Delta)'))
      ->setDescription('The delta of the scenario.');

    $properties['viewport_id'] = DataDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Viewport (Delta)'))
      ->setDescription('The delta of the viewport.');

    $properties['reference'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Reference'))
      ->setDescription('The path of the reference screenshot.')
      ->addConstraint(
        'Length',
        [
          'max' => $fieldDefinition->getSetting('max_url_length'),
        ]
      );

    $properties['full_reference'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Reference (Full)'))
      ->setDescription('The full path of the reference screenshot.')
      ->setComputed(TRUE)
      ->setClass(ComputedScreenshotPath::class)
      ->setSetting('url source', 'reference');

    $properties['test'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Test'))
      ->setDescription('The path of the test screenshot.')
      ->addConstraint(
        'Length',
        [
          'max' => $fieldDefinition->getSetting('max_url_length'),
        ]
      );

    $properties['full_test'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Test (Full)'))
      ->setDescription('The full path of the test screenshot.')
      ->setComputed(TRUE)
      ->setClass(ComputedScreenshotPath::class)
      ->setSetting('url source', 'test');

    $properties['diff'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Diff'))
      ->setDescription('The path of the diff screenshot.')
      ->addConstraint(
        'Length',
        [
          'max' => $fieldDefinition->getSetting('max_url_length'),
        ]
      );

    $properties['full_diff'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Diff (Full)'))
      ->setDescription('The full path of the diff screenshot.')
      ->setComputed(TRUE)
      ->setClass(ComputedScreenshotPath::class)
      ->setSetting('url source', 'diff');

    $properties['success'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Success'))
      ->setDescription('Whether the result is considered a success.');

    return $properties;
  }

}
