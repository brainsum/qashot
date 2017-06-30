<?php

namespace Drupal\qa_shot\Queue;

use Drupal\Core\Queue\QueueGarbageCollectionInterface;
use Drupal\Core\Queue\ReliableQueueInterface;

/**
 * Interface QAShotQueueInterface.
 *
 * @package Drupal\qa_shot\Queue
 */
interface QAShotQueueInterface extends ReliableQueueInterface, QueueGarbageCollectionInterface {

  /**
   * Clears the full queue.
   */
  public function clearQueue();

  /**
   * Get the count of currently running tests.
   *
   * @return int
   *   The count of running tests.
   *
   * @throws \Exception
   */
  public function numberOfRunningItems(): int;

  /**
   * Gets an item by ID.
   *
   * @param int|string $itemId
   *   The ID of the test.
   *
   * @return mixed
   *   The item.
   *
   * @throws \Drupal\Core\Database\InvalidQueryException
   */
  public function getItem($itemId);

  /**
   * Gets an item status by ID.
   *
   * @param int|string $itemId
   *   The ID of the test.
   *
   * @return string
   *   The item status.
   *
   * @throws \Drupal\Core\Database\InvalidQueryException
   */
  public function getItemStatus($itemId): string;

  /**
   * Returns items for a single or every queue.
   *
   * @param string $name
   *   Queue name.
   *
   * @return array
   *   The items.
   *
   * @throws \Drupal\Core\Database\InvalidQueryException
   */
  public function getItems($name = NULL): array;

  /**
   * Update the status for an item.
   *
   * @param \stdClass $item
   *   The item.
   */
  public function updateItemStatus(\stdClass $item);

}
