<?php
/**
 * Created by PhpStorm.
 * User: Marton
 * Date: 2017. 07. 14.
 * Time: 12:58
 */

namespace Drupal\qa_shot\Service;


use Drupal\Core\Database\Connection;

class QAShotQueueData {
  /**
   * The database table name.
   */
  const TABLE_NAME = 'qa_shot_queue';

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

  // @todo: Fixme
  function getDataFromQueue($itemId) {
    $query = $this->connection->select(static::TABLE_NAME);
    $query->fields(static::TABLE_NAME);
    $query->condition('tid', $itemId);
    $rows = $query->execute();

    return $rows->fetchObject();
  }
}