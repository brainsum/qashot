<?php

namespace Drupal\qa_shot\Normalizer;

use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\serialization\Normalizer\ComplexDataNormalizer;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Class EntityReferenceFieldItemNormalizer.
 *
 * @package Drupal\qa_shot\Normalizer
 */
class EntityReferenceItemNormalizer extends ComplexDataNormalizer {

  /**
   * EntityReferenceItemNormalizer constructor.
   */
  public function __construct() {
    $this->supportedInterfaceOrClass = [EntityReferenceItem::class];
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($fieldItem, $format = NULL, array $context = []) {
    $value = NULL;
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    if ($entity = $fieldItem->get('entity')->getValue()) {
      $value = $entity->id();
    }

    return $value;
  }

}
