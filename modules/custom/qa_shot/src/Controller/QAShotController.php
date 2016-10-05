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

class QAShotController extends ControllerBase {
  public function entityRunPage(RouteMatchInterface $route_match) {
    $output = [];

    $parameter_name = $route_match->getRouteObject()->getOption('_qa_shot_test_entity_type_id'); // @todo: maybe qa_shot_test_run
    $entity = $route_match->getParameter($parameter_name);

    if ($entity && $entity instanceof EntityInterface) {
      $output = ['#markup' => $entity->label()];
    }

    return $output;
  }
}