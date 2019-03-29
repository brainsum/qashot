<?php

namespace Drupal\qa_shot\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\qa_shot\Entity\QAShotTestInterface;
use Drupal\views\Plugin\views\field\BulkForm;

/**
 * Defines a user operations bulk form element.
 *
 * @ViewsField("qa_shot_test_bulk_form")
 */
class QAShotTestBulkForm extends BulkForm {

  /**
   * {@inheritdoc}
   *
   * Provide a more useful title to improve the accessibility.
   */
  public function viewsForm(&$form, FormStateInterface $form_state) {
    parent::viewsForm($form, $form_state);
    if (!empty($this->view->result)) {
      foreach ($this->view->result as $row_index => $result) {
        /** @var \Drupal\qa_shot\Entity\QAShotTestInterface $test */
        $test = $result->_entity;
        if ($test instanceof QAShotTestInterface) {
          $form[$this->options['id']][$row_index]['#title'] = $this->t('Update the test %name', ['%name' => $test->getName()]);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function emptySelectedMessage() {
    return $this->t('No tests selected.');
  }

}
