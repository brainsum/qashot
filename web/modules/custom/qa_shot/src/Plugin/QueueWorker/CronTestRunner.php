<?php

namespace Drupal\qa_shot\Plugin\QueueWorker;

use Drupal\Core\Annotation\QueueWorker;
use Drupal\Core\Annotation\Translation;

/**
 * Class CronTestRunner.
 *
 * @package Drupal\qa_shot\Plugin\QueueWorker
 *
 * @QueueWorker(
 *   id = "cron_run_qa_shot_test",
 *   title = @Translation("QAShot Cron Test Runner"),
 *   cron = {"time" = 10}
 * )
 */
class CronTestRunner extends TestRunnerBase {
}
