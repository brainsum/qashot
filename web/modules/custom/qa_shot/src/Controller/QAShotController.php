<?php

namespace Drupal\qa_shot\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\qa_shot\Entity\QAShotTest;
use Drupal\qa_shot\Entity\QAShotTestInterface;
use Drupal\backstopjs\Exception\BackstopBaseException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class QAShotController.
 */
class QAShotController extends ControllerBase {

  /**
   * Route controller for the "Run" route.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   Route match object.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   *
   * @return array
   *   The configured template.
   */
  public function entityRunPage(RouteMatchInterface $routeMatch, Request $request) {
    $entityId = $routeMatch->getParameters()->get('qa_shot_test');

    // @todo: if we come here via the edit form "Run Test" button,
    // automatically start the test
    // if just opening, show list of previous results:
    // Time, Who started it, pass/fail, html report link
    $entity = QAShotTest::load($entityId);
    if (!$entity || !$entity instanceof QAShotTestInterface) {
      return ['#markup' => 'Invalid entity.'];
    }

    // @fixme
    if ('a_b' === $entity->bundle()) {
      if ($request->query->get('start_now') == 1) {
        // If we come from a valid route, run the tests.
        try {
          $entity->run(NULL);
        }
        catch (BackstopBaseException $e) {
          drupal_set_message($e->getMessage(), 'error');
        }
      }
    }
    else {
      drupal_set_message($this->t('Running this type of test is not yet supported.', 'error'));
    }

    $output = [];
    $output['#theme'] = 'qa_shot__qa_shot_test__run';

    $reportUrl = file_create_url($entity->getHtmlReportPath());
    $output['#html_report_url'] = $reportUrl;
    $output['#entity'] = $entity;

    return $output;
  }

}
