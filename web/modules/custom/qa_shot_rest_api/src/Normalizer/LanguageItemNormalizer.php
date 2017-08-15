<?php

namespace Drupal\qa_shot_rest_api\Normalizer;

use Drupal\Core\Field\Plugin\Field\FieldType\LanguageItem;
use Drupal\serialization\Normalizer\NormalizerBase;

/**
 * Class LanguageItemNormalizer.
 *
 * @package Drupal\qa_shot_rest_api\Normalizer
 */
class LanguageItemNormalizer extends NormalizerBase {

  /**
   * LanguageItemNormalizer constructor.
   */
  public function __construct() {
    $this->supportedInterfaceOrClass = [LanguageItem::class];
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($fieldItem, $format = NULL, array $context = []) {
    /** @var \Drupal\Core\Field\Plugin\Field\FieldType\LanguageItem $fieldItem */
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
