<?php

namespace Drupal\qa_shot\Plugin\DataType;

use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedData;
use Drupal\Core\TypedData\TypedDataInterface;

/**
 * Class ComputedScreenshotPath.
 *
 * @package Drupal\qa_shot\Plugin\DataType
 */
class ComputedScreenshotPath extends TypedData {

  /**
   * Cached processed url.
   *
   * @var string|null
   */
  protected $processed;

  const SETTING_NAME = 'url source';

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

    $item = $this->getParent();
    $url = $item->{$this->definition->getSetting($this::SETTING_NAME)};

    // Avoid running check_markup() on empty strings.
    if (!isset($url) || $url === '') {
      $this->processed = '';
      return $this->processed;

    }

    $parsed = \parse_url($url);
    if (isset($parsed['host'])) {
      $this->processed = $url;
      return $this->processed;
    }

    $this->processed = PublicStream::baseUrl() . '/qa_test_data/' . $url;
    return $this->processed;
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

}
