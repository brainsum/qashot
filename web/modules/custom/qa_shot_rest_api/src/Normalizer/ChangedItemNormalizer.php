<?php

namespace Drupal\qa_shot_rest_api\Normalizer;

use Drupal\Core\Field\Plugin\Field\FieldType\ChangedItem;
use Drupal\serialization\Normalizer\NormalizerBase;

/**
 * Class ChangedItemNormalizer.
 *
 * @package Drupal\qa_shot_rest_api\Normalizer
 */
class ChangedItemNormalizer extends NormalizerBase {

  /**
   * ChangedItemNormalizer constructor.
   */
  public function __construct() {
    $this->supportedInterfaceOrClass = [ChangedItem::class];
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($fieldItem, $format = NULL, array $context = array()) {
    /** @var \Drupal\Core\Field\Plugin\Field\FieldType\ChangedItem $fieldItem */
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
