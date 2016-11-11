<?php

namespace Drupal\qa_shot;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Routing\LinkGeneratorTrait;
use Drupal\Core\Url;

/**
 * Defines a class to build a listing of QAShot Test entities.
 *
 * @ingroup qa_shot
 */
class QAShotTestListBuilder extends EntityListBuilder {

  use LinkGeneratorTrait;

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('QAShot Test ID');
    $header['name'] = $this->t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\qa_shot\Entity\QAShotTest */
    $row['id'] = $entity->id();
    $row['name'] = $this->l(
      $entity->label(),
      new Url(
        'entity.qa_shot_test.edit_form', array(
          'qa_shot_test' => $entity->id(),
        )
      )
    );
    return $row + parent::buildRow($entity);
  }

}
