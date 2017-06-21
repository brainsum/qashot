<?php

namespace Drupal\qa_shot\Service;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Class TestQueueState.
 *
 * @package Drupal\qa_shot\Service
 */
class TestQueueState {

  use StringTranslationTrait;

  const STATE_KEY = 'qa_shot_queue';
  const STATUS_RUNNING = 'running';
  const STATUS_IDLE = 'idle';
  const STATUS_QUEUED = 'queued';
  const STATUS_ERROR = 'error';

  /**
   * Drupal state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  private $state;

  /**
   * Logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  private $logger;

  /**
   * Test storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private $testStorage;

  /**
   * TestQueueState constructor.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   Drupal state service.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger
   *   Logger service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   */
  public function __construct(StateInterface $state, LoggerChannelFactory $logger, EntityTypeManagerInterface $entityTypeManager) {
    $this->state = $state;
    $this->logger = $logger->get('qa_shot');
    $this->testStorage = $entityTypeManager->getStorage('qa_shot_test');
  }

  /**
   * Update an entity with the status.
   *
   * @param int|string $entityId
   *   Entity id.
   * @param string $status
   *   Status.
   */
  private function updateEntity($entityId, $status) {
    /** @var \Drupal\qa_shot\Entity\QAShotTestInterface $test */
    $test = $this->testStorage->load($entityId);
    $test->setQueueStatus($status)->save();
  }

  /**
   * Check if an ID is in the queue or not.
   *
   * @param string|int $testId
   *   The ID of the test.
   *
   * @return bool
   *   TRUE if the given ID is in the queue, FALSE otherwise.
   */
  private function inQueue($testId): bool {
    $queueState = $this->state->get($this::STATE_KEY);
    $isInQueue = !empty($queueState) && is_array($queueState) && array_key_exists($testId, $queueState);
    return $isInQueue;
  }

  /**
   * Checks whether the queue has a running item.
   *
   * @return bool
   *   The status.
   */
  public function hasRunningItem(): bool {
    /** @var array $queueState */
    $queueState = $this->state->get($this::STATE_KEY);
    foreach ($queueState as $item) {
      if ($item['status'] === 'running') {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Get the status of the entity.
   *
   * @param string|int $testId
   *   The ID of the test.
   *
   * @return string
   *   The status: 'idle', 'queued', 'running', 'error'.
   */
  public function getStatus($testId): string {
    if ($this->inQueue($testId)) {
      return $this->state->get($this::STATE_KEY)[$testId]['status'];
    }

    return $this::STATUS_IDLE;
  }

  /**
   * Return the full queue from the state.
   *
   * @return array
   *   The queue state.
   */
  public function getQueue(): array {
    return $this->state->get($this::STATE_KEY);
  }

  /**
   * Set the status to 'running'.
   *
   * @param string|int $testId
   *   The ID of the test.
   */
  public function setToRunning($testId) {
    $currentState = $this->state->get($this::STATE_KEY);
    $currentState[$testId] = $this->createState($this::STATUS_RUNNING);
    $this->state->set($this::STATE_KEY, $currentState);

    $this->logger->info(
      $this->t('Test with ID #@testID status changed to running.', ['@testID' => $testId])
    );

    $this->updateEntity($testId, $this::STATUS_RUNNING);
  }

  /**
   * Set the status to 'error'.
   *
   * @param string|int $testId
   *   The ID of the test to be queued.
   */
  public function setToError($testId) {
    $currentState = $this->state->get($this::STATE_KEY);
    $currentState[$testId] = $this->createState($this::STATUS_ERROR);
    $this->state->set($this::STATE_KEY, $currentState);

    $this->logger->error(
      $this->t('Test with ID #@testID status changed to error.', ['@testID' => $testId])
    );

    $this->updateEntity($testId, $this::STATUS_ERROR);
  }

  /**
   * Set the status to 'queued'.
   *
   * @param string|int $testId
   *   The ID of the test to be queued.
   */
  public function setToQueued($testId) {
    $currentState = $this->state->get($this::STATE_KEY);
    $currentState[$testId] = $this->createState($this::STATUS_QUEUED);

    $this->state->set($this::STATE_KEY, $currentState);

    $this->logger->info(
      $this->t('Test with ID #@testID status changed to queued.', ['@testID' => $testId])
    );

    $this->updateEntity($testId, $this::STATUS_QUEUED);
  }

  /**
   * Helper function to create a state.
   *
   * @param string|int $status
   *   The status.
   *
   * @return array
   *   The state.
   */
  private function createState($status): array {
    $now = new DrupalDateTime();
    return [
      'status' => $status,
      'date' => $now->format('Y-m-d H:i:s'),
    ];
  }

  /**
   * Add an entity to the queue.
   *
   * @param string|int $testId
   *   The ID of the test to be queued.
   *
   * @return bool
   *   TRUE if the entity could be added to the queue.
   */
  public function add($testId): bool {
    // If the current ID is in the queue, return FALSE.
    if ($this->inQueue($testId) && $this->getStatus($testId) !== $this::STATUS_ERROR) {
      drupal_set_message('The test is already queued.', 'warning');
      return FALSE;
    }
    $this->setToQueued($testId);
    return TRUE;
  }

  /**
   * Remove an entity from the queue.
   *
   * @param string|int $testId
   *   The ID of the test to be queued.
   */
  public function remove($testId) {
    $queueState = $this->state->get($this::STATE_KEY);

    // If the current ID is in the queue, return FALSE.
    if (!empty($queueState) && is_array($queueState) && array_key_exists($testId, $queueState)) {
      unset($queueState[$testId]);
      $this->state->set($this::STATE_KEY, $queueState);
    }

    $this->logger->info(
      $this->t('Test with ID #@testID removed from the testing queue.', ['@testID' => $testId])
    );

    $this->updateEntity($testId, $this::STATUS_IDLE);
  }

  /**
   * Clear both the state and the DB table.
   */
  public function clearQueue() {
    // @todo: This should be something like this:
    //   lock queue
    //   get queue
    //   foreach (queue) { remove item from queue, remove item from db }
    //   if db not empty, log error and truncate.
    $this->state->set($this::STATE_KEY, []);

    \Drupal::database()->truncate('queue')->execute();
    $tests = \Drupal::entityTypeManager()->getStorage('qa_shot_test')->loadMultiple();

    /** @var \Drupal\qa_shot\Entity\QAShotTestInterface $test */
    foreach ($tests as $test) {
      if ($test->getQueueStatus()[0]['value'] !== $this::STATUS_IDLE) {
        $test->setQueueStatus($this::STATUS_IDLE);
        $test->save();
      }
    }

    /*
     $tests = \Drupal::entityTypeManager()->getStorage('qa_shot_test')->loadMultiple();
foreach($tests as $test) {
   if (NULL === $test->getTestEngine()) {
      $test->set('field_tester_engine', 'phantomjs');
   }

   if (NULL === $test->getQueueStatus()) {
      $test->set('field_state_in_queue', 'idle');
   }


$test->save();
}
     */

    /* Code to get entity IDs from the queue table.
         $queue = \Drupal::database()->select('queue')->fields('queue')->execute()->fetchAll();
         kint($queue);
     */
  }

}
