<?php

namespace Drupal\qa_shot_rest_api\Normalizer;

use Drupal\Core\Field\Plugin\Field\FieldType\UuidItem;
use Drupal\serialization\Normalizer\NormalizerBase;

/**
 * Class UuidItemNormalizer.
 *
 * @package Drupal\qa_shot_rest_api\Normalizer
 */
class UuidItemNormalizer extends NormalizerBase {

  /**
   * UuidItemNormalizer constructor.
   */
  public function __construct() {
    $this->supportedInterfaceOrClass = [UuidItem::class];
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($fieldItem, $format = NULL, array $context = array()) {
    /** @var \Drupal\Core\Field\Plugin\Field\FieldType\UuidItem $fieldItem */
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
