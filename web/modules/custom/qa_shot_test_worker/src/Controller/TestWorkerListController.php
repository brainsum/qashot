<?php

namespace Drupal\qa_shot_test_worker\Controller;

use function array_map;
use Drupal\Core\Controller\ControllerBase;
use Drupal\qa_shot_test_worker\TestWorker\TestWorkerManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class TestWorkerListController.
 *
 * @package Drupal\qa_shot_test_worker\Controller
 */
class TestWorkerListController extends ControllerBase {

  /**
   * Test worker plugin manager.
   *
   * @var \Drupal\qa_shot_test_worker\TestWorker\TestWorkerManager
   */
  protected $workerManager;

  /**
   * TestWorkerListController constructor.
   *
   * @param \Drupal\qa_shot_test_worker\TestWorker\TestWorkerManager $workerManager
   *   Test worker plugin manager.
   */
  public function __construct(
    TestWorkerManager $workerManager
  ) {
    $this->workerManager = $workerManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container
  ) {
    return new static(
      $container->get('plugin.manager.test_worker')
    );
  }

  /**
   * List every available worker.
   *
   * @return array
   *   Render array.
   */
  public function list(): array {
    $workers = $this->workerManager->getDefinitions();
    $rows = array_map(static function ($worker) {
      return [
        $worker['id'],
        $worker['label'],
        $worker['type'],
        $worker['backend'],
        $worker['description'],
      ];
    }, $workers);

    return [
      '#type' => 'table',
      '#caption' => $this->t('Available workers'),
      '#empty' => $this->t('There are no available workers.'),
      '#header' => [
        $this->t('id'),
        $this->t('label'),
        $this->t('type'),
        $this->t('backend'),
        $this->t('description'),
      ],
      '#rows' => $rows,
    ];
  }

}
