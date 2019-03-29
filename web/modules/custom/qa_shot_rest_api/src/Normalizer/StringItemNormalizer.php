<?php

namespace Drupal\qa_shot_rest_api\Normalizer;

use Drupal\Core\Field\Plugin\Field\FieldType\StringItem;
use Drupal\serialization\Normalizer\NormalizerBase;

/**
 * Class StringItemNormalizer.
 *
 * @package Drupal\qa_shot_rest_api\Normalizer
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
  public function normalize($fieldItem, $format = NULL, array $context = []) {
    /** @var \Drupal\Core\Field\Plugin\Field\FieldType\StringItem $fieldItem */
    $value = $fieldItem->getValue();
    if (isset($value['value'])) {
      return $value['value'];
    }
    return $value[0]['value'] ?? $value;
  }

}
