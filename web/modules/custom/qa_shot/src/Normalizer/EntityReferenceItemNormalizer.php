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
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = [EntityReferenceItem::class];

  /**
   * {@inheritdoc}
   */
  public function normalize($fieldItem, $format = NULL, array $context = []) {
    $values = parent::normalize($fieldItem, $format, $context);

    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    if ($entity = $fieldItem->get('entity')->getValue()) {
      $values['target_type'] = $entity->getEntityTypeId();
      // Add the target entity UUID to the normalized output values.
      $values['target_uuid'] = $entity->uuid();

      // Add a 'url' value if there is a reference and a canonical URL. Hard
      // code 'canonical' here as config entities override the default $rel
      // parameter value to 'edit-form.
      if ($url = $entity->url('canonical')) {
        $values['url'] = $url;
      }
    }

    return $values;
  }

}
