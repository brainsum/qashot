<?php

namespace Drupal\qa_shot;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Database\Query\Condition;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\qa_shot\Entity\QAShotTestInterface;

/**
 * Defines a storage handler class that handles the qa_shot_test grants system.
 *
 * This is used to build qa_shot_test query access.
 *
 * @ingroup qa_shot_test_access
 */
class QAShotGrantDatabaseStorage implements QAShotGrantDatabaseStorageInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a NodeGrantDatabaseStorage object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(Connection $database, ModuleHandlerInterface $module_handler, LanguageManagerInterface $language_manager) {
    $this->database = $database;
    $this->moduleHandler = $module_handler;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function access(QAShotTestInterface $qa_shot_test, $operation, AccountInterface $account) {
    // Grants only support these operations.
    if (!in_array($operation, ['view', 'update', 'delete'])) {
      return AccessResult::neutral();
    }

    // Check the database for potential access grants.
    $query = $this->database->select('qa_shot_test_access');
    $query->addExpression('1');
    // Only interested for granting in the current operation.
    $query->condition('grant_' . $operation, 1, '>=');
    // Check for grants for this qa_shot_test and the correct langcode.
    $ids = $query->andConditionGroup()
      ->condition('id', $qa_shot_test->id())
      ->condition('langcode', $qa_shot_test->language()->getId());
    // If the qa_shot_test is published, also take the default grant into
    // account. The default is saved with a qa_shot_test ID of 0.
    $status = $qa_shot_test->isPublished();
    if ($status) {
      $ids = $query->orConditionGroup()
        ->condition($ids)
        ->condition('id', 0);
    }
    $query->condition($ids);
    $query->range(0, 1);

    $grants = static::buildGrantsQueryCondition(qa_shot_access_grants($operation, $account));

    if (count($grants) > 0) {
      $query->condition($grants);
    }

    // Only the 'view' qa_shot_test grant can currently be cached; the others
    // currently don't have any cacheability metadata. Hopefully, we can add
    // that in the future, which would allow this access check result to be
    // cacheable in all cases. For now, this must remain marked as uncacheable,
    // even when it is theoretically cacheable, because we don't have the
    // necessary metadata to know it for a fact.
    $set_cacheability = function (AccessResult $access_result) use ($operation) {
      $access_result->addCacheContexts(['user.qa_shot_test_grants:' . $operation]);
      if ($operation !== 'view') {
        $access_result->setCacheMaxAge(0);
      }
      return $access_result;
    };

    if ($query->execute()->fetchField()) {
      return $set_cacheability(AccessResult::allowed());
    }
    else {
      return $set_cacheability(AccessResult::neutral());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function checkAll(AccountInterface $account) {
    $query = $this->database->select('qa_shot_test_access');
    $query->addExpression('COUNT(*)');
    $query
      ->condition('id', 0)
      ->condition('grant_view', 1, '>=');

    $grants = static::buildGrantsQueryCondition(qa_shot_access_grants('view', $account));

    if (count($grants) > 0) {
      $query->condition($grants);
    }
    return $query->execute()->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function alterQuery($query, array $tables, $op, AccountInterface $account, $base_table) {
    if (!$langcode = $query->getMetaData('langcode')) {
      $langcode = FALSE;
    }

    // Find all instances of the base table being joined -- could appear
    // more than once in the query, and could be aliased. Join each one to
    // the qa_shot_test_access table.
    $grants = qa_shot_access_grants($op, $account);
    foreach ($tables as $nalias => $tableinfo) {
      $table = $tableinfo['table'];
      if (!($table instanceof SelectInterface) && $table == $base_table) {
        // Set the subquery.
        $subquery = $this->database->select('qa_shot_test_access', 'na')
          ->fields('na', ['id']);

        // If any grant exists for the specified user, then user has access to
        // the qa_shot_test for the specified operation.
        $grant_conditions = static::buildGrantsQueryCondition($grants);

        // Attach conditions to the subquery for qa_shot_tests.
        if (count($grant_conditions->conditions())) {
          $subquery->condition($grant_conditions);
        }
        $subquery->condition('na.grant_' . $op, 1, '>=');

        // Add langcode-based filtering if this is a multilingual site.
        if (\Drupal::languageManager()->isMultilingual()) {
          // If no specific langcode to check for is given, use the grant entry
          // which is set as a fallback.
          // If a specific langcode is given, use the grant entry for it.
          if ($langcode === FALSE) {
            $subquery->condition('na.fallback', 1, '=');
          }
          else {
            $subquery->condition('na.langcode', $langcode, '=');
          }
        }

        $field = 'id';
        // Now handle entities.
        $subquery->where("$nalias.$field = na.id");

        $query->exists($subquery);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function write(QAShotTestInterface $qa_shot_test, array $grants, $realm = NULL, $delete = TRUE) {
    if ($delete) {
      $query = $this->database->delete('qa_shot_test_access')->condition('id', $qa_shot_test->id());
      if ($realm) {
        $query->condition('realm', [$realm, 'all'], 'IN');
      }
      $query->execute();
    }
    // Only perform work when qa_shot_test_access modules are active.
    if (!empty($grants) && count($this->moduleHandler->getImplementations('qa_shot_test_grants'))) {
      $query = $this->database->insert('qa_shot_test_access')->fields([
        'id', 'langcode', 'fallback', 'realm', 'gid', 'grant_view', 'grant_update', 'grant_delete',
      ]);
      // If we have defined a granted langcode, use it. But if not, add a grant
      // for every language this qa_shot_test is translated to.
      foreach ($grants as $grant) {
        if ($realm && $realm != $grant['realm']) {
          continue;
        }
        if (isset($grant['langcode'])) {
          $grant_languages = [$grant['langcode'] => $this->languageManager->getLanguage($grant['langcode'])];
        }
        else {
          $grant_languages = $qa_shot_test->getTranslationLanguages(TRUE);
        }
        foreach ($grant_languages as $grant_langcode => $grant_language) {
          // Only write grants; denies are implicit.
          if ($grant['grant_view'] || $grant['grant_update'] || $grant['grant_delete']) {
            $grant['id'] = $qa_shot_test->id();
            $grant['langcode'] = $grant_langcode;
            // The record with the original langcode is used as the fallback.
            if ($grant['langcode'] == $qa_shot_test->language()->getId()) {
              $grant['fallback'] = 1;
            }
            else {
              $grant['fallback'] = 0;
            }
            $query->values($grant);
          }
        }
      }
      $query->execute();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    $this->database->truncate('qa_shot_test_access')->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function writeDefault() {
    $this->database->insert('qa_shot_test_access')
      ->fields([
        'id' => 0,
        'realm' => 'all',
        'gid' => 0,
        'grant_view' => 0,
        'grant_update' => 0,
        'grant_delete' => 0,
      ])
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function count() {
    return $this->database->query('SELECT COUNT(*) FROM {qa_shot_test_access}')->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteNodeRecords(array $ids) {
    $this->database->delete('qa_shot_test_access')
      ->condition('id', $ids, 'IN')
      ->execute();
  }

  /**
   * Creates a query condition from an array of qa_shot_test access grants.
   *
   * @param array $qa_shot_test_access_grants
   *   An array of grants, as returned by qa_shot_test_access_grants().
   *
   * @return \Drupal\Core\Database\Query\Condition
   *   A condition object to be passed to $query->condition().
   *
   * @see qa_shot_test_access_grants()
   */
  protected static function buildGrantsQueryCondition(array $qa_shot_test_access_grants) {
    $grants = new Condition("OR");
    foreach ($qa_shot_test_access_grants as $realm => $gids) {
      if (!empty($gids)) {
        $and = new Condition('AND');
        $grants->condition($and
          ->condition('gid', $gids, 'IN')
          ->condition('realm', $realm)
        );
      }
    }

    return $grants;
  }

}
