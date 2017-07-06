<?php

namespace Drupal\qa_shot\Plugin\Action;

use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;

/**
 * Adds a test to the queue.
 *
 * @Action(
 *   id = "qa_shot_queue_test",
 *   label = @Translation("Add the test to the queue"),
 *   type = "qa_shot_test"
 * )
 */
class QueueTest extends ActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($test = NULL) {
    /** @var \Drupal\qa_shot\Entity\QAShotTestInterface $test */
    $test->queue(NULL);
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
