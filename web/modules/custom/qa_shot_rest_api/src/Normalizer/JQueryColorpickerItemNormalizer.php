<?php

namespace Drupal\qa_shot_rest_api\Normalizer;

use Drupal\jquery_colorpicker\Plugin\Field\FieldType\JQueryColorpickerItem;
use Drupal\serialization\Normalizer\NormalizerBase;

/**
 * Class JQueryColorpickerItemNormalizer.
 *
 * @package Drupal\qa_shot_rest_api\Normalizer
 */
class JQueryColorpickerItemNormalizer extends NormalizerBase {

  /**
   * JQueryColorpickerItemNormalizer constructor.
   */
  public function __construct() {
    $this->supportedInterfaceOrClass = [JQueryColorpickerItem::class];
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($fieldItem, $format = NULL, array $context = []) {
    /** @var \Drupal\jquery_colorpicker\Plugin\Field\FieldType\JQueryColorpickerItem $fieldItem */
    $value = $fieldItem->getValue();
    if (isset($value['value'])) {
      return $value['value'];
    }
    if (isset($value[0]['value'])) {
      return $value[0]['value'];
    }
    return $value;
  }

}
