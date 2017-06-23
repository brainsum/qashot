<?php

namespace Drupal\qa_shot\Normalizer;

use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\serialization\Normalizer\NormalizerBase;

/**
 * Class DateTimeItemNormalizer.
 *
 * @package Drupal\qa_shot\Normalizer
 */
class DateTimeItemNormalizer extends NormalizerBase {

  /**
   * DateTimeItemNormalizer constructor.
   */
  public function __construct() {
    $this->supportedInterfaceOrClass = [DateTimeItem::class];
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($fieldItem, $format = NULL, array $context = array()) {
    /** @var \Drupal\datetime\Plugin\Field\FieldType\DateTimeItem $fieldItem */
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
