<?php

namespace Drupal\qa_shot\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Class RouteSubscriber.
 *
 * @package Drupal\qa_shot\Routing
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // The collection page is less advanced, the main one is the
    // custom view, which also is the front page. So we use that instead.
    if ($route = $collection->get('entity.qa_shot_test.collection')) {
      $route->setPath('<front>');
    }
  }

}
