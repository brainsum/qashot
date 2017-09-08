<?php

namespace Drupal\qa_shot;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\qa_shot\Entity\QAShotTestInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Access controller for the QAShot Test entity.
 *
 * @see \Drupal\qa_shot\Entity\QAShotTest.
 */
class QAShotTestAccessControlHandler extends EntityAccessControlHandler implements QAShotTestAccessControlHandlerInterface, EntityHandlerInterface {

  /**
   * The qa_shot_test grant storage.
   *
   * @var \Drupal\qa_shot\QAShotGrantDatabaseStorageInterface
   */
  protected $grantStorage;

  /**
   * Constructs a NodeAccessControlHandler object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\qa_shot\QAShotGrantDatabaseStorageInterface $grant_storage
   *   The qa_shot_test grant storage.
   */
  public function __construct(EntityTypeInterface $entity_type, QAShotGrantDatabaseStorageInterface $grant_storage) {
    parent::__construct($entity_type);
    $this->grantStorage = $grant_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('qa_shot.grant_storage')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add qashot test entities');
  }


  /**
   * {@inheritdoc}
   */
  public function access(EntityInterface $entity, $operation, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $account = $this->prepareUser($account);

    if ($account->hasPermission('bypass qashot access')) {
      $result = AccessResult::allowed()->cachePerPermissions();
      return $return_as_object ? $result : $result->isAllowed();
    }
    if (!$account->hasPermission('access content')) {
      $result = AccessResult::forbidden("The 'access content' permission is required.")->cachePerPermissions();
      return $return_as_object ? $result : $result->isAllowed();
    }
    $result = parent::access($entity, $operation, $account, TRUE)->cachePerPermissions();

    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function createAccess($entity_bundle = NULL, AccountInterface $account = NULL, array $context = [], $return_as_object = FALSE) {
    $account = $this->prepareUser($account);

    if ($account->hasPermission('bypass qashot access')) {
      $result = AccessResult::allowed()->cachePerPermissions();
      return $return_as_object ? $result : $result->isAllowed();
    }
    if (!$account->hasPermission('access content')) {
      $result = AccessResult::forbidden()->cachePerPermissions();
      return $return_as_object ? $result : $result->isAllowed();
    }
    if ($account->hasPermission('add qashot test entities')) {
      $result = AccessResult::allowed()->cachePerPermissions();
      return $return_as_object ? $result : $result->isAllowed();
    }

    return $return_as_object ? AccessResult::neutral() : AccessResult::neutral()->isNeutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $qa_shot_test, $operation, AccountInterface $account) {
    /** @var \Drupal\qa_shot\Entity\QAShotTestInterface $qa_shot_test */

    switch ($operation) {
      case 'view':
        if (!$qa_shot_test->isPublished() && (
            $account->id() == $qa_shot_test->getOwnerId() && $account->hasPermission('view own unpublished qashot test entities') ||
            $account->hasPermission('view any unpublished qashot test entities')
          )) {
          return AccessResult::allowed()->cachePerPermissions()->cachePerUser()->addCacheableDependency($qa_shot_test);
        }

        if (
          $account->id() == $qa_shot_test->getOwnerId() && $account->hasPermission('view own published qashot test entities') ||
          $account->hasPermission('view any published qashot test entities')
        ) {
          return AccessResult::allowed()->cachePerPermissions()->cachePerUser()->addCacheableDependency($qa_shot_test);
        }
        break;

      case 'update':
        if (
          $account->id() == $qa_shot_test->getOwnerId() && $account->hasPermission('edit own qashot test entities') ||
          $account->hasPermission('edit any qashot test entities')
        ) {
          return AccessResult::allowed()->cachePerPermissions()->cachePerUser()->addCacheableDependency($qa_shot_test);
        }
        break;

      case 'delete':
        if (
          $account->id() == $qa_shot_test->getOwnerId() && $account->hasPermission('delete own qashot test entities') ||
          $account->hasPermission('delete any qashot test entities')
        ) {
          return AccessResult::allowed()->cachePerPermissions()->cachePerUser()->addCacheableDependency($qa_shot_test);
        }
        break;
    }

    // Evaluate qa_shot_test grants.
    return $this->grantStorage->access($qa_shot_test, $operation, $account);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkFieldAccess($operation, FieldDefinitionInterface $field_definition, AccountInterface $account, FieldItemListInterface $items = NULL) {
    // Only users with the administer qa_shot_tests permission can edit
    // administrative fields.
    $administrative_fields = ['uid', 'status', 'created', 'promote', 'sticky'];
    if ($operation == 'edit' && in_array($field_definition->getName(), $administrative_fields, TRUE)) {
      return AccessResult::allowedIfHasPermission($account, 'administer qashot test entities');
    }

    // No user can change read only fields.
    $read_only_fields = ['revision_timestamp', 'revision_uid'];
    if ($operation == 'edit' && in_array($field_definition->getName(), $read_only_fields, TRUE)) {
      return AccessResult::forbidden();
    }

    // Users have access to the revision_log field either if they have
    // administrative permissions or if the new revision option is enabled.
    if ($operation == 'edit' && $field_definition->getName() == 'revision_log') {
      if ($account->hasPermission('administer qashot test entities')) {
        return AccessResult::allowed()->cachePerPermissions();
      }
      return AccessResult::allowedIf($items->getEntity()->type->entity->isNewRevision())->cachePerPermissions();
    }
    return parent::checkFieldAccess($operation, $field_definition, $account, $items);
  }

  /**
   * {@inheritdoc}
   */
  public function acquireGrants(QAShotTestInterface $qa_shot_test) {
    $grants = $this->moduleHandler->invokeAll('qa_shot_test_access_records', [$qa_shot_test]);
    // Let modules alter the grants.
    $this->moduleHandler->alter('qa_shot_test_access_records', $grants, $qa_shot_test);
    // If no grants are set and the qa_shot_test is published, then use the
    // default grant.
    if (empty($grants) && $qa_shot_test->isPublished()) {
      $grants[] = ['realm' => 'all', 'gid' => 0, 'grant_view' => 1, 'grant_update' => 0, 'grant_delete' => 0];
    }
    return $grants;
  }

  /**
   * {@inheritdoc}
   */
  public function writeGrants(QAShotTestInterface $qa_shot_test, $delete = TRUE) {
    $grants = $this->acquireGrants($qa_shot_test);
    $this->grantStorage->write($qa_shot_test, $grants, NULL, $delete);
  }

  /**
   * {@inheritdoc}
   */
  public function writeDefaultGrant() {
    $this->grantStorage->writeDefault();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteGrants() {
    $this->grantStorage->delete();
  }

  /**
   * {@inheritdoc}
   */
  public function countGrants() {
    return $this->grantStorage->count();
  }

  /**
   * {@inheritdoc}
   */
  public function checkAllGrants(AccountInterface $account) {
    return $this->grantStorage->checkAll($account);
  }

}
