<?php

namespace Drupal\backstopjs\Form;

use Drupal\backstopjs\Backstopjs\BackstopjsWorkerManager;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class BackstopjsSettingsForm.
 *
 * @todo: Add public function validateForm();
 * @todo: Validate suite.binary_path for slashes, etc.
 *
 * @package Drupal\backstopjs\Form
 */
class BackstopjsSettingsForm extends ConfigFormBase {

  const LOCAL_SUITE = 'local';
  const REMOTE_SUITE = 'remote';

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Path to the app root.
   *
   * @var string
   */
  protected $appRoot;

  /**
   * The Backstopjs Worker plugin manager.
   *
   * @var \Drupal\backstopjs\Backstopjs\BackstopjsWorkerManager
   */
  protected $workerManager;

  /**
   * {@inheritdoc}
   *
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('logger.factory'),
      $container->get('app.root'),
      $container->get('plugin.manager.backstopjs_worker')
    );
  }

  /**
   * BackstopjsSettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   Logger factory service.
   * @param string $appRoot
   *   The app root path.
   * @param \Drupal\backstopjs\Backstopjs\BackstopjsWorkerManager $workerManager
   *   The Backstopjs Worker plugin manager.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    LoggerChannelFactoryInterface $loggerChannelFactory,
    $appRoot,
    BackstopjsWorkerManager $workerManager
  ) {
    parent::__construct($configFactory);
    $this->logger = $loggerChannelFactory->get('backstopjs');
    $this->appRoot = $appRoot;
    $this->workerManager = $workerManager;
  }

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

    $form['suite'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#collapsible' => FALSE,
      '#title' => $this->t('BackstopJS suite'),
    ];

    $options = [];
    foreach ($this->workerManager->getDefinitions() as $pluginId => $definition) {
      $options[$pluginId] = $definition['title'];
    }

    $form['suite']['location'] = [
      '#type' => 'radios',
      '#title' => $this->t('Suite'),
      '#default_value' => $config->get('suite.location') ?? NULL,
      '#options' => $options,
      '#required' => TRUE,
      '#description' => $this->t('Select the location of BackstopJS.'),
    ];

    // @todo: Add states to hide binaries/remotes.
    // Path to binaries.
    $form['suite']['binary_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Path to the BackstopJS executables'),
      '#default_value' => $config->get('suite.binary_path'),
      '#required' => FALSE,
      '#description' => $this->t('If needed, the path to the BackstopJS executable, <b>including</b> the trailing slash/backslash. For example: <kbd>/usr/bin/</kbd> or <kbd>C:\Program Files\BackstopJS\</kbd>.'),
    ];

    if (self::LOCAL_SUITE === $config->get('suite.location')) {
      // Version information.
      $status = $this->checkPath($config->get('suite.binary_path'));
      $version_info = empty($status['errors']) ? explode("\n", preg_replace('/\r/', '', Html::escape($status['output']))) : $status['errors'];
      $form['suite']['version'] = [
        '#type' => 'details',
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
        '#title' => $this->t('Version information'),
        '#description' => '<pre>' . implode('<br />', $version_info) . '</pre>',
      ];
    }

    // @todo: Make it a multi-row textfield, one URL per line, etc.
    $form['suite']['remote_locations'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Remote URL to the BackstopJS execution app'),
      '#default_value' => $config->get('suite.remote_locations'),
      '#required' => FALSE,
      '#description' => $this->t('The base URLs of the custom BackstopJS workers. One URL per line. E.g. <kbd>backstop_node:3000</kbd> for the bundled docker environment.'),
    ];

    $form['backstopjs'] = [
      '#type' => 'details',
      '#title' => $this->t('Backstop JS'),
      '#open' => TRUE,
    ];

    $form['backstopjs']['async_compare_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Parallel comparison limit'),
      '#description' => $this->t('Limit the amount of parallel image comparisons. Lower value results in slower comparisons and lower RAM usage. As a (very approximate) rule of thumb, BackstopJS will use 100MB RAM plus approximately 5 MB for each concurrent image comparison.'),
      '#default_value' => $config->get('backstopjs.async_compare_limit') ?? 30,
      '#min' => 1,
      '#max' => 100,
    ];

    $form['backstopjs']['test_engine'] = [
      '#type' => 'select',
      '#title' => $this->t('Test engine'),
      '#options' => [
        'phantomjs' => $this->t('PhantomJS'),
        'slimerjs' => $this->t('SlimerJS'),
      ],
      '#default_value' => $config->get('backstopjs.test_engine') ?? 'phantomjs',
      '#description' => $this->t('PhantomJS uses webkit (e.g. Chrome), SlimerJS uses gecko (e.g. Firefox).'),
    ];

    $form['backstopjs']['mismatch_threshold'] = [
      '#type' => 'number',
      '#title' => $this->t('Mismatch Threshold'),
      '#description' => $this->t('The amount of difference BackstopJS will tolerate before marking a test screenshot as "failed". 0 does not allow any differences.'),
      '#default_value' => $config->get('backstopjs.mismatch_threshold') ?? 0.00,
      '#min' => 0.00,
      '#max' => 100.00,
      '#step' => 0.1,
    ];

    $form['backstopjs']['resemble_output_options'] = [
      '#type' => 'details',
      '#title' => $this->t('Resemble output options'),
      '#open' => TRUE,
    ];

    $form['backstopjs']['resemble_output_options']['fallback_color'] = [
      '#type' => 'jquery_colorpicker',
      '#title' => $this->t('Fallback color'),
      '#description' => $this->t('The global fallback for diff error highlighting. You should use a bright and ugly color.'),
      '#default_value' => $config->get('backstopjs.resemble_output_options.fallback_color') ?? 'FF00FF',
    ];

    $form['backstopjs']['resemble_output_options']['error_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Error type'),
      '#options' => [
        'movement' => $this->t('Movement'),
        'flat' => $this->t('Flat'),
      ],
      '#default_value' => $config->get('backstopjs.resemble_output_options.error_type') ?? 'movement',
      '#description' => $this->t('Movement: Merges error color with base image. This is recommended.'),
    ];

    $form['backstopjs']['resemble_output_options']['transparency'] = [
      '#type' => 'number',
      '#title' => $this->t('Transparency'),
      '#description' => $this->t('Fade unchanged areas to make changed areas more apparent.'),
      '#default_value' => $config->get('backstopjs.resemble_output_options.transparency') ?? 0.3,
      '#min' => 0,
      '#max' => 1,
      '#step' => 0.1,
    ];
    $form['backstopjs']['resemble_output_options']['large_image_threshold'] = [
      '#type' => 'number',
      '#title' => $this->t('Large image threshold'),
      '#description' => $this->t('By default, the comparison algorithm skips pixels when the image width or height is larger than 1200 pixels. This is there to mitigate performance issues. Set it to 0 to switch it off completely.'),
      '#default_value' => $config->get('backstopjs.resemble_output_options.large_image_threshold') ?? 1200,
      '#min' => 0,
      '#max' => 5000,
    ];
    $form['backstopjs']['resemble_output_options']['use_cross_origin'] = [
      '#type' => 'select',
      '#title' => $this->t('Use cross origin'),
      '#options' => [
        1 => 'Yes',
        0 => 'No',
      ],
      '#default_value' => $config->get('backstopjs.resemble_output_options.use_cross_origin') ?? 1,
      '#description' => $this->t('Should be "Yes" for QAShot. Visit the @link for more info before using "No".', [
        '@link' => Link::fromTextAndUrl(
          'mozilla developer docs',
          Url::fromUri('https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/Data_URIs')
        )->toString(),
      ]),
      '#disabled' => TRUE,
    ];

    $form['backstopjs']['debug_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Enable debug mode'),
      '#options' => [
        1 => 'Yes',
        0 => 'No',
      ],
      '#default_value' => $config->get('backstopjs.debug_mode') ?? 0,
      '#description' => $this->t('"Yes" results in a verbose output so more data is available in the logs.'),
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

    $suiteOptions = [
      'location',
      'binary_path',
      'remote_locations',
    ];

    foreach ($suiteOptions as $option) {
      $configKey = "suite.$option";
      $formStateKey = ['suite', $option];
      $config->set($configKey, $form_state->getValue($formStateKey));
    }

    $config->save();
  }

  /**
   * Verifies file path of the executable binary by checking its version.
   *
   * @param string $path
   *   The user-submitted file path to the BackstopJS binary.
   *
   * @return array
   *   An associative array containing:
   *   - output: The shell output of 'backstop --version', if any.
   *   - errors: A list of error messages indicating if the executable could
   *     not be found or executed.
   */
  public function checkPath($path = ''): array {
    $status = [
      'output' => '',
      'errors' => [],
    ];

    $binary = 'backstop';
    $executable = $path . $binary;

    // If a path is given, we check whether the binary exists and can be
    // invoked.
    if (!empty($path)) {
      // Check whether the given file exists.
      if (!is_file($executable)) {
        $status['errors'][] = $this->t('The @suite executable %file does not exist.', ['@suite' => 'BackstopJS', '%file' => $executable]);
      }
      // If it exists, check whether we can execute it.
      if (!is_executable($executable)) {
        $status['errors'][] = $this->t('The @suite file %file is not executable.', ['@suite' => 'BackstopJS', '%file' => $executable]);
      }
    }

    // In case of errors, check for open_basedir restrictions.
    if ($status['errors'] && ($openBasedir = ini_get('open_basedir'))) {
      $status['errors'][] = $this->t('The PHP <a href=":php-url">open_basedir</a> security restriction is set to %open-basedir, which may prevent to locate the @suite executable.', [
        '@suite' => 'BackstopJS',
        '%open-basedir' => $openBasedir,
        ':php-url' => 'http://php.net/manual/en/ini.core.php#ini.open-basedir',
      ]);
    }

    // Unless we had errors so far, try to invoke convert.
    if (!$status['errors']) {
      $error = NULL;
      $this->runOsShell($executable, '--version', $status['output'], $error);
      if ($error !== '') {
        // $error normally needs check_plain(), but file system errors on
        // Windows use a unknown encoding. check_plain() would eliminate the
        // entire string.
        $status['errors'][] = $error;
      }
    }

    return $status;
  }

  /**
   * Executes a command on the operating system.
   *
   * @param string $command
   *   The command to run.
   * @param string $arguments
   *   The arguments of the command to run.
   * @param string &$output
   *   (optional) A variable to assign the shell stdout to, passed by
   *   reference.
   * @param string &$error
   *   (optional) A variable to assign the shell stderr to, passed by
   *   reference.
   *
   * @return int|bool
   *   The operating system returned code, or FALSE if it was not possible to
   *   execute the command.
   *
   * @todo: Move to the LocalBackstopJS class.
   */
  protected function runOsShell($command, $arguments, &$output = NULL, &$error = NULL) {
    $commandLine = escapeshellcmd($command . ' ' . $arguments);

    // Executes the command on the OS via proc_open().
    $descriptors = [
      // This is stdin.
      0 => ['pipe', 'r'],
      // This is stdout.
      1 => ['pipe', 'w'],
      // This is stderr.
      2 => ['pipe', 'w'],
    ];

    if ($process = proc_open($commandLine, $descriptors, $pipes, $this->appRoot)) {
      $output = '';
      while (!feof($pipes[1])) {
        $output .= fgets($pipes[1]);
      }
      $output = utf8_encode($output);
      $error = '';
      while (!feof($pipes[2])) {
        $error .= fgets($pipes[2]);
      }
      $error = utf8_encode($error);
      fclose($pipes[0]);
      fclose($pipes[1]);
      fclose($pipes[2]);
      $returnCode = proc_close($process);
    }
    else {
      $returnCode = FALSE;
    }

    // Process debugging information if required.
    // @todo: This is not implemented.
    if ($this->configFactory->get('backstopjs.settings')->get('debug')) {
      $this->debugMessage('@suite command: <pre>@raw</pre>', [
        '@suite' => 'BackstopJS',
        '@raw' => print_r($commandLine, TRUE),
      ]);
      if ($output !== '') {
        $this->debugMessage('@suite output: <pre>@raw</pre>', [
          '@suite' => 'BackstopJS',
          '@raw' => print_r($output, TRUE),
        ]);
      }
      if ($error !== '') {
        $this->debugMessage('@suite error @return_code: <pre>@raw</pre>', [
          '@suite' => 'BackstopJS',
          '@return_code' => $returnCode,
          '@raw' => print_r($error, TRUE),
        ]);
      }
    }

    return $returnCode;
  }

  /**
   * Logs a debug message, and shows it on the screen for authorized users.
   *
   * @param string $message
   *   The debug message.
   * @param string[] $context
   *   Context information.
   */
  public function debugMessage($message, array $context) {
    $this->logger->debug($message, $context);
    if ($this->currentUser()->hasPermission('administer site configuration')) {
      // Strips raw text longer than 10 lines to optimize displaying.
      if (isset($context['@raw'])) {
        $raw = explode("\n", $context['@raw']);
        if (count($raw) > 10) {
          $tmp = [];
          for ($i = 0; $i < 9; $i++) {
            $tmp[] = $raw[$i];
          }
          $tmp[] = (string) $this->t('[Further text stripped. The watchdog log has the full text.]');
          $context['@raw'] = implode("\n", $tmp);
        }
      }
      // @codingStandardsIgnoreStart
      drupal_set_message($this->t($message, $context), 'status', TRUE);
      // @codingStandardsIgnoreEnd
    }
  }

}
