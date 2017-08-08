<?php

namespace Drupal\qa_shot_rest_api\Controller;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\qa_shot\Entity\QAShotTest;
use Drupal\qa_shot\Exception\QAShotBaseException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

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
   * Serialization service.
   *
   * @var \Symfony\Component\Serializer\Normalizer\NormalizerInterface
   */
  protected $serializer;

  /**
   * Create.
   *
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('serializer')
    );
  }

  /**
   * ApiController constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Symfony\Component\Serializer\Normalizer\NormalizerInterface $serializer
   *   Serializer service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    NormalizerInterface $serializer
  ) {
    $this->testStorage = $entityTypeManager->getStorage('qa_shot_test');
    $this->serializer = $serializer;
  }

  /**
   * Starts a test.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @throws BadRequestHttpException
   * @throws \InvalidArgumentException
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   * @throws \LogicException
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response.
   */
  public function queueTest(Request $request): JsonResponse {
    $settings = $this->parseRunnerSettings($request);
    $stage = $settings['stage'];
    $frontendUrl = $settings['frontend_url'];

    /** @var \Drupal\qa_shot\Entity\QAShotTestInterface $entity */
    $entity = $this->loadEntityFromId($request->attributes->get('qa_shot_test'));

    $storedFrontendUrl = $entity->getFrontendUrl();
    if (NULL === $storedFrontendUrl || $storedFrontendUrl !== $frontendUrl) {
      $entity->setFrontendUrl($frontendUrl);
      $entity->save();
    }

    try {
      $message = $entity->queue($stage, 'rest_api');
      $responseCode = 'added_to_queue' === $message ? 201 : 202;
    }
    catch (QAShotBaseException $e) {
      $message = $e->getMessage();
      $responseCode = 500;
    }

    $entityData = $this->serializer->normalize($entity);

    $responseData = [
      'runner_settings' => ['stage' => $stage],
      'message' => $message,
      'entity' => $entityData,
    ];

    return new JsonResponse($responseData, $responseCode);
  }

  /**
   * For testing login function.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @throws BadRequestHttpException
   * @throws \InvalidArgumentException
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   * @throws \LogicException
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response.
   */
  public function loginTest(Request $request): JsonResponse {
    $responseData = [
      'status' => "success",
    ];

    return new JsonResponse($responseData);
  }

  /**
   * Get force run a test function.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @throws BadRequestHttpException
   * @throws \InvalidArgumentException
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   * @throws \LogicException
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response.
   */
  public function forceRun(Request $request): JsonResponse {
    $settings = $this->parseRunnerSettings($request);
    $id = $settings['tid'];

    try {
      /** @var \Drupal\qa_shot\Service\RunTestImmediately $test_runner */
      $test_runner = \Drupal::service('qa_shot.immediately_test');
      $test_runner->run($id);
      $status = "success";
    }
    catch (QAShotBaseException $e) {
      $status = $e->getMessage();
    }

    $responseData = [
      'tested_id' => $id,
      'status' => $status,
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
   * @return string|null
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

    if (!isset($runnerSettings['frontend_url']) || empty($runnerSettings['frontend_url'])) {
      throw new BadRequestHttpException("The 'frontend_url' parameter is missing.");
    }

    if (!UrlHelper::isValid($runnerSettings['frontend_url'], TRUE)) {
      throw new BadRequestHttpException("The 'frontend_url' parameter is not a valid URL.");
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
  private function loadEntityFromId($entityId): QAShotTest {
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
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @throws \Symfony\Component\Routing\Exception\RouteNotFoundException
   * @throws \Symfony\Component\Routing\Exception\MissingMandatoryParametersException
   * @throws \Symfony\Component\Routing\Exception\InvalidParameterException
   * @throws \InvalidArgumentException
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response.
   */
  public function testList(Request $request): JsonResponse {
    $page = (int) $request->query->get('page', 1);
    $page = $page < 1 ? 1 : $page;
    $limit = (int) $request->query->get('limit', 10);
    $limit = $limit < 0 ? 0 : $limit;
    $type = $request->query->get('type', '');
    $condition = [];

    $queryStartIndex = ($page - 1) * $limit;

    $query = $this->testStorage->getQuery()->range($queryStartIndex, $limit);
    if (!empty($type)) {
      $query->condition('type', $type, '=');
      $condition['type'] = ['value' => $type, 'operator' => '='];
    }
    $testsIds = $query->execute();
    $tests = $this->testStorage->loadMultiple($testsIds);

    $responseData = [
      'pagination' => $this->generatePager($page, $limit, $condition),
      'entity' => [],
    ];

    foreach ($tests as $test) {
      $testAsArray = $this->serializer->normalize($test);
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
   * @param array $condition
   *   Items condition.
   *
   * @throws \Symfony\Component\Routing\Exception\MissingMandatoryParametersException
   * @throws \Symfony\Component\Routing\Exception\InvalidParameterException
   * @throws \Symfony\Component\Routing\Exception\RouteNotFoundException
   *
   * @return array
   *   The pager as array.
   */
  private function generatePager($page, $limit, $condition = []): array {
    $query = $this->testStorage->getQuery();
    foreach ($condition as $key => $data) {
      $query->condition($key, $data['value'], $data['operator']);
    }
    $totalEntityCount = $query->count()->execute();
    $totalPageCount = (int) ceil($totalEntityCount / $limit);

    $routeParams = [
      '_format' => 'json',
      'page' => $page,
      'limit' => $limit,
      'type' => $limit,
    ];

    foreach ($condition as $key => $data) {
      $routeParams[$key] = $data['value'];
    }

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
        'self' => Url::fromRoute('qa_shot_rest_api.test_list', $routeParams, $routeOptions)->toString(),
      ],
    ];

    if ($page > 1) {
      $routeParams['page'] = $page - 1;
      $pager['links']['previous'] = Url::fromRoute('qa_shot_rest_api.test_list', $routeParams, $routeOptions)->toString();
      $routeParams['page'] = 1;
      $pager['links']['first'] = Url::fromRoute('qa_shot_rest_api.test_list', $routeParams, $routeOptions)->toString();
    }
    if ($page < $totalPageCount) {
      $routeParams['page'] = $page + 1;
      $pager['links']['next'] = Url::fromRoute('qa_shot_rest_api.test_list', $routeParams, $routeOptions)->toString();
      $routeParams['page'] = $totalPageCount;
      $pager['links']['last'] = Url::fromRoute('qa_shot_rest_api.test_list', $routeParams, $routeOptions)->toString();
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
  public function access(AccountInterface $account): AccessResult {
    if ((int) $account->id() === 1) {
      return AccessResult::allowed();
    }

    return AccessResult::allowedIf(in_array('rest_api_user', $account->getRoles(TRUE), FALSE));
  }

}
