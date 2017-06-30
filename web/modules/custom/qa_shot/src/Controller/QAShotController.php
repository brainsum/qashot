<?php

namespace Drupal\qa_shot\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\qa_shot\Entity\QAShotTest;
use Drupal\qa_shot\Entity\QAShotTestInterface;
use Drupal\qa_shot\Exception\QAShotBaseException;
use function PHPSTORM_META\type;
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
      if ((int) $request->query->get('start_now') === 1) {
        // If we come from a valid route, run the tests.
        try {
          $entity->queue(NULL);
        }
        catch (QAShotBaseException $e) {
          drupal_set_message($e->getMessage(), 'error');
        }
      }
    }
    else {
      drupal_set_message($this->t('Running this type of test is not yet supported.', 'error'));
    }

    $reportUrl = file_create_url($entity->getHtmlReportPath());
    $lastRun = $entity->getLastRunMetadataValue();
    $reportTime = empty($lastRun) ? NULL : end($lastRun)['datetime'];

    // If the report time is not NULL, format it to an '.. ago' string.
    if (NULL !== $reportTime) {
      $reportTimestamp = (new \DateTime($reportTime))->getTimestamp();
      /** @var \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter */
      $dateFormatter = \Drupal::service('date.formatter');
      $currentTimestamp = \Drupal::time()->getRequestTime();
      $reportTime = 'from ' . $dateFormatter->formatDiff($currentTimestamp, $reportTimestamp) . ' ago';
    }

    $build = [
      '#type' => 'markup',
      '#theme' => 'qa_shot__qa_shot_test__run',
      '#queue_status' => $entity->getHumanReadableQueueStatus(),
      '#html_report_url' => $reportUrl,
      '#entity' => $entity,
      '#report_time' => $reportTime,
    ];

    return $build;
  }

}
