<?php

namespace Drupal\qa_shot\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'qa_shot_viewport' widget.
 *
 * @FieldWidget(
 *   id = "qa_shot_viewport",
 *   label = @Translation("Viewport"),
 *   field_types = {
 *     "qa_shot_viewport"
 *   }
 * )
 */
class ViewportWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'size' => 40,
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
    $element['name'] = [
      '#type' => 'textfield',
      '#default_value' => isset($items[$delta]->name) ? $items[$delta]->name : NULL,
      '#size' => $this->getSetting('size'),
      '#placeholder' => $this->getSetting('placeholder'),
      '#maxlength' => $this->getFieldSetting('max_name_length'),
      '#title' => $this->t('Name'),
      '#description' => 'Name of the Viewport, e.g: Desktop, Mobile, ' . $this->getSetting('size') . ' characters at most.',
      '#required' => $element['#required']
    ];

    $element['width'] = [
      '#type' => 'number',
      '#default_value' => isset($items[$delta]->width) ? $items[$delta]->width : NULL,
      '#min' => $this->getFieldSetting('min_width'),
      '#max' => $this->getFieldSetting('max_width'),
      '#title' => $this->t('Width'),
      '#required' => $element['#required']
      ];

    $element['height'] = [
      '#type' => 'number',
      '#default_value' => isset($items[$delta]->height) ? $items[$delta]->height : NULL,
      '#min' => $this->getFieldSetting('min_height'),
      '#max' => $this->getFieldSetting('max_height'),
      '#title' => $this->t('Height'),
      '#required' => $element['#required']
      ];
    
    return $element;
  }

}
