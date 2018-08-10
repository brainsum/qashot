<?php

namespace Drupal\qa_shot\Cli;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\qa_shot\Entity\QAShotTestInterface;
use Drupal\qa_shot\Queue\QAShotQueue;

/**
 * Class CliRemoteQueueRunner.
 *
 * @package Drupal\qa_shot\Cli
 */
class CliRemoteQueueRunner {

  use StringTranslationTrait;

  const QUEUE_NAME = 'qa_shot_remote_queue';
  const ENDPOINT_TEST_ADD = '/api/v1/test/add';

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The current queue instance.
   *
   * @var \Drupal\qa_shot\Queue\QAShotQueue
   */
  protected $queue;

  /**
   * The remote worker.
   *
   * @var \Drupal\backstopjs\Backstopjs\BackstopjsWorkerInterface
   */
  protected $remoteWorker;

  /**
   * Test storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $testStorage;

  /**
   * BackstopJS settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The remote host URL.
   *
   * @var string
   */
  protected $remoteHost;

  /**
   * The HTTP Client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * Logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * CliRemoteQueueRunner constructor.
   */
  public function __construct() {
    $this->messenger = \Drupal::messenger();
    $this->queue = new QAShotQueue(self::QUEUE_NAME, \Drupal::database());
    $this->remoteWorker = \Drupal::service('backstopjs.worker_factory')->get('remote');
    $this->testStorage = \Drupal::entityTypeManager()->getStorage('qa_shot_test');

    $this->httpClient = \Drupal::httpClient();

    $this->config = \Drupal::configFactory()->get('backstopjs.settings');
    $this->remoteHost = $this->config->get('suite.remote_host');

    $this->logger = \Drupal::logger('qa_shot');
  }

  /**
   * Publish available tests to the external queue.
   */
  public function publishAll() {
    $date = (new \DateTime())->setTimestamp(time());
    $this->messenger->addMessage($this->t('Publishing ot external queue initiated at @datetime', [
      '@datetime' => $date->format('Y-m-d H:i:s'),
    ]));


    $items = $this->queue->getItems(self::QUEUE_NAME, QAShotQueue::QUEUE_STATUS_WAITING);
    $itemCount = \count($items);
    $this->messenger->addMessage($this->t('The "@queue" has @count items waiting.', [
      '@queue' => self::QUEUE_NAME,
      '@count' => $itemCount,
    ]));
    if (0 === $itemCount) {
      $this->messenger->addMessage($this->t('Skipping.'));
      return;
    }

    $testIds = \array_map(function ($item) {
      return $item->tid;
    }, $items);

    /** @var \Drupal\qa_shot\Entity\QAShotTestInterface $tests */
    $tests = $this->testStorage->loadMultiple($testIds);

    $successCount = 0;
    $failedCount = 0;
    foreach ($items as $item) {
      $test = $tests[$item->tid];

      $result = $this->publish($test);
      if (TRUE === $result) {
        ++$successCount;
        $item->status = QAShotQueue::QUEUE_STATUS_REMOTE;
      }
      else {
        ++$failedCount;
        $item->status = QAShotQueue::QUEUE_STATUS_ERROR;
      }

      $this->queue->updateItemStatus($item);
    }

    $this->messenger->addMessage('Publishing ended.');
    $this->messenger->addMessage("-- $successCount item(s) published successfully.");
    $this->messenger->addMessage("-- $failedCount item(s) failed to get published.");
  }

  /**
   * Publish an item.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $test
   *   The item.
   *
   * @return bool
   *   TRUE on success, FALSE otherwise.
   */
  protected function publish(QAShotTestInterface $test): bool {
    $result = $this->sendToRemote($test);
    $this->messenger->addMessage('Test @tid: @message.', [
      '@tid' => $test->id(),
      '@message' => $result['message'],
    ]);

    $this->messenger->addMessage('Status code: ' . $result['code']);

    $isErrorResult = ((int) $result['code'] === 204 || (int) $result['code'] >= 400);

    return !$isErrorResult;
  }

  /**
   * Send a test to the remote queue.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $test
   *   The test.
   *
   * @return array
   *   The result/status array.
   *
   * @todo: Clean this up.
   */
  protected function sendToRemote(QAShotTestInterface $test): array {
    // @todo: Remake the architecture, as this is not long-term viable.
    if (NULL === $this->remoteHost) {
      return [
        'code' => 204,
        'message' => $this->t('Remote host undefined, skipping.'),
        'reason' => '',
      ];
    }

    $backstopConfig = \json_decode(\file_get_contents($test->getConfigurationPath()), TRUE);
    // Send chrome only until we finish work on other workers.
    $requestData = [
      'browser' => 'chrome',
      'mode' => 'a_b',
      'stage' => '',
      'test_config' => $backstopConfig,
    ];

    $requestOptions = [
      // @todo: auth
      'json' => $requestData,
      'connect_timeout' => 10,
    ];

    try {
      $response = $this->httpClient->post($this->remoteHost . self::ENDPOINT_TEST_ADD, $requestOptions);
    }
    catch (\Exception $exception) {
      $this->logger->warning('Could not push test (ID: ' . $test->id() . ')  to the remote worker. See: ' . $exception->getMessage());
      return [
        'code' => $exception->getCode(),
        'message' => $exception->getMessage(),
        'reason' => '',
      ];
    }

    $remoteMessage = \json_decode($response->getBody()->getContents(), TRUE);
    $this->logger->info('Test (ID: ' . $test->id() . ') pushed to the remote worker. Message: ' . $remoteMessage['message']);

    return [
      'message' => $response->getBody()->getContents(),
      'reason' => $response->getReasonPhrase(),
      'code' => $response->getStatusCode(),
    ];
  }

  public function consumeAll() {

  }

}
