<?php

namespace Drupal\qa_shot\Cli;

use DateTime;
use Drupal;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Messenger\MessengerTrait;

/**
 * Class CliQueueRunner.
 *
 * @package Drupal\qa_shot\Cli
 */
class CliQueueRunner {

  use StringTranslationTrait;
  use MessengerTrait;

  /**
   * Executes a cron run.
   *
   * @throws \Exception
   */
  public function execute(): void {
    $date = (new DateTime())->setTimestamp(time());
    $this->messenger()->addStatus($this->t('Runner script initiated at @datetime', [
      '@datetime' => $date->format('Y-m-d H:i:s'),
    ]));

    /** @var \Drupal\qa_shot\Queue\QAShotQueueRunner $runner */
    $runner = Drupal::service('qa_shot.test_queue_runner');

    $count = 0;
    /** @var [] $queue */
    foreach ($runner->getQueues() as $queue) {
      $queueInstance = $runner->getQueue($queue['id']);
      $itemCount = $queueInstance->numberOfItems();

      if (0 === $itemCount) {
        $this->messenger()->addStatus($this->t('No items in the @queue queue, skipping.', [
          '@queue' => $queue['id'],
        ]));
        continue;
      }

      $this->messenger()->addStatus($this->t('Pre-run: Number of tests currently in the queue: @number', [
        '@number' => $itemCount,
      ]));

      $runningItems = $queueInstance->numberOfRunningItems();
      if (0 < $runningItems) {
        $this->messenger()->addStatus($this->t('The queue @queue has already @count/@limit item(s) running. Skipping.', [
          '@queue' => $queue['id'],
          '@count' => $runningItems,
          '@limit' => 1,
        ]));
        continue;
      }

      $limit = $queue['cron']['time'] ?? 15;
      $count += $runner->run($queue['id'], $limit);

      $this->messenger()->addStatus($this->t('Post-run: Number of tests currently in the queue: @number', [
        '@number' => $queueInstance->numberOfItems(),
      ]));
    }

    if ($count === 1) {
      $this->messenger()->addStatus($this->t('QAShot queues executed, @count item has been processed.', [
        '@count' => $count,
      ]));
    }
    else {
      $this->messenger()->addStatus($this->t('QAShot queues executed, @count items have been processed.', [
        '@count' => $count,
      ]));
    }
    $this->messenger()->addStatus($this->t('---------'));
  }

}
