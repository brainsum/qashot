<?php

namespace Drupal\qa_shot\Annotation;

use Drupal\Core\Annotation\QueueWorker;

/**
 * Extension of Drupal\Core\Annotation\QueueWorker.
 *
 * Required, so core cron does not try to run the QAShot queues.
 *
 * @Annotation
 */
class QAShotQueueWorker extends QueueWorker {
}
