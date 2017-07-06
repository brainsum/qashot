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
    echo t('Runner script initiated at @datetime', [
      '@datetime' => $date->format('Y-m-d H:i:s'),
    ]) . "\n";

    /** @var \Drupal\qa_shot\Queue\QAShotQueueRunner $runner */
    $runner = \Drupal::service('qa_shot.test_queue_runner');

    $count = 0;
    /** @var [] $queue */
    foreach ($runner->getQueues() as $queue) {
      $queueInstance = $runner->getQueue($queue['id']);
      $itemCount = $queueInstance->numberOfItems();

      if (0 === $itemCount) {
        echo t('No items in the @queue queue, skipping.', [
          '@queue' => $queue['id'],
        ]) . "\n";
        continue;
      }

      echo t('Pre-run: Number of tests currently in the queue: @number', [
        '@number' => $itemCount,
      ]) . "\n";

      $runningItems = $queueInstance->numberOfRunningItems();
      if (0 < $runningItems) {
        echo t('The queue @queue has already @count/@limit item(s) running. Skipping.', [
          '@queue' => $queue['id'],
          '@count' => $runningItems,
          '@limit' => 1,
        ]) . "\n";
        continue;
      }

      $limit = isset($queue['cron']['time']) ? $queue['cron']['time'] : 15;
      ob_start();
      $count += $runner->run($queue['id'], $limit);
      echo ob_get_clean();

      echo t('Post-run: Number of tests currently in the queue: @number', [
        '@number' => $queueInstance->numberOfItems(),
      ]) . "\n";
    }

    if ($count === 1) {
      echo t('QAShot queues executed, @count item has been processed.', [
        '@count' => $count,
      ]) . "\n";
    }
    else {
      echo t('QAShot queues executed, @count items have been processed.', [
        '@count' => $count,
      ]) . "\n";
    }

    echo "\n";
  }

}
