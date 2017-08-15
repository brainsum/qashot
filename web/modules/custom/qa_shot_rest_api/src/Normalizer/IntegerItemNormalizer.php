<?php

namespace Drupal\qa_shot_rest_api\Normalizer;

use Drupal\Core\Field\Plugin\Field\FieldType\IntegerItem;
use Drupal\serialization\Normalizer\NormalizerBase;

/**
 * Class IntegerItemNormalizer.
 *
 * @package Drupal\qa_shot_rest_api\Normalizer
 */
class IntegerItemNormalizer extends NormalizerBase {

  /**
   * DateTimeItemNormalizer constructor.
   */
  public function __construct() {
    $this->supportedInterfaceOrClass = [IntegerItem::class];
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($fieldItem, $format = NULL, array $context = []) {
    /** @var \Drupal\Core\Field\Plugin\Field\FieldType\IntegerItem $fieldItem */
    $value = $fieldItem->getValue();
    if (isset($value['value'])) {
      return (int) $value['value'];
    }
    if (isset($value[0]['value'])) {
      return (int) $value[0]['value'];
    }
    return $value;
  }

}
