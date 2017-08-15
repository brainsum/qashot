<?php

namespace Drupal\qa_shot_rest_api\Normalizer;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Validation\Plugin\Validation\Constraint\CountConstraint;
use Drupal\qa_shot\Entity\QAShotTestInterface;
use Drupal\serialization\Normalizer\ComplexDataNormalizer;
use Drupal\serialization\Normalizer\FieldableEntityNormalizerTrait;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Class QAShotTestNormalizer.
 *
 * @package Drupal\qa_shot_rest_api\Normalizer
 */
class QAShotTestNormalizer extends ComplexDataNormalizer implements DenormalizerInterface {

  use FieldableEntityNormalizerTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Entity type repository manager.
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
   *   Entity type repository manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   Entity field manager.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    EntityTypeRepositoryInterface $entityTypeRepository,
    EntityFieldManagerInterface $entityFieldManager
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityTypeRepository = $entityTypeRepository;
    $this->entityFieldManager = $entityFieldManager;

    $this->supportedInterfaceOrClass = [QAShotTestInterface::class];
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function normalize($object, $format = NULL, array $context = []) {
    $attributes = [];
    /** @var array $object */
    /** @var \Drupal\Core\TypedData\TypedDataInterface $field */
    foreach ($object as $name => $field) {
      // @fixme This is just a hotfix.
      if ($name === 'result') {
        $attributes[$name] = $this->serializer->normalize($this->computeResultField($field), $format, $context + ['qa_shot_field_name' => $name]);
        continue;
      }

      $countConstraint = -1;
      foreach ($field->getConstraints() as $constraint) {
        if (get_class($constraint) === CountConstraint::class) {
          /** @var \Drupal\Core\Validation\Plugin\Validation\Constraint\CountConstraint $constraint */
          $countConstraint = $constraint->max;
          break;
        }
      }
      // Single item arrays should only contain the value.
      $value = $this->serializer->normalize($field, $format, $context + ['qa_shot_field_name' => $name]);
      // @todo FIXME
      if ($name === 'frontend_url') {
        $value = empty($value) ? '' : $value;
      }
      if ($countConstraint === 1 && is_array($value) && count($value) === 1) {
        $value = reset($value);
      }

      $attributes[$name] = $value;
    }
    return $attributes;
  }

  /**
   * Hotfix.
   *
   * @param $result
   *   The result field.
   *
   * @return array
   *   The computed field values as an array.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  private function computeResultField($result): array {
    $computedValue = [];

    /** @var \Drupal\qa_shot\Plugin\Field\FieldType\Result $item */
    foreach ($result as $delta => $item) {
      /** @var \Drupal\Core\TypedData\TypedDataInterface $property */
      foreach ($item->getProperties(TRUE) as $name => $property) {
        $computedValue[$delta][$name] = $property->getValue();
      }
    }

    return $computedValue;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Symfony\Component\Serializer\Exception\UnexpectedValueException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\Exception\AmbiguousEntityClassException
   * @throws \Drupal\Core\Entity\Exception\NoCorrespondingEntityClassException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   * @throws \LogicException
   * @throws \InvalidArgumentException
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    $entityTypeId = $this->determineEntityTypeId($class, $context);
    $entityTypeDefinition = $this->getEntityTypeDefinition($entityTypeId);

    // The bundle property will be required to denormalize a bundleable
    // fieldable entity.
    if ($entityTypeDefinition->hasKey('bundle') && $entityTypeDefinition->entityClassImplements(FieldableEntityInterface::class)) {
      // Get an array containing the bundle only. This also remove the bundle
      // key from the $data array.
      $bundleData = $this->extractBundleData($data, $entityTypeDefinition);

      // Create the entity from bundle data only, then apply field values after.
      $entity = $this->entityTypeManager->getStorage($entityTypeId)->create($bundleData);

      $this->denormalizeFieldData($data, $entity, $format, $context);
    }
    else {
      // Create the entity from all data.
      $entity = $this->entityTypeManager->getStorage($entityTypeId)->create($data);
    }

    // Pass the names of the fields whose values can be merged.
    // @todo https://www.drupal.org/node/2456257 remove this.
    $entity->_restSubmittedFields = array_keys($data);

    return $entity;
  }

  /**
   * Determines the entity type ID to denormalize as.
   *
   * @param string $class
   *   The entity type class to be denormalized to.
   * @param array $context
   *   The serialization context data.
   *
   * @throws \Drupal\Core\Entity\Exception\AmbiguousEntityClassException
   * @throws \Drupal\Core\Entity\Exception\NoCorrespondingEntityClassException
   *
   * @return string
   *   The entity type ID.
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
   * @throws \Symfony\Component\Serializer\Exception\UnexpectedValueException
   * @throws \LogicException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function extractBundleData(array &$data, EntityTypeInterface $entityTypeDefinition): array {
    $bundleKey = $entityTypeDefinition->getKey('bundle');
    // Get the base field definitions for this entity type.
    $baseFieldDefinitions = $this->entityFieldManager->getBaseFieldDefinitions($entityTypeDefinition->id());

    // Get the ID key from the base field definition for the bundle key or
    // default to 'value'.
    $keyId = isset($baseFieldDefinitions[$bundleKey]) ? $baseFieldDefinitions[$bundleKey]->getFieldStorageDefinition()->getMainPropertyName() : 'value';

    // Normalize the bundle if it is not explicitly set.
    $bundleValue = isset($data[$bundleKey][0][$keyId]) ? $data[$bundleKey][0][$keyId] : (isset($data[$bundleKey]) ? $data[$bundleKey] : NULL);
    // Unset the bundle from the data.
    unset($data[$bundleKey]);

    // Get the bundle entity type from the entity type definition.
    $bundleTypeId = $entityTypeDefinition->getBundleEntityType();
    $bundleTypes = $bundleTypeId ? $this->entityTypeManager->getStorage($bundleTypeId)->getQuery()->execute() : [];

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

  /**
   * Denormalizes entity data by denormalizing each field individually.
   *
   * @param array $data
   *   The data to denormalize.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The fieldable entity to set field values for.
   * @param string $format
   *   The serialization format.
   * @param array $context
   *   The context data.
   *
   * @throws \InvalidArgumentException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  protected function denormalizeFieldData(array $data, FieldableEntityInterface $entity, $format, array $context) {
    foreach ($data as $fieldName => $fieldData) {
      $fieldItemList = $entity->get($fieldName);

      // Remove any values that were set as a part of entity creation (e.g
      // uuid). If the incoming field data is set to an empty array, this will
      // also have the effect of emptying the field in REST module.
      $fieldItemList->setValue([]);
      $fieldClass = get_class($fieldItemList);

      if ($fieldData) {
        // The field instance must be passed in the context so that the field
        // denormalizer can update field values for the parent entity.
        $context['qa_shot_field_name'] = $fieldName;
        $context['target_instance'] = $fieldItemList;
        $this->serializer->denormalize($fieldData, $fieldClass, $format, $context);
      }
    }
  }

}
