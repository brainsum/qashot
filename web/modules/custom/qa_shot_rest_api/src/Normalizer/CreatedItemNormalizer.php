<?php

namespace Drupal\qa_shot_rest_api\Normalizer;

use Drupal\Core\Field\Plugin\Field\FieldType\CreatedItem;
use Drupal\serialization\Normalizer\NormalizerBase;

/**
 * Class CreatedItemNormalizer.
 *
 * @package Drupal\qa_shot_rest_api\Normalizer
 */
class CreatedItemNormalizer extends NormalizerBase {

  /**
   * CreatedItemNormalizer constructor.
   */
  public function __construct() {
    $this->supportedInterfaceOrClass = [CreatedItem::class];
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($fieldItem, $format = NULL, array $context = []) {
    /** @var \Drupal\Core\Field\Plugin\Field\FieldType\CreatedItem $fieldItem */
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
