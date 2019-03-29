<?php

namespace Drupal\qa_shot_rest_api\Normalizer;

use Drupal\Core\Field\Plugin\Field\FieldType\BooleanItem;
use Drupal\serialization\Normalizer\NormalizerBase;

/**
 * Class BooleanItemNormalizer.
 *
 * @package Drupal\qa_shot_rest_api\Normalizer
 */
class BooleanItemNormalizer extends NormalizerBase {

  /**
   * BooleanItemNormalizer constructor.
   */
  public function __construct() {
    $this->supportedInterfaceOrClass = [BooleanItem::class];
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($fieldItem, $format = NULL, array $context = []) {
    /** @var \Drupal\Core\Field\Plugin\Field\FieldType\BooleanItem $fieldItem */
    $value = $fieldItem->getValue();
    return $value['value'] ?? $value[0]['value'] ?? $value;
  }

}
