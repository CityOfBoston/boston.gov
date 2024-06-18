<?php

namespace Drupal\bos_email\Plugin\EmailProcessor;

use Drupal\bos_core\Event\BosCoreFormEvent;
use Drupal\bos_email\CobEmail;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * EmailProcessor class for Contact Form.
 */
class Contactform extends EmailProcessorBase implements EventSubscriberInterface {

  /**
   * @inheritDoc
   */
  public static function getSubscribedEvents() {
    return [
      BosCoreFormEvent::CONFIG_FORM_BUILD => 'buildForm',
      BosCoreFormEvent::CONFIG_FORM_SUBMIT => 'submitForm',
    ];
  }

  /**
   * Domain to be used as the sender.
   */
  private const OUTBOUND_DOMAIN = "web-inbound.boston.gov";

  /**
   * @inheritDoc
   */
  public static function parseEmailFields(array &$payload, CobEmail &$email_object): void {

    // Do the base email fields processing first.
    parent::parseEmailFields($payload, $email_object);

    $email_object->setField("Tag", ($payload['tag'] ?? "Contact Form"));

    self::templatePlainText($payload, $email_object);
    if (empty($payload["useHtml"])) {
      $email_object->delField("HtmlBody");
    }
    else {
      self::templateHtmlText($payload, $email_object);
    }

    // Create a hash of the original poster's email
    $hashemail = $email_object::encodeFakeEmail($payload["from_address"], self::OUTBOUND_DOMAIN );
    $email_object->setField("Metadata", [
      "opmail" => $email_object::hashText($payload["from_address"], $email_object::ENCODE)
    ]);
    $email_object->setField("From", "Boston.gov Contact Form <{$hashemail}>");

    isset($payload["name"]) && $email_object->setField("ReplyTo", "{$payload["name"]}<{$payload["from_address"]}>");
    !empty($payload['headers']) && $email_object->setField("Headers", $payload['headers']);
    empty($payload["TemplateID"])  && $email_object->setField("TemplateID", $payload["template_id"]);

  }

  /**
   * @inheritDoc
   */
  public static function templatePlainText(array &$payload, CobEmail &$email_object): void {

    $msg = strip_tags($payload["message"]);

    if (empty($payload["TemplateID"]) && empty($payload["template_id"])) {
      $text = "-- REPLY ABOVE THIS LINE -- \n\n";
      $text .= "{$msg}\n\n";
      $text .= "{$payload["phone"]}\n\n";
      $text .= "-------------------------------- \n";
      $text .= "This message was sent using the contact form on Boston.gov.";
      $text .= " It was sent by {$payload["name"]} from {$payload["from_address"]} and {$payload["phone"]}.";
      $text .= " It was sent from {$payload["url"]}.\n\n";
      $text .= "-------------------------------- \n";
      $email_object->setField("TextBody", $text);
    }
    else {
      // we are using a template
      $email_object->delField("TextBody");
      $email_object->setField("TemplateID", $payload['TemplateID']);
      $email_object->setField("TemplateModel", [
        "subject" => $payload["subject"],
        "TextBody" => $msg,
        "ReplyTo" => $payload["from_address"],
      ]);
      $payload["useHtml"] = 0;
    }

  }

  /**
   * @inheritDoc
   */
  public static function templateHtmlText(array &$payload, CobEmail &$email_object): void {

    if (empty($payload["TemplateID"]) && empty($payload["template_id"])) {

      $msg = Html::escape(Xss::filter($payload["message"]));
      $msg = str_replace("\n", "<br>", $msg);

      $html = "<br>----- REPLY ABOVE THIS LINE ----- <br><br>";
      $html .= "<div style='background-color:#eeeeee;color:#222;padding:5px 15px;border-left: 15px #288BE4 solid;'>{$msg}</div>";
      $html .= "<br>";
      $html .= "{$payload["phone"]}";
      $html .= "<hr>";
      $html .= "<table style='border-spacing:10px;'><tr><td><img src='https://patterns.boston.gov/images/public/seal.png' height='50'></td>";
      $html .= "<td>This message was sent using the contact form on Boston.gov.<br>";
      $html .= " It was sent by <b>{$payload["name"]}</b> from {$payload["from_address"]} and {$payload["phone"]}.<br>";
      $html .= " It was sent from {$payload["url"]}.</td>";
      $html .= "</tr></table>";
      $html .= "<hr>";

      $email_object->setField("HtmlBody", $html);

    }

  }

  /**
   * @inheritDoc
   */
  public static function formatInboundEmail(array $payload, CobEmail &$email_object): void {

    $email_service = self::getEmailService(self::getGroupID());

    // Create the email.
    $original_recipient = $email_object::decodeFakeEmail($payload["OriginalRecipient"], self::OUTBOUND_DOMAIN);
    $email_object->setField("To", $original_recipient);
    $email_object->setField("From", "contactform@boston.gov");
    $email_object->setField("Subject", $payload["Subject"]);
    $email_object->setField("HtmlBody", $payload["HtmlBody"]);
    $email_object->setField("TextBody", $payload["TextBody"]);
    $email_object->setField("endpoint", $email_service::DEFAULT_ENDPOINT);
    // Select Headers
    $email_object->processHeaders($payload["Headers"]);

    // Remove redundant fields
    $email_object->delField("TemplateModel");
    $email_object->delField("TemplateID");

  }

  /**
   * @inheritDoc
   */
  public static function getHoneypotField(): string {
    return "contact";
  }

  /**
   * @inheritDoc
   */
  public static function getGroupID(): string {
    return "contactform";
  }

  /**
   * @inheritDoc
   */
  public static function buildForm(BosCoreFormEvent $event): void {

    if ($event->getEventType() == "bos_email_config_settings") {
      $form = $event->getForm();
      $form["bos_email"]["contactform"] = [
        '#type' => 'fieldset',
        '#title' => 'Contact Form',
        '#markup' => 'Emails from the main Contact Form - when clicking on email addresses on boston.gov.',
        '#collapsible' => FALSE,
        '#weight' => 0,

        "service" => [
          "#type" => "select",
          '#title' => t('Contact Form Email Service'),
          '#description' => t('The Email Service which is currently being used.'),
          "#options" => $form["service_options"],
          '#default_value' => $event->getConfig('contactform.service')
        ],
        "enabled" => [
          '#type' => 'checkbox',
          '#title' => t('Contact Form email service enabled'),
          '#default_value' => $event->getConfig('contactform.enabled'),
        ],
        "q_enabled" => [
          '#type' => 'checkbox',
          '#title' => t('Contact Form queue processing enabled'),
          '#description' => t('When selected, emails which initially fail to send are queued will be processed on each cron run.'),
          '#default_value' => $event->getConfig('contactform.q_enabled'),
        ],
      ];

      $event->setForm($form);
    }
  }

  /**
   * @inheritDoc
   */
  public static function submitForm(BosCoreFormEvent $event): void {
    if ($event->getEventType() == "bos_email_config_settings") {
      $input = $event->getFormState()->getUserInput()["bos_email"];
      $event->setConfig("contactform.service", $input["contactform"]["service"]);
      $event->setConfig("contactform.enabled", $input["contactform"]["enabled"] ?? 0);
      $event->setConfig("contactform.q_enabled", $input["contactform"]["q_enabled"] ?? 0);
    }
  }

}
