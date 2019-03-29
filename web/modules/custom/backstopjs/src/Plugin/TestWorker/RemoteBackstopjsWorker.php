<?php

namespace Drupal\backstopjs\Plugin\TestWorker;

use Drupal\backstopjs\Backstopjs\BackstopjsWorkerBase;
use Drupal\qa_shot\Entity\QAShotTestInterface;

/**
 * Class LocalBackstopJS.
 *
 * Implements running BackstopJS from a local binary.
 *
 * @todo: Move 'Local' related functions here from
 *   web/modules/custom/backstopjs/src/Form/BackstopjsSettingsForm.php
 * @todo: Refactor exec-s and etc to use the new functions
 * @todo: @fixme: @important: This is currently a dummy implementation.
 *
 * @package Drupal\backstopjs\Plugin\BackstopjsWorker
 *
 * @TestWorker(
 *   id = "backstopjs.remote",
 *   backend = "backstopjs",
 *   type = "local",
 *   label = @Translation("Remote worker"),
 *   description = @Translation("Worker for remote execution")
 * )
 */
class RemoteBackstopjsWorker extends BackstopjsWorkerBase {

  public const COMMAND_CHECK_STATUS = 'pgrep -f backstop -c';

  public const COMMAND_GET_STATUS = 'pgrep -l -a -f backstop';

  /**
   * {@inheritdoc}
   */
  public function status() {
    return 'remote';
  }

  /**
   * {@inheritdoc}
   */
  public function checkRunStatus() {
  }

  /**
   * {@inheritdoc}
   */
  public function run(string $browser, string $command, QAShotTestInterface $entity): array {
    return [];
  }

}
