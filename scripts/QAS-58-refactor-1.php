<?php

/**
 * @file
 * Refactor related code.
 *
 * Run this script after cim and before entity-updates.
 */

/**
 * Move viewport values into the field_viewport field.
 */
function viewport_field() {
  // Load tests.
  $testStorage = \Drupal::entityTypeManager()->getStorage('qa_shot_test');
  /** @var \Drupal\qa_shot\Entity\QAShotTestInterface[] $tests */
  $tests = $testStorage->loadMultiple();

  $paragraphStorage = \Drupal::entityTypeManager()->getStorage('paragraph');

  /** @var \Drupal\qa_shot\Entity\QAShotTestInterface $test */
  foreach ($tests as $test) {
    // Get the existing viewports.
    $viewports = $test->get('viewport')->getValue();

    $paragraphs = [];
    foreach ($viewports as $viewport) {
      // Transform their values, create paragraphs.
      $values = [
        'type' => 'viewport',
        'field_name' => $viewport['name'],
        'field_width' => $viewport['width'],
        'field_height' => $viewport['height'],
      ];

      $paragraphs[] = $paragraphStorage->create($values);
    }

    // Set the new viewport values and save the test.
    $test->set('field_viewport', $paragraphs);
    $test->set('viewport', []);
    $test->save();
  }

  $database = \Drupal::database();
  // If there are stuck values (improper removal of an entity, etc.),
  // remove them.
  $database->truncate('qa_shot_test__viewport')->execute();
}
