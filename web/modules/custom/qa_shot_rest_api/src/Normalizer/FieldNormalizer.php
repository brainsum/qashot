<?php

namespace Drupal\qa_shot_rest_api\Normalizer;

use Drupal;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\serialization\Normalizer\ListNormalizer;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Denormalizes data to Drupal field values.
 *
 * This class simply calls denormalize() on the individual FieldItems. The
 * FieldItem normalizers are responsible for setting the field values for each
 * item.
 *
 * Modified copy of Drupal\serialization\NormalizerFieldNormalizer.
 *
 * @see \Drupal\serialization\Normalizer\FieldItemNormalizer.
 */
class FieldNormalizer extends ListNormalizer implements DenormalizerInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * ItemNormalizer constructor.
   *
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
   */
  public function __construct() {
    $this->supportedInterfaceOrClass = [FieldItemListInterface::class];
    // @codingStandardsIgnoreStart
    // @todo: Find a way to properly inject this service.
    $this->entityTypeManager = Drupal::entityTypeManager();
    // @codingStandardsIgnoreEnd
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    if (!isset($context['target_instance'])) {
      throw new InvalidArgumentException("\$context['target_instance'] must be set to denormalize with the FieldNormalizer.");
    }
    if (!isset($context['qa_shot_field_name'])) {
      throw new InvalidArgumentException("\$context['qa_shot_field_name'] must be set to denormalize with the FieldNormalizer.");
    }
    if ($context['target_instance']->getParent() === NULL) {
      throw new InvalidArgumentException("The field passed in via \$context['target_instance'] must have a parent set.");
    }

    /** @var \Drupal\Core\Field\FieldItemListInterface $items */
    $items = $context['target_instance'];
    $itemClass = $items->getItemDefinition()->getClass();
    $fieldName = $context['qa_shot_field_name'];

    // @fixme: Not elegant, but works.
    if (!is_array($data)) {
      $key = 'value';
      if ($fieldName === 'user_id') {
        $key = 'target_id';
      }
      $data = [[$key => $data]];
    }
    // Create or load tags by name.
    if ($fieldName === 'field_tag') {
      /** @var \Drupal\taxonomy\TermStorageInterface $termStorage */
      $termStorage = $this->entityTypeManager->getStorage('taxonomy_term');
      $termData = [];
      foreach ($data as $termName) {
        // Load or create term.
        /** @var \Drupal\Core\Entity\EntityInterface[] $terms */
        // Try loading the term by name.
        $terms = $termStorage->loadByProperties([
          'vid' => 'tag',
          'name' => $termName,
        ]);
        // If not there, create it.
        if (empty($terms)) {
          $term = $termStorage->create(['vid' => 'tag', 'name' => $termName]);
        }
        // Otherwise, use the first existing one.
        else {
          $term = reset($terms);
        }

        $termData[] = $term;
      }

      $data = $termData;
    }

    /** @var array $data */
    foreach ($data as $itemData) {
      // Create a new item and pass it as the target for the unserialization of
      // $item_data. All items in field should have removed before this method
      // was called.
      // @see \Drupal\serialization\Normalizer\ContentEntityNormalizer::denormalize().
      $context['target_instance'] = $items->appendItem();
      $this->serializer->denormalize($itemData, $itemClass, $format, $context);
    }
    return $items;
  }

}
