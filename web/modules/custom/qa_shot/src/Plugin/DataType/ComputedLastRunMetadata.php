<?php

namespace Drupal\qa_shot\Plugin\DataType;

use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedData;
use Drupal\Core\TypedData\TypedDataInterface;

/**
 * Class ComputedLastRunMetadata.
 *
 * @package Drupal\qa_shot\Plugin\DataType
 */
class ComputedLastRunMetadata extends TypedData {

  /**
   * Cached processed url.
   *
   * @var string|null
   */
  protected $processed;

  const SETTING_NAME = 'data source';

  /**
   * {@inheritdoc}
   *
   * @throws \InvalidArgumentException
   */
  public function __construct(DataDefinitionInterface $definition, $name = NULL, TypedDataInterface $parent = NULL) {
    parent::__construct($definition, $name, $parent);

    if ($definition->getSetting($this::SETTING_NAME) === NULL) {
      throw new \InvalidArgumentException("The definition's '" . $this::SETTING_NAME . "' key has to specify the name of the url property to be processed.");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    if ($this->processed !== NULL) {
      return $this->processed;
    }

    /** @var \Drupal\Core\Entity\Plugin\DataType\EntityAdapter $parent */
    $parent = $this->getParent();
    $property = $parent->get($this->definition->getSetting($this::SETTING_NAME));
    $data = $property->getValue();
    $bundle = $parent->get('type')->target_id;

    if (empty($data)) {
      $this->processed = [];
    }
    else {
      $this->processed = $this->getLastItems($data, $bundle);
    }

    return $this->processed;
  }

  /**
   * Get the last run metadata from the lifetime data.
   *
   * @param array $data
   *   The lifetime metadata.
   *
   * @return array
   *   The last run metadata.
   */
  private function getLastItems(array $data): array {
    $result = [];

    // Get the latest metadata.
    $latestMetadata = end($data);
    // The latest element is needed for sure, we add it to the results.
    $result[] = $latestMetadata;

    // Iterate the $data array backwards.
    // This should ensure the following:
    // * Every stage exists only once.
    // * Inconsistencies can't happen (if 'run' is properly implemented).
    $stages = [$latestMetadata['stage']];
    while (FALSE !== prev($data)) {
      $item = current($data);
      // If a stage is already added, we reached the termination point.
      if (in_array($item['stage'], $stages, FALSE)) {
        // There shouldn't be another item with this stage value, so break.
        break;
      }
      // Otherwise, cache the stage and add the data to the results.
      $stages[] = $item['stage'];
      $result[] = $data;
    }

    // Sort results by ID.
    // This way, the results are in chronological order.
    // Note: 'datetime' could also be used, but ID should be faster (even
    // if it's just an unnoticeable increase in speed).
    usort($result, [$this, 'compareMetadata']);

    return $result;
  }

  /**
   * Helper callback function to compare two arrays of metadata by ID.
   *
   * Used with usort.
   *
   * @param array $first
   *   The first array.
   * @param array $second
   *   The second array.
   *
   * @return int
   *   The retun value.
   */
  public function compareMetadata(array $first, array $second): int {
    if ($first['id'] === $second['id']) {
      return 0;
    }

    return ($first['id'] < $second['id']) ? -1 : 1;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($value, $notify = TRUE) {
    $this->processed = $value;
    // Notify the parent of any changes.
    if ($notify && isset($this->parent)) {
      $this->parent->onChange($this->name);
    }
  }

  /**
   * This is required for some reason.
   */
  public function setLangcode() {}

  /**
   * This is required for some reason.
   */
  public function getLangcode() {}

}
