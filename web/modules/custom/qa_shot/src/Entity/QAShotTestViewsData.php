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

    $data['qa_shot_test']['table']['base']['access query tag'] = 'qa_shot_test_access';

    // Additional information for Views integration, such as table joins, can be
    // put here.
    $data['views']['table']['group'] = t('Custom Global');
    $data['views']['table']['join'] = [
      // #global is a special flag which allows a table to appear all the time.
      '#global' => [],
    ];

    $data['views']['entity_bundle_label'] = [
      'title' => t('QAShot Test Bundle Label'),
      'help' => t('Display the label of the entity.'),
      'field' => [
        'id' => 'entity_bundle_label',
      ],
    ];

    $data['views']['entity_status_label'] = [
      'title' => t('QAShot Test Status in queue'),
      'help' => t('Display the status of the entity in queue.'),
      'field' => [
        'id' => 'entity_status_label',
      ],
    ];

    $data['views']['qa_shot_test_bulk_form'] = [
      'title' => $this->t('Bulk update'),
      'help' => $this->t('Add a form element that lets you run operations on multiple tests.'),
      'field' => [
        'id' => 'qa_shot_test_bulk_form',
      ],
    ];

    // Define the base group of this table. Fields that don't have a group
    // defined will go into this field by default.
    $data['qa_shot_test_access']['table']['group'] = $this->t('Content access');

    // For other base tables, explain how we join.
    $data['qa_shot_test_access']['table']['join'] = [
      'qa_shot_test' => [
        'left_field' => 'id',
        'field' => 'id',
      ],
    ];
    $data['qa_shot_test_access']['id'] = [
      'title' => $this->t('Access'),
      'help' => $this->t('Filter by access.'),
      'filter' => [
        'id' => 'qa_shot_test_access',
        'help' => $this->t('Filter for content by view access. <strong>Not necessary if you are using QAShot test as your base table.</strong>'),
      ],
    ];

    return $data;
  }

}
