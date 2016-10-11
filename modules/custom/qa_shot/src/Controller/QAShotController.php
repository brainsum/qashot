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
use Symfony\Component\HttpFoundation\Request;

/**
 * Class QAShotController.
 */
class QAShotController extends ControllerBase {

  /**
   * Route controller for the "Run" route.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   Route match object.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   *
   * @return array
   *   The configured template.
   */
  public function entityRunPage(RouteMatchInterface $route_match, Request $request) {
    $entityId = $route_match->getParameters()->get("qa_shot_test");

    if (empty($entityId)) {
      return ['#markup' => "Invalid entity."];
    }

    // @todo: if we come here via the edit form "Run Test" button,
    // automatically start the test
    // if just opening, show list of previous results:
    // Time, Who started it, pass/fail, html report link
    $entity = QAShotTest::load($entityId);
    if ($this->isRedirectRouteValid($request)) {
      // If we come from a valid route, run the tests.
      _qa_shot_run_test_for_entity($entity);
    }

    $output = [];
    $output['#theme'] = 'qa_shot__qa_shot_test__run';

    if ($entity && $entity instanceof EntityInterface) {
      $reportUrl = file_create_url($entity->get('field_html_report_path')->value);
      $output['#html_report_url'] = $reportUrl;
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
  private function isRedirectRouteValid(Request &$request) {
    kint($request->headers->get("referer"));
    if (!empty($refererURL = $request->headers->get("referer"))) {
      $host = $request->getHttpHost();
      $internalReferer = substr($refererURL, (strpos($refererURL, $host) + strlen($host) + 1));
      $refererRoute = \Drupal::pathValidator()->getUrlIfValid($internalReferer)->getRouteName();

      kint($host, $internalReferer, $refererRoute);

      // @todo: Maybe use path instead of route.
      if ("entity.qa_shot_test.edit_form" === $refererRoute) {
        // Unset the referer, so we can safely reload the page.
        // @todo: FIXME, unset not working
        $request->headers->remove("referer");
        return TRUE;
      }
    }

    return FALSE;
  }

}
