<?php

namespace Drupal\qa_shot_rest_api\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\qa_shot\Custom\Backstop;
use Drupal\qa_shot\Exception\BackstopBaseException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\SerializerInterface;

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
   * Serializer.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  private $serializer;

  /**
   * Create.
   *
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('serializer')
    );
  }

  /**
   * Constructor.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    SerializerInterface $serializer
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
      \Drupal::service('qa_shot.backstop')->runTestBySettings(
        $entity->bundle(),
        $runnerSettings['test_stage'],
        $entity
      );
    }
    catch (BackstopBaseException $e) {
      $message = $e->getMessage();
    }

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

    if (!isset($runnerSettings['test_stage'])) {
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
  public function testList() {
    // @todo: Pagination.
    $tests = $this->testStorage->loadMultiple();

    $responseData = [];

    foreach ($tests as $test) {
      $testAsArray = $test->toArray();

      $responseData[] = [
        'id' => $testAsArray['id'],
        'name' => $testAsArray['name'],
        'type' => $testAsArray['type'],
        'metadata_last_run' => $testAsArray['metadata_last_run'],
      ];
    }

    return new JsonResponse($responseData);
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
