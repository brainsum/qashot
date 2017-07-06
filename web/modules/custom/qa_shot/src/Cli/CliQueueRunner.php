<?php

namespace Drupal\qa_shot\Cli;

/**
 * Class CliQueueRunner.
 *
 * @package Drupal\qa_shot\Cli
 */
class CliQueueRunner {

  /**
   * Executes a cron run.
   */
  public function execute() {
    $date = (new \DateTime())->setTimestamp(time());
    drupal_set_message(t('Runner script initiated at @datetime', [
      '@datetime' => $date->format('Y-m-d H:i:s'),
    ]));

    /** @var \Drupal\qa_shot\Queue\QAShotQueueRunner $runner */
    $runner = \Drupal::service('qa_shot.test_queue_runner');

    $count = 0;
    /** @var [] $queue */
    foreach ($runner->getQueues() as $queue) {
      $queueInstance = $runner->getQueue($queue['id']);
      $itemCount = $queueInstance->numberOfItems();

      if (0 === $itemCount) {
        drupal_set_message(t('No items in the @queue queue, skipping.', [
          '@queue' => $queue['id'],
        ]));
        continue;
      }

      drupal_set_message(t('Pre-run: Number of tests currently in the queue: @number', [
        '@number' => $itemCount,
      ]));

      $runningItems = $queueInstance->numberOfRunningItems();
      if (0 < $runningItems) {
        drupal_set_message(t('The queue @queue has already @count/@limit item(s) running. Skipping.', [
          '@queue' => $queue['id'],
          '@count' => $runningItems,
          '@limit' => 1,
        ]));
        continue;
      }

      $limit = isset($queue['cron']['time']) ? $queue['cron']['time'] : 15;
      $count += $runner->run($queue['id'], $limit);

      drupal_set_message(t('Post-run: Number of tests currently in the queue: @number', [
        '@number' => $queueInstance->numberOfItems(),
      ]));
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

    drupal_set_message("\n");
  }

}
