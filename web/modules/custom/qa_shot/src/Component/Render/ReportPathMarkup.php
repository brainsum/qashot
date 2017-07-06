<?php

namespace Drupal\qa_shot\Component\Render;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Class ReportPathMarkup.
 *
 * @package Drupal\qa_shot\Component\Render
 */
class ReportPathMarkup implements MarkupInterface, \Countable {

  use StringTranslationTrait;

  /**
   * @var string
   */
  protected $path;

  /**
   * @var string
   */
  protected $time;

  /**
   * ReportPathMarkup constructor.
   *
   * @param string|null $reportPath
   * @param string|null $reportTime
   */
  public function __construct($reportPath = NULL, $reportTime = NULL) {
    $this->path = (NULL === $reportPath) ? '' : $reportPath;
    $this->time = (NULL === $reportTime) ? '' : $reportTime;
  }

  public function getLink() {
    $urlOptions = ['absolute' => TRUE];

    $attributes = [
      'class' => [
        'btn',
        'button',
        'btn-info',
      ],
      'role' => ['button'],
      'target' => '_blank',
      'rel' => 'noopener',
    ];

    $markup = [
      '#type' => 'link',
      '#title' => $this->t('HTML Report'),
      '#url' => Url::fromUserInput('/' . $this->path, $urlOptions),
      '#attributes' => $attributes,
    ];

    if ('' === $this->time) {
      $markup['#disabled'] = TRUE;
    }
    else {
      /** @var \Drupal\qa_shot\Service\DataFormatter $service */
      $dataFormatter = \Drupal::service('qa_shot.data_formatter');
      $reportDateTime = new DrupalDateTime($this->time);
      $reportTime = $dataFormatter->dateAsAgo($reportDateTime);
      $markup['#title'] = $this->t('HTML Report from @timestamp', [
        '@timestamp' => $reportTime,
      ]);
      $markup['#attributes']['title'] = $reportDateTime->format('Y-m-d H:i:s');
    }

    return $markup;
  }

  /**
   * Count elements of an object.
   *
   * @link http://php.net/manual/en/countable.count.php
   *
   * @return int
   *   The return value is cast to an integer.
   */
  public function count(): int {
    return strlen($this->path);
  }

  /**
   * Returns markup.
   *
   * @return string
   *   The markup.
   */
  public function __toString() {
    return (string) $this->path;
  }

  /**
   * Specify data which should be serialized to JSON.
   *
   * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
   *
   * @return string
   *   Mixed data which can be serialized by json_encode,
   *   which is a value of any type other than a resource.
   */
  public function jsonSerialize(): string {
    return $this->path;
  }

}
