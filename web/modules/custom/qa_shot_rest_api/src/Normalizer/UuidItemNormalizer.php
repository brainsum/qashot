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
  public function normalize($fieldItem, $format = NULL, array $context = []) {
    /** @var \Drupal\Core\Field\Plugin\Field\FieldType\UuidItem $fieldItem */
    $value = $fieldItem->getValue();
    return $value['value'] ?? $value[0]['value'] ?? $value;
  }

}
