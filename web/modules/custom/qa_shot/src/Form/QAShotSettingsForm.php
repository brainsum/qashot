<?php

namespace Drupal\qa_shot\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
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
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('qa_shot.test_queue_state'),
      $container->get('database'),
      $container->get('datetime.time')
    );
  }

  /**
   * QAShotSettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   * @param \Drupal\qa_shot\Service\TestQueueState $queueState
   *   Queue state.
   * @param \Drupal\Core\Database\Connection $databaseConnection
   *   Database connection.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Time service.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    TestQueueState $queueState,
    Connection $databaseConnection,
    TimeInterface $time
  ) {
    parent::__construct($configFactory);

    $this->queueState = $queueState;
    $this->database = $databaseConnection;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'qa_shot_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      'qa_shot.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('qa_shot.settings');

    $form['#tree'] = TRUE;
    $form['queue'] = [
      '#type' => 'details',
      '#title' => t('Queue'),
      '#open' => TRUE,
    ];

    // @todo: Use a view instead (#type => view).
    $form['queue']['table'] = [
      '#type' => 'table',
      '#caption' => $this->t('Queue at @time', [
        '@time' => $this->formatTimestamp($this->time->getCurrentTime()),
      ]),
      '#header' => [
        'test_id' => $this->t('Test ID'),
        'status' => $this->t('Status'),
        'date' => $this->t('Date'),
        'stage' => $this->t('Stage'),
        'origin' => $this->t('Origin'),
        'item_id' => $this->t('Item ID'),
      ],
      '#rows' => $this->createQueueTableRows(),
    ];

    $form['queue']['clear_all'] = [
      '#type' => 'submit',
      '#value' => $this->t('Clear every queue'),
      '#submit' => ['::submitQueueClearAll'],
    ];

    $form['backstopjs'] = [
      '#type' => 'details',
      '#title' => t('Backstop JS'),
      '#open' => TRUE,
    ];

    $form['backstopjs']['resemble_output_options'] = [
      '#type' => 'details',
      '#title' => t('Resemble output options'),
      '#open' => TRUE,
    ];

    $form['backstopjs']['resemble_output_options']['error_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Error type'),
      '#options' => [
        'movement' => $this->t('Movement'),
        'flat' => $this->t('Flat'),
      ],
      '#default_value' => $config->get('backstopjs.resemble_output_options.error_type') ?? 'movement',
      '#description' => $this->t('Movement: Merges error color with base image. This is recommended.'),
    ];

    $form['backstopjs']['resemble_output_options']['transparency'] = [
      '#type' => 'number',
      '#title' => $this->t('Transparency'),
      '#description' => $this->t('Fade unchanged areas to make changed areas more apparent.'),
      '#default_value' => $config->get('backstopjs.resemble_output_options.transparency') ?? 0.3,
      '#min' => 0,
      '#max' => 1,
      '#step' => 0.1,
    ];
    $form['backstopjs']['resemble_output_options']['large_image_threshold'] = [
      '#type' => 'number',
      '#title' => $this->t('Large image threshold'),
      '#description' => $this->t('By default, the comparison algorithm skips pixels when the image width or height is larger than 1200 pixels. This is there to mitigate performance issues. Set it to 0 to switch it off completely.'),
      '#default_value' => $config->get('backstopjs.resemble_output_options.large_image_threshold') ?? 1200,
      '#min' => 0,
      '#max' => 5000,
    ];
    $form['backstopjs']['resemble_output_options']['use_cross_origin'] = [
      '#type' => 'select',
      '#title' => $this->t('Use cross origin'),
      '#options' => [
        1 => 'Yes',
        0 => 'No',
      ],
      '#default_value' => $config->get('backstopjs.resemble_output_options.use_cross_origin') ?? 1,
      '#description' => $this->t('Should be "Yes" for QAShot. Visit the @link for more info before using "No".', [
        '@link' => Link::fromTextAndUrl(
          'mozilla developer docs',
          Url::fromUri('https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/Data_URIs')
        )->toString(),
      ]),
      '#disabled' => TRUE,
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
  public function submitQueueClearAll(array &$form, FormStateInterface $form_state) {
    $this->queueState->clearQueue();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('qa_shot.settings');

    $resembleOptions = [
      'error_type',
      'transparency',
      'large_image_threshold',
      'use_cross_origin',
    ];
    foreach ($resembleOptions as $option) {
      $configKey = "backstopjs.resemble_output_options.$option";
      $formStateKey = ['backstopjs', 'resemble_output_options', $option];
      $config->set($configKey, $form_state->getValue($formStateKey));
    }

    $config->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Create rows for the 'Queue' table.
   *
   * @return array
   *   The rows.
   */
  private function createQueueTableRows(): array {
    $stateQueue = $this->queueState->getQueue();
    /** @var \stdClass[] $databaseQueue */
    $databaseQueue = $this->database
      ->select('queue')
      ->fields('queue')
      ->execute()
      ->fetchAll();

    $queueTableRows = [];
    $index = 0;

    // Set an 'Empty message' as the default.
    $queueTableRows[$index]['test_id'] = 'The queue is empty.';
    $queueTableRows[$index]['status'] = '';
    $queueTableRows[$index]['date'] = '';
    $queueTableRows[$index]['stage'] = '';
    $queueTableRows[$index]['origin'] = '';
    $queueTableRows[$index]['item_id'] = '';

    /** @var \stdClass $item */
    // Go through the database queue.
    // If an item is in the database and also in the state,
    // then we update the status and date from the state.
    foreach ($databaseQueue as $item) {
      $data = unserialize($item->data, [\stdClass::class]);

      $queueTableRows[$index]['test_id'] = $data->entityId;
      // Default state should be 'inconsistent'.
      $queueTableRows[$index]['status'] = $this->t('Inconsistent (In database, not in state)');
      $queueTableRows[$index]['date'] = $this->formatTimestamp($item->created);
      $queueTableRows[$index]['stage'] = empty($data->stage) ? '-' : $data->stage;
      $queueTableRows[$index]['origin'] = $data->origin;
      $queueTableRows[$index]['item_id'] = $item->item_id;

      if (isset($stateQueue[$data->entityId])) {
        $queueTableRows[$index]['status'] = $stateQueue[$data->entityId]['status'];
        $queueTableRows[$index]['date'] = $stateQueue[$data->entityId]['date'];
        unset($stateQueue[$data->entityId]);
      }

      ++$index;
    }

    // If there are leftover items in the state,
    // flag them as inconsistent.
    foreach ($stateQueue as $entityId => $data) {
      $queueTableRows[$index]['test_id'] = $entityId;
      $queueTableRows[$index]['status'] = $this->t('@status, but inconsistent (In state, not in database)', [
        '@status' => $data['status'],
      ]);
      $queueTableRows[$index]['date'] = $data['$data'];
      $queueTableRows[$index]['stage'] = '-';
      $queueTableRows[$index]['origin'] = '-';
      $queueTableRows[$index]['item_id'] = '-';

      ++$index;
    }

    return $queueTableRows;
  }

  /**
   * Helper function to convert timestamps to formatted date strings.
   *
   * @param int $timestamp
   *   Timestamp.
   * @param string $format
   *   The format.
   *
   * @throws \InvalidArgumentException
   *
   * @return string
   *   The formatted date.
   */
  private function formatTimestamp($timestamp, $format = 'Y-m-d H:i:s'): string {
    return DrupalDateTime::createFromTimestamp($timestamp)->format($format);
  }

}
