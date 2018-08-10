<?php

namespace Drupal\qa_shot\Queue;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use PDO;

/**
 * Default queue implementation.
 *
 * Modified version of Drupal\Core\Queue\DatabaseQueue.
 *
 * @see https://www.sitepoint.com/drupal-8-queue-api-powerful-manual-and-cron-queueing/
 * @see https://spinningcode.org/2017/01/drupal-8-plugins-are-addictive/
 *
 * @ingroup queue
 */
class QAShotQueue implements QAShotQueueInterface {

  use DependencySerializationTrait;

  /**
   * The database table name.
   */
  const TABLE_NAME = 'qa_shot_queue';

  const QUEUE_STATUS_IDLE = 'idle';
  const QUEUE_STATUS_WAITING = 'waiting';
  const QUEUE_STATUS_REMOTE = 'remote';
  const QUEUE_STATUS_RUNNING = 'running';
  const QUEUE_STATUS_ERROR = 'error';

  /**
   * The name of the queue this instance is working with.
   *
   * @var string
   */
  protected $name;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * QAShotQueue constructor.
   *
   * @param string $name
   *   The name of the queue.
   * @param \Drupal\Core\Database\Connection $connection
   *   The Connection object containing the key-value tables.
   */
  public function __construct($name, Connection $connection) {
    // @todo: Inject the time, logger service.
    $this->name = $name;
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   * @throws \InvalidArgumentException
   */
  public function createItem(\stdClass $item) {
    try {
      $query = $this->connection->insert(self::TABLE_NAME)
        ->fields([
          'tid' => $item->tid,
          'queue_name' => $this->name,
          'stage' => $item->stage,
          'origin' => $item->origin,
          'data' => serialize($item->data),
          // We cannot rely on REQUEST_TIME because many items might be created
          // by a single request which takes longer than 1 second.
          'created' => time(),
        ]);
      // Return the new serial ID, or FALSE on failure.
      $itemId = $query->execute();
    }
    catch (\Exception $e) {
      // @todo: log
      return FALSE;
    }

    // @todo: dep.inj.
    \Drupal::logger('qa_shot_queue')->info(
      t('Test with ID #@testID status changed to @status.', [
        '@testID' => $item->tid,
        '@status' => self::QUEUE_STATUS_WAITING,
      ])
    );

    return $itemId;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   * @throws \InvalidArgumentException
   */
  public function deleteItem(\stdClass $item) {
    try {
      $this->connection->delete(static::TABLE_NAME)
        ->condition('tid', $item->tid)
        ->execute();
    }
    catch (\Exception $e) {
      // @todo log
      throw $e;
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   * @throws \InvalidArgumentException
   */
  public function releaseItem(\stdClass $item): bool {
    try {
      $update = $this->connection->update(static::TABLE_NAME)
        ->fields([
          'expire' => 0,
          'status' => $item->status,
        ])
        ->condition('tid', $item->tid);
      return $update->execute();
    }
    catch (\Exception $e) {
      // @todo log
      throw $e;
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function numberOfItems($status = NULL): int {
    try {
      $query = $this->connection->select(static::TABLE_NAME);
      $query->condition('queue_name', $this->name);
      if (NULL !== $status) {
        $query->condition('status', $status);
      }
      $result = $query->countQuery()->execute();

      $count = $result->fetchField();

      if (FALSE === $count) {
        throw new \Exception('Could not count items in queue.');
      }

      return (int) $count;
    }
    catch (\Exception $e) {
      // @todo log
      throw $e;
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function claimItem($leaseTime = 30) {
    // @todo: Items get in one by one.
    // First is the first.
    // From first to last, try to get an item that's waiting.
    // Claim the first waiting item.
    // Claim an item by updating its expire fields. If claim is not successful
    // another thread may have claimed the item in the meantime. Therefore loop
    // until an item is successfully claimed or we are reasonably sure there
    // are no unclaimed items left.
    while (TRUE) {
      try {
        $query = $this->connection->select(static::TABLE_NAME);
        $query->fields(static::TABLE_NAME);
        $query->condition('expire', 0);
        $query->condition('queue_name', $this->name);
        $query->orderBy('created');
        $query->orderBy('tid');
        $query->range(0, 1);
        $result = $query->execute();
        $item = $result->fetchObject();
      }
      catch (\Exception $e) {
        // @todo log
        throw $e;
        // If the table does not exist there are no items currently available to
        // claim.
      }
      if ($item) {
        // Try to update the item. Only one thread can succeed in UPDATing the
        // same row. We cannot rely on REQUEST_TIME because items might be
        // claimed by a single consumer which runs longer than 1 second. If we
        // continue to use REQUEST_TIME instead of the current time(), we steal
        // time from the lease, and will tend to reset items before the lease
        // should really expire.
        $expire = time() + $leaseTime;
        $update = $this->connection->update(static::TABLE_NAME)
          ->fields([
            'expire' => $expire,
          ])
          ->condition('tid', $item->tid)
          ->condition('expire', 0);
        // If there are affected rows, this update succeeded.
        if ($update->execute()) {
          $item->expire = $expire;
          $item->data = unserialize($item->data, [\stdClass::class]);
          return $item;
        }
      }
      else {
        // No items currently available to claim.
        return FALSE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function createQueue() {
    // All tasks are stored in a single database table (which is created on
    // demand) so there is nothing we need to do to create a new queue.
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function deleteQueue() {
    try {
      $this->connection->delete(static::TABLE_NAME)
        ->condition('queue_name', $this->name)
        ->execute();
    }
    catch (\Exception $e) {
      // @todo log
      throw $e;
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function garbageCollection() {
    $requestTime = \Drupal::time()->getRequestTime();
    try {
      $timeLimit = 864000;
      // Clean up the queue for failed batches.
      $this->connection->delete(static::TABLE_NAME)
        ->condition('created', $requestTime - $timeLimit, '<')
        ->condition('queue_name', 'drupal_batch:%', 'LIKE')
        ->execute();

      // Reset expired items in the default queue implementation table.
      // If that's not used, this will simply be a no-op.
      $this->connection->update(static::TABLE_NAME)
        ->fields([
          'expire' => 0,
        ])
        ->condition('expire', 0, '<>')
        ->condition('expire', $requestTime, '<')
        ->execute();
    }
    catch (\Exception $e) {
      // @todo: Log
      throw $e;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function numberOfRunningItems(): int {
    return $this->numberOfItems(static::QUEUE_STATUS_RUNNING);
  }

  /**
   * {@inheritdoc}
   */
  public function clearQueue() {
    $this->connection->truncate(static::TABLE_NAME)->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getItem($itemId) {
    $query = $this->connection->select(static::TABLE_NAME);
    $query->fields(static::TABLE_NAME);
    $query->condition('tid', $itemId);
    $rows = $query->execute();

    return $rows->fetchObject();
  }

  /**
   * {@inheritdoc}
   */
  public function getItems($name = NULL, $status = NULL): array {
    $query = $this->connection->select(static::TABLE_NAME);
    $query->fields(static::TABLE_NAME);
    if (NULL !== $name) {
      $query->condition('queue_name', $name);
    }
    if (NULL !== $status) {
      $query->condition('status', $status);
    }
    $rows = $query->execute();
    return $rows->fetchAll(PDO::FETCH_CLASS);
  }

  /**
   * {@inheritdoc}
   */
  public function updateItemStatus(\stdClass $item) {
    $query = $this->connection->update(static::TABLE_NAME);
    $query->fields(['status' => $item->status]);
    $query->condition('tid', $item->tid);
    $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getItemStatus($itemId): string {
    $query = $this->connection->select(static::TABLE_NAME);
    $query->fields(static::TABLE_NAME, ['status']);
    $query->condition('tid', $itemId);
    $rows = $query->execute();

    return $rows->fetchObject()->status;
  }

}
