<?php

namespace Drupal\qa_shot\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\qa_shot\Entity\QAShotTest;
use Drupal\qa_shot\Entity\QAShotTestInterface;
use Drupal\qa_shot\Exception\QAShotBaseException;

/**
 * Class QAShotController.
 */
class QAShotController extends ControllerBase {

  /**
   * The entity.
   *
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
  private function loadEntity(RouteMatchInterface $routeMatch): bool {
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
      /** @var \Drupal\qa_shot\Service\DataFormatter $service */
      $dataFormatter = \Drupal::service('qa_shot.data_formatter');
      $reportDateTime = new DrupalDateTime($reportTime);
      $reportTime = $dataFormatter->dateAsAgo($reportDateTime);
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

  /**
   * Disaplays a private debug file.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   Route match interface.
   *
   * @return array
   *   Return the markup render array.
   */
  public function displayDebugFile(RouteMatchInterface $routeMatch): array {
    $entityId = $routeMatch->getParameters()->get('qa_shot_test');
    $fileName = $routeMatch->getParameters()->get('file_name');

    $debugPath = PrivateStream::basePath() . '/qa_test_data/' . $entityId . '/debug/' . $fileName;
    $contents = file_get_contents($debugPath);

    return [
      '#markup' => '<pre>' . $contents . '</pre>',
    ];
  }

}
