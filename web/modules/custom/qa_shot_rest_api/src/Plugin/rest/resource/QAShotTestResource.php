<?php

namespace Drupal\qa_shot_rest_api\Plugin\rest\resource;

use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\qa_shot\Entity\QAShotTest;
use Drupal\qa_shot\Entity\QAShotTestInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Defines restful resource for the QAShot Test entities.
 *
 * Code is based on Drupal\rest\Plugin\rest\resource\EntityResource.
 *
 * @RestResource(
 *   id = "entity:qa_shot_test",
 *   label = @Translation("QAShot Test Resource"),
 *   serialization_class = "Drupal\qa_shot\Entity\QAShotTest",
 *   deriver = "Drupal\qa_shot_rest_api\Plugin\Deriver\QAShotTestDeriver",
 *   uri_paths = {
 *     "canonical" = "/api/rest/v1/qa_shot_test/{qa_shot_test}",
 *     "https://www.drupal.org/link-relations/create" = "/api/rest/v1/qa_shot_test"
 *   }
 * )
 */
class QAShotTestResource extends ResourceBase implements DependentPluginInterface {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The entity type targeted by this resource.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $entityType;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $testStorage;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   *
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
   * @throws \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('config.factory'),
      $container->get('current_user')
    );
  }

  /**
   * Constructs a Drupal\rest\Plugin\rest\resource\EntityResource object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    array $serializer_formats,
    LoggerInterface $logger,
    ConfigFactoryInterface $config_factory,
    AccountProxyInterface $current_user
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    // @todo: handle the thrown \Drupal\Component\Plugin\Exception\PluginNotFoundException .
    $this->entityType = $entity_type_manager->getDefinition($plugin_definition['entity_type']);
    $this->testStorage = $entity_type_manager->getStorage('qa_shot_test');
    $this->configFactory = $config_factory;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    if (NULL !== $this->entityType) {
      return ['module' => [$this->entityType->getProvider()]];
    }

    return [];
  }

  /**
   * Handles GET requests.
   *
   * @param int $qaShotTest
   *   The ID of the entity.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response.
   *
   * @throws NotFoundHttpException
   * @throws BadRequestHttpException
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   */
  public function get($qaShotTest): ResourceResponse {
    $entity = $this->loadEntityFromId($qaShotTest);

    /** @var \Drupal\Core\Access\AccessResultReasonInterface $entityAccess */
    $entityAccess = $entity->access('view', NULL, TRUE);
    if (!$entityAccess->isAllowed()) {
      throw new AccessDeniedHttpException($entityAccess->getReason() ?: $this->generateFallbackAccessDeniedMessage($entity, 'view'));
    }

    $response = new ResourceResponse($entity, 200);
    $response->addCacheableDependency($entity);
    $response->addCacheableDependency($entityAccess);

    return $response;
  }

  /**
   * Responds to entity POST requests and saves the new entity.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $entity
   *   The entity received in the request.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   *
   * @throws \InvalidArgumentException
   * @throws \Drupal\Core\Entity\Exception\UndefinedLinkTemplateException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   */
  public function post(QAShotTestInterface $entity) {
    if ($entity === NULL) {
      throw new BadRequestHttpException('No entity content received.');
    }

    if (!$entity->access('create')) {
      throw new AccessDeniedHttpException();
    }
    $definition = $this->getPluginDefinition();
    // Verify that the deserialized entity is of the type that we expect to
    // prevent security issues.
    if ($entity->getEntityTypeId() !== $definition['entity_type']) {
      throw new BadRequestHttpException('Invalid entity type');
    }
    // POSTed entities must not have an ID set, because we always want to create
    // new entities here.
    if (!$entity->isNew()) {
      throw new BadRequestHttpException('Only new entities can be created');
    }

    // Only check 'edit' permissions for fields that were actually
    // submitted by the user. Field access makes no difference between 'create'
    // and 'update', so the 'edit' operation is used here.
    foreach ($entity->_restSubmittedFields as $key => $fieldName) {
      if (!$entity->get($fieldName)->access('edit')) {
        throw new AccessDeniedHttpException("Access denied on creating field '$fieldName'");
      }
    }

    // Validate the received data before saving.
    $this->validate($entity);
    try {
      $entity->save();
      $this->logger->notice('Created entity %type with ID %id.', ['%type' => $entity->getEntityTypeId(), '%id' => $entity->id()]);

      // 201 Created responses return the newly created entity in the response
      // body. These responses are not cacheable, so we add no cacheability
      // metadata here.
      $url = $entity->toUrl('canonical', ['absolute' => TRUE])->toString(TRUE);
      $response = new ModifiedResourceResponse($entity, 201, ['Location' => $url->getGeneratedUrl()]);
      return $response;
    }
    catch (EntityStorageException $e) {
      throw new HttpException(500, 'Internal Server Error', $e);
    }
  }

  /**
   * Responds to entity DELETE requests.
   *
   * @param string $qaShotTest
   *   The entity id.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   */
  public function delete($qaShotTest) {
    $entity = $this->loadEntityFromId($qaShotTest);

    if (!$entity->access('delete')) {
      throw new AccessDeniedHttpException();
    }
    try {
      $entity->delete();
      $this->logger->notice('Deleted entity %type with ID %id.', ['%type' => $entity->getEntityTypeId(), '%id' => $entity->id()]);

      // DELETE responses have an empty body.
      return new ModifiedResourceResponse(NULL, 204);
    }
    catch (EntityStorageException $e) {
      throw new HttpException(500, 'Internal Server Error', $e);
    }
  }

  /**
   * Responds to entity PATCH requests.
   *
   * @param int|string $entityId
   *   The original entity object.
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $updatedEntity
   *   I don't know.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   * @throws \InvalidArgumentException
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   */
  public function patch($entityId, QAShotTestInterface $updatedEntity) {
    $originalEntity = $this->loadEntityFromId($entityId);

    $definition = $this->getPluginDefinition();
    if ($updatedEntity->getEntityTypeId() !== $definition['entity_type']) {
      throw new BadRequestHttpException('Invalid entity type');
    }
    // The denormalizer requires the bundle to be sent for some reason.
    // We don't want to allow the bundle to be changed.
    if ($originalEntity->bundle() !== $updatedEntity->bundle()) {
      throw new BadRequestHttpException('Changing the entity type is not allowed.');
    }
    if (!$updatedEntity->access('update')) {
      throw new AccessDeniedHttpException();
    }

    // Overwrite the received properties.
    $entityKeys = $updatedEntity->getEntityType()->getKeys();
    foreach ($updatedEntity->_restSubmittedFields as $fieldName) {
      $field = $updatedEntity->get($fieldName);

      // Entity key fields need special treatment: together they uniquely
      // identify the entity. Therefore it does not make sense to modify any of
      // them. However, rather than throwing an error, we just ignore them as
      // long as their specified values match their current values.
      if (in_array($fieldName, $entityKeys, TRUE)) {
        // Unchanged values for entity keys don't need access checking.
        if ($originalEntity->get($fieldName)->getValue() === $updatedEntity->get($fieldName)->getValue()) {
          continue;
        }
        // It is not possible to set the language to NULL as it is automatically
        // re-initialized. As it must not be empty, skip it if it is.
        if (isset($entityKeys['langcode']) && $fieldName === $entityKeys['langcode'] && $field->isEmpty()) {
          continue;
        }
      }

      if (!$originalEntity->get($fieldName)->access('edit')) {
        throw new AccessDeniedHttpException("Access denied on updating field '$fieldName'.");
      }

      $originalEntity->set($fieldName, $field->getValue());
    }

    // Validate the received data before saving.
    $this->validate($originalEntity);
    try {
      $originalEntity->save();
      $this->logger->notice('Updated entity %type with ID %id.', ['%type' => $originalEntity->getEntityTypeId(), '%id' => $originalEntity->id()]);

      // Return the updated entity in the response body.
      return new ModifiedResourceResponse($originalEntity, 200);
    }
    catch (EntityStorageException $e) {
      throw new HttpException(500, 'Internal Server Error', $e);
    }
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
        t('The QAShot Test with ID @id was not found', [
          '@id' => $entityId,
        ])
      );
    }

    return $entity;
  }

  /**
   * Verifies that the whole entity does not violate any validation constraints.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   If validation errors are found.
   */
  protected function validate(EntityInterface $entity) {
    // @todo Remove when https://www.drupal.org/node/2164373 is committed.
    if (!$entity instanceof FieldableEntityInterface) {
      return;
    }
    $violations = $entity->validate();

    // Remove violations of inaccessible fields as they cannot stem from our
    // changes.
    $violations->filterByFieldAccess();

    if (count($violations) > 0) {
      $message = "Unprocessable Entity: validation failed.\n";
      foreach ($violations as $violation) {
        $message .= $violation->getPropertyPath() . ': ' . $violation->getMessage() . "\n";
      }
      // Instead of returning a generic 400 response we use the more specific
      // 422 Unprocessable Entity code from RFC 4918. That way clients can
      // distinguish between general syntax errors in bad serializations (code
      // 400) and semantic errors in well-formed requests (code 422).
      throw new HttpException(422, $message);
    }
  }

  /**
   * Generates a fallback access denied message, when no specific reason is set.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   * @param string $operation
   *   The disallowed entity operation.
   *
   * @return string
   *   The proper message to display in the AccessDeniedHttpException.
   */
  protected function generateFallbackAccessDeniedMessage(EntityInterface $entity, $operation): string {
    $message = "You are not authorized to {$operation} this {$entity->getEntityTypeId()} entity";

    if ($entity->bundle() !== $entity->getEntityTypeId()) {
      $message .= " of bundle {$entity->bundle()}";
    }
    return "{$message}.";
  }

}
