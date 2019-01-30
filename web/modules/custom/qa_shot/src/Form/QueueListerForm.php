<?php

namespace Drupal\qa_shot\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\qa_shot\Queue\QAShotQueueFactory;
use Drupal\qa_shot\Service\DataFormatter;
use Drupal\qa_shot\TestBackendInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class QueueListerForm.
 *
 * @package Drupal\qa_shot\Form
 *
 * @todo: Maybe add as a controller instead of a form?
 */
class QueueListerForm extends FormBase {

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
   * The data formatter.
   *
   * @var \Drupal\qa_shot\Service\DataFormatter
   */
  protected $dataFormatter;

  /**
   * The BackstopJS service.
   *
   * @var \Drupal\qa_shot\TestBackendInterface
   */
  protected $backstop;

  /**
   * {@inheritdoc}
   *
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('qa_shot.test_queue_factory'),
      $container->get('database'),
      $container->get('datetime.time'),
      $container->get('qa_shot.data_formatter'),
      $container->get('backstopjs.backstop')
    );
  }

  /**
   * QAShotSettingsForm constructor.
   *
   * @param \Drupal\qa_shot\Queue\QAShotQueueFactory $queueFactory
   *   Queue.
   * @param \Drupal\Core\Database\Connection $databaseConnection
   *   Database connection.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Time service.
   * @param \Drupal\qa_shot\Service\DataFormatter $dataFormatter
   *   Data formatter service.
   * @param \Drupal\qa_shot\TestBackendInterface $backstop
   *   BackstopJS service.
   */
  public function __construct(
    QAShotQueueFactory $queueFactory,
    Connection $databaseConnection,
    TimeInterface $time,
    DataFormatter $dataFormatter,
    TestBackendInterface $backstop
  ) {
    $this->queue = $queueFactory->get('cron_run_qa_shot_test');
    $this->database = $databaseConnection;
    $this->time = $time;
    $this->dataFormatter = $dataFormatter;
    $this->backstop = $backstop;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'qa_shot_queue_list';
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Database\InvalidQueryException
   * @throws \InvalidArgumentException
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#tree'] = TRUE;
    $form['queue'] = [
      '#type' => 'details',
      '#title' => $this->t('Queue'),
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
        'add_date' => $this->t('Add date'),
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

    $prettyPrintStatus = \json_encode(\json_decode($this->backstop->getStatus(), TRUE), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $form['backstopjs_status'] = [
      '#type' => 'markup',
      '#markup' => "Results for using pgrep to search for BackstopJS: <pre>{$prettyPrintStatus}</pre>",
      '#title' => $this->t('BackstopJS status'),
    ];

    return $form;
  }

  /**
   * Clear the queues.
   */
  public function submitQueueClearAll() {
    $this->queue->clearQueue();
  }

  /**
   * Create rows for the 'Queue' table.
   *
   * @return array
   *   The rows.
   *
   * @throws \Drupal\Core\Database\InvalidQueryException
   */
  private function createQueueTableRows(): array {
    $items = $this->queue->getItems();
    $queueTableRows = [];
    $index = 0;

    // Default 'table empty' message row.
    $queueTableRows[$index]['test_id'] = 'There are no tests in the queue.';
    $queueTableRows[$index]['status'] = '';
    $queueTableRows[$index]['add_date'] = '';
    $queueTableRows[$index]['stage'] = '';
    $queueTableRows[$index]['origin'] = '';
    $queueTableRows[$index]['queue_name'] = '';

    foreach ($items as $item) {
      $queueTableRows[$index]['test_id'] = $item->tid;
      $queueTableRows[$index]['status'] = $item->status;
      $queueTableRows[$index]['add_date'] = $this->dataFormatter->timestampAsAgo($item->created);
      $queueTableRows[$index]['stage'] = $item->stage ?? '-';
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

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

}
