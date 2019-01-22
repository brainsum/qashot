<?php

/**
 * @file
 * Hooks defined by this module.
 */

/**
 * Modify the list of available Test Workers.
 *
 * This hook may be used to modify plugin properties after they have been
 * specified by other modules.
 *
 * @param \Drupal\qa_shot_test_worker\TestWorker\TestWorkerInterface[] $definitions
 *   An array of all the existing plugin definitions, passed by reference.
 *
 * @see \Drupal\qa_shot_test_worker\TestWorker\TestWorkerManager
 */
function hook_test_worker_info_alter(array &$definitions) {
  $definitions['someplugin']['label'] = t('Better name');
}
