<?php

namespace Drupal\qa_shot;

use Drupal\Core\Session\AccountInterface;
use Drupal\qa_shot\Entity\QAShotTestInterface;

/**
 * Provides an interface for qa_shot_test access grant storage.
 *
 * @ingroup qa_shot_test_access
 */
interface QAShotGrantDatabaseStorageInterface {

  /**
   * Checks all grants for a given account.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   A user object representing the user for whom the operation is to be
   *   performed.
   *
   * @return int
   *   Status of the access check.
   */
  public function checkAll(AccountInterface $account);

  /**
   * Alters a query when qa_shot_test access is required.
   *
   * @param mixed $query
   *   Query that is being altered.
   * @param array $tables
   *   A list of tables that need to be part of the alter.
   * @param string $op
   *   The operation to be performed on the qa_shot_test. Possible values are:
   *    - "view"
   *    - "update"
   *    - "delete"
   *    - "create".
   * @param \Drupal\Core\Session\AccountInterface $account
   *   A user object representing the user for whom the operation is to be
   *   performed.
   * @param string $base_table
   *   The base table of the query.
   *
   * @return int
   *   Status of the access check.
   */
  public function alterQuery($query, array $tables, $op, AccountInterface $account, $base_table);

  /**
   * Writes a list of grants to the database, deleting previously saved ones.
   *
   * If a realm is provided, it will only delete grants from that realm, but
   * it will always delete a grant from the 'all' realm. Modules that use
   * qa_shot_test access can use this method when doing mass updates due to
   * widespread permission changes.
   *
   * Note: Don't call this method directly from a contributed module. Call
   * \Drupal\qa_shot\QAShotAccessControlHandlerInterface::acquireGrants()
   * instead.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $qa_shot_test
   *   The qa_shot_test whose grants are being written.
   * @param array $grants
   *   A list of grants to write. Each grant is an array that must contain the
   *   following keys: realm, gid, grant_view, grant_update, grant_delete.
   *   The realm is specified by a particular module; the gid is as well, and
   *   is a module-defined id to define grant privileges. each grant_* field
   *   is a boolean value.
   * @param string $realm
   *   (optional) If provided, read/write grants for that realm only. Defaults
   *   to NULL.
   * @param bool $delete
   *   (optional) If false, does not delete records. This is only for
   *   optimization purposes, and assumes the caller has already performed a
   *   mass delete of some form. Defaults to TRUE.
   */
  public function write(QAShotTestInterface $qa_shot_test, array $grants, $realm = NULL, $delete = TRUE);

  /**
   * Deletes all qa_shot_test access entries.
   */
  public function delete();

  /**
   * Creates the default qa_shot_test access grant entry.
   */
  public function writeDefault();

  /**
   * Determines access to qa_shot_tests based on qa_shot_test grants.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $qa_shot_test
   *   The entity for which to check 'create' access.
   * @param string $operation
   *   The entity operation. Usually one of 'view', 'edit', 'create' or
   *   'delete'.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result, either allowed or neutral. If there are no
   *   qa_shot_test grants, the default grant defined by writeDefault() is
   *   applied.
   *
   * @see hook_qa_shot_test_grants()
   * @see hook_qa_shot_test_access_records()
   * @see \Drupal\qa_shot\QAShotGrantDatabaseStorageInterface::writeDefault()
   */
  public function access(QAShotTestInterface $qa_shot_test, $operation, AccountInterface $account);

  /**
   * Counts available qa_shot_test grants.
   *
   * @return int
   *   Returns the amount of qa_shot_test grants.
   */
  public function count();

  /**
   * Remove the access records belonging to certain qa_shot_tests.
   *
   * @param array $ids
   *   A list of qa_shot_test IDs. The grant records belonging to these
   *   qa_shot_tests will be deleted.
   */
  public function deleteQAShotRecords(array $ids);

}
