<?php

/**
 * @file
 * Contains qa_shot_test.page.inc.
 *
 * Page callback for QAShot Test entities.
 */

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Link;
use Drupal\Core\Render\Element;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\Core\Url;
use Drupal\qa_shot\Component\Render\ReportPathMarkup;

/**
 * Prepares variables for QAShot Test templates.
 *
 * Default template: qa_shot_test.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_qa_shot_test(array &$variables) {
  // Fetch QAShotTest Entity Object.
  /** @var \Drupal\qa_shot\Entity\QAShotTest $qaShotTest */
  $qaShotTest = $variables['elements']['#qa_shot_test'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }

  $urlOptions = ['absolute' => TRUE];

  $attributes = [
    'class' => [
      'btn',
      'button',
    ],
    'role' => ['button'],
  ];

  $runAttributes = $attributes;
  $runAttributes['class'][] = 'btn-primary';

  $variables['status_in_queue'] = [
    '#type' => 'item',
    '#title' => t('State in queue'),
    '#markup' => "<div class=\"field--item\">" . $qaShotTest->getHumanReadableQueueStatus() . "</div>",
  ];

  if ($reportPath = $qaShotTest->getHtmlReportPath()) {
    $lastRun = $qaShotTest->getLastRunMetadataValue();
    $reportTime = empty($lastRun) ? '' : end($lastRun)['datetime'];
    $variables['report_path'] = (new ReportPathMarkup($reportPath, $reportTime))->getLink();
  }

  $entity_type = $variables['elements']['#qa_shot_test']->getType();
  if ($entity_type == "a_b") {
    $variables['run_path'] = [
      '#title'      => 'Add test to queue',
      '#type'       => 'link',
      '#url'        => Url::fromRoute('entity.qa_shot_test.add_to_queue', [
        'qa_shot_test' => $qaShotTest->id(),
      ], $urlOptions),
      '#attributes' => $runAttributes,
    ];
  }
  elseif ($entity_type == "before_after") {
    $variables['run_path_reference'] = [
      '#title'      => 'Queue taking "Before" shots',
      '#type'       => 'link',
      '#url'        => Url::fromRoute('entity.qa_shot_test.add_to_queue_reference', [
        'qa_shot_test' => $qaShotTest->id(),
      ], $urlOptions),
      '#attributes' => $runAttributes,
    ];

    $test_results = $variables['elements']['#qa_shot_test']->getLifetimeMetadataValue();
    if (count($test_results) > 0) {
      $variables['run_path_test'] = [
        '#title'      => 'Queue taking "After" shots',
        '#type'       => 'link',
        '#url'        => Url::fromRoute('entity.qa_shot_test.add_to_queue_test', [
          'qa_shot_test' => $qaShotTest->id(),
        ], $urlOptions),
        '#attributes' => $runAttributes,
      ];
    }
  }

  $user = \Drupal::currentUser();
  if ($user->hasPermission('view qa_shot debug data') || $user->id() === 1) {
    $debugPath = PrivateStream::basePath() . '/qa_test_data/' . $qaShotTest->id() . '/debug';

    if (is_dir($debugPath)) {
      $iterator = new \RecursiveDirectoryIterator($debugPath, \RecursiveDirectoryIterator::SKIP_DOTS);
      $files = new \RecursiveIteratorIterator($iterator,
        \RecursiveIteratorIterator::CHILD_FIRST);

      $rows = [];
      /** @var \SplFileInfo $file */
      foreach ($files as $file) {
        if (!$file->isDir()) {
          $filename = trim(explode('.', $file->getFilename())[0]);
          $filename = (new \DateTime())->setTimestamp((int) $filename)->format('Y-m-d H:i:s');

          $rows[] = [
            'file' => Link::fromTextAndUrl(
              $filename,
              Url::fromRoute('entity.qa_shot_test.debug', [
                'qa_shot_test' => $qaShotTest->id(),
                'file_name' => urlencode($file->getFilename()),
              ])
            ),
          ];
        }
      }

      $debug = [
        '#type' => 'table',
        '#caption' => t('Debug data'),
        '#header' => [
          'file' => t('Debug file'),
        ],
        '#rows' => $rows,
      ];

      /** @var \Drupal\Core\Render\RendererInterface $renderer */
      $renderer = \Drupal::service('renderer');
      $variables['debug'] = $renderer->render($debug);
    }
  }
}
