<?php

namespace Drupal\qa_shot\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Class DataFormatter.
 *
 * @package Drupal\qa_shot\Service
 */
class DataFormatter {

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * DataFormatter constructor.
   *
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter.
   */
  public function __construct(TimeInterface $time, DateFormatterInterface $dateFormatter) {
    $this->time = $time;
    $this->dateFormatter = $dateFormatter;
  }

  /**
   * Formats a timestamp as a time interval.
   *
   * @param int $timestamp
   *   The timestamp.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The formatted date/time string using the past or future format setting.
   *
   * @throws \InvalidArgumentException
   */
  public function timestampAsAgo($timestamp): MarkupInterface {
    $date = DrupalDateTime::createFromTimestamp($timestamp);
    return $this->dateAsAgo($date);
  }

  /**
   * Formats a date/time as a time interval.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $date
   *   A date/time object.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The formatted date/time string using the past or future format setting.
   *
   * @see \Drupal\datetime\Plugin\Field\FieldFormatter\DateTimeTimeAgoFormatter::formatDate()
   */
  public function dateAsAgo(DrupalDateTime $date): MarkupInterface {
    $granularity = 2;
    $timestamp = $date->getTimestamp();
    $options = [
      'granularity' => $granularity,
      'return_as_object' => TRUE,
    ];

    $requestTime = $this->time->getRequestTime();

    if ($requestTime > $timestamp) {
      $result = $this->dateFormatter->formatTimeDiffSince($timestamp, $options);
      $build = new FormattableMarkup('@interval ago', ['@interval' => $result->getString()]);
    }
    else {
      $result = $this->dateFormatter->formatTimeDiffUntil($timestamp, $options);
      $build = new FormattableMarkup('@interval hence', ['@interval' => $result->getString()]);
    }

    return $build;
  }

}
