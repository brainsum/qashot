<?php

namespace Drupal\qa_shot\Plugin\Action;

use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\qa_shot\Service\QueueManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds a test to the queue.
 *
 * @Action(
 *   id = "qa_shot_queue_test",
 *   label = @Translation("Add the test to the queue"),
 *   type = "qa_shot_test"
 * )
 */
class QueueTest extends ActionBase implements ContainerFactoryPluginInterface {

  protected $queueManager;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('qa_shot.queue_manager')
    );
  }

  /**
   * Constructs a Drupal\Component\Plugin\PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\qa_shot\Service\QueueManager $queueManager
   *   The queue manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    QueueManager $queueManager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->queueManager = $queueManager;
  }

  /**
   * {@inheritdoc}
   */
  public function execute($test = NULL) {
    /** @var \Drupal\qa_shot\Entity\QAShotTestInterface $test */
    $this->queueManager->addTest($test);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\qa_shot\Entity\QAShotTestInterface $object */
    if ('a_b' !== $object->bundle()) {
      return (TRUE === $return_as_object) ? new AccessResultForbidden() : FALSE;
    }

    return (TRUE === $return_as_object) ? new AccessResultAllowed() : TRUE;
    // @todo: FIXME, add actual access check.
  }

}
