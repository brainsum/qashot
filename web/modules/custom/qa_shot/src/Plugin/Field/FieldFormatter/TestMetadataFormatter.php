<?php

namespace Drupal\qa_shot\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'qa_shot_test_metadata' formatter.
 *
 * @FieldFormatter(
 *   id = "qa_shot_test_metadata",
 *   label = @Translation("Test Metadata"),
 *   field_types = {
 *     "qa_shot_test_metadata"
 *   }
 * )
 */
class TestMetadataFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return ([] + parent::defaultSettings());
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    return ([] + parent::settingsForm($form, $form_state));
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];

    foreach ($items as $delta => $item) {
      $elements[$delta] = [
        '#type' => 'markup',
        'markup' => $this->viewValue($item),
      ];
    }

    return $elements;
  }

  /**
   * Generate the output appropriate for one field item.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   One field item.
   *
   * @return string
   *   The textual output generated.
   */
  protected function viewValue(FieldItemInterface $item): string {
    // The text value has no text format assigned to it, so the user input
    // should equal the output, including newlines.
    return rtrim(implode(', ', $item->getValue()), ", \t\n\r\0\x0B");
  }

}
