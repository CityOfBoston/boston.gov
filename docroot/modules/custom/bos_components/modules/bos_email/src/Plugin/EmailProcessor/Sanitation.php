<?php

namespace Drupal\bos_email\Plugin\EmailProcessor;

use Drupal\bos_core\Event\BosCoreFormEvent;
use Drupal\bos_email\CobEmail;
use Exception;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * EmailProcessor class for Sanitation Scheduling Emails.
 */
class Sanitation extends EmailProcessorBase implements EventSubscriberInterface {

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
   * @inheritDoc
   */
  public static function getGroupID(): string {
    return "sanitation";
  }

  /**
   * Decodes the payload from a json string into an associative array.
   *
   * @param string $payload
   *
   * @return void
   */
  public static function fetchPayload(Request $request): array {

    if ($request->getContentTypeFormat() != "json") {
      return [];
    }

    $payload = $request->getContent();

    if ($payload) {
      try {
        return json_decode($payload, TRUE);
      }
      catch (Exception $e) {
        return [];
      }
    }
    return [];
  }

  /**
   * @inheritDoc
   */
  public static function parseEmailFields(array &$payload, CobEmail &$email_object): void {

    // Do the base email fields processing first.
    parent::parseEmailFields($payload, $email_object);

    // Set up the sanitation template.
    $template_id = \Drupal::config("bos_email.settings")->get("sanitation.template");
    $email_object->setField("TemplateID", $template_id);
    $email_object->setField("Tag", $payload["type"]);

    $email_object->setField("TemplateModel", [
      "TextBody" => ($email_object->getField("TextBody") ?: ($email_object->getField("HtmlBody") ?: ($email_object->getField("message") ?: ""))),
      "HtmlBody" => ($email_object->getField("HtmlBody") ?: ($email_object->getField("TextBody") ?: ($email_object->getField("message") ?: ""))),
    ]);

    // is this to be scheduled?
    if (!empty($payload["senddatetime"])) {
      $email_object->setSendDate($payload["senddatetime"]);
    }
    else {
      // Remove the field from the object.
      $email_object->delField("senddatetime");
    }

  }

  /**
   * @inheritDoc
   */
  public static function buildForm(BosCoreFormEvent $event): void {

    if ($event->getEventType() == "bos_email_config_settings") {
      $form = $event->getForm();
      $form["bos_email"]["sanitation"] = [
        '#type' => 'fieldset',
        '#title' => 'Sanitation Email Services',
        '#markup' => 'Emails sent from Sanitation WebApp.',
        '#collapsible' => FALSE,
        '#weight' => 3,

        "service" => [
          "#type" => "select",
          '#title' => t('Sanitation Email Service'),
          '#description' => t('The Email Service which is currently being used.'),
          "#options" => $form["service_options"],
          '#default_value' => $event->getConfig('sanitation.service')
        ],
        "template" => [
          "#type" => "textfield",
          '#title' => t('Default Sanitation Email Template'),
          '#description' => t('The ID for the template being used  -leave blank if no template is required.'),
          '#default_value' => $event->getConfig('sanitation.template')
        ],
        "enabled" => [
          '#type' => 'checkbox',
          '#title' => t('Sanitation email service enabled'),
          '#default_value' => $event->getConfig('sanitation.enabled'),
        ],
        "q_enabled" => [
          '#type' => 'checkbox',
          '#title' => t('Sanitation queue processing enabled'),
          '#description' => t('When selected, emails which initially fail to send are queued will be processed on each cron run.'),
          '#default_value' => $event->getConfig('sanitation.q_enabled'),
        ],
        "sched_enabled" => [
          '#type' => 'checkbox',
          '#title' => t('Sanitation scheduled email processing enabled'),
          '#description' => t('When selected, scheduled emails are queued will be processed on each cron run.'),
          '#default_value' => $event->getConfig('sanitation.sched_enabled'),
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
      $event->setConfig("sanitation.service", $input["sanitation"]["service"]);
      $event->setConfig("sanitation.template", $input["sanitation"]["template"]);
      $event->setConfig("sanitation.enabled", $input["sanitation"]["enabled"] ?? 0);
      $event->setConfig("sanitation.sched_enabled", $input["sanitation"]["sched_enabled"] ?? 0);
      $event->setConfig("sanitation.q_enabled", $input["sanitation"]["q_enabled"] ?? 0);
    }
  }

}
