<?php

namespace Drupal\qa_shot\Queue;

use Drupal\Core\Database\Connection;

/**
 * Defines the queue factory.
 */
class QAShotQueueFactory {

  /**
   * Instantiated queues, keyed by name.
   *
   * @var array
   */
  protected $queues = [];

  /**
   * The settings object.
   *
   * @var \Drupal\Core\Site\Settings
   */
  protected $database;

  /**
   * QAShotQueueFactory constructor.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   Database connection.
   */
  public function __construct(Connection $connection) {
    $this->database = $connection;
  }

  /**
   * Constructs a new reliable QAShot queue.
   *
   * @param string $name
   *   The name of the queue to work with.
   *
   * @return \Drupal\qa_shot\Queue\QAShotQueueInterface
   *   A queue implementation for the given name.
   */
  public function get($name): QAShotQueueInterface {
    if (!isset($this->queues[$name])) {
      $this->queues[$name] = new QAShotQueue($name, $this->database);
    }
    return $this->queues[$name];
  }

}
