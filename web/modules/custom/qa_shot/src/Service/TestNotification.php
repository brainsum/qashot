<?php

namespace Drupal\qa_shot\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
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
   * Url service.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  private $urlGenerator;

  /**
   * The site email.
   *
   * @var string
   */
  private $siteMail;

  /**
   * The site name.
   *
   * @var string
   */
  private $siteName;

  /**
   * TestNotification constructor.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $urlGenerator
   */
  public function __construct(
    LanguageManagerInterface $languageManager,
    MailManagerInterface $mailManager,
    ConfigFactoryInterface $configFactory,
    LoggerChannelFactoryInterface $loggerChannelFactory,
    UrlGeneratorInterface $urlGenerator
  ) {
    $this->languageManager = $languageManager;
    $this->mailManager = $mailManager;
    $this->logger = $loggerChannelFactory->get('qa_shot');
    $this->urlGenerator = $urlGenerator;

    $this->siteMail = $configFactory->get('system.site')->get('mail');
    $this->siteName = $configFactory->get('system.site')->get('name');
  }

  /**
   * Send the notification.
   *
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $entity
   *   The test entity.
   * @param string $module
   *   The module where the processing hook_mail() resides.
   */
  public function sendNotification(QAShotTestInterface $entity, $module = 'qa_shot') {
    $metadata = $entity->getLastRunMetadataValue();

    // @todo: FIXME, code below is quick and dirty.
    if (empty($metadata)) {
      return;
    }

    // In case of befor/after, only the after contains passed/failed data.
    if ($entity->bundle() === 'before_after') {
      $shouldSend = FALSE;
      foreach ($metadata as $meta) {
        if ($meta['stage'] === 'after') {
          $shouldSend = TRUE;
          break;
        }
      }

      if (FALSE === $shouldSend) {
        return;
      }
    }

    $testResults = [];
    $stageKey = $entity->bundle() === 'a_b' ? NULL : 'after';
    foreach ($metadata as $meta) {
      if ($meta['stage'] === $stageKey) {
        $testResults = $meta;
        break;
      }
    }

    $passPercentage = (int) $testResults['passed_count'] / ((int) $testResults['passed_count'] + (int) $testResults['failed_count']) * 100;

    $entityTypeId = $entity->getEntityTypeId();

    try {
      $link = $this->urlGenerator->generateFromRoute(
        $entity->toUrl()->getRouteName(),
        [$entityTypeId => $entity->id()],
        ['absolute' => TRUE],
        TRUE
      )->getGeneratedUrl();
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

    // @todo: get the field that contains the user who started the test.
    // Run Initiator field.
    $receiver = $entity->getOwner()->getEmail();

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
      '@status' => 'fail',
      '@id' => $entity->id(),
    ];

    if ($result['result'] === TRUE) {
      $this->logger->notice(
        'Notification about the @status of the test with id #@id has been sent.',
        $logParameters
      );
    }
    else {
      $this->logger->warning(
        'Notification about the @status of the test with id #@id has not been sent.',
        $logParameters
      );
    }
  }

}
