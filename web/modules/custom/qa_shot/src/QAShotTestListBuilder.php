<?php

namespace Drupal\qa_shot;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;

/**
 * Defines a class to build a listing of QAShot Test entities.
 *
 * @ingroup qa_shot
 */
class QAShotTestListBuilder extends EntityListBuilder {

  private $linkGenerator;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entityType) {
    return new static(
      $entityType,
      $container->get('entity_type.manager')->getStorage($entityType->id()),
      $container->get('link_generator')
    );
  }

  /**
   * Constructs a new EntityListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entityType
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $linkGenerator
   *   Link generator.
   */
  public function __construct(
    EntityTypeInterface $entityType,
    EntityStorageInterface $storage,
    LinkGeneratorInterface $linkGenerator
  ) {
    parent::__construct($entityType, $storage);

    $this->linkGenerator = $linkGenerator;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['id'] = $this->t('QAShot Test ID');
    $header['name'] = $this->t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /* @var $entity \Drupal\qa_shot\Entity\QAShotTest */
    $row['id'] = $entity->id();
    $row['name'] = $this->linkGenerator->generate(
      $entity->label(),
      new Url(
        'entity.qa_shot_test.edit_form', [
          'qa_shot_test' => $entity->id(),
        ]
      )
    );

    return $row + parent::buildRow($entity);
  }

}
