<?php

namespace Drupal\qa_shot\Service;

use Drupal\Core\Datetime\DrupalDateTime;
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
   * TestQueueState constructor.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   Drupal state service.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger
   *   Logger service.
   */
  public function __construct(StateInterface $state, LoggerChannelFactory $logger) {
    $this->state = $state;
    $this->logger = $logger->get('qa_shot');
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
  private function inQueue($testId) {
    $queueState = $this->state->get($this::STATE_KEY);
    $isInQueue = !empty($queueState) && is_array($queueState) && array_key_exists($testId, $queueState);
    return $isInQueue;
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
  public function getStatus($testId) {
    if ($this->inQueue($testId)) {
      return $this->state->get($this::STATE_KEY)[$testId]['status'];
    }

    return $this::STATUS_IDLE;
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
  private function createState($status) {
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
  public function add($testId) {
    // If the current ID is in the queue, return FALSE.
    if ($this->inQueue($testId)) {
      // @todo: Check if API calls don't spam every user with this.
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
  }

}