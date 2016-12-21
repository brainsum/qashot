<?php

namespace Drupal\qa_shot\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'qa_shot_scenario' field type.
 *
 * @FieldType(
 *   id = "qa_shot_scenario",
 *   label = @Translation("Scenario"),
 *   description = @Translation("Scenario type for QA Shot tests."),
 *   default_widget = "qa_shot_scenario",
 *   default_formatter = "qa_shot_scenario"
 * )
 */
class Scenario extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [
      // BackstopJS has some bugs with very long file names.
      'max_label_length' => 80,
      'max_url_length' => 255,
    ] + parent::defaultStorageSettings();
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

  /**
   * {@inheritdoc}
   */
  public static function schema(
    FieldStorageDefinitionInterface $field_definition
  ) {
    $schema = [
      'columns' => [
        'label' => [
          'description' => "The label for the scenario.",
          'type' => 'varchar',
          'length' => 255,
          'not null' => TRUE,
        ],
        'referenceUrl' => [
          'description' => "The URL of the reference site.",
          'type' => 'varchar',
          'length' => 255,
          'not null' => TRUE,
        ],
        'testUrl' => [
          'description' => "The URL of the site to test.",
          'type' => 'varchar',
          'length' => 255,
          'not null' => TRUE,
        ],
      ],
    ];

    // We allow the user to input 80 character long URLs only
    // We allow 255 character long URLs in the DB for some future proofing.
    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $referenceUrl = $this->get('referenceUrl')->getValue();
    $testUrl = $this->get('testUrl')->getValue();
    $label = $this->get('label')->getValue();

    return $referenceUrl === NULL || $referenceUrl === '' ||
           $label === NULL || $label === '' ||
           $testUrl === NULL || $testUrl === '';
  }

}