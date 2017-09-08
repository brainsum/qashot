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

    $schemas['qa_shot_test_access'] = [
      'description' => 'Identifies which realm/grant pairs a user must possess in order to view, update, or delete specific QAShot tests.',
      'fields' => [
        'id' => [
          'description' => 'The {qa_shot_test}.id this record affects.',
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
          'default' => 0,
        ],
        'langcode' => [
          'description' => 'The {language}.langcode of this QAShot test.',
          'type' => 'varchar_ascii',
          'length' => 12,
          'not null' => TRUE,
          'default' => '',
        ],
        'fallback' => [
          'description' => 'Boolean indicating whether this record should be used as a fallback if a language condition is not provided.',
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
          'default' => 1,
          'size' => 'tiny',
        ],
        'gid' => [
          'description' => "The grant ID a user must possess in the specified realm to gain this row's privileges on the QAShot test.",
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
          'default' => 0,
        ],
        'realm' => [
          'description' => 'The realm in which the user must possess the grant ID. Each QAShot test access QAShot test can define one or more realms.',
          'type' => 'varchar_ascii',
          'length' => 255,
          'not null' => TRUE,
          'default' => '',
        ],
        'grant_view' => [
          'description' => 'Boolean indicating whether a user with the realm/grant pair can view this QAShot test.',
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
          'default' => 0,
          'size' => 'tiny',
        ],
        'grant_update' => [
          'description' => 'Boolean indicating whether a user with the realm/grant pair can edit this QAShot test.',
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
          'default' => 0,
          'size' => 'tiny',
        ],
        'grant_delete' => [
          'description' => 'Boolean indicating whether a user with the realm/grant pair can delete this QAShot test.',
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
          'default' => 0,
          'size' => 'tiny',
        ],
      ],
      'primary key' => ['id', 'gid', 'realm', 'langcode'],
      'foreign keys' => [
        'affected_qa_shot_test' => [
          'table' => 'qa_shot_test',
          'columns' => ['id' => 'id'],
        ],
      ],
    ];
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
