<?php

namespace Drupal\qa_shot\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\qa_shot\Entity\QAShotTest;
use Drupal\qa_shot\Entity\QAShotTestInterface;
use Drupal\qa_shot\Exception\QAShotBaseException;
use Drupal\qa_shot\Service\DataFormatter;
use Drupal\qa_shot\Service\QueueManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

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
   * Data formatter.
   *
   * @var \Drupal\qa_shot\Service\DataFormatter
   */
  protected $dataFormatter;

  /**
   * The queue manager.
   *
   * @var \Drupal\qa_shot\Service\QueueManager
   */
  protected $queueManager;

  /**
   * {@inheritdoc}
   *
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('qa_shot.data_formatter'),
      $container->get('qa_shot.queue_manager')
    );
  }

  /**
   * QAShotController constructor.
   *
   * @param \Drupal\qa_shot\Service\DataFormatter $dataFormatter
   *   The data formatter.
   * @param \Drupal\qa_shot\Service\QueueManager $queueManager
   *   The queue manager.
   */
  public function __construct(
    DataFormatter $dataFormatter,
    QueueManager $queueManager
  ) {
    $this->dataFormatter = $dataFormatter;
    $this->queueManager = $queueManager;
  }

  /**
   * Load entity to controller functions.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   Route match object.
   *
   * @return bool
   *   If there's error it will return true otherwise false.
   *
   * @throws \InvalidArgumentException
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
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|array
   *   Redirect to the 'status' page.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function entityAddToQueue(RouteMatchInterface $routeMatch, Request $request) {
    if ($this->loadEntity($routeMatch)) {
      return ['#markup' => 'Invalid entity.'];
    }

    if ('a_b' === $this->entity->bundle()) {
      // If we come from a valid route, run the tests.
      try {
        $this->queueManager->addTest($this->entity);
      }
      catch (QAShotBaseException $e) {
        $this->messenger()->addMessage($e->getMessage(), 'error');
      }
    }
    elseif ('before_after' === $this->entity->bundle()) {
      // If we come from a valid route, run the tests.
      try {
        $this->queueManager->addTest($this->entity, $request->attributes->get('run_type'));
      }
      catch (QAShotBaseException $e) {
        $this->messenger()->addMessage($e->getMessage(), 'error');
      }
    }
    else {
      $this->messenger()->addMessage($this->t('Running this type of test is not yet supported.'), 'error');
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
   *
   * @throws \InvalidArgumentException
   * @throws \Drupal\qa_shot\Exception\QAShotBaseException
   */
  public function entityRunPage(RouteMatchInterface $routeMatch): array {
    if ($this->loadEntity($routeMatch)) {
      return ['#markup' => 'Invalid entity.'];
    }

    $localReportUrl = \file_create_url($this->entity->getHtmlReportPath());
    $lastRun = $this->entity->getLastRunMetadataValue();
    $reportTime = empty($lastRun) ? NULL : \end($lastRun)['datetime'];
    $resultExist = FALSE;

    foreach ($this->entity->getLifetimeMetadataValue() as $item) {
      if (NULL === $item['stage'] || $item['stage'] === 'after') {
        $resultExist = TRUE;
        break;
      }
    }

    // If the report time is not NULL, format it to an '.. ago' string.
    if (NULL !== $reportTime) {
      $reportDateTime = new DrupalDateTime($reportTime);
      $reportTime = $this->dataFormatter->dateAsAgo($reportDateTime);
    }

    list($lastRunTime, $lastReferenceRunTime, $lastTestRunTime) = \array_values($this->entity->getLastRunTimes());

    $build = [
      '#type' => 'markup',
      '#theme' => 'qa_shot__qa_shot_test__run',
      '#queue_status' => $this->entity->getHumanReadableQueueStatus(),
      '#html_report_url' => $localReportUrl,
      '#remote_html_report_url' => $this->entity->getRemoteHtmlReportPath(),
      '#entity' => $this->entity,
      '#report_time' => $reportTime,
      '#result_exist' => $resultExist,
      '#last_run_time' => $lastRunTime,
      '#last_reference_run_time' => $lastReferenceRunTime,
      '#last_test_run_time' => $lastTestRunTime,
    ];

    return $build;
  }

  /**
   * Displays a private debug file.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   Route match interface.
   *
   * @return array
   *   Return the markup render array.
   *
   * @throws \InvalidArgumentException
   */
  public function displayDebugFile(RouteMatchInterface $routeMatch): array {
    $entityId = $routeMatch->getParameters()->get('qa_shot_test');
    $fileName = $routeMatch->getParameters()->get('file_name');

    $debugPath = PrivateStream::basePath() . '/qa_test_data/' . $entityId . '/debug/' . $fileName;
    $contents = \file_get_contents($debugPath);

    return [
      '#markup' => '<pre>' . $contents . '</pre>',
    ];
  }

}
