<?php

namespace Drupal\qa_shot\Service;

use Drupal\Core\Database\Connection;

/**
 * Class QAShotQueueData.
 *
 * @package Drupal\qa_shot\Service
 */
class QAShotQueueData {

  /**
   * The database table name.
   */
  public const TABLE_NAME = 'qa_shot_queue';

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * QAShotQueue constructor.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The Connection object containing the key-value tables.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * Gets an item from the qa_shot_queue.
   *
   * @param int $itemId
   *   The id of the item.
   *
   * @return bool|\stdClass
   *   The item as an object.
   *
   * @throws \Drupal\Core\Database\InvalidQueryException
   */
  public function getDataFromQueue($itemId) {
    $query = $this->connection->select(static::TABLE_NAME);
    $query->fields(static::TABLE_NAME);
    $query->condition('tid', $itemId);
    $rows = $query->execute();

    return $rows->fetchObject();
  }

}
