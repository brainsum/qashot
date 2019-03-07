<?php

namespace Drupal\backstopjs\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList;
use Drupal\qa_shot\Entity\QAShotTestInterface;

/**
 * Class ConfigurationConverter.
 *
 * Provides various functions to create a QAShotTest instance.
 * Saving the new entity is optional.
 *
 * @package Drupal\backstopjs\Service
 * @backstopjs v3.8.8
 */
class ConfigurationConverter {

  /**
   * The private files folder for QAShot Test Entities without a trailing /.
   *
   * @var string
   */
  private $privateDataPath;

  /**
   * The public files folder for QAShot Test Entities without a trailing /.
   *
   * @var string
   */
  private $publicDataPath;

  /**
   * QAShotTest entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private $testStorage;

  /**
   * Paragraphs entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private $paragraphStorage;

  /**
   * The config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private $config;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  private $fileSystem;

  /**
   * ConfigurationConverter constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   EntityTypeManager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    ConfigFactoryInterface $configFactory,
    FileSystemInterface $fileSystem
  ) {
    $this->privateDataPath = PrivateStream::basePath() . '/' . FileSystem::DATA_BASE_FOLDER;
    $this->publicDataPath = PublicStream::basePath() . '/' . FileSystem::DATA_BASE_FOLDER;

    $this->testStorage = $entityTypeManager->getStorage('qa_shot_test');
    $this->paragraphStorage = $entityTypeManager->getStorage('paragraph');
    $this->config = $configFactory->get('backstopjs.settings');

    $this->fileSystem = $fileSystem;
  }

  /**
   * Get the engine options from the entity.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $entity
   *   The test entity.
   *
   * @return array
   *   An associative array with name, scripts and options.
   */
  protected function getTestEngine(QAShotTestInterface $entity): array {
    $private = $this->privateDataPath . '/' . $entity->id();
    $browser = $entity->getBrowser() ?? $this->config->get('backstopjs.browser');

    switch ($browser) {
      case 'firefox':
        // @todo: Get real path?
        $optionsKey = 'casperFlags';
        $testEngine = 'slimerjs';
        $engineScripts = $private . '/casper_scripts';
        $engineOptions = [
          '--ignore-ssl-errors=true',
          '--ssl-protocol=any',
          '--headless',
        ];
        $useAbsolutePaths = TRUE;
        break;

      case 'phantomjs':
        $optionsKey = 'casperFlags';
        $testEngine = 'casper';
        $engineScripts = $private . '/casper_scripts';
        $engineOptions = [
          '--ignore-ssl-errors=true',
          '--ssl-protocol=any',
        ];
        $useAbsolutePaths = FALSE;
        break;

      case 'chrome':
        $optionsKey = 'engineOptions';
        $testEngine = 'puppeteer';
        $engineScripts = $private . '/puppeteer_scripts';
        $engineOptions = [
          'waitTimeout' => 20000,
          'ignoreHTTPSErrors' => TRUE,
          'args' => [
            '--lang=en-GB,en-US',
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--headless',
            '--disable-gpu',
            '--ignore-certificate-errors',
            '--force-device-scale-factor=1',
            '--disable-infobars=true',
            '--no-zygote',
            '--process-per-site',
            '--disable-accelerated-2d-canvas',
            '--disable-accelerated-jpeg-decoding',
            '--disable-accelerated-mjpeg-decode',
            '--disable-accelerated-video-decode',
            '--disable-gpu-rasterization',
            '--disable-zero-copy',
            '--disable-extensions',
            '--disable-notifications',
            '--disable-sync',
            '--mute-audio',
          ],
        ];
        $useAbsolutePaths = FALSE;
        break;

      default:
        throw new \RuntimeException('The selected browser "' . $browser . '" is invalid.');
    }

    return [
      'name' => $testEngine,
      'scripts' => $engineScripts,
      'optionsKey' => $optionsKey,
      'options' => $engineOptions,
      'useAbsolutePaths' => $useAbsolutePaths,
    ];
  }

  /**
   * Parse the given path.
   *
   * @param string $path
   *   The path.
   * @param bool $absolute
   *   Flag to parse it as absolute.
   *
   * @return string
   *   The parsed path.
   */
  protected function parsePath(string $path, $absolute = FALSE): string {
    $absolute = FALSE;
    if ($absolute === TRUE) {
      $realpath = $this->fileSystem->realpath($path);

      if ($realpath === FALSE) {
        // @todo: Throw exception/message.
        return $path;
      }

      return $realpath;
    }

    return $path;
  }

  /**
   * Map the current entity to the array representation of a BackstopJS config.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $entity
   *   The test entity.
   * @param bool $withDebug
   *   Whether we should add CasperJS debug options.
   *
   * @return array
   *   The entity as a BackstopJS config array.
   *
   * @throws \InvalidArgumentException
   */
  public function entityToArray(QAShotTestInterface $entity, $withDebug = FALSE): array {
    // @todo: get some field values global settings
    $entityId = $entity->id();
    $public = $this->publicDataPath . '/' . $entityId;
    // @todo: Cleanup.
    $engine = $this->getTestEngine($entity);

    $mapConfigToArray = [
      // @todo: maybe id + revision id.
      'id' => $entity->uuid(),
      'fileNameTemplate' => '{scenarioLabel}_{selectorIndex}_{selectorLabel}_{viewportIndex}_{viewportLabel}',
      'viewports' => $this->viewportToArray($entity->getFieldViewport()),
      'scenarios' => $this->scenarioToArray(
        $entity->getFieldScenario(),
        $entity->getSelectorsToHide(),
        $entity->getSelectorsToRemove(),
        $engine['name']
      ),
      'paths' => [
        'engine_scripts' => $this->parsePath($engine['scripts'], $engine['useAbsolutePaths']),
        'bitmaps_reference' => $this->parsePath($public . '/reference', $engine['useAbsolutePaths']),
        'bitmaps_test' => $this->parsePath($public . '/test', $engine['useAbsolutePaths']),
        'html_report' => $this->parsePath($public . '/html_report', $engine['useAbsolutePaths']),
        'ci_report' => $this->parsePath($public . '/ci_report', $engine['useAbsolutePaths']),
      ],
      // 'onBeforeScript' => 'onBefore.js', //.
      // 'onReadyScript' => 'onReady.js', //.
      'engine' => $engine['name'],
      'report' => [
        // Skipping 'browser' will still generate it, but it won't try to open
        // the generated report. For reasons.
        // 'browser',
        // CI is added, as omitting it won't result in it being generated.
        'CI',
      ],
      'resembleOutputOptions' => $this->generateResembleOptions($entity->get('field_diff_color')),
      'asyncCompareLimit' => (int) $this->config->get('backstopjs.async_compare_limit'),
      // @todo: Enable on settings UI.
      'asyncCaptureLimit' => 1,
      'debug' => FALSE,
      // Only allowed for chrome.
      'debugWindow' => FALSE,
    ];

    $mapConfigToArray[$engine['optionsKey']] = $engine['options'];

    if (TRUE === $withDebug || TRUE === (bool) $this->config->get('backstopjs.debug_mode')) {
      $mapConfigToArray['debug'] = TRUE;
    }

    return $mapConfigToArray;
  }

  /**
   * Generates the resemble output array.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $diffColorField
   *   The diff_color field.
   *
   * @return array
   *   The resembleOutputOptions array.
   */
  public function generateResembleOptions(FieldItemListInterface $diffColorField): array {
    $red = 255;
    $green = 0;
    $blue = 255;
    // Allow diff color to be set on an entity level.
    if ($hexValue = $diffColorField->getValue()) {
      $hex = $hexValue[0]['value'];
      $red = \hexdec(\substr($hex, 0, 2));
      $green = \hexdec(\substr($hex, 2, 2));
      $blue = \hexdec(\substr($hex, 4, 2));
    }
    // If for some reason it's not set, use the global config.
    // If it's also not set, use rgb(255, 0, 255).
    elseif ($hexValue = $this->config->get('backstopjs.resemble_output_options.fallback_color')) {
      $hex = $hexValue;
      $red = \hexdec(\substr($hex, 0, 2));
      $green = \hexdec(\substr($hex, 2, 2));
      $blue = \hexdec(\substr($hex, 4, 2));
    }

    $output = [
      'errorColor' => [
        'red' => $red,
        'green' => $green,
        'blue' => $blue,
      ],
      // Can be 'flat' or 'movement'.
      // Movement: Merges error color with base image
      // which makes it a little easier to spot movement.
      'errorType' => $this->config->get('backstopjs.resemble_output_options.error_type'),
      // Fade unchanged areas to make changed areas more apparent.
      'transparency' => $this->config->get('backstopjs.resemble_output_options.transparency'),
      // Set as 0 to disable.
      'largeImageThreshold' => $this->config->get('backstopjs.resemble_output_options.large_image_threshold'),
      // Set to FALSE for DATA URIs.
      // @see: https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/Data_URIs
      // Note: This shouldn't matter here.
      'useCrossOrigin' => (bool) $this->config->get('backstopjs.resemble_output_options.use_cross_origin'),
    ];

    return $output;
  }

  /**
   * Convert the viewport field so it can be used in a BackstopJS config array.
   *
   * @param \Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList $viewportField
   *   The viewport field.
   *
   * @throws \InvalidArgumentException
   *
   * @return array
   *   Array representation of the viewport field.
   */
  public function viewportToArray(
    EntityReferenceRevisionsFieldItemList $viewportField
  ): array {
    // Flatten the field values from target_id + revision_target_id
    // to target_id only.
    $ids = \array_map(function ($item) {
      return $item['target_id'];
    }, $viewportField->getValue());

    $viewports = $this->paragraphStorage->loadMultiple($ids);

    $viewportData = [];
    /** @var \Drupal\paragraphs\Entity\Paragraph $viewport */
    foreach ($viewports as $viewport) {
      $viewportData[] = [
        'name' => (string) $viewport->get('field_name')->getValue()[0]['value'],
        'width' => (int) $viewport->get('field_width')->getValue()[0]['value'],
        'height' => (int) $viewport->get('field_height')->getValue()[0]['value'],
      ];
    }
    return $viewportData;
  }

  /**
   * Convert the scenario field so it can be used in a BackstopJS config array.
   *
   * @param \Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList $scenarioField
   *   The scenario field.
   * @param string[] $selectorsToHide
   *   An array of selectors that should be visually hidden.
   *   The values are merged with the default ones.
   * @param string[] $selectorsToRemove
   *   An array of selectors that should be removed from te DOM.
   *   The values are merged with the default ones.
   * @param string $engine
   *   The engine.
   *
   * @throws \InvalidArgumentException
   *
   * @return array
   *   Array representation of the scenario field.
   */
  public function scenarioToArray(
    EntityReferenceRevisionsFieldItemList $scenarioField,
    array $selectorsToHide,
    array $selectorsToRemove,
    $engine
  ): array {
    $scenarioData = [];

    // Flatten the field values from target_id + revision_target_id
    // to target_id only.
    $ids = \array_map(function ($item) {
      return $item['target_id'];
    }, $scenarioField->getValue());

    $scenarios = $this->paragraphStorage->loadMultiple($ids);

    /** @var \Drupal\paragraphs\Entity\Paragraph $scenario */
    foreach ($scenarios as $scenario) {
      $currentScenario = [];
      $currentScenario['label'] = (string) $scenario->get('field_label')->getValue()[0]['value'];

      if ($referenceUrl = $scenario->get('field_reference_url')->getValue()[0]['uri']) {
        $currentScenario['referenceUrl'] = (string) $referenceUrl;
      }

      $misMatch = $this->config->get('backstopjs.mismatch_threshold') ?? 0.0;
      $currentScenario += [
        'url' => (string) $scenario->get('field_test_url')->getValue()[0]['uri'],
        'readyEvent' => NULL,
        'delay' => 10000,
        'misMatchThreshold' => (float) $misMatch,
        'selectors' => [
          'document',
        ],
        'removeSelectors' => $selectorsToRemove,
        'hideSelectors' => $selectorsToHide,
        'onBeforeScript' => 'onBefore.js',
        'onReadyScript' => 'onReady.js',
      ];

      $scenarioData[] = $currentScenario;
    }

    return $scenarioData;
  }

  // @codingStandardsIgnoreStart
  /**
   * Instantiates a new QAShotEntity from the array. Optionally saves it.
   *
   * @param array $config
   *   The entity as an array.
   * @param bool $saveEntity
   *   Whether to persist the entity as well.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *
   * @return \Drupal\qa_shot\Entity\QAShotTestInterface|\Drupal\Core\Entity\EntityInterface
   *   The entity object.
   *
   * @deprecated
   */
  public function arrayToEntity(array $config, $saveEntity = FALSE) {
    $testEntity = $this->testStorage->create($config);

    if ($saveEntity) {
      $testEntity->save();
    }

    return $testEntity;
  }

  /**
   * Instantiates a new QAShotEntity from the json string. Optionally saves it.
   *
   * @param string $json
   *   The backstop.json config file as a string.
   * @param bool $saveEntity
   *   Whether to persist the entity as well.
   *
   * @return \Drupal\qa_shot\Entity\QAShotTestInterface
   *   The entity object.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *
   * @deprecated
   */
  public function jsonStringToEntity($json, $saveEntity = FALSE): QAShotTestInterface {
    /** @var array $rawData */
    $rawData = \json_decode($json, TRUE);

    $entityData = [
      'name' => 'Import for backstop.json with id #' . $rawData['id'],
      'type' => 'a_b',
      'viewport' => $rawData['viewports'],
      'field_scenario' => [],
    ];

    foreach ($rawData['scenarios'] as $scenario) {
      $entityData['field_scenario'][] = [
        'label' => $scenario['label'],
        'referenceUrl' => $scenario['referenceUrl'],
        'testUrl' => $scenario['url'],
      ];
    }

    return $this->arrayToEntity($entityData, $saveEntity);
  }

  /**
   * Instantiates a new QAShotEntity from the json file. Optionally saves it.
   *
   * @param string $jsonFile
   *   The backstop.json config file path as a string.
   * @param bool $saveEntity
   *   Whether to persist the entity as well.
   *
   * @return \Drupal\qa_shot\Entity\QAShotTestInterface
   *   The entity object.
   *
   * @deprecated
   */
  public function jsonFileToEntity($jsonFile, $saveEntity = FALSE): QAShotTestInterface {
    $data = file_get_contents($jsonFile);
    return $this->jsonStringToEntity($data, $saveEntity);
  }

  /**
   * Example function on how to use this class.
   *
   * @internal
   * @deprecated
   */
  public function example() {
    $basePath = PrivateStream::basePath() . '/qa_test_data';

    $ids = [
      '1',
    ];

    foreach ($ids as $id) {
      $this->jsonFileToEntity($basePath . '/' . $id . '/backstop.json');
    }
  }

  /**
   * Example function on how to use the ideas behind this class.
   *
   * This is intended to be copy-pasteable into devel/php as a rudimentary
   * import script.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *
   * @internal
   * @deprecated
   */
  public function copyPasteableExample() {
    $basePath = PrivateStream::basePath() . '/qa_test_data';

    $ids = [
      '1',
    ];

    foreach ($ids as $id) {
      /** @var string $fileData */
      $fileData = file_get_contents($basePath . '/' . $id . '/backstop.json');
      /** @var array $jsonData */
      $jsonData = json_decode($fileData, TRUE);

      $entityData = [
        'name' => 'Import for backstop.json with id #' . $jsonData['id'],
        'type' => 'a_b',
        'viewport' => $jsonData['viewports'],
        'field_scenario' => [],
      ];

      foreach ($jsonData['scenarios'] as $scenario) {
        $entityData['field_scenario'][] = [
          'label' => $scenario['label'],
          'referenceUrl' => $scenario['referenceUrl'],
          'testUrl' => $scenario['url'],
        ];
      }

      /** @var \Drupal\qa_shot\Entity\QAShotTestInterface $newTest */
      $newTest = \Drupal::entityTypeManager()->getStorage('qa_shot_test')->create($entityData);
      // dpm($newTest);
      $newTest->save();
    }
  }
  // @codingStandardsIgnoreEnd
}
