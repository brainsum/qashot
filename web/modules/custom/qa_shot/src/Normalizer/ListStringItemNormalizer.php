<?php

namespace Drupal\qa_shot\Normalizer;

use Drupal\options\Plugin\Field\FieldType\ListStringItem;
use Drupal\serialization\Normalizer\NormalizerBase;

/**
 * Class ListStringItemNormalizer.
 *
 * @package Drupal\qa_shot\Normalizer
 */
class ListStringItemNormalizer extends NormalizerBase {

  /**
   * ListStringItemNormalizer constructor.
   */
  public function __construct() {
    $this->supportedInterfaceOrClass = [ListStringItem::class];
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($fieldItem, $format = NULL, array $context = array()) {
    /** @var \Drupal\Core\Field\Plugin\Field\FieldType\ListStringItem $fieldItem */
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
