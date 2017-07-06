<?php

/**
 * @file
 * Helper script to run QAShot queues.
 */

use Drupal\qa_shot\Cli\CliQueueRunner;

$runner = new CliQueueRunner();
$runner->execute();
