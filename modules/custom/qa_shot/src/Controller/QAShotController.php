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
    if ($request->query->get('start_now') == 1) {
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

}
