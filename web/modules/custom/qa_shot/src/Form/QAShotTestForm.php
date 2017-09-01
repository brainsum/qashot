<?php

namespace Drupal\qa_shot\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for QAShot Test edit forms.
 *
 * @ingroup qa_shot
 */
class QAShotTestForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /* @var $entity \Drupal\qa_shot\Entity\QAShotTest */
    $form = parent::buildForm($form, $form_state);

    // If we need to use the entity:
    // $entity = $this->entity;
    // But it's not needed yet.
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = &$this->entity;

    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        drupal_set_message($this->t('Created the %label QAShot Test.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        drupal_set_message($this->t('Saved the %label QAShot Test.', [
          '%label' => $entity->label(),
        ]));
    }
    $form_state->setRedirect('entity.qa_shot_test.canonical', ['qa_shot_test' => $entity->id()]);
  }

}
