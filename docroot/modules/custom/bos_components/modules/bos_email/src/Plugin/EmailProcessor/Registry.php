<?php

namespace Drupal\bos_email\Plugin\EmailProcessor;

use Drupal\bos_core\Event\BosCoreFormEvent;
use Drupal\bos_email\CobEmail;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * EmailProcessor class for registry emails.
 */
class Registry extends EmailProcessorBase implements EventSubscriberInterface {

  /**
   * @inheritDoc
   */
  public static function getSubscribedEvents() {
    return [
      BosCoreFormEvent::CONFIG_FORM_BUILD => 'buildForm',
      BosCoreFormEvent::CONFIG_FORM_SUBMIT => 'submitForm',
//      BosCoreFormEvent::CONFIG_FORM_VALIDATE => 'validateForm'
    ];
  }

  /**
   * @inheritDoc
   */
  public static function parseEmailFields(array &$payload, CobEmail &$email_object): void {

    // Do the base email fields processing first.
    parent::parseEmailFields($payload, $email_object);

    $email_object->setField("TemplateID", $payload["template_id"]);

    isset($emailFields["name"]) && $email_object->setField("ReplyTo", "{$payload["name"]}<{$emailFields["from_address"]}>");
    $email_object->setField("TemplateModel", [
      "TextBody" => ($email_object->getField("TextBody") ?: ($email_object->getField("HtmlBody") ?: ($email_object->getField("message") ?: ""))),
    ]);

    // Create a relevant tag.
    if (str_contains($payload["subject"], "Birth")) {
      $email_object->setField("Tag", "Birth Certificate");
    }
    elseif (str_contains($payload["subject"], "Intention")) {
      $email_object->setField("Tag", "Marriage Intention");
    }
    elseif (str_contains($payload["subject"], "Death")) {
      $email_object->setField("Tag", "Death Certificate");
    }

  }

  /**
   * @inheritDoc
   */
  public static function getHoneypotField(): string {
    return "";
  }

  /**
   * @inheritDoc
   */
  public static function getGroupID(): string {
    return "registry";
  }

  /**
   * @inheritDoc
   */
  public static function buildForm(BosCoreFormEvent $event): void {

    if ($event->getEventType() == "bos_email_config_settings") {
      $form = $event->getForm();
      $form["bos_email"]["registry"] = [
        '#type' => 'fieldset',
        '#title' => 'Registry Suite',
        '#markup' => 'Emails from the Registry App - confirmations.',
        '#collapsible' => FALSE,
        '#weight' => 1,

        "service" => [
          "#type" => "select",
          '#title' => t('Registry Email Service'),
          '#description' => t('The Email Service which is currently being used.'),
          "#options" => $form["service_options"],
          '#default_value' => $event->getConfig('registry.service')
        ],
        "template" => [
          "#type" => "textfield",
          '#title' => t('Default Registry Email Template'),
          '#description' => t('The ID for the template being used  -leave blank if no template is required.'),
          '#default_value' => $event->getConfig('registry.template')
        ],
        "enabled" => [
          '#type' => 'checkbox',
          '#title' => t('Registry email service enabled'),
          '#default_value' => $event->getConfig('registry.enabled'),
        ],
        "q_enabled" => [
          '#type' => 'checkbox',
          '#title' => t('Registry queue processing enabled'),
          '#description' => t('When selected, emails which initially fail to send are queued will be processed on each cron run.'),
          '#default_value' => $event->getConfig('registry.q_enabled'),
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
      $event->setConfig("registry.service", $input["registry"]["service"]);
      $event->setConfig("registry.template", $input["registry"]["template"]);
      $event->setConfig("registry.enabled", $input["registry"]["enabled"] ?? 0);
      $event->setConfig("registry.q_enabled", $input["registry"]["q_enabled"] ?? 0);

    }
  }
}
