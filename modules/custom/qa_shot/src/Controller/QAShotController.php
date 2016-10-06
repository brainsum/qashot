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

/**
 * Class QAShotController.
 */
class QAShotController extends ControllerBase {

  /**
   * Route controller for the "Run" route.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   Route match object.
   *
   * @return array
   *   The configured template.
   */
  public function entityRunPage(RouteMatchInterface $route_match) {
    $entityId = $route_match->getParameters()->get("qa_shot_test");

    if (empty($entityId)) {
      return ['#markup' => "Invalid entity."];
    }

    // @todo: if we come here via the edit form "Run Test" button,
    // automatically start the test
    // if just opening, show list of previous results:
    // Time, Who started it, pass/fail, html report link
    $entity = QAShotTest::load($entityId);
    // kint($drupalRootPath = realpath("."), "realpath"); //.
    if ($this->redirectRouteIsValid()) {
      // If we come from a valid route, run the tests.
      _qa_shot_run_test_for_entity($entity);
      // Unset the referer, so we can safely reload the page.
      \Drupal::request()->headers->set("referer", NULL);
    }

    $output = [];
    $output['#theme'] = 'qa_shot__qa_shot_test__run';

    if ($entity && $entity instanceof EntityInterface) {
      $output['#entity'] = $entity;
      // $output = ['#markup' => $entity->label()];
    }

    return $output;
  }

  /**
   * Helper function to decide whether we need to run the test on page access.
   *
   * @return bool
   *   TRUE, if we should run the tests, FALSE otherwise.
   */
  private function redirectRouteIsValid() {
    if (!empty($refererURL = \Drupal::request()->headers->get("referer"))) {
      $host = \Drupal::request()->getHttpHost();
      $internalReferer = substr($refererURL, (strpos($refererURL, $host) + strlen($host) + 1));
      $refererRoute = \Drupal::pathValidator()->getUrlIfValid($internalReferer)->getRouteName();

      kint($host, $internalReferer, $refererRoute);

      // @todo: Maybe use path instead of route.
      if ("entity.qa_shot_test.edit_form" === $refererRoute) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
