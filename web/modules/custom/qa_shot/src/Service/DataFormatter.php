<?php

namespace Drupal\qa_shot\Service;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Class DataFormatter.
 *
 * @package Drupal\qa_shot\Service
 */
class DataFormatter {

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

    $requestTime = \Drupal::time()->getRequestTime();
    /** @var \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter */
    $dateFormatter = \Drupal::service('date.formatter');

    if ($requestTime > $timestamp) {
      $result = $dateFormatter->formatTimeDiffSince($timestamp, $options);
      $build = new FormattableMarkup('@interval ago', ['@interval' => $result->getString()]);
    }
    else {
      $result = $dateFormatter->formatTimeDiffUntil($timestamp, $options);
      $build = new FormattableMarkup('@interval hence', ['@interval' => $result->getString()]);
    }

    return $build;
  }

}
