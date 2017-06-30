<?php

namespace Drupal\qa_shot\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\qa_shot\Queue\QAShotQueueFactory;
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
   * @var \Drupal\qa_shot\Queue\QAShotQueueFactory
   */
  protected $queue;

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
      $container->get('qa_shot.test_queue_factory'),
      $container->get('database'),
      $container->get('datetime.time')
    );
  }

  /**
   * QAShotSettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   * @param \Drupal\qa_shot\Queue\QAShotQueueFactory $queueFactory
   *   Queue.
   * @param \Drupal\Core\Database\Connection $databaseConnection
   *   Database connection.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Time service.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    QAShotQueueFactory $queueFactory,
    Connection $databaseConnection,
    TimeInterface $time
  ) {
    parent::__construct($configFactory);

    $this->queue = $queueFactory->get('cron_run_qa_shot_test');
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
        'queue_name' => $this->t('Queue name'),
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
   * Clear the queues.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function submitQueueClearAll(array &$form, FormStateInterface $form_state) {
    $this->queue->clearQueue();
  }

  /**
   * Create rows for the 'Queue' table.
   *
   * @return array
   *   The rows.
   */
  private function createQueueTableRows(): array {
    $items = $this->queue->getItems();
    $queueTableRows = [];
    $index = 0;

    // Default 'table empty' message row.
    $queueTableRows[$index]['test_id'] = 'There are no tests in the queue.';
    $queueTableRows[$index]['status'] = '';
    $queueTableRows[$index]['date'] = '';
    $queueTableRows[$index]['stage'] = '';
    $queueTableRows[$index]['origin'] = '';
    $queueTableRows[$index]['queue_name'] = '';

    foreach ($items as $item) {
      $queueTableRows[$index]['test_id'] = $item->tid;
      $queueTableRows[$index]['status'] = $item->status;
      $queueTableRows[$index]['date'] = $item->created;
      $queueTableRows[$index]['stage'] = (NULL === $item->stage) ? '-' : $item->stage;
      $queueTableRows[$index]['origin'] = $item->origin;
      $queueTableRows[$index]['queue_name'] = $item->queue_name;

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
