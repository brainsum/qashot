<?php

/**
 * @file
 * Contains qa_shot_test.page.inc.
 *
 * Page callback for QAShot Test entities.
 */

use Drupal\Core\Render\Element;
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
  $reportAttributes = [
    'class' => [
      'btn',
      'button',
      'btn-info',
    ],
    'role' => ['button'],
  ];

  $runAttributes = [
    'class' => [
      'btn',
      'button',
      'btn-primary',
    ],
    'role' => ['button'],
  ];

  $variables['report_path'] = [
    '#title' => 'HTML Report Path',
    '#type' => 'link',
    '#url' => Url::fromUserInput('/' . $qaShotTest->getHtmlReportPath(), $urlOptions),
    '#attributes' => $reportAttributes,
  ];

  $variables['run_path'] = [
    '#title' => 'Run test',
    '#type' => 'link',
    '#url' => Url::fromRoute('entity.qa_shot_test.run', [
      'qa_shot_test' => $qaShotTest->id(),
    ], $urlOptions + ['query' => ['start_now' => TRUE]]),
    '#attributes' => $runAttributes,
  ];
}
