<?php

namespace Drupal\qa_shot_rest_api\Normalizer;

use Drupal\options\Plugin\Field\FieldType\ListStringItem;
use Drupal\serialization\Normalizer\NormalizerBase;

/**
 * Class ListStringItemNormalizer.
 *
 * @package Drupal\qa_shot_rest_api\Normalizer
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
  public function normalize($fieldItem, $format = NULL, array $context = []) {
    /** @var \Drupal\options\Plugin\Field\FieldType\ListStringItem $fieldItem */
    $value = $fieldItem->getValue();
    return $value['value'] ?? $value[0]['value'] ?? $value;
  }

}
