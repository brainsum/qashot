<?php

/**
 * @file
 * Provide views runtime hooks for node.module.
 */

use Drupal\user\RoleInterface;
use Drupal\views\Analyzer;
use Drupal\views\ViewExecutable;
use Drupal\user\Entity\Role;

/**
 * Implements hook_views_query_substitutions().
 */
function qa_shot_views_query_substitutions(ViewExecutable $view) {
  $account = \Drupal::currentUser();
  return [
    '***ADMINISTER_QASHOT_TESTS***' => intval($account->hasPermission('administer qashot test entities')),
    '***VIEW_OWN_UNPUBLISHED_QASHOT_TESTS***' => intval($account->hasPermission('view own unpublished qashot test entities')),
    '***BYPASS_QASHOT_TEST_ACCESS***' => intval($account->hasPermission('bypass qashot access')),
  ];
}

/**
 * Implements hook_views_analyze().
 */
function qa_shot_views_analyze(ViewExecutable $view) {
  $ret = [];

  // Check for something other than the default display:
  if ($view->storage->get('base_table') == 'qa_shot_test') {
    foreach ($view->displayHandlers as $display) {
      if (!$display->isDefaulted('access') || !$display->isDefaulted('filters')) {
        // Check for no access control.
        $access = $display->getOption('access');
        if (empty($access['type']) || $access['type'] == 'none') {
          $anonymous_role = Role::load(RoleInterface::ANONYMOUS_ID);
          $anonymous_has_access = $anonymous_role && $anonymous_role->hasPermission('access content');
          $authenticated_role = Role::load(RoleInterface::AUTHENTICATED_ID);
          $authenticated_has_access = $authenticated_role && $authenticated_role->hasPermission('access content');
          if (!$anonymous_has_access || !$authenticated_has_access) {
            $ret[] = Analyzer::formatMessage(t('Some roles lack permission to access content, but display %display has no access control.', ['%display' => $display->display['display_title']]), 'warning');
          }
          $filters = $display->getOption('filters');
          foreach ($filters as $filter) {
            if ($filter['table'] == 'qa_shot_test' && ($filter['field'] == 'status' || $filter['field'] == 'status_extra')) {
              continue 2;
            }
          }
          $ret[] = Analyzer::formatMessage(t('Display %display has no access control but does not contain a filter for published QAShot tests.', ['%display' => $display->display['display_title']]), 'warning');
        }
      }
    }
  }
  foreach ($view->displayHandlers as $display) {
    if ($display->getPluginId() == 'page') {
      if ($display->getOption('path') == 'qa_shot_test/%') {
        $ret[] = Analyzer::formatMessage(t('Display %display has set qa_shot_test/% as path. This will not produce what you want. If you want to have multiple versions of the QAShot test view, use panels.', ['%display' => $display->display['display_title']]), 'warning');
      }
    }
  }

  return $ret;
}
