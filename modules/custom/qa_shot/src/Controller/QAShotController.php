<?php
/**
 * Created by PhpStorm.
 * User: mhavelant
 * Date: 2016.10.05.
 * Time: 14:15
 */

namespace Drupal\qa_shot\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\qa_shot\Entity\QAShotTest;

class QAShotController extends ControllerBase {
  public function entityRunPage(RouteMatchInterface $route_match) {
    $entityId = $route_match->getParameters()->get("qa_shot_test");

    if (empty($entityId)) {
      return ['#markup' => "Invalid entity."];
    }

    // @todo: if we come here via the edit form "Run Test" button,
    // automatically start the test

    // if just opening, show list of previous results:
    //    Time, Who started it, pass/fail, html report link

    $entity = QAShotTest::load($entityId);

    $output = [];
    $output['#theme'] = 'qa_shot__qa_shot_test__run';

    if ($entity && $entity instanceof EntityInterface) {
      $output['#entity'] = $entity;
      // $output = ['#markup' => $entity->label()];
    }

    return $output;
  }
}