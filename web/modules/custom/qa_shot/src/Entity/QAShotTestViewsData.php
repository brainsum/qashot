<?php

namespace Drupal\qa_shot\Entity;

use Drupal\views\EntityViewsData;

/**
 * Provides Views data for QAShot Test entities.
 */
class QAShotTestViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    // Additional information for Views integration, such as table joins, can be
    // put here.

    return $data;
  }

}
