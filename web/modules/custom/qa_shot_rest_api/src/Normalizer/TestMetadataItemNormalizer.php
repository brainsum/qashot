<?php

namespace Drupal\qa_shot_rest_api\Normalizer;

use Drupal\qa_shot\Plugin\Field\FieldType\TestMetadata;
use Drupal\serialization\Normalizer\NormalizerBase;

/**
 * Class TestMetadataItemNormalizer.
 *
 * @package Drupal\qa_shot_rest_api\Normalizer
 */
class TestMetadataItemNormalizer extends NormalizerBase {

  /**
   * TestMetadataItemNormalizer constructor.
   */
  public function __construct() {
    $this->supportedInterfaceOrClass = [TestMetadata::class];
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($fieldItem, $format = NULL, array $context = array()) {
    /** @var \Drupal\qa_shot\Plugin\Field\FieldType\TestMetadata $fieldItem */
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
