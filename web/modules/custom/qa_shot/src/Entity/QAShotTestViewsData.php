<?php

namespace Drupal\qa_shot\Entity;

use Drupal\views\EntityViewsData;
use Drupal\views\EntityViewsDataInterface;

/**
 * Provides Views data for QAShot Test entities.
 */
class QAShotTestViewsData extends EntityViewsData implements EntityViewsDataInterface {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    $data['qa_shot_test']['table']['base'] = array(
      'field' => 'id',
      'title' => $this->t('QAShot Test'),
      'help' => $this->t('The QAShot Test ID.'),
    );

    return $data;
  }

}
