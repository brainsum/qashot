<?php

namespace Drupal\qa_shot\Cache;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CalculatedCacheContextInterface;
use Drupal\Core\Cache\Context\UserCacheContextBase;

/**
 * Defines the qa_shot_test access view cache context service.
 *
 * Cache context ID: 'user.qa_shot_test_grants' (to vary by all operations'
 * grants). Calculated cache context ID: 'user.qa_shot_test_grants:%operation',
 * e.g. 'user.qa_shot_test_grants:view' (to vary by the view operation's
 * grants).
 *
 * This allows for qa_shot_test access grants-sensitive caching when listing
 * qa_shot_tests.
 *
 * @see qa_shot_query_qa_shot_test_access_alter()
 * @ingroup qa_shot_test_access
 */
class QAShotAccessGrantsCacheContext extends UserCacheContextBase implements CalculatedCacheContextInterface {

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t("Content access view grants (QAShot)");
  }

  /**
   * {@inheritdoc}
   */
  public function getContext($operation = NULL) {
    // If the current user either can bypass qa_shot_test access then we don't
    // need to determine the exact qa_shot_test grants for the current user.
    if ($this->user->hasPermission('bypass qashot access')) {
      return 'all';
    }

    // When no specific operation is specified, check the grants for all three
    // possible operations.
    if ($operation === NULL) {
      $result = [];
      foreach (['view', 'update', 'delete'] as $op) {
        $result[] = $this->checkNodeGrants($op);
      }
      return implode('-', $result);
    }
    else {
      return $this->checkNodeGrants($operation);
    }
  }

  /**
   * Checks the qa_shot_test grants for the given operation.
   *
   * @param string $operation
   *   The operation to check the qa_shot_test grants for.
   *
   * @return string
   *   The string representation of the cache context.
   */
  protected function checkNodeGrants($operation) {
    // When checking the grants for the 'view' operation and the current user
    // has a global view grant (i.e. a view grant for qa_shot_test ID 0) — note
    // that this is automatically the case if no qa_shot_test access modules
    // exist (no hook_qa_shot_test_grants() implementations) then we don't need
    // to determine the exact qa_shot_test view grants for the current user.
    if ($operation === 'view' && qa_shot_access_view_all_qa_shot_tests($this->user)) {
      return 'view.all';
    }

    $grants = qa_shot_access_grants($operation, $this->user);
    $grants_context_parts = [];
    foreach ($grants as $realm => $gids) {
      $grants_context_parts[] = $realm . ':' . implode(',', $gids);
    }
    return $operation . '.' . implode(';', $grants_context_parts);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($operation = NULL) {
    $cacheable_metadata = new CacheableMetadata();

    if (!\Drupal::moduleHandler()->getImplementations('qa_shot_test_grants')) {
      return $cacheable_metadata;
    }

    // The qa_shot_test grants may change if the user is updated. (The max-age
    // is set to zero below, but sites may override this cache context, and
    // change it to a non-zero value. In such cases, this cache tag is needed
    // for correctness.)
    $cacheable_metadata->setCacheTags(['user:' . $this->user->id()]);

    // If the site is using qa_shot_test grants, this cache context can not be
    // optimized.
    return $cacheable_metadata->setCacheMaxAge(0);
  }

}
