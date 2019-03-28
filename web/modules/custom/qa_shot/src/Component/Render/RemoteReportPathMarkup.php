<?php

namespace Drupal\qa_shot\Component\Render;

use Countable;
use Drupal;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use function strlen;

/**
 * Class RemoteReportPathMarkup.
 *
 * Designed to create formatted markup for the HTML report path with
 * proper '..ago' time notation.
 *
 * @package Drupal\qa_shot\Component\Render
 */
class RemoteReportPathMarkup implements MarkupInterface, Countable {

  use StringTranslationTrait;

  /**
   * The path as a string.
   *
   * @var string
   */
  protected $path;

  /**
   * The time of the report.
   *
   * @var string
   */
  protected $time;

  /**
   * The data formatter service.
   *
   * @var \Drupal\qa_shot\Service\DataFormatter
   */
  protected $dataFormatter;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * ReportPathMarkup constructor.
   *
   * @param string|null $reportPath
   *   The path as a string.
   * @param string|null $reportTime
   *   The time of the report.
   */
  public function __construct($reportPath = NULL, $reportTime = NULL) {
    $this->path = $reportPath ?? '';
    $this->time = $reportTime ?? '';
    $this->dataFormatter = Drupal::service('qa_shot.data_formatter');
    $this->currentUser = Drupal::currentUser();
  }

  /**
   * Return the path with timestamp as a link.
   *
   * @return array
   *   Render array.
   *
   * @throws \InvalidArgumentException
   */
  public function getLink(): array {
    if ($this->path === NULL) {
      return [];
    }

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
      '#title' => $this->t('Remote HTML Report'),
      '#url' => Url::fromUri($this->path, $urlOptions),
      '#attributes' => $attributes,
    ];

    if ('' === $this->time) {
      $markup['#disabled'] = TRUE;
    }
    else {
      $reportDateTime = new DrupalDateTime($this->time, $this->currentUser->getTimeZone(), [
        'langcode' => $this->currentUser->getPreferredLangcode(),
      ]);
      $reportTime = $this->dataFormatter->dateAsAgo($reportDateTime);
      $markup['#title'] = $this->t('Remote HTML Report from @timestamp', [
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
  public function __toString(): string {
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
