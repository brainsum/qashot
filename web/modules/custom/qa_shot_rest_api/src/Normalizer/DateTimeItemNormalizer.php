<?php

namespace Drupal\qa_shot_rest_api\Normalizer;

use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\serialization\Normalizer\NormalizerBase;

/**
 * Class DateTimeItemNormalizer.
 *
 * @package Drupal\qa_shot_rest_api\Normalizer
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
  public function normalize($fieldItem, $format = NULL, array $context = []) {
    /** @var \Drupal\datetime\Plugin\Field\FieldType\DateTimeItem $fieldItem */
    $value = $fieldItem->getValue();
    return $value['value'] ?? $value[0]['value'] ?? $value;
  }

}
