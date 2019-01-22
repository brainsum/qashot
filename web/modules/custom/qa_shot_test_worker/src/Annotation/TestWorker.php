<?php

namespace Drupal\qa_shot_test_worker\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Test Worker annotation.
 *
 * @Annotation
 *
 * @package Drupal\qa_shot_test_worker\Annotation
 */
class TestWorker extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The type of the worker.
   *
   * Possible values: local, remote.
   *
   * @var string
   *
   * @todo: Enum instead of string.
   */
  public $type;

  /**
   * The name of the backend.
   *
   * E.g: BackstopJS.
   *
   * @var string
   */
  public $backend;

  /**
   * The human-readable label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * Short description of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

}
