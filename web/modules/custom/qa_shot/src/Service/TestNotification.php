<?php

namespace Drupal\qa_shot\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\qa_shot\Entity\QAShotTestInterface;

/**
 * Class TestNotification.
 *
 * @package Drupal\qa_shot\Service
 */
class TestNotification {

  /**
   * Mail manager service parameter. If true, emails are sent right away.
   */
  const SEND_NOW = TRUE;

  const NOTIFICATION_MAIL_KEY = 'qashot_test_notification';

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  private $languageManager;

  /**
   * The mail manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  private $mailManager;

  /**
   * Logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  private $logger;

  /**
   * The site email.
   *
   * @var string
   */
  private $siteMail;

  /**
   * TestNotification constructor.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   Language manager.
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   *   Mail manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   Logger factory.
   */
  public function __construct(
    LanguageManagerInterface $languageManager,
    MailManagerInterface $mailManager,
    ConfigFactoryInterface $configFactory,
    LoggerChannelFactoryInterface $loggerChannelFactory
  ) {
    $this->languageManager = $languageManager;
    $this->mailManager = $mailManager;
    $this->logger = $loggerChannelFactory->get('qa_shot');

    $this->siteMail = $configFactory->get('system.site')->get('mail');
  }

  /**
   * Send the notification.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $entity
   *   The test entity.
   * @param string $origin
   *   The origin of the request, 'drupal' or 'api'.
   * @param string $module
   *   The module where the processing hook_mail() resides.
   */
  public function sendNotification(QAShotTestInterface $entity, $origin, $module = 'qa_shot') {
    $metadata = $entity->getLastRunMetadataValue();

    // @todo: FIXME, code below is quick and dirty.
    if (empty($metadata)) {
      return;
    }

    $result = NULL;
    // Get the first metadata with results.
    foreach ($metadata as $data) {
      if (TRUE === (boolean) $data['contains_result']) {
        $result = $data;
        break;
      }
    }

    // If there are no result metadata rows, don't send a notification.
    if (NULL === $result) {
      return;
    }

    $passPercentage = round($result['pass_rate'] * 100, 3);

    $linkOptions = [
      'absolute' => TRUE,
      'external' => TRUE,
    ];

    try {
      $link = $entity->getFrontendUrl();
      if ('drupal' === $origin || NULL === $link) {
        $link = $entity
          ->toUrl('canonical', $linkOptions)
          ->toString(TRUE)
          ->getGeneratedUrl();
      }
    }
    catch (\Exception $exception) {
      $this->logger->error(
        'Notification could not be sent. Error message: @msg',
        ['@msg' => $exception->getMessage()]
      );
      return;
    }

    $params = [
      'subject' => $entity->getName() . ', ' . $passPercentage . '% PASSED',
      'body' => [$link],
    ];

    $receiver = $entity->getInitiator()->getEmail();

    $result = $this->mailManager->mail(
      $module,
      $this::NOTIFICATION_MAIL_KEY,
      $receiver,
      $this->languageManager->getCurrentLanguage()->getId(),
      $params,
      $this->siteMail,
      $this::SEND_NOW
    );

    $logParameters = [
      '@status' => (100 === (int) $passPercentage) ? 'success' : 'fail',
      '@id' => $entity->id(),
    ];

    if ($result['result'] === TRUE) {
      $this->logger->notice(
        'Notification about the result [@status] of the test with id #@id has been sent.',
        $logParameters
      );
    }
    else {
      $this->logger->warning(
        'Notification about the result [@status] of the test with id #@id has not been sent.',
        $logParameters
      );
    }
  }

}
