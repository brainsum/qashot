<?php

namespace Drupal\backstopjs\Plugin\BackstopjsWorker;

use Drupal\backstopjs\Backstopjs\BackstopjsWorkerBase;
use Drupal\backstopjs\Service\FileSystem;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\qa_shot\Entity\QAShotTestInterface;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class RemoteBackstopJS.
 *
 * Implements running BackstopJS from a remote source.
 * Sends HTTP requests.
 *
 * @package Drupal\backstopjs\Plugin\BackstopjsWorker
 *
 * @BackstopjsWorker(
 *   id = "remote",
 *   title = "Remote execution",
 *   description = @Translation("Worker for remote node apps")
 * )
 */
class RemoteBackstopjsWorker extends BackstopjsWorkerBase {

  const ENDPOINT_TEST_ADD = '/api/v1/test/add';

  /**
   * The remote host URL.
   *
   * @var string
   */
  protected $remoteHost;

  /**
   * The HTTP Client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * {@inheritdoc}
   *
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('backstopjs.file_system'),
      $container->get('logger.factory'),
      $container->get('config.factory'),
      $container->get('http_client')
    );
  }

  /**
   * LocalBackstopJS constructor.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \Drupal\backstopjs\Service\FileSystem $backstopFileSystem
   *   The BackstopJS file system service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger channel factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The site config factory.
   * @param \GuzzleHttp\Client $httpClient
   *   The HTTP client.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    array $plugin_definition,
    FileSystem $backstopFileSystem,
    LoggerChannelFactoryInterface $loggerChannelFactory,
    ConfigFactoryInterface $configFactory,
    Client $httpClient
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $backstopFileSystem, $loggerChannelFactory, $configFactory);
    $this->remoteHost = $this->config->get('suite.remote_host');
    $this->httpClient = $httpClient;
  }

  /**
   * {@inheritdoc}
   */
  public function checkRunStatus() {
    // TODO: Implement checkRunStatus() method.
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus(): string {
    // TODO: Implement getStatus() method.
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function run(string $browser, string $command, QAShotTestInterface $entity): array {
    // @todo: Remake the architecture, as this is not long-term viable.
    if (NULL === $this->remoteHost) {
      return [
        'code' => 204,
        'message' => $this->t('Remote host undefined, skipping.'),
        'reason' => '',
      ];
    }

    $backstopConfig = \json_decode(\file_get_contents($entity->getConfigurationPath()), TRUE);
    // Send chrome only until we finish work on other workers.
    $requestData = [
      'browser' => 'chrome',
      'mode' => 'a_b',
      'stage' => '',
      'test_config' => $backstopConfig,
    ];

    $requestOptions = [
      // @todo: auth
      'json' => $requestData,
      'connect_timeout' => 10,
    ];

    try {
      $response = $this->httpClient->post($this->remoteHost . self::ENDPOINT_TEST_ADD, $requestOptions);
    }
    catch (\Exception $exception) {
      $this->logger->warning('Could not push test (ID: ' . $entity->id() . ')  to the remote worker. See: ' . $exception->getMessage());
      return [
        'code' => $exception->getCode(),
        'message' => $exception->getMessage(),
        'reason' => '',
      ];
    }

    $remoteMessage = \json_decode($response->getBody()->getContents(), TRUE);
    $this->logger->info('Test (ID: ' . $entity->id() . ') pushed to the remote worker. Message: ' . $remoteMessage['message']);

    return [
      'message' => $response->getBody()->getContents(),
      'reason' => $response->getReasonPhrase(),
      'code' => $response->getStatusCode(),
    ];
  }

}
