<?php

namespace Drupal\qa_shot\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Filter by qa_shot_test_access records.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("qa_shot_test_access")
 */
class Access extends FilterPluginBase {

  public function adminSummary() { }
  protected function operatorForm(&$form, FormStateInterface $form_state) { }
  public function canExpose() {
    return FALSE;
  }

  /**
   * See _qa_shot_access_where_sql() for a non-views query based implementation.
   */
  public function query() {
    $account = $this->view->getUser();
    kint("hf");
    if (!$account->hasPermission('bypass qashot access')) {
      $table = $this->ensureMyTable();
      $grants = db_or();
      foreach (qa_shot_access_grants('view', $account) as $realm => $gids) {
        foreach ($gids as $gid) {
          $grants->condition(db_and()
            ->condition($table . '.gid', $gid)
            ->condition($table . '.realm', $realm)
          );
        }
      }

      $this->query->addWhere('AND', $grants);
      $this->query->addWhere('AND', $table . '.grant_view', 1, '>=');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $contexts = parent::getCacheContexts();

    $contexts[] = 'user.qa_shot_test_grants:view';

    return $contexts;
  }

}
