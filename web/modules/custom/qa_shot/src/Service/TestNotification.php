<?php

namespace Drupal\qa_shot\Service;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\qa_shot\Entity\QAShotTestInterface;

/**
 * Class TestNotification.
 *
 * @package Drupal\qa_shot\Service
 */
class TestNotification {

  use StringTranslationTrait;

  /**
   * Mail manager service parameter. If true, emails are sent right away.
   */
  const SEND_NOW = TRUE;

  const NOTIFICATION_MAIL_KEY = 'qashot_test_notification';

  /**
   * The language manager service.
   *
   * @var LanguageManagerInterface
   */
  private $languageManager;

  /**
   * The mail manager service.
   *
   * @var MailManagerInterface
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
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   */
  public function __construct(
    LanguageManagerInterface $languageManager,
    MailManagerInterface $mailManager,
    ConfigFactoryInterface $configFactory,
    LoggerChannelFactoryInterface $loggerChannelFactory,
    UrlGeneratorInterface $urlGenerator,
    EntityTypeManagerInterface $entityTypeManager) {
    $this->languageManager = $languageManager;
    $this->mailManager = $mailManager;
    $this->logger = $loggerChannelFactory->get('tieto_notification');
    $this->urlGenerator = $urlGenerator;
    $this->entityTypeManager = $entityTypeManager;

    $this->siteMail = $configFactory->get('system.site')->get('mail');
    $this->siteName = $configFactory->get('system.site')->get('name');
  }

  /**
   * @param \Drupal\qa_shot\Entity\QAShotTestInterface $entity
   * @param string $module
   */
  public function sendNotification(QAShotTestInterface $entity, $module = 'qa_shot') {
    $to = "almafa@gmail.com";

    $params = [
      'subject' => '',
      'body' => '',
    ];


    $result = $this->mailManager->mail(
      $module,
      $this::NOTIFICATION_MAIL_KEY,
      $to,
      $this->languageManager->getCurrentLanguage()->getId(),
      $params,
      $this->siteMail,
      $this::SEND_NOW
    );

    if ($result['result'] === TRUE) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  private function composeMessage() {
    $from = "site.mail";
    $to = "whoever started the test";
    $subject = 'Test "{test name}" finished with a X% success rate.';
    $body = 'entity link';
  }

}
