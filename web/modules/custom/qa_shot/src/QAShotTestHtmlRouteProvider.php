<?php

namespace Drupal\qa_shot;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Symfony\Component\Routing\Route;

/**
 * Provides routes for QAShot Test entities.
 *
 * @see \Drupal\Core\Entity\Routing\AdminHtmlRouteProvider
 * @see \Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider
 */
class QAShotTestHtmlRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entityType) {
    $collection = parent::getRoutes($entityType);

    $entityType_id = $entityType->id();

    if ($collection_route = $this->getCollectionRoute($entityType)) {
      $collection->add("entity.{$entityType_id}.collection", $collection_route);
    }

    if ($settings_form_route = $this->getSettingsFormRoute($entityType)) {
      $collection->add("$entityType_id.settings", $settings_form_route);
    }

    return $collection;
  }

  /**
   * Gets the collection route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entityType
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getCollectionRoute(EntityTypeInterface $entityType) {
    if ($entityType->hasLinkTemplate('collection') && $entityType->hasListBuilderClass()) {
      $entityType_id = $entityType->id();
      $route = new Route($entityType->getLinkTemplate('collection'));
      $route
        ->setDefaults([
          '_entity_list' => $entityType_id,
          '_title' => "{$entityType->getLabel()} list",
        ])
        ->setRequirement('_permission', 'access qashot test overview')
        ->setOption('_admin_route', TRUE);

      return $route;
    }
  }

  /**
   * Gets the settings form route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entityType
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getSettingsFormRoute(EntityTypeInterface $entityType): ?Route {
    if (!$entityType->getBundleEntityType()) {
      $route = new Route("/admin/structure/{$entityType->id()}/settings");
      $route
        ->setDefaults([
          '_form' => 'Drupal\qa_shot\Form\QAShotTestSettingsForm',
          '_title' => "{$entityType->getLabel()} settings",
        ])
        ->setRequirement('_permission', $entityType->getAdminPermission())
        ->setOption('_admin_route', TRUE);

      return $route;
    }

    return NULL;
  }

}
