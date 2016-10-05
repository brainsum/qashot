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
    $output = [];

    $entityId = $route_match->getParameters()->get("qa_shot_test");

    // @todo: create template/form for Run page
    if (empty($entityId)) {
      return ['#markup' => "Invalid entity."];
    }

    $entity = QAShotTest::load($entityId);

    if ($entity && $entity instanceof EntityInterface) {
      $output = ['#markup' => $entity->label()];
    }

    return $output;
  }
}