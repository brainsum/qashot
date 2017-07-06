<?php

namespace Drupal\qa_shot\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Declare a backend to be used by QAShot.
 *
 * Plugin Namespace: Plugin\QAShotTestBackend.
 *
 * @note: Work in progress, not yet used.
 * @todo: Add a factory, refactor backstopjs/src/Service into this.
 * @todo: Force extending the TestBackendBase.
 *
 * @package Drupal\qa_shot\Annotation
 * @Annotation
 */
class QAShotTestBackend extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable title of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $title;

}
