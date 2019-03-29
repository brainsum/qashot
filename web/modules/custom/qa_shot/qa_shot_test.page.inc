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
use Drupal\qa_shot\Component\Render\RemoteReportPathMarkup;
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
 *
 * @throws \Drupal\qa_shot\Exception\QAShotBaseException
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
    '#title' => \t('State in queue'),
    '#markup' => '<div class="field--item">' . $qaShotTest->getHumanReadableQueueStatus() . '</div>',
  ];

  if ($reportPath = $qaShotTest->getHtmlReportPath()) {
    $lastRun = $qaShotTest->getLastRunMetadataValue();
    $reportTime = empty($lastRun) ? '' : \end($lastRun)['datetime'];
    $variables['report_path'] = (new ReportPathMarkup($reportPath, $reportTime))->getLink();
  }

  if ($remoteReportPath = $qaShotTest->getRemoteHtmlReportPath()) {
    $lastRun = $qaShotTest->getLastRunMetadataValue();
    $reportTime = empty($lastRun) ? '' : \end($lastRun)['datetime'];
    $variables['remote_report_path'] = (new RemoteReportPathMarkup($remoteReportPath, $reportTime))->getLink();
  }

  list($lastRunTime, $lastReferenceRunTime, $lastTestRunTime) = \array_values($qaShotTest->getLastRunTimes());
  if ($lastRunTime) {
    $variables['last_run_time'] = [
      '#type' => 'item',
      '#title' => \t('Last run was at'),
      '#markup' => '<div class="field--item">' . $lastRunTime . '</div>',
    ];
  }
  if ($lastReferenceRunTime) {
    $variables['last_reference_run_time'] = [
      '#type' => 'item',
      '#title' => \t('Reference was created at'),
      '#markup' => '<div class="field--item">' . $lastReferenceRunTime . '</div>',
    ];
  }
  if ($lastTestRunTime) {
    $variables['last_test_run_time'] = [
      '#type' => 'item',
      '#title' => \t('Last test was run at'),
      '#markup' => '<div class="field--item">' . $lastTestRunTime . '</div>',
    ];
  }

  $entityType = $qaShotTest->getType();
  if ($entityType === 'a_b') {
    $variables['run_path'] = [
      '#title' => 'Add test to queue',
      '#type' => 'link',
      '#url' => Url::fromRoute('entity.qa_shot_test.add_to_queue', [
        'qa_shot_test' => $qaShotTest->id(),
      ], $urlOptions),
      '#attributes' => $runAttributes,
    ];
  }
  elseif ($entityType === 'before_after') {
    $variables['run_path_reference'] = [
      '#title' => 'Queue taking "Before" shots',
      '#type' => 'link',
      '#url' => Url::fromRoute('entity.qa_shot_test.add_to_queue_reference', [
        'qa_shot_test' => $qaShotTest->id(),
      ], $urlOptions),
      '#attributes' => $runAttributes,
    ];

    $testResults = $qaShotTest->getLifetimeMetadataValue();
    if (\count($testResults) > 0) {
      $variables['run_path_test'] = [
        '#title' => 'Queue taking "After" shots',
        '#type' => 'link',
        '#url' => Url::fromRoute('entity.qa_shot_test.add_to_queue_test', [
          'qa_shot_test' => $qaShotTest->id(),
        ], $urlOptions),
        '#attributes' => $runAttributes,
      ];
    }
  }

  $user = \Drupal::currentUser();
  if ($user->hasPermission('view qa_shot debug data') || $user->id() === 1) {
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = \Drupal::service('renderer');
    // Debug data.
    $debugPath = PrivateStream::basePath() . '/qa_test_data/' . $qaShotTest->id() . '/debug';

    if (\is_dir($debugPath)) {
      $files = \scandir($debugPath, SCANDIR_SORT_DESCENDING);
      $rows = [];

      foreach ($files as $file) {
        if (!\in_array($file, ['.', '..'], TRUE)) {
          $fileNameFragments = \explode('.', $file);
          $rawTimestamp = \trim($fileNameFragments[0]);
          $fileDate = (new \DateTime())->setTimestamp((int) $rawTimestamp)
            ->format('Y-m-d H:i:s');
          $command = $fileNameFragments[1] ?? 'unknown';

          // @todo: Update displayed name.
          $rows[] = [
            'file' => Link::fromTextAndUrl(
              "{$fileDate}, {$command} command",
              Url::fromRoute('entity.qa_shot_test.debug', [
                'qa_shot_test' => $qaShotTest->id(),
                'file_name' => \urlencode($file),
              ])
            ),
          ];
        }
      }

      $debug = [
        '#type' => 'table',
        '#header' => [
          'file' => \t('Debug file'),
        ],
        '#rows' => $rows,
      ];
      $debugGroup = [
        '#type' => 'details',
        '#title' => \t('Debug data'),
        'metadata' => $debug,
      ];
      $variables['debug'] = $renderer->render($debugGroup);
    }

    // Metadata.
    $header = [
      'id' => \t('ID'),
      'stage' => \t('Stage'),
      'viewport_count' => \t('Viewport count'),
      'scenario_count' => \t('Scenario count'),
      'datetime' => \t('Date/Time of run'),
      'duration' => \t('Duration of run'),
      'passed_count' => \t('Passed count'),
      'failed_count' => \t('Failed count'),
      'pass_rate' => \t('Pass rate'),
      'contains_result' => \t('Is result?'),
      'success' => \t('Success?'),
    ];

    $rows = [];
    foreach ($qaShotTest->getLifetimeMetadataValue() as $data) {
      $tmp = [];
      foreach (\array_keys($header) as $key) {
        switch ($key) :
          case 'stage':
            $tmp[$key] = $data[$key] ?? '-';
            break;

          case 'duration':
            $element = [
              '#type' => 'html_tag',
              '#tag' => 'span',
              '#value' => (new \DateTime())->setTime(0, 0, \round($data[$key]))
                ->format('H:i:s'),
              '#attributes' => [
                'title' => t('@seconds seconds', [
                  '@seconds' => $data[$key],
                ]),
              ],
            ];
            $tmp[$key] = $renderer->render($element);
            break;

          case 'contains_result':
          case 'success':
            $tmp[$key] = (1 === (int) $data[$key]) ? \t('Yes') : \t('No');
            break;

          case 'pass_rate':
            $tmp[$key] = \t('@percent%', [
              '@percent' => $data[$key] * 100,
            ]);
            break;

          default:
            $tmp[$key] = $data[$key];
        endswitch;
      }
      $rows[] = $tmp;
    }

    $metadata = [
      '#type' => 'table',
      '#header' => \array_values($header),
      '#rows' => \array_reverse($rows),
    ];
    $metadataGroup = [
      '#type' => 'details',
      '#title' => \t('Metadata'),
      'metadata' => $metadata,
    ];
    $variables['metadata'] = $renderer->render($metadataGroup);
  }
}
