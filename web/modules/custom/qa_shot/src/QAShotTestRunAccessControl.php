<?php

namespace Drupal\qa_shot;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

/**
 * Access QAShot run pages.
 *
 * @package Drupal\qa_shot
 */
class QAShotTestRunAccessControl {

  /**
   * Checks access for run QAShot test entities.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   If user has acces it will return isAllowed, otherwise isNeutral.
   */
  public function checkRunAccess(AccountInterface $account) {
    $account_id = $account->id();
    // If user 1.
    if ($account_id == 1) {
      return AccessResult::allowed();
    }

    /* @var $node \Drupal\qa_shot\Entity\QAShotTestInterface */
    $node = \Drupal::routeMatch()->getParameter('qa_shot_test');

    // Own content.
    if ($node->getOwnerId() == $account_id) {
      return AccessResult::allowedIfHasPermission($account, 'run own qashot test entities');
    }

    // Run any.
    return AccessResult::allowedIfHasPermission($account, 'run any qashot test entities');
  }
}