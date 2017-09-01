<?php

namespace Drupal\backstopjs\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Declare a worker class for executing backstop commands.
 *
 * @Annotation
 */
class BackstopjsWorker extends Plugin {

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

  /**
   * Short description of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

}
