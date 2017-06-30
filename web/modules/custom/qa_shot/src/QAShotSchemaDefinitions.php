<?php

namespace Drupal\qa_shot;

use Drupal\qa_shot\Queue\QAShotQueue;

/**
 * Class QAShotSchemaDefinitions.
 *
 * Contains schema definitions.
 *
 * @package Drupal\qa_shot
 */
class QAShotSchemaDefinitions {

  /**
   * Returns every defined schema.
   *
   * @return array
   *   The schema definitions.
   */
  public function everySchema(): array {
    $schemas = [];
    $schemas = array_merge($schemas, $this->queueSchema());
    return $schemas;
  }

  /**
   * Returns the 'qa_shot_queue' database table schema.
   *
   * @return array
   *   The schema.
   */
  public function queueSchema(): array {
    $schema = [];
    $schema[QAShotQueue::TABLE_NAME] = [
      'description' => 'Stores the test queue.',
      'fields' => [
        'tid' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
          'description' => 'The test ID.',
        ],
        // @todo: Maybe store these separately,
        // This should likely be a foreign key.
        'queue_name' => [
          'type' => 'text',
          'not null' => TRUE,
          'description' => 'The queue name.',
        ],
        // @todo: Maybe store separately,
        // This should be a foreign key.
        'status' => [
          'type' => 'varchar_ascii',
          'length' => 63,
          'not null' => TRUE,
          'default' => QAShotQueue::QUEUE_STATUS_WAITING,
          'description' => 'The item status.',
        ],
        'stage' => [
          'type' => 'varchar_ascii',
          'length' => 63,
          'not null' => FALSE,
          'default' => NULL,
          'description' => 'The requested test stage.',
        ],
        'origin' => [
          'type' => 'varchar_ascii',
          'length' => 63,
          'not null' => TRUE,
          'default' => 'drupal',
          'description' => 'The origin from where the item was placed into the queue.',
        ],
        // @todo: Remove when finished with prototyping.
        'data' => [
          'type' => 'blob',
          'not null' => FALSE,
          'size' => 'big',
          'serialize' => TRUE,
          'description' => 'Miscellaneous data.',
        ],
        'created' => [
          'type' => 'int',
          'not null' => TRUE,
          'default' => 0,
          'description' => 'Timestamp when the item was created.',
        ],
        'expire' => [
          'type' => 'int',
          'not null' => TRUE,
          'default' => 0,
          'description' => 'Timestamp when the claim lease expires on the item.',
        ],
      ],
      'primary key' => ['tid'],
      'indexes' => [
        'name_created' => ['queue_name', 'created'],
        'expire' => ['expire'],
      ],
      'foreign keys' => [
        'qa_shot_test' => [
          'table' => 'qa_shot_test',
          'columns' => ['tid' => 'id'],
        ],
      ],
    ];
    return $schema;
  }

}
