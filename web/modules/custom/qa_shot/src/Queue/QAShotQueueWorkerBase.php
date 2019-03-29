<?php

namespace Drupal\qa_shot\Queue;

use Drupal\Component\Plugin\PluginBase;

/**
 * Provides a base implementation for a QAShot QueueWorker plugin.
 *
 * Modified version of Drupal\Core\Queue\QueueWorkerBase.
 *
 * @see \Drupal\Core\Queue\QueueWorkerInterface
 * @see \Drupal\Core\Queue\QueueWorkerManager
 * @see \Drupal\Core\Annotation\QueueWorker
 * @see plugin_api
 */
abstract class QAShotQueueWorkerBase extends PluginBase implements QAShotQueueWorkerInterface {

}
