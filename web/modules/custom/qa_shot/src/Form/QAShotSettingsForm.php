<?php

namespace Drupal\qa_shot\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Database;
use \Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
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

    $form['queue'] = [
      '#type' => 'details',
      '#title' => t('Queue'),
      '#open' => TRUE,
    ];

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

    return $form;
  }

  /**
   * Create rows for the 'Queue' table.
   *
   * @return array
   *   The rows.
   */
  private function createQueueTableRows() {
    $stateQueue = $this->queueState->getQueue();
    /** @var \stdClass[] $databaseQueue */
    $databaseQueue = $this->database
      ->select('queue')
      ->fields('queue')
      ->execute()
      ->fetchAll();

    $queueTableRows = [];
    $index = 0;
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
   * @return string
   *   The formatted date.
   */
  private function formatTimestamp($timestamp, $format = 'Y-m-d H:i:s') {
    return DrupalDateTime::createFromTimestamp($timestamp)->format($format);
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

}
