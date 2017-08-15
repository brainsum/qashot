<?php

namespace Drupal\backstopjs\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Class BackstopjsSettingsForm.
 *
 * @package Drupal\backstopjs\Form
 */
class BackstopjsSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'backstopjs_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      'backstopjs.settings',
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @throws \InvalidArgumentException
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);

    $form['#tree'] = TRUE;
    /** @var \Drupal\Core\Config\ImmutableConfig $config */
    $config = $this->configFactory()->get('backstopjs.settings');

    $form['backstopjs'] = [
      '#type' => 'details',
      '#title' => t('Backstop JS'),
      '#open' => TRUE,
    ];

    $form['backstopjs']['async_compare_limit'] = [
      '#type' => 'number',
      '#title' => t('Parallel comparison limit'),
      '#description' => t('Limit the amount of parallel image comparisons. Lower value results in slower comparisons and lower RAM usage. As a (very approximate) rule of thumb, BackstopJS will use 100MB RAM plus approximately 5 MB for each concurrent image comparison.'),
      '#default_value' => $config->get('backstopjs.async_compare_limit') ?? 30,
      '#min' => 1,
      '#max' => 100,
    ];

    $form['backstopjs']['test_engine'] = [
      '#type' => 'select',
      '#title' => t('Test engine'),
      '#options' => [
        'phantomjs' => t('PhantomJS'),
        'slimerjs' => t('SlimerJS'),
      ],
      '#default_value' => $config->get('backstopjs.test_engine') ?? 'phantomjs',
      '#description' => t('PhantomJS uses webkit (e.g. Chrome), SlimerJS uses gecko (e.g. Firefox).'),
    ];

    $form['backstopjs']['mismatch_threshold'] = [
      '#type' => 'number',
      '#title' => t('Mismatch Threshold'),
      '#description' => t('The amount of difference BackstopJS will tolerate before marking a test screenshot as "failed". 0 does not allow any differences.'),
      '#default_value' => $config->get('backstopjs.mismatch_threshold') ?? 0.00,
      '#min' => 0.00,
      '#max' => 100.00,
      '#step' => 0.1,
    ];

    $form['backstopjs']['resemble_output_options'] = [
      '#type' => 'details',
      '#title' => t('Resemble output options'),
      '#open' => TRUE,
    ];

    $form['backstopjs']['resemble_output_options']['fallback_color'] = [
      '#type' => 'jquery_colorpicker',
      '#title' => t('Fallback color'),
      '#description' => t('The global fallback for diff error highlighting. You should use a bright and ugly color.'),
      '#default_value' => $config->get('backstopjs.resemble_output_options.fallback_color') ?? 'FF00FF',
    ];

    $form['backstopjs']['resemble_output_options']['error_type'] = [
      '#type' => 'select',
      '#title' => t('Error type'),
      '#options' => [
        'movement' => t('Movement'),
        'flat' => t('Flat'),
      ],
      '#default_value' => $config->get('backstopjs.resemble_output_options.error_type') ?? 'movement',
      '#description' => t('Movement: Merges error color with base image. This is recommended.'),
    ];

    $form['backstopjs']['resemble_output_options']['transparency'] = [
      '#type' => 'number',
      '#title' => t('Transparency'),
      '#description' => t('Fade unchanged areas to make changed areas more apparent.'),
      '#default_value' => $config->get('backstopjs.resemble_output_options.transparency') ?? 0.3,
      '#min' => 0,
      '#max' => 1,
      '#step' => 0.1,
    ];
    $form['backstopjs']['resemble_output_options']['large_image_threshold'] = [
      '#type' => 'number',
      '#title' => t('Large image threshold'),
      '#description' => t('By default, the comparison algorithm skips pixels when the image width or height is larger than 1200 pixels. This is there to mitigate performance issues. Set it to 0 to switch it off completely.'),
      '#default_value' => $config->get('backstopjs.resemble_output_options.large_image_threshold') ?? 1200,
      '#min' => 0,
      '#max' => 5000,
    ];
    $form['backstopjs']['resemble_output_options']['use_cross_origin'] = [
      '#type' => 'select',
      '#title' => t('Use cross origin'),
      '#options' => [
        1 => 'Yes',
        0 => 'No',
      ],
      '#default_value' => $config->get('backstopjs.resemble_output_options.use_cross_origin') ?? 1,
      '#description' => t('Should be "Yes" for QAShot. Visit the @link for more info before using "No".', [
        '@link' => Link::fromTextAndUrl(
          'mozilla developer docs',
          Url::fromUri('https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/Data_URIs')
        )->toString(),
      ]),
      '#disabled' => TRUE,
    ];

    $form['backstopjs']['debug_mode'] = [
      '#type' => 'select',
      '#title' => t('Enable debug mode'),
      '#options' => [
        1 => 'Yes',
        0 => 'No',
      ],
      '#default_value' => $config->get('backstopjs.debug_mode') ?? 0,
      '#description' => t('"Yes" results in a verbose output so more data is available in the logs.'),
    ];

    return $form;
  }

  /**
   * Handles submission of the altered parts.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @throws \Drupal\Core\Config\ConfigValueException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Config\Config $config */
    $config = $this->configFactory()->getEditable('backstopjs.settings');
    $config->set('backstopjs.async_compare_limit', $form_state->getValue(['backstopjs', 'async_compare_limit']));
    $config->set('backstopjs.test_engine', $form_state->getValue(['backstopjs', 'test_engine']));
    $config->set('backstopjs.debug_mode', $form_state->getValue(['backstopjs', 'debug_mode']));
    $config->set('backstopjs.mismatch_threshold', $form_state->getValue(['backstopjs', 'mismatch_threshold']));

    $resembleOptions = [
      'error_type',
      'transparency',
      'large_image_threshold',
      'use_cross_origin',
      'fallback_color',
    ];
    foreach ($resembleOptions as $option) {
      $configKey = "backstopjs.resemble_output_options.$option";
      $formStateKey = ['backstopjs', 'resemble_output_options', $option];
      $config->set($configKey, $form_state->getValue($formStateKey));
    }

    $config->save();
  }

}
