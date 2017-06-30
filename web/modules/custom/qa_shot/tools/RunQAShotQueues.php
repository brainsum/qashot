<?php

/**
 * @file
 * Helper script to run QAShot queues.
 */

/** @var \Drupal\qa_shot\Queue\QAShotQueueRunner $runner */
$runner = \Drupal::service('qa_shot.test_queue_runner');

$count = 0;
foreach ($runner->getQueues() as $queue) {
  $limit = isset($queue['cron']['time']) ? $queue['cron']['time'] : 15;
  $count += $runner->run($queue['id'], $limit);
}

if ($count === 1) {
  drupal_set_message(t('QAShot queues executed, @count item has been processed.', [
    '@count' => $count,
  ]));
}
else {
  drupal_set_message(t('QAShot queues executed, @count items have been processed.', [
    '@count' => $count,
  ]));
}
