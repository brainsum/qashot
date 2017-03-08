<?php

/**
 * @file
 * Views include file.
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


  $data['views']['test_metadata_last_run'] = array(
    'title' => t('Views Field for Metadata (last run)'),
    'help' => t('Display the Metadata (Last Run) field in a custom fashion.'),
    'field' => array(
      'id' => 'test_metadata_last_run',
    ),
  );

  return $data;
}
