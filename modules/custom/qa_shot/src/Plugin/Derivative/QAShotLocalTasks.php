<?php
/**
 * Created by PhpStorm.
 * User: mhavelant
 * Date: 2016.10.05.
 * Time: 14:13
 */

namespace Drupal\qa_shot\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class QAShotLocalTasks extends DeriverBase implements ContainerDeriverInterface {

  protected $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives = array();

    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if ($entity_type->hasLinkTemplate('canonical')) {
        $this->derivatives["$entity_type_id.run_tab"] = [
            'route_name' => "entity.$entity_type_id.run", // @todo: maybe qa_shot_test
            'title' => t('Run'),
            'base_route' => "entity.$entity_type_id.canonical",
            'weight' => 15,
          ] + $base_plugin_definition;
      }
    }

    return $this->derivatives;
  }

}