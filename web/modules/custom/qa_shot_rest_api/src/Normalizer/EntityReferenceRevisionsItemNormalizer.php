<?php

namespace Drupal\qa_shot_rest_api\Normalizer;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\entity_reference_revisions\Plugin\Field\FieldType\EntityReferenceRevisionsItem;
use Drupal\serialization\Normalizer\ComplexDataNormalizer;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Class EntityReferenceRevisionsItemNormalizer.
 *
 * Used to normalize and denormalize EntityReferenceRevisionsItem items.
 * Most notably, the Paragraph type fields.
 *
 * @package Drupal\qa_shot_rest_api\Normalizer
 */
class EntityReferenceRevisionsItemNormalizer extends ComplexDataNormalizer implements DenormalizerInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Entity repository manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeRepositoryInterface
   */
  protected $entityTypeRepository;

  /**
   * Entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * QAShotTestNormalizer constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeRepositoryInterface $entityTypeRepository
   *   The entity type repository.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    EntityTypeRepositoryInterface $entityTypeRepository,
    EntityFieldManagerInterface $entityFieldManager
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityTypeRepository = $entityTypeRepository;
    $this->entityFieldManager = $entityFieldManager;

    $this->supportedInterfaceOrClass = [EntityReferenceRevisionsItem::class];
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($fieldItem, $format = NULL, array $context = []) {
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    if ($entity = $fieldItem->get('entity')->getValue()) {
      // Simplify the entity array representation to only the
      // id, revision_id and field_ prefixed items.
      // @codingStandardsIgnoreStart
      $simplifiedEntity = array_filter($entity->toArray(), static function ($key) {
        $isId = in_array($key, ['id', 'revision_id'], TRUE);
        $isField = (strpos($key, 'field_') === 0);

        return $isId || $isField;
      }, ARRAY_FILTER_USE_KEY);

      // Simplify the remaining fields.
      $values = array_map(static function ($value) {
        if (!is_array($value)) {
          return $value;
        }

        $itemValue = [];
        // @todo @fixme.
        foreach ($value as $item) {
          if (isset($item['uri'])) {
            $itemValue[] = $item['uri'];
          }
          else {
            $itemValue[] = $item['value'];
          }
        }

        $itemCount = count($itemValue);
        if ($itemCount < 1) {
          return NULL;
        }

        return $itemCount === 1 ? $itemValue[0] : $itemValue;
      }, $simplifiedEntity);
      // @codingStandardsIgnoreEnd
    }
    // Fallback.
    else {
      $values = parent::normalize($fieldItem, $format, $context);
    }

    // Return an array/scalar.
    return $values;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Symfony\Component\Serializer\Exception\InvalidArgumentException
   * @throws \Drupal\Core\Entity\Exception\AmbiguousEntityClassException
   * @throws \Symfony\Component\Serializer\Exception\UnexpectedValueException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\Exception\NoCorrespondingEntityClassException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   * @throws \InvalidArgumentException
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    if (!isset($context['target_instance'])) {
      throw new InvalidArgumentException('$context[\'target_instance\'] must be set to denormalize with the FieldItemNormalizer');
    }

    /** @var \Drupal\Core\Field\FieldItemListInterface $fieldItem */
    $fieldItem = $context['target_instance'];

    /** @var \Drupal\Core\TypedData\TraversableTypedDataInterface $parent */
    $parent = $fieldItem->getParent();

    if ($parent === NULL) {
      throw new InvalidArgumentException('The field item passed in via $context[\'target_instance\'] must have a parent set.');
    }

    $fieldHandlerSettings = $parent->getSettings();
    $context['entity_type'] = $fieldHandlerSettings['target_type'];

    $entityTypeId = $this->determineEntityTypeId($class, $context);
    $entityTypeDefinition = $this->getEntityTypeDefinition($entityTypeId);
    // The bundle property will be required to denormalize a bundleable
    // entity.
    if ($entityTypeDefinition->hasKey('bundle')) {
      $data['type'] = array_values($fieldHandlerSettings['handler_settings']['target_bundles'])[0];

      // Get an array containing the bundle only. This also remove the bundle
      // key from the $data array.
      $bundleData = $this->extractBundleData($data, $entityTypeDefinition);

      $bundleData = array_merge($bundleData, $data);

      // Create the entity from bundle data only,
      // then apply field values after.
      $entity = $this->entityTypeManager->getStorage($entityTypeId)
        ->create($bundleData);
    }
    else {
      // Create the entity from all data.
      $entity = $this->entityTypeManager->getStorage($entityTypeId)
        ->create($data);
    }

    // Pass the names of the fields whose values can be merged.
    // @todo https://www.drupal.org/node/2456257 remove this.
    $entity->_restSubmittedFields = array_keys($data);
    $fieldItem->setValue($entity);

    return $fieldItem;
  }

  /**
   * Determines the entity type ID to denormalize as.
   *
   * @param string $class
   *   The entity type class to be denormalized to.
   * @param array $context
   *   The serialization context data.
   *
   * @return string
   *   The entity type ID.
   *
   * @throws \Drupal\Core\Entity\Exception\NoCorrespondingEntityClassException
   *
   * @throws \Drupal\Core\Entity\Exception\AmbiguousEntityClassException
   */
  protected function determineEntityTypeId($class, array $context): string {
    // Get the entity type ID while letting context override the $class param.
    return !empty($context['entity_type']) ? $context['entity_type'] : $this->entityTypeRepository->getEntityTypeFromClass($class);
  }

  /**
   * Gets the entity type definition.
   *
   * @param string $entityTypeId
   *   The entity type ID to load the definition for.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface
   *   The loaded entity type definition.
   *
   * @throws \Symfony\Component\Serializer\Exception\UnexpectedValueException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getEntityTypeDefinition($entityTypeId): EntityTypeInterface {
    /** @var \Drupal\Core\Entity\EntityTypeInterface $entityTypeDefinition */
    // Get the entity type definition.
    $entityTypeDefinition = $this->entityTypeManager->getDefinition($entityTypeId, FALSE);

    // Don't try to create an entity without an entity type id.
    if (!$entityTypeDefinition) {
      throw new UnexpectedValueException(sprintf('The specified entity type "%s" does not exist. A valid entity type is required for denormalization', $entityTypeId));
    }

    return $entityTypeDefinition;
  }

  /**
   * Denormalizes the bundle property so entity creation can use it.
   *
   * @param array $data
   *   The data being denormalized.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entityTypeDefinition
   *   The entity type definition.
   *
   * @return array
   *   The valid bundle name.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function extractBundleData(array &$data, EntityTypeInterface $entityTypeDefinition): array {
    $bundleKey = $entityTypeDefinition->getKey('bundle');
    // Get the base field definitions for this entity type.
    $baseFieldDefinitions = $this->entityFieldManager->getBaseFieldDefinitions($entityTypeDefinition->id());

    // Get the ID key from the base field definition for the bundle key or
    // default to 'value'.
    $keyId = isset($baseFieldDefinitions[$bundleKey]) ? $baseFieldDefinitions[$bundleKey]->getFieldStorageDefinition()
      ->getMainPropertyName() : 'value';

    // Normalize the bundle if it is not explicitly set.
    $bundleValue = $data[$bundleKey][0][$keyId] ?? $data[$bundleKey] ?? NULL;
    // Unset the bundle from the data.
    unset($data[$bundleKey]);

    // Get the bundle entity type from the entity type definition.
    $bundleTypeId = $entityTypeDefinition->getBundleEntityType();
    $bundleTypes = $bundleTypeId ? $this->entityTypeManager->getStorage($bundleTypeId)
      ->getQuery()
      ->execute() : [];

    // Make sure a bundle has been provided.
    if (!is_string($bundleValue)) {
      throw new UnexpectedValueException(sprintf('Could not determine entity type bundle: "%s" field is missing.', $bundleKey));
    }

    // Make sure the submitted bundle is a valid bundle for the entity type.
    if ($bundleTypes && !in_array($bundleValue, $bundleTypes, FALSE)) {
      throw new UnexpectedValueException(sprintf('"%s" is not a valid bundle type for denormalization.', $bundleValue));
    }

    return [$bundleKey => $bundleValue];
  }

}
