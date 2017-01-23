<?php

namespace Drupal\qa_shot\Plugin\rest\resource;

use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
 * @package Drupal\qa_shot\Plugin\rest\resource
 *
 * @RestResource(
 *   id = "entity:qa_shot_test",
 *   label = @Translation("QAShot Test Entity"),
 *   deriver = "Drupal\rest\Plugin\Deriver\EntityDeriver",
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
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    // @todo: handle \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
    // @todo: handle \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
    // @todo: handle \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException

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
  public function calculateDependencies() {
    if (NULL !== $this->entityType) {
      return ['module' => [$this->entityType->getProvider()]];
    }
  }

  /**
   * Handles GET requests.
   *
   * @param int $qaShotTest
   *   The ID of the entity.
   *
   * @throws NotFoundHttpException
   * @throws BadRequestHttpException
   *
   * @return ResourceResponse
   *   The response.
   */
  public function get($qaShotTest) {
    if (!is_numeric($qaShotTest)) {
      throw new BadRequestHttpException(
        t('The supplied parameter ( @param ) is not valid.', [
          '@param' => $qaShotTest,
        ])
      );
    }

    /** @var \Drupal\qa_shot\Entity\QAShotTest $entity */
    $entity = $this->testStorage->load($qaShotTest);

    if (NULL === $entity) {
      throw new NotFoundHttpException(
        t('The QAShot Test with ID @id was not found', array(
          '@id' => $qaShotTest,
        ))
      );
    }

    $response = new ResourceResponse($entity, 200);
    $response->addCacheableDependency($entity);

    return $response;
  }

  /**
   * Responds to entity POST requests and saves the new entity.
   *
   * @param QAShotTestInterface $entity
   *   The entity received in the request.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   */
  public function post(QAShotTestInterface $entity) {
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }

    try {
      $entity->save();
    }
    catch (EntityStorageException $e) {
      return new ResourceResponse($e->getMessage(), 400);
    }


    return new ResourceResponse($entity, 200);



    /*
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
    foreach ($entity->_restSubmittedFields as $key => $field_name) {
      if (!$entity->get($field_name)->access('edit')) {
        throw new AccessDeniedHttpException("Access denied on creating field '$field_name'");
      }
    }

    // Validate the received data before saving.
    $this->validate($entity);
    try {
      $entity->save();
      $this->logger->notice('Created entity %type with ID %id.', array('%type' => $entity->getEntityTypeId(), '%id' => $entity->id()));

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
    */
  }

  public function delete() {
    return new ResourceResponse('Placeholder.', 200);
  }

  public function patch() {
    return new ResourceResponse('Placeholder.', 200);
  }

}
