<?php

namespace Drupal\qa_shot\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class QAShotSettingsForm.
 *
 * @package Drupal\qa_shot\Form
 */
class QAShotSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'qa_shot_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    drupal_set_message($this->t('Not yet implemented.'), 'warning');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      'qa_shot.settings',
    ];
  }

}
