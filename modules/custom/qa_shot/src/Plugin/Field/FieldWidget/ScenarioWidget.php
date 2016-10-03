<?php

namespace Drupal\qa_shot\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'qa_shot_scenario' widget.
 *
 * @FieldWidget(
 *   id = "qa_shot_scenario",
 *   label = @Translation("Scenario"),
 *   field_types = {
 *     "qa_shot_scenario"
 *   }
 * )
 */
class ScenarioWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'size' => 80,
      'placeholder' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = [];

    $elements['size'] = [
      '#type' => 'number',
      '#title' => t('Size of textfield'),
      '#default_value' => $this->getSetting('size'),
      '#required' => TRUE,
      '#min' => 1,
    ];
    $elements['placeholder'] = [
      '#type' => 'textfield',
      '#title' => t('Placeholder'),
      '#default_value' => $this->getSetting('placeholder'),
      '#description' => t('Text that will be shown inside the field until a value is entered. This hint is usually a sample value or a brief description of the expected format.'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $summary[] = t('Textfield size: @size', ['@size' => $this->getSetting('size')]);
    if (!empty($this->getSetting('placeholder'))) {
      $summary[] = t('Placeholder: @placeholder', ['@placeholder' => $this->getSetting('placeholder')]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    // @todo: Label should be unique for an entity
    // So QAShot test id: 1 can have only one label value with "Opening page"
    // But QAShot test id: 2 can have a label value with "Opening page" as well
    $element['label'] = [
      '#type' => 'url',
      '#default_value' => isset($items[$delta]->label) ? $items[$delta]->label : NULL,
      '#size' => $this->getSetting('size'),
      '#placeholder' => $this->getSetting('placeholder'),
      '#maxlength' => $this->getFieldSetting('max_label_length'),
      '#title' => $this->t('Label'),
      '#description' => 'A unique name to identify the scenario, ' . $this->getFieldSetting('max_label_length') . ' characters at most.',
      '#required' => $element['#required']
    ];

    // @todo: referenceUrl and testUrl should be a unique par for a single entity (same id example like for label)
    $element['referenceUrl'] = [
      '#type' => 'url',
      '#default_value' => isset($items[$delta]->referenceUrl) ? $items[$delta]->referenceUrl : NULL,
      '#size' => $this->getSetting('size'),
      '#placeholder' => $this->getSetting('placeholder'),
      '#maxlength' => $this->getFieldSetting('max_url_length'),
      '#title' => $this->t('Reference URL'),
      '#description' => 'The URL of the reference site.',
      '#required' => $element['#required']
    ];

    $element['testUrl'] = [
      '#type' => 'url',
      '#default_value' => isset($items[$delta]->testUrl) ? $items[$delta]->testUrl : NULL,
      '#size' => $this->getSetting('size'),
      '#placeholder' => $this->getSetting('placeholder'),
      '#maxlength' => $this->getFieldSetting('max_url_length'),
      '#title' => $this->t('Test URL'),
      '#description' => 'The URL of the site to test.',
      '#required' => $element['#required']
    ];

    return $element;
  }

  //@todo: public function validate
}
