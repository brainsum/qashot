<?php

namespace Drupal\qa_shot\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\qa_shot\Service\TestQueueState;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class QAShotSettingsForm.
 *
 * @package Drupal\qa_shot\Form
 */
class QAShotSettingsForm extends ConfigFormBase {

  /**
   * The queue state.
   *
   * @var \Drupal\qa_shot\Service\TestQueueState
   */
  protected $queueState;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('qa_shot.test_queue_state')
    );
  }

  /**
   * QAShotSettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\qa_shot\Service\TestQueueState $queueState
   *   Queue state.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TestQueueState $queueState
  ) {
    parent::__construct($config_factory);

    $this->queueState = $queueState;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'qa_shot_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'qa_shot.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $form['clear_queue'] = [
      '#type' => 'details',
      '#title' => t('Clear queue'),
      '#open' => TRUE,
    ];

    $form['clear_queue']['clear'] = [
      '#type' => 'submit',
      '#value' => t('Clear every queue'),
      '#submit' => ['::submitQueueClear'],
    ];

    return $form;
  }

  /**
   * Clear the queues.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function submitQueueClear(array &$form, FormStateInterface $form_state) {
    $this->queueState->clearQueue();
  }

}
