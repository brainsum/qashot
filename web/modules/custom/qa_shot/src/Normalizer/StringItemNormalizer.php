<?php

namespace Drupal\qa_shot\Normalizer;

use Drupal\Core\Field\Plugin\Field\FieldType\StringItem;
use Drupal\serialization\Normalizer\NormalizerBase;

/**
 * Class StringItemNormalizer.
 *
 * @package Drupal\qa_shot\Normalizer
 */
class StringItemNormalizer extends NormalizerBase {

  /**
   * StringItemNormalizer constructor.
   */
  public function __construct() {
    $this->supportedInterfaceOrClass = [StringItem::class];
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($fieldItem, $format = NULL, array $context = array()) {
    /** @var \Drupal\Core\Field\Plugin\Field\FieldType\StringItem $fieldItem */
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
