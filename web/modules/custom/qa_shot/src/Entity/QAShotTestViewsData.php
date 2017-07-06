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
  public function getViewsData(): array {
    $data = parent::getViewsData();

    // Additional information for Views integration, such as table joins, can be
    // put here.
    $data['views']['table']['group'] = t('Custom Global');
    $data['views']['table']['join'] = [
      // #global is a special flag which allows a table to appear all the time.
      '#global' => [],
    ];


    $data['views']['test_queue_status'] = [
      'title' => t('QAShot Test Queue Status'),
      'help' => t('Display the current status of the Test in the queue.'),
      'field' => [
        'id' => 'test_queue_status',
      ],
    ];

    $data['views']['entity_bundle_label'] = [
      'title' => t('QAShot Test Bundle Label'),
      'help' => t('Display the label of the entity.'),
      'field' => [
        'id' => 'entity_bundle_label',
      ],
    ];

    $data['views']['qa_shot_test_bulk_form'] = [
      'title' => $this->t('Bulk update'),
      'help' => $this->t('Add a form element that lets you run operations on multiple tests.'),
      'field' => [
        'id' => 'qa_shot_test_bulk_form',
      ],
    ];

    return $data;
  }

}
