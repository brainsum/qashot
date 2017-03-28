<?php

namespace Drupal\qa_shot_rest_api\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\backstopjs\Exception\BackstopBaseException;
use Drupal\qa_shot\TestBackendInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for the custom API endpoints.
 *
 * @package Drupal\qa_shot_rest_api\Controller
 */
class ApiController extends ControllerBase {

  /**
   * Test entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private $testStorage;

  /**
   * URL Generator.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * Tester service.
   *
   * @var \Drupal\qa_shot\TestBackendInterface
   */
  private $tester;

  /**
   * Create.
   *
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('url_generator'),
      $container->get('backstopjs.backstop')
    );
  }

  /**
   * ApiController constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $urlGenerator
   *   Url generator.
   * @param \Drupal\qa_shot\TestBackendInterface $tester
   *   The tester service.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    UrlGeneratorInterface $urlGenerator,
    TestBackendInterface $tester
  ) {
    $this->testStorage = $entityTypeManager->getStorage('qa_shot_test');
    $this->urlGenerator = $urlGenerator;
    $this->tester = $tester;
  }

  /**
   * Starts a test.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @throws BadRequestHttpException
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response.
   */
  public function runTest(Request $request) {
    $runnerSettings = $this->parseRunnerSettings($request);

    /** @var \Drupal\qa_shot\Entity\QAShotTestInterface $entity */
    $entity = $this->loadEntityFromId($request->attributes->get('qa_shot_test'));

    $message = 'success';

    try {
      $this->tester->runTestBySettings(
        $entity,
        $runnerSettings['test_stage']
      );
    }
    catch (BackstopBaseException $e) {
      $message = $e->getMessage();
    }

    // TODO: If queued, the response code should be 201 (accepted) or smth.
    // TODO: possible values: queued, in progress, done, error.
    $responseData = [
      'runner_settings' => $runnerSettings,
      'message' => $message,
      'entity' => $entity->toArray(),
    ];

    return new JsonResponse($responseData);
  }

  /**
   * Parse the runner settings from the request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @throws BadRequestHttpException
   * @throws \LogicException
   *
   * @return array|mixed
   *   The parameters.
   */
  private function parseRunnerSettings(Request $request) {
    $runnerSettings = [];

    if (!empty($request->getContent())) {
      $runnerSettings = json_decode($request->getContent(), TRUE);
    }

    if (empty($runnerSettings)) {
      throw new BadRequestHttpException('The request parameters are empty.');
    }

    if (!isset($runnerSettings['test_stage']) || empty($runnerSettings['test_stage'])) {
      $runnerSettings['test_stage'] = NULL;
    }

    return $runnerSettings;
  }

  /**
   * Loads an entity from its ID.
   *
   * @param string|int $entityId
   *   The ID to be loaded.
   *
   * @throws BadRequestHttpException
   * @throws NotFoundHttpException
   *
   * @return \Drupal\qa_shot\Entity\QAShotTest
   *   The entity.
   */
  private function loadEntityFromId($entityId) {
    if (!is_numeric($entityId)) {
      throw new BadRequestHttpException(
        t('The supplied parameter ( @param ) is not valid.', [
          '@param' => $entityId,
        ])
      );
    }

    /** @var \Drupal\qa_shot\Entity\QAShotTest $entity */
    $entity = $this->testStorage->load($entityId);

    if (NULL === $entity) {
      throw new NotFoundHttpException(
        t('The QAShot Test with ID @id was not found', array(
          '@id' => $entityId,
        ))
      );
    }

    return $entity;
  }

  /**
   * Return the list of tests.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response.
   */
  public function testList(Request $request) {
    $page = (int) $request->query->get('page', 1);
    $page = $page < 1 ? 1 : $page;
    $limit = (int) $request->query->get('limit', 10);
    $limit = $limit < 0 ? 0 : $limit;

    $queryStartIndex = ($page - 1) * $limit;

    $testsIds = $this->testStorage->getQuery()->range($queryStartIndex, $limit)->execute();
    $tests = $this->testStorage->loadMultiple($testsIds);

    $responseData = [
      'pagination' => $this->generatePager($page, $limit),
      'entity' => [],
    ];

    foreach ($tests as $test) {
      $testAsArray = $test->toArray();
      $responseData['entity'][] = [
        'id' => $testAsArray['id'],
        'name' => $testAsArray['name'],
        'type' => $testAsArray['type'],
        'metadata_last_run' => $testAsArray['metadata_last_run'],
      ];
    }

    return new JsonResponse($responseData);
  }

  /**
   * Function to generate a pager.
   *
   * @param int $page
   *   The current page.
   * @param int $limit
   *   Items per page.
   *
   * @return array
   *   The pager as array.
   */
  private function generatePager($page, $limit) {
    $totalEntityCount = $this->testStorage->getQuery()->count()->execute();
    $totalPageCount = (int) ceil($totalEntityCount / $limit);

    $routeParams = [
      '_format' => 'json',
      'page' => $page,
      'limit' => $limit,
    ];

    $routeOptions = [
      'absolute' => TRUE,
    ];

    $pager = [
      // The number of the current page.
      'page' => (string) $page,
      // The limit of items on the page.
      'limit' => (string) $limit,
      // The total count of entities.
      'total_entities' => (string) $totalEntityCount,
      // The total count of pages.
      'total_pages' => (string) $totalPageCount,
      'links' => [
        'self' => $this->urlGenerator->generateFromRoute('qa_shot_rest_api.test_list', $routeParams, $routeOptions),
      ],
    ];

    if ($page > 1) {
      $routeParams['page'] = $page - 1;
      $pager['links']['previous'] = $this->urlGenerator->generateFromRoute('qa_shot_rest_api.test_list', $routeParams, $routeOptions);
      $routeParams['page'] = 1;
      $pager['links']['first'] = $this->urlGenerator->generateFromRoute('qa_shot_rest_api.test_list', $routeParams, $routeOptions);
    }
    if ($page < $totalPageCount) {
      $routeParams['page'] = $page + 1;
      $pager['links']['next'] = $this->urlGenerator->generateFromRoute('qa_shot_rest_api.test_list', $routeParams, $routeOptions);
      $routeParams['page'] = $totalPageCount;
      $pager['links']['last'] = $this->urlGenerator->generateFromRoute('qa_shot_rest_api.test_list', $routeParams, $routeOptions);
    }

    return $pager;
  }

  /**
   * Implements access logic for this controller.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The permission-check result.
   */
  public function access(AccountInterface $account) {
    if ((int) $account->id() === 1) {
      return AccessResult::allowed();
    }

    return AccessResult::allowedIf(in_array('rest_api_user', $account->getRoles(TRUE), FALSE));
  }

}
