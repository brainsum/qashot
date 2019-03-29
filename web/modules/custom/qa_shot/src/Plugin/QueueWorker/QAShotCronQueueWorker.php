<?php

namespace Drupal\qa_shot\Plugin\QueueWorker;

/**
 * Class CronTestRunner.
 *
 * @package Drupal\qa_shot\Plugin\QueueWorker
 *
 * @QAShotQueueWorker(
 *   id = "cron_run_qa_shot_test",
 *   title = @Translation("QAShot Cron Test Runner"),
 *   cron = {"time" = 15}
 * )
 */
class QAShotCronQueueWorker extends QAShotQueueWorkerBase {

}
