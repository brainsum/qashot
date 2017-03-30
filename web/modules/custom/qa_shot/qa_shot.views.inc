<?php

/**
 * @file
 * Contains qa_shot\qa_shot.views.inc..
 *
 * Provide a custom views field data that isn't tied to any other module.
 */

/**
 * Implements hook_views_data().
 */
function qa_shot_views_data() {

  $data['views']['table']['group'] = t('Custom Global');
  $data['views']['table']['join'] = array(
    // #global is a special flag which allows a table to appear all the time.
    '#global' => array(),
  );


  $data['views']['test_queue_status'] = array(
    'title' => t('QAShot Test Queue Status'),
    'help' => t('Display the current status of the Test in the queue.'),
    'field' => array(
      'id' => 'test_queue_status',
    ),
  );

  return $data;
}
