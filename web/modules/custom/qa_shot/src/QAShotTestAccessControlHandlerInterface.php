<?php

namespace Drupal\qa_shot;

use Drupal\Core\Session\AccountInterface;
use Drupal\qa_shot\Entity\QAShotTestInterface;

/**
 * Node specific entity access control methods.
 *
 * @ingroup qa_shot_test_access
 */
interface QAShotTestAccessControlHandlerInterface {

  /**
   * Gets the list of qa_shot_test access grants.
   *
   * This function is called to check the access grants for a qa_shot_test. It
   * collects all qa_shot_test access grants for the qa_shot_test from
   * hook_qa_shot_test_access_records() implementations, allows these grants to
   * be altered via hook_qa_shot_test_access_records_alter() implementations,
   * and returns the grants to the caller.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $qa_shot_test
   *   The $qa_shot_test to acquire grants for.
   *
   * @return array
   *   The access rules for the qa_shot_test.
   */
  public function acquireGrants(QAShotTestInterface $qa_shot_test);

  /**
   * Writes a list of grants to the database, deleting any previously saved ones.
   *
   * Modules that use qa_shot_test access can use this function when doing mass
   * updates due to widespread permission changes.
   *
   * Note: Don't call this function directly from a contributed module. Call
   * \Drupal\qa_shot\QAShotAccessControlHandlerInterface::acquireGrants() instead.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $qa_shot_test
   *   The qa_shot_test whose grants are being written.
   * @param $delete
   *   (optional) If false, does not delete records. This is only for
   *   optimization purposes, and assumes the caller has already performed a
   *   mass delete of some form. Defaults to TRUE.
   *
   * @deprecated in Drupal 8.x, will be removed before Drupal 9.0.
   *   Use \Drupal\qa_shot\QAShotAccessControlHandlerInterface::acquireGrants().
   */
  public function writeGrants(QAShotTestInterface $qa_shot_test, $delete = TRUE);

  /**
   * Creates the default qa_shot_test access grant entry on the grant storage.
   */
  public function writeDefaultGrant();

  /**
   * Deletes all qa_shot_test access entries.
   */
  public function deleteGrants();

  /**
   * Counts available qa_shot_test grants.
   *
   * @return int
   *   Returns the amount of qa_shot_test grants.
   */
  public function countGrants();

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
  public function checkAllGrants(AccountInterface $account);

}
