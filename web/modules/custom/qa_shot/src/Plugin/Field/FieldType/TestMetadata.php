<?php

namespace Drupal\qa_shot\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation for the qa_shot_test_metadata field type.
 *
 * @todo: Add these when needed.
 *   default_widget = "qa_shot_test_metadata",
 *
 *
 * @FieldType(
 *   id = "qa_shot_test_metadata",
 *   label = @Translation("Test Metadata"),
 *   description = @Translation("Test Metadata field type for QA Shot tests."),
 *   category = @Translation("QAShot"),
 *   default_formatter = "qa_shot_test_metadata"
 * )
 */
class TestMetadata extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings(): array {
    return [
      'max_stage_length' => 30,
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $fieldDefinition): array {
    // Unsigned small ints should be enough, 65535 max value.
    // Unsigned big float is used for double precision.
    $schema = [
      'columns' => [
        'stage' => [
          'description' => 'The test stage of the run.',
          'type' => 'varchar',
          'length' => $fieldDefinition->getSetting('max_stage_length'),
        ],
        'viewport_count' => [
          'description' => 'The amount of viewports in the test.',
          'type' => 'int',
          'size' => 'small',
          'unsigned' => TRUE,
        ],
        'scenario_count' => [
          'description' => 'The amount of scenarios in the test.',
          'type' => 'int',
          'size' => 'small',
          'unsigned' => TRUE,
        ],
        'datetime' => [
          'description' => 'Time of the run.',
          'type' => 'varchar',
          'length' => 20,
        ],
        'duration' => [
          'description' => 'The duration of the run in seconds.',
          'type' => 'float',
          'size' => 'big',
          'unsigned' => TRUE,
        ],
        'passed_count' => [
          'description' => 'The amount of passed tests.',
          'type' => 'int',
          'size' => 'small',
          'unsigned' => TRUE,
        ],
        'failed_count' => [
          'description' => 'The amount of failed tests.',
          'type' => 'int',
          'size' => 'small',
          'unsigned' => TRUE,
        ],
        'pass_rate' => [
          'description' => 'The pass rate of the tests. Number between 0 and 1.',
          'type' => 'varchar',
          'length' => 40,
        ],
        'contains_result' => [
          'description' => 'Flag indicating whether these are result values or intermediate ones.',
          'type' => 'int',
          'size' => 'tiny',
          'not null' => TRUE,
          'default' => 0,
        ],
        'success' => [
          'description' => 'Flag indicating whether a test is considered a success.',
          'type' => 'int',
          'size' => 'tiny',
          'not null' => TRUE,
          'default' => 0,
        ],
      ],
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $fieldDefinition) {
    // Prevent early t() calls by using the TranslatableMarkup.
    $properties['stage'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Stage'))
      ->setDescription('The test stage of the run.')
      ->addConstraint(
        'Length',
        [
          'max' => $fieldDefinition->getSetting('max_stage_length'),
        ]
      );

    $properties['viewport_count'] = DataDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Viewports'))
      ->setDescription('The amount of viewports in the test.')
      ->setRequired(TRUE);

    $properties['scenario_count'] = DataDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Scenarios'))
      ->setDescription('The amount of scenarios in the test.')
      ->setRequired(TRUE);

    // MAybe date-time field?
    $properties['datetime'] = DataDefinition::create('datetime_iso8601')
      ->setLabel(new TranslatableMarkup('Date/Time'))
      ->setDescription('Time of the run.')
      ->setRequired(TRUE);

    $properties['duration'] = DataDefinition::create('float')
      ->setLabel(new TranslatableMarkup('Stage'))
      ->setDescription('The duration of the run in seconds.')
      ->setRequired(TRUE);

    $properties['passed_count'] = DataDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Passed tests'))
      ->setDescription('The amount of passed tests.');

    $properties['failed_count'] = DataDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Failed tests'))
      ->setDescription('The amount of failed tests.');

    // @see: web/core/lib/Drupal/Core/Field/Plugin/Field/FieldType/DecimalItem.php
    $properties['pass_rate'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Test pass rate'))
      ->setDescription('The amount rate of passed tests (number between 0 and 1).');

    $properties['contains_result'] = DataDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Contains result'))
      ->setDescription('Whether the metadata contains test results.')
      ->setRequired(TRUE);

    // @todo: Should be computed.
    // @see: https://www.drupal.org/node/2112677
    $properties['success'] = DataDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Success'))
      ->setDescription('Whether the test is considered a success.')
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    // Stage can be NULL, so we don't check that here.
    $fields = [
      'viewport_count',
      'scenario_count',
      'datetime',
      'duration',
      'passed_count',
      'failed_count',
      'success',
    ];

    // If any of the properties is empty, consider the fields empty.
    foreach ($fields as $field) {
      $value = $this->get($field)->getValue();

      if ($value === NULL || $value === '') {
        return TRUE;
      }
    }

    return FALSE;
  }

}
