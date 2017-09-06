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
          if ($account->id() == $entity->getOwnerId()) {
            return AccessResult::allowedIfHasPermission($account, 'view own unpublished qashot test entities');
          }
          return AccessResult::allowedIfHasPermission($account, 'view any unpublished qashot test entities');
        }

        if ($account->id() == $entity->getOwnerId()) {
          return AccessResult::allowedIfHasPermission($account, 'view own published qashot test entities');
        }
        return AccessResult::allowedIfHasPermission($account, 'view any published qashot test entities');

      case 'update':
        if ($account->id() == $entity->getOwnerId()) {
          return AccessResult::allowedIfHasPermission($account, 'edit own qashot test entities');
        }
        return AccessResult::allowedIfHasPermission($account, 'edit any qashot test entities');

      case 'delete':
        if ($account->id() == $entity->getOwnerId()) {
          return AccessResult::allowedIfHasPermission($account, 'delete own qashot test entities');
        }
        return AccessResult::allowedIfHasPermission($account, 'delete any qashot test entities');
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
