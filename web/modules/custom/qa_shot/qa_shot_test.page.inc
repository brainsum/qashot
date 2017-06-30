<?php

/**
 * @file
 * Contains qa_shot_test.page.inc.
 *
 * Page callback for QAShot Test entities.
 */

use Drupal\Core\Link;
use Drupal\Core\Render\Element;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\Core\Url;

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

  $reportAttributes = $attributes;
  $reportAttributes['class'][] = 'btn-info';
  $reportAttributes['target'] = ['_blank'];
  $reportAttributes['rel'] = ['noopener'];

  $runAttributes = $attributes;
  $runAttributes['class'][] = 'btn-primary';

  if ($reportPath = $qaShotTest->getHtmlReportPath()) {
    $lastRun = $qaShotTest->getLastRunMetadataValue();
    $reportTime = empty($lastRun) ? '' : end($lastRun)['datetime'];
    $variables['report_path'] = [
      '#type' => 'link',
      '#url' => Url::fromUserInput('/' . $reportPath, $urlOptions),
      '#attributes' => $reportAttributes,
    ];

    if ('' === $reportTime) {
      $variables['report_path']['#title'] = t('HTML Report');
      $variables['report_path']['#disabled'] = TRUE;
    }
    else {
      // Turn the report time into an '.. ago' string.
      $reportTime = (new \DateTime($reportTime))->getTimestamp();
      /** @var \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter */
      $dateFormatter = \Drupal::service('date.formatter');
      $currentTimestamp = \Drupal::time()->getRequestTime();
      $reportTime = 'from ' . $dateFormatter->formatDiff($currentTimestamp, $reportTime) . ' ago';

      $variables['report_path']['#title'] = t('HTML Report @timestamp', [
        '@timestamp' => $reportTime,
      ]);
    }
  }

  $variables['run_path'] = [
    '#title' => 'Add test to queue',
    '#type' => 'link',
    '#url' => Url::fromRoute('entity.qa_shot_test.add_to_queue', [
      'qa_shot_test' => $qaShotTest->id(),
    ], $urlOptions),
    '#attributes' => $runAttributes,
  ];

  $user = \Drupal::currentUser();
  if ($user->hasPermission('view qa_shot debug data') || $user->id() === 1) {
    $debugPath = PrivateStream::basePath() . '/qa_test_data/' . $qaShotTest->id() . '/debug';

    $iterator = new \RecursiveDirectoryIterator($debugPath, \RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new \RecursiveIteratorIterator($iterator,
      \RecursiveIteratorIterator::CHILD_FIRST);

    $rows = [];
    /** @var \SplFileInfo $file */
    foreach ($files as $file) {
      if (!$file->isDir()) {
        $rows[] = [
          'file' => Link::fromTextAndUrl(
            $file->getFilename(),
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
