<?php

/**
 * @file
 * Hooks specific to the Node module.
 */

use Drupal\Core\Access\AccessResult;

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Inform the qa_shot_test access system what permissions the user has.
 *
 * This hook is for implementation by qa_shot_test access modules. In this hook,
 * the module grants a user different "grant IDs" within one or more
 * "realms". In hook_qa_shot_test_access_records(), the realms and grant IDs are
 * associated with permission to view, edit, and delete individual
 * qa_shot_tests.
 *
 * The realms and grant IDs can be arbitrarily defined by your qa_shot_test
 * access module; it is common to use role IDs as grant IDs, but that is not
 * required. Your module could instead maintain its own list of users, where
 * each list has an ID. In that case, the return value of this hook would be an
 * array of the list IDs that this user is a member of.
 *
 * A qa_shot_test access module may implement as many realms as necessary to
 * properly define the access privileges for the qa_shot_tests. Note that the
 * system makes no distinction between published and unpublished qa_shot_tests.
 * It is the module's responsibility to provide appropriate realms to limit
 * access to unpublished content.
 *
 * qa_shot_test access records are stored in the {qa_shot_test_access} table and
 * define which grants are required to access a qa_shot_test. There is a special
 * case for the view operation -- a record with qa_shot_test ID 0 corresponds to
 * a "view all" grant for the realm and grant ID of that record. If there are no
 * qa_shot_test access modules enabled, the core qa_shot_test module adds a
 * qa_shot_test ID 0 record for realm 'all'. qa_shot_test access modules can
 * also grant "view all" permission on their custom realms; for example, a
 * module could create a record in {qa_shot_test_access} with:
 * @code
 * $record = array(
 *   'nid' => 0,
 *   'gid' => 888,
 *   'realm' => 'example_realm',
 *   'grant_view' => 1,
 *   'grant_update' => 0,
 *   'grant_delete' => 0,
 * );
 * db_insert('qa_shot_test_access')->fields($record)->execute();
 * @endcode
 * And then in its hook_qa_shot_test_grants() implementation, it would need to
 * return:
 * @code
 * if ($op == 'view') {
 *   $grants['example_realm'] = array(888);
 * }
 * @endcode
 * If you decide to do this, be aware that the qa_shot_test_access_rebuild()
 * function will erase any qa_shot_test ID 0 entry when it is called, so you
 * will need to make sure to restore your {qa_shot_test_access} record after
 * qa_shot_test_access_rebuild() is called.
 *
 * For a detailed example, see qa_shot_test_access_example.module.
 *
 * @param \Drupal\Core\Session\AccountInterface $account
 *   The account object whose grants are requested.
 * @param string $op
 *   The qa_shot_test operation to be performed, such as 'view', 'update', or
 *   'delete'.
 *
 * @return array
 *   An array whose keys are "realms" of grants, and whose values are arrays of
 *   the grant IDs within this realm that this user is being granted.
 *
 * @see qa_shot_test_access_view_all_qa_shot_tests()
 * @see qa_shot_test_access_rebuild()
 * @ingroup qa_shot_test_access
 */
function hook_qa_shot_test_grants(\Drupal\Core\Session\AccountInterface $account, $op) {
  $grants = NULL;
  if ($account->hasPermission('access private content')) {
    $grants['example'] = [1];
  }
  if ($account->id()) {
    $grants['example_author'] = [$account->id()];
  }
  return $grants;
}

/**
 * Set permissions for a qa_shot_test to be written to the database.
 *
 * When a qa_shot_test is saved, a module implementing
 * hook_qa_shot_test_access_records() will be asked if it is interested in the
 * access permissions for a qa_shot_test. If it is interested, it must respond
 * with an array of permissions arrays for that qa_shot_test.
 *
 * qa_shot_test access grants apply regardless of the published or unpublished
 * status of the qa_shot_test. Implementations must make sure not to grant
 * access to unpublished qa_shot_tests if they don't want to change the standard
 * access control behavior. Your module may need to create a separate access
 * realm to handle access to unpublished qa_shot_tests.
 *
 * Note that the grant values in the return value from your hook must be
 * integers and not boolean TRUE and FALSE.
 *
 * Each permissions item in the array is an array with the following elements:
 * - 'realm': The name of a realm that the module has defined in
 *   hook_qa_shot_test_grants().
 * - 'gid': A 'grant ID' from hook_qa_shot_test_grants().
 * - 'grant_view': If set to 1 a user that has been identified as a member
 *   of this gid within this realm can view this qa_shot_test. This should
 *   usually be set to $qa_shot_test->isPublished(). Failure to do so may expose
 *   unpublished content to some users.
 * - 'grant_update': If set to 1 a user that has been identified as a member
 *   of this gid within this realm can edit this qa_shot_test.
 * - 'grant_delete': If set to 1 a user that has been identified as a member
 *   of this gid within this realm can delete this qa_shot_test.
 * - langcode: (optional) The language code of a specific translation of the
 *   qa_shot_test, if any. Modules may add this key to grant different access to
 *   different translations of a qa_shot_test, such that (e.g.) a particular
 *   group is granted access to edit the Catalan version of the qa_shot_test,
 *   but not the Hungarian version. If no value is provided, the langcode is set
 *   automatically from the $qa_shot_test parameter and the qa_shot_test's
 *   original language (if specified) is used as a fallback. Only specify
 *   multiple grant records with different languages for a qa_shot_test if the
 *   site has those languages configured.
 *
 * A "deny all" grant may be used to deny all access to a particular
 * qa_shot_test or qa_shot_test translation:
 * @code
 * $grants[] = array(
 *   'realm' => 'all',
 *   'gid' => 0,
 *   'grant_view' => 0,
 *   'grant_update' => 0,
 *   'grant_delete' => 0,
 *   'langcode' => 'ca',
 * );
 * @endcode
 * Note that another module qa_shot_test access module could override this by
 * granting access to one or more qa_shot_tests, since grants are additive. To
 * enforce that access is denied in a particular case, use
 * hook_qa_shot_test_access_records_alter().
 * Also note that a deny all is not written to the database; denies are
 * implicit.
 *
 * @param \Drupal\qa_shot\Entity\QAShotTestInterface $qa_shot_test
 *   The qa_shot_test that has just been saved.
 *
 * @return array
 *   An array of grants as defined above.
 *
 * @see hook_qa_shot_test_access_records_alter()
 * @ingroup qa_shot_test_access
 */
function hook_qa_shot_test_access_records(\Drupal\qa_shot\Entity\QAShotTestInterface $qa_shot_test) {
  // We only care about the qa_shot_test if it has been marked private. If not,
  // it is treated just like any other qa_shot_test and we completely ignore it.
  if ($qa_shot_test->private->value) {
    $grants = [];
    // Only published Catalan translations of private qa_shot_tests should be
    // viewable to all users. If we fail to check $qa_shot_test->isPublished(),
    // all users would be able to view an unpublished qa_shot_test.
    if ($qa_shot_test->isPublished()) {
      $grants[] = [
        'realm' => 'example',
        'gid' => 1,
        'grant_view' => 1,
        'grant_update' => 0,
        'grant_delete' => 0,
        'langcode' => 'ca',
      ];
    }
    // For the example_author array, the GID is equivalent to a UID, which
    // means there are many groups of just 1 user.
    // Note that an author can always view his or her qa_shot_tests, even if
    // they have status unpublished.
    if ($qa_shot_test->getOwnerId()) {
      $grants[] = [
        'realm' => 'example_author',
        'gid' => $qa_shot_test->getOwnerId(),
        'grant_view' => 1,
        'grant_update' => 1,
        'grant_delete' => 1,
        'langcode' => 'ca',
      ];
    }

    return $grants;
  }
}

/**
 * Alter permissions for a qa_shot_test before it is written to the database.
 *
 * The qa_shot_test access modules establish rules for user access to content.
 * qa_shot_test access records are stored in the {qa_shot_test_access} table and
 * define which permissions are required to access a qa_shot_test. This hook is
 * invoked after qa_shot_test access modules returned their requirements via
 * hook_qa_shot_test_access_records(); doing so allows modules to modify the
 * $grants array by reference before it is stored, so custom or advanced
 * business logic can be applied.
 *
 * Upon viewing, editing or deleting a qa_shot_test, hook_qa_shot_test_grants()
 * builds a permissions array that is compared against the stored access
 * records. The user must have one or more matching permissions in order to
 * complete the requested operation.
 *
 * A module may deny all access to a qa_shot_test by setting $grants to an empty
 * array.
 *
 * The preferred use of this hook is in a module that bridges multiple
 * qa_shot_test access modules with a configurable behavior, as shown in the
 * example with the 'is_preview' field.
 *
 * @param array $grants
 *   The $grants array returned by hook_qa_shot_test_access_records().
 * @param \Drupal\qa_shot\Entity\QAShotTestInterface $qa_shot_test
 *   The qa_shot_test for which the grants were acquired.
 *
 * @see hook_qa_shot_test_access_records()
 * @see hook_qa_shot_test_grants()
 * @see hook_qa_shot_test_grants_alter()
 * @ingroup qa_shot_test_access
 */
function hook_qa_shot_test_access_records_alter(array &$grants, \Drupal\qa_shot\Entity\QAShotTestInterface $qa_shot_test) {
  // Our module allows editors to mark specific articles with the 'is_preview'
  // field. If the qa_shot_test being saved has a TRUE value for that field,
  // then only our grants are retained, and other grants are removed. Doing so
  // ensures that our rules are enforced no matter what priority other grants
  // are given.
  if ($qa_shot_test->is_preview) {
    // Our module grants are set in $grants['example'].
    $temp = $grants['example'];
    // Now remove all module grants but our own.
    $grants = ['example' => $temp];
  }
}

/**
 * Alter user access rules when trying to view, edit or delete a qa_shot_test.
 *
 * The qa_shot_test access modules establish rules for user access to content.
 * hook_qa_shot_test_grants() defines permissions for a user to view, edit or
 * delete qa_shot_tests by building a $grants array that indicates the
 * permissions assigned to the user by each qa_shot_test access module. This
 * hook is called to allow modules to modify the $grants array by reference, so
 * the interaction of multiple qa_shot_test access modules can be altered or
 * advanced business logic can be applied.
 *
 * The resulting grants are then checked against the records stored in the
 * {qa_shot_test_access} table to determine if the operation may be completed.
 *
 * A module may deny all access to a user by setting $grants to an empty array.
 *
 * Developers may use this hook to either add additional grants to a user or to
 * remove existing grants. These rules are typically based on either the
 * permissions assigned to a user role, or specific attributes of a user
 * account.
 *
 * @param array $grants
 *   The $grants array returned by hook_qa_shot_test_grants().
 * @param \Drupal\Core\Session\AccountInterface $account
 *   The account requesting access to content.
 * @param string $op
 *   The operation being performed, 'view', 'update' or 'delete'.
 *
 * @see hook_qa_shot_test_grants()
 * @see hook_qa_shot_test_access_records()
 * @see hook_qa_shot_test_access_records_alter()
 * @ingroup qa_shot_test_access
 */
function hook_qa_shot_test_grants_alter(array &$grants, \Drupal\Core\Session\AccountInterface $account, $op) {
  /* Our sample module never allows certain roles to edit or delete
   * content. Since some other qa_shot_test access modules might allow this
   * permission, we expressly remove it by returning an empty $grants
   * array for roles specified in our variable setting. */

  // Get our list of banned roles.
  $restricted = \Drupal::config('example.settings')->get('restricted_roles');

  if ($op != 'view' && !empty($restricted)) {
    // Now check the roles for this account against the restrictions.
    foreach ($account->getRoles() as $rid) {
      if (in_array($rid, $restricted)) {
        $grants = [];
      }
    }
  }
}

/**
 * Controls access to a qa_shot_test.
 *
 * Modules may implement this hook if they want to have a say in whether or not
 * a given user has access to perform a given operation on a qa_shot_test.
 *
 * The administrative account (user ID #1) always passes any access check, so
 * this hook is not called in that case. Users with the "bypass qa_shot_test
 * access" permission may always view and edit content through the
 * administrative interface.
 *
 * Note that not all modules will want to influence access on all qa_shot_test
 * types. If your module does not want to explicitly allow or forbid access,
 * return an AccessResultInterface object with neither isAllowed() nor
 * isForbidden() equaling TRUE. Blindly returning an object with isForbidden()
 * equaling TRUE will break other qa_shot_test access modules.
 *
 * Also note that this function isn't called for qa_shot_test listings (e.g.,
 * RSS feeds, the default home page at path 'qa_shot_test', a recent content
 * block, etc.) See
 * @link qa_shot_test_access qa_shot_test access rights @endlink for a full explanation.
 *
 * @param \Drupal\qa_shot\Entity\QAShotTestInterface|string $qa_shot_test
 *   Either a qa_shot_test entity or the machine name of the content type on
 *   which to perform the access check.
 * @param string $op
 *   The operation to be performed. Possible values:
 *   - "create"
 *   - "delete"
 *   - "update"
 *   - "view".
 * @param \Drupal\Core\Session\AccountInterface $account
 *   The user object to perform the access check operation on.
 *
 * @return \Drupal\Core\Access\AccessResultInterface
 *   The access result.
 *
 * @ingroup qa_shot_test_access
 */
function hook_qa_shot_test_access(\Drupal\qa_shot\Entity\QAShotTestInterface $qa_shot_test, $op, \Drupal\Core\Session\AccountInterface $account) {
  $type = $qa_shot_test->bundle();

  switch ($op) {
    case 'create':
      return AccessResult::allowedIfHasPermission($account, 'create ' . $type . ' content');

    case 'update':
      if ($account->hasPermission('edit any qashot test entities')) {
        return AccessResult::allowed()->cachePerPermissions();
      }
      else {
        return AccessResult::allowedIf($account->hasPermission('edit own ' . $type . ' content') && ($account->id() == $qa_shot_test->getOwnerId()))->cachePerPermissions()->cachePerUser()->addCacheableDependency($qa_shot_test);
      }

    case 'delete':
      if ($account->hasPermission('delete any qashot test entities')) {
        return AccessResult::allowed()->cachePerPermissions();
      }
      else {
        return AccessResult::allowedIf($account->hasPermission('delete own ' . $type . ' content') && ($account->id() == $qa_shot_test->getOwnerId()))->cachePerPermissions()->cachePerUser()->addCacheableDependency($qa_shot_test);
      }

    default:
      // No opinion.
      return AccessResult::neutral();
  }
}

/**
 * @} End of "addtogroup hooks".
 */
