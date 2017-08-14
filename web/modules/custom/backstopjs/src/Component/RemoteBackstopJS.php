<?php

namespace Drupal\backstopjs\Component;

use Drupal\qa_shot\Entity\QAShotTestInterface;

/**
 * Class RemoteBackstopJS.
 *
 * Implements running BackstopJS from a remote source.
 * Sends HTTP requests.
 *
 * @package Drupal\backstopjs\Component
 */
class RemoteBackstopJS extends BackstopJSBase {

  const COMMAND_CHECK_STATUS = 'pgrep -f backstop -c';
  const COMMAND_GET_STATUS = 'pgrep -l -a -f backstop';

  /**
   * {@inheritdoc}
   */
  public function checkRunStatus() {
    // TODO: Implement checkRunStatus() method.
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus(): string {
    // TODO: Implement getStatus() method.
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function run(string $engine, string $command, QAShotTestInterface $entity): array {
    // @todo: Implement.
    // @todo: Get worker list.
    // @todo: Query worker statuses, get an available one.
    // @todo: Send POST to one of the workers.
    return [];
  }

}
