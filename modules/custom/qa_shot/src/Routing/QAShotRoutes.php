<?php

/**
 * Created by PhpStorm.
 * User: mhavelant
 * Date: 2016.10.05.
 * Time: 14:03
 */

namespace Drupal\qa_shot\Routing;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class QAShotRoutes implements ContainerInjectionInterface {

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  public function getEntityRunRoute() {
    $collection = new RouteCollection();

    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if ($entity_type->hasLinkTemplate('canonical')) {
        $route = new Route("admin/structure/$entity_type_id/{{$entity_type_id}}/run");
        $route
          ->addDefaults([
            '_controller' => '\Drupal\qa_shot\Controller\QAShotController::entityRunPage',
            '_title' => 'Route to Run the QAShot Test.',
          ])
          ->addRequirements([
            '_permission' => 'edit qashot test permission',
          ])
          ->setOption('_qa_shot_test_entity_type_id', $entity_type_id)
          ->setOption('parameters', [
            $entity_type_id => ['type' => 'entity:' . $entity_type_id],
          ]);

        $collection->add("entity.$entity_type_id.run", $route);
      }
    }

    return $collection;
  }

}