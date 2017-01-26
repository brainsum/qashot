<?php

namespace Drupal\qa_shot_rest_api\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\qa_shot\Entity\QAShotTest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\Serializer;

/**
 * Controller for the custom API endpoints.
 *
 * @package Drupal\qa_shot_rest_api\Controller
 */
class ApiController extends ControllerBase {

  public function __construct() {
  }

  /**
   * Starts a test.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response.
   */
  public function runTest(RouteMatchInterface $routeMatch, Request $request) {
    kint($routeMatch);
    kint($request);
    $entity = $this->loadEntityFromId($request->attributes->get('qa_shot_test'));

    /** @var Serializer $serializer */
    $serializer = \Drupal::service('serializer');
    $response = $serializer->serialize($entity, 'json');

    return new JsonResponse($response);
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
    $entity = QAShotTest::load($entityId);

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

    // Filter out every role except the rest_api_user one and count the results.
    $hasNecessaryRoles = count(array_filter($account->getRoles(), function ($role) {
      return $role == 'rest_api_user';
    })) >= 1;

    return AccessResult::allowedIf($hasNecessaryRoles);
  }

}
