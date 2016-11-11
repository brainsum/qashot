<?php

namespace Drupal\qa_shot;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the QAShot Test entity.
 *
 * @see \Drupal\qa_shot\Entity\QAShotTest.
 */
class QAShotTestAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\qa_shot\Entity\QAShotTestInterface $entity */
    switch ($operation) {
      case 'view':
        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished qashot test entities');
        }
        return AccessResult::allowedIfHasPermission($account, 'view published qashot test entities');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit qashot test entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete qashot test entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add qashot test entities');
  }

}
