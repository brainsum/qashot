<?php

namespace Drupal\qa_shot\Cli;

use Drupal\backstopjs\Exception\InvalidRunnerOptionsException;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\qa_shot\Entity\QAShotTestInterface;
use Drupal\qa_shot\Queue\QAShotQueue;
use GuzzleHttp\Exception\ClientException;

/**
 * Class CliRemoteQueueRunner.
 *
 * @package Drupal\qa_shot\Cli
 */
class CliRemoteQueueRunner {

  use StringTranslationTrait;

  const QUEUE_NAME = 'qa_shot_remote_queue';
  const ENDPOINT_TEST_ADD = '/api/v1/test/add';
  const ENDPOINT_RESULT_FETCH = '/api/v1/result/fetch';

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
   * Test storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $testStorage;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

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
    $this->testStorage = \Drupal::entityTypeManager()->getStorage('qa_shot_test');

    $this->httpClient = \Drupal::httpClient();

    $this->configFactory = \Drupal::configFactory();
    $this->remoteHost = $this->configFactory->get('backstopjs.settings')->get('suite.remote_host');

    $this->logger = \Drupal::logger('qa_shot');
  }

  /**
   * Publish available tests to the external queue.
   */
  public function publishAll() {
    $date = (new DrupalDateTime())->setTimestamp(\time());
    $this->messenger->addMessage($this->t('Publishing to external queue initiated at @datetime', [
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

    /** @var \Drupal\qa_shot\Entity\QAShotTestInterface[] $tests */
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
    $this->messenger->addMessage($this->t('Test @tid: @code | @message', [
      '@tid' => $test->id(),
      '@code' => $result['code'],
      '@message' => $result['message'],
    ]));

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
    if (NULL === $backstopConfig) {
      $backstopConfig = [];
    }

    // Send chrome only until we finish work on other workers.
    $requestData = [
      'browser' => $test->getBrowser(),
      // @todo: Send the actual mode and stage.
      'mode' => 'a_b',
      'stage' => '',
      'uuid' => $test->uuid(),
      'origin' => $this->configFactory->get('qashot.settings')->get('instance_id'),
      'environment' => $this->configFactory->get('qashot.settings')->get('current_environment'),
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
    catch (ClientException $exception) {
      $this->logger->warning('Client error! Could not push test (ID: ' . $test->id() . ')  to the remote worker. See: ' . $exception->getMessage());
      $response = $exception->getResponse();
      if (NULL === $response) {
        return [
          'code' => $exception->getCode(),
          'message' => $exception->getMessage(),
          'reason' => '',
          'errors' => [],
        ];
      }
      $remoteMessage = \json_decode($response->getBody()->getContents(), TRUE);
      return [
        'code' => $response->getStatusCode(),
        'message' => $remoteMessage['message'],
        'reason' => $response->getReasonPhrase(),
        'errors' => $remoteMessage['errors'] ?? [],
      ];
    }
    catch (\Exception $exception) {
      $this->logger->warning('Unknown error! Could not push test (ID: ' . $test->id() . ')  to the remote worker. See: ' . $exception->getMessage());
      return [
        'code' => $exception->getCode(),
        'message' => $exception->getMessage(),
        'reason' => '',
        'errors' => [],
      ];
    }

    $remoteMessage = \json_decode($response->getBody()->getContents(), TRUE);
    $this->logger->info('Test (ID: ' . $test->id() . ') pushed to the remote worker. Message: ' . $remoteMessage['message']);

    return [
      'code' => $response->getStatusCode(),
      'message' => $remoteMessage['message'],
      'reason' => $response->getReasonPhrase(),
      'errors' => $remoteMessage['errors'] ?? [],
    ];
  }

  /**
   * Try consuming results for an array of uuids.
   *
   * @param array $uuids
   *   Uuids.
   *
   * @return array
   *   Results.
   */
  protected function consume(array $uuids): array {
    if (NULL === $this->remoteHost) {
      return [
        'code' => 204,
        'message' => $this->t('Remote host undefined, skipping.'),
        'reason' => '',
        'results' => [],
      ];
    }

    $payload = [
      'origin' => $this->configFactory->get('qashot.settings')->get('instance_id'),
      'testUuids' => $uuids,
    ];

    $requestOptions = [
      // @todo: auth
      'json' => $payload,
      'connect_timeout' => 10,
    ];

    try {
      $response = $this->httpClient->post($this->remoteHost . self::ENDPOINT_RESULT_FETCH, $requestOptions);
    }
    catch (ClientException $exception) {
      $this->logger->warning('Client error! Could not fetch results from the remote worker. See: ' . $exception->getMessage());
      $response = $exception->getResponse();
      if (NULL === $response) {
        return [
          'code' => $exception->getCode(),
          'message' => $exception->getMessage(),
          'results' => [],
          'reason' => '',
          'errors' => [],
        ];
      }
      $remoteMessage = \json_decode($response->getBody()->getContents(), TRUE);
      return [
        'code' => $response->getStatusCode(),
        'message' => $remoteMessage['message'],
        'reason' => $response->getReasonPhrase(),
        'results' => [],
        'errors' => $remoteMessage['errors'] ?? [],
      ];
    }
    catch (\Exception $exception) {
      $this->logger->warning('Unknown error! Could not fetch results from the remote worker. See: ' . $exception->getMessage());
      return [
        'code' => $exception->getCode(),
        'message' => $exception->getMessage(),
        'results' => [],
        'reason' => '',
        'errors' => [],
      ];
    }

    $results = \json_decode($response->getBody()->getContents(), TRUE);
    $this->logger->info('Results fetched from remote worker.');

    return [
      'code' => $response->getStatusCode(),
      'results' => $results['results'],
      'reason' => $response->getReasonPhrase(),
      'errors' => $remoteMessage['errors'] ?? [],
    ];
  }

  /**
   * Consume messages from the remote queue.
   */
  public function consumeAll() {
    $date = (new \DateTime())->setTimestamp(time());
    $this->messenger->addMessage($this->t('Reading from external queue initiated at @datetime', [
      '@datetime' => $date->format('Y-m-d H:i:s'),
    ]));

    $items = $this->queue->getItems(self::QUEUE_NAME, QAShotQueue::QUEUE_STATUS_REMOTE);
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

    /** @var \Drupal\qa_shot\Entity\QAShotTestInterface[] $tests */
    $tests = $this->testStorage->loadMultiple($testIds);

    /** @var string[] $uuids */
    $uuids = \array_map(function ($test) {
      return $test->uuid();
    }, $tests);

    $results = [];
    $uuidCount = \count($uuids);
    for ($i = 0; $uuidCount > $i; $i += 20) {
      $subset = \array_slice($uuids, $i, 20);

      if (empty($subset)) {
        break;
      }

      $rawResults = $this->consume($subset);
      $results = $rawResults['results'];

      if (!empty($results)) {
        break;
      }
    }

    $remainingTestUuids = $uuids;
    foreach ($results as $resultUuid => $resultData) {
      $uuid = $resultData['data']['metadata']['id'];
      $testUuidIndex = \array_search($uuid, $remainingTestUuids, TRUE);
      if (FALSE !== $testUuidIndex) {
        unset($remainingTestUuids[$testUuidIndex]);
      }

      /** @var \Drupal\qa_shot\Entity\QAShotTestInterface $test */
      $tests = $this->testStorage->loadByProperties(['uuid' => $uuid]);
      $test = \reset($tests);
      // @note This should not happen, btw.
      if (NULL === $test) {
        $this->messenger->addMessage("UUID $uuid has no test.");
        continue;
      }

      \file_put_contents('private://qa_test_data/' . $test->id() . '/results/' . \time() . ".$resultUuid.json", \json_encode($resultData));

      /** @var \stdClass $queueItem */
      $queueItem = $items[$test->id()];

      $this->messenger->addMessage("UUID $uuid has results.");

      try {
        $this->saveResults($test, $resultData['data']);
        $this->queue->deleteItem($queueItem);
        $queueItem = NULL;
      }
      catch (\Exception $e) {
        $this->logger->error($e->getMessage());
        $queueItem->status = QAShotQueue::QUEUE_STATUS_ERROR;
      }

      if (NULL !== $queueItem) {
        $this->queue->updateItemStatus($queueItem);
      }

      // @todo: use the RemoteHtmlReportPath in displays.
    }

    $resultCount = \count($results);
    $remainingCount = \count($remainingTestUuids);
    $this->messenger->addMessage('Fetch ended.');
    $this->messenger->addMessage("-- Requested $uuidCount results.");
    $this->messenger->addMessage("-- Received $resultCount results.");
    $this->messenger->addMessage("-- Remaining $remainingCount results.");
  }

  /**
   * Save results.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $test
   *   The test.
   * @param array $results
   *   The results.
   *
   * @return int
   *   SAVED_NEW or SAVED_UPDATED.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\backstopjs\Exception\InvalidRunnerOptionsException
   */
  protected function saveResults(QAShotTestInterface $test, array $results): int {
    if ('a_b' === $results['metadata']['mode']) {
      $containsResults = TRUE;
    }
    elseif ('before_after' === $results['metadata']['mode']) {
      $containsResults = $results['metadata']['stage'] === 'after';
    }
    else {
      throw new InvalidRunnerOptionsException('The test mode is invalid or the app was not prepared for it.');
    }

    $metadata = [
      'mode' => $results['metadata']['mode'],
      'stage' => $results['metadata']['stage'],
      'queue_name' => 'qa_shot_remote_queue',
      'runner_name' => 'remote',
      'test_suite' => 'remote',
      'tool' => 'backstopjs',
      'browser' => $results['metadata']['browser'],
      'engine' => $results['metadata']['engine'],
      'viewport_count' => (int) $results['metadata']['viewportCount'],
      'scenario_count' => (int) $results['metadata']['scenarioCount'],
      // @todo: Save as timestamp.
      'datetime' => (new DrupalDateTime($results['sentAt']))->format('Y-m-d H:i:s'),
      'duration' => (float) $results['metadata']['duration']['full']['duration'],
      'passed_count' => $results['metadata']['passedCount'],
      'failed_count' => $results['metadata']['failedCount'],
      'pass_rate' => (float) $results['metadata']['passRate'],
      'contains_result' => $containsResults,
      'success' => (bool) $results['metadata']['success'],
    ];

    $result = $this->parseScreenshots($results['results'], $test);

    $this->logger->debug(\var_export([
      'metadata' => $metadata,
      'result' => $result,
    ], TRUE));

    // @todo: Also save these.
    unset(
      $metadata['mode'],
      $metadata['tool'],
      $metadata['browser'],
      $metadata['engine'],
      $metadata['test_suite'],
      $metadata['queue_name'],
      $metadata['runner_name']
    );
    $test->setRemoteHtmlReportPath($results['resultsUrl']);
    $test->setResult($result);
    $test->addMetadata($metadata);
    return $test->save();
  }

  /**
   * Get the result screenshots.
   *
   * @param array $results
   *   The results from remote.
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $test
   *   The test entity.
   *
   * @return array
   *   The screenshots.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function parseScreenshots(array $results, QAShotTestInterface $test): array {
    $scenarioIds = $this->scenarioNameToIdMap($test);
    $viewportIds = $this->viewportNameToIdMap($test);

    $screenshots = [];
    foreach ($results as $result) {
      $scenarioId = $scenarioIds[$result['scenarioLabel']] ?? NULL;
      if (NULL === $scenarioId) {
        throw new \RuntimeException('The "' . $result['scenarioLabel'] . '" scenario does not exist for test ' . $test->id() . '.');
      }
      $viewportId = $viewportIds[$result['viewportLabel']] ?? NULL;
      if (NULL === $viewportId) {
        throw new \RuntimeException('The "' . $result['viewportLabel'] . '" viewport does not exist for test ' . $test->id() . '.');
      }

      $screenshots[] = [
        'scenario_id' => (int) $scenarioId,
        'viewport_id' => (int) $viewportId,
        'reference' => $result['referenceUrl'] ?? '',
        'test' => $result['referenceUrl'] ?? '',
        'diff' => $result['referenceUrl'] ?? '',
        'success' => (bool) $result['success'],
      ];
    }

    return $screenshots;
  }

  /**
   * Create a name/id map for scenarios.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $test
   *   The test.
   *
   * @return array
   *   A "scenario name" => "scenario id" map.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function scenarioNameToIdMap(QAShotTestInterface $test): array {
    $scenarioIds = array_map(function ($item) {
      return $item['target_id'];
    }, $test->getFieldScenario()->getValue(TRUE));

    $paragraphStorage = \Drupal::entityTypeManager()->getStorage('paragraph');
    $scenarios = $paragraphStorage->loadMultiple($scenarioIds);

    $map = [];
    /** @var \Drupal\paragraphs\ParagraphInterface $scenario */
    foreach ($scenarios as $scenario) {
      $map[$scenario->get('field_label')->value] = $scenario->id();
    }

    return $map;
  }

  /**
   * Create a name/id map for viewports.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $test
   *   The test.
   *
   * @return array
   *   A "viewport name" => "viewport id" map.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function viewportNameToIdMap(QAShotTestInterface $test): array {
    $viewportIds = array_map(function ($item) {
      return $item['target_id'];
    }, $test->getFieldViewport()->getValue(TRUE));

    $paragraphStorage = \Drupal::entityTypeManager()->getStorage('paragraph');
    $viewports = $paragraphStorage->loadMultiple($viewportIds);

    $map = [];
    /** @var \Drupal\paragraphs\ParagraphInterface $viewport */
    foreach ($viewports as $viewport) {
      $map[$viewport->get('field_name')->value] = $viewport->id();
    }

    return $map;
  }

}
