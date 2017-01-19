<?php

namespace Drupal\qa_shot\Plugin\rest\resource;

use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\rest\ResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
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
      $container->get('config.factory')
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
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    array $serializer_formats,
    LoggerInterface $logger,
    ConfigFactoryInterface $config_factory
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    // @todo: handle the thrown \Drupal\Component\Plugin\Exception\PluginNotFoundException .
    $this->entityType = $entity_type_manager->getDefinition($plugin_definition['entity_type']);
    $this->testStorage = $entity_type_manager->getStorage('qa_shot_test');
    $this->configFactory = $config_factory;
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

  public function post() {
    return new ResourceResponse('Placeholder.', 200);
  }

  public function delete() {
    return new ResourceResponse('Placeholder.', 200);
  }

  public function patch() {
    return new ResourceResponse('Placeholder.', 200);
  }

}
