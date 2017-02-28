<?php

namespace Drupal\qa_shot\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class QAShotTestTypeForm.
 *
 * @package Drupal\qa_shot\Form
 */
class QAShotTestTypeForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $qa_shot_test_type = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $qa_shot_test_type->label(),
      '#description' => $this->t("Label for the QAShot Test type."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $qa_shot_test_type->id(),
      '#machine_name' => [
        'exists' => '\Drupal\qa_shot\Entity\QAShotTestType::load',
      ],
      '#disabled' => !$qa_shot_test_type->isNew(),
    ];

    /* You will need additional form elements for your custom properties. */

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $qa_shot_test_type = $this->entity;
    $status = $qa_shot_test_type->save();

    switch ($status) {
      case SAVED_NEW:
        drupal_set_message($this->t('Created the %label QAShot Test type.', [
          '%label' => $qa_shot_test_type->label(),
        ]));
        break;

      default:
        drupal_set_message($this->t('Saved the %label QAShot Test type.', [
          '%label' => $qa_shot_test_type->label(),
        ]));
    }
    $form_state->setRedirectUrl($qa_shot_test_type->toUrl('collection'));
  }

}
