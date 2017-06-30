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
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class QAShotController.
 */
class QAShotController extends ControllerBase {

  /**
   * @var \Drupal\qa_shot\Entity\QAShotTest
   */
  private $entity;

  /**
   * Load entity to controller functions.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   Route match object.
   *
   * @return bool
   *   If there's error it will return true otherwise false.
   */
  private function loadEntity(RouteMatchInterface $routeMatch) {
    $entityId = $routeMatch->getParameters()->get('qa_shot_test');

    // @todo: if we come here via the edit form "Run Test" button,
    // automatically start the test
    // if just opening, show list of previous results:
    // Time, Who started it, pass/fail, html report link
    $this->entity = QAShotTest::load($entityId);
    return (!$this->entity || !$this->entity instanceof QAShotTestInterface);
  }

  /**
   * Route controller for the "add_to_queue" route.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   Route match object.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|array
   *   Redirect to the 'status' page.
   */
  public function entityAddToQueue(RouteMatchInterface $routeMatch) {
    if ($this->loadEntity($routeMatch)) {
      return ['#markup' => 'Invalid entity.'];
    }

    if ('a_b' === $this->entity->bundle()) {
      // If we come from a valid route, run the tests.
      try {
        $this->entity->queue(NULL);
      }
      catch (QAShotBaseException $e) {
        drupal_set_message($e->getMessage(), 'error');
      }
    }
    else {
      drupal_set_message($this->t('Running this type of test is not yet supported.'), 'error');
    }

    return $this->redirect('entity.qa_shot_test.run', ['qa_shot_test' => $this->entity->id()]);
  }

  /**
   * Route controller for the "Run" route.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   Route match object.
   *
   * @return array
   *   The configured template.
   */
  public function entityRunPage(RouteMatchInterface $routeMatch): array {
    if ($this->loadEntity($routeMatch)) {
      return ['#markup' => 'Invalid entity.'];
    }

    $reportUrl = file_create_url($this->entity->getHtmlReportPath());
    $lastRun = $this->entity->getLastRunMetadataValue();
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
      '#queue_status' => $this->entity->getHumanReadableQueueStatus(),
      '#html_report_url' => $reportUrl,
      '#entity' => $this->entity,
      '#report_time' => $reportTime,
    ];

    return $build;
  }

}
