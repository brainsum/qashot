<?php

/**
 * @file
 * Run remote jobs.
 */

use Drupal\qa_shot\Cli\CliRemoteQueueRunner;

$runner = new CliRemoteQueueRunner();

// First, publish available tests to the remote.
$runner->publishAll();

// Then try to get the existing results.
$runner->consumeAll();
