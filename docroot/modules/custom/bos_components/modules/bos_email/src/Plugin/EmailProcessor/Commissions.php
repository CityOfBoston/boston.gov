<?php

namespace Drupal\bos_email\Plugin\EmailProcessor;

use Drupal\bos_core\Event\BosCoreFormEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * EmailProcessor class for Commissions.
 */
class Commissions extends DefaultEmail implements EventSubscriberInterface {

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
    return "commissions";
  }

  /**
   * @inheritDoc
   */
  public static function buildForm(BosCoreFormEvent $event): void {

    if ($event->getEventType() == "bos_email_config_settings") {
      $form = $event->getForm();
      $form["bos_email"]["commissions"] = [
        '#type' => 'fieldset',
        '#title' => 'Commissions App',
        '#markup' => 'Emails from the Commissions App.',
        '#collapsible' => FALSE,
        '#weight' => 4,

        "service" => [
          "#type" => "select",
          '#title' => t('Commissions Email Service'),
          '#description' => t('The Email Service which is currently being used.'),
          "#options" => $form["service_options"],
          '#default_value' => $event->getConfig('commissions.service')
        ],
        "enabled" => [
          '#type' => 'checkbox',
          '#title' => t('Commission email service enabled'),
          '#default_value' => $event->getConfig('commissions.enabled'),
        ],
        "q_enabled" => [
          '#type' => 'checkbox',
          '#title' => t('Commissions queue processing enabled'),
          '#description' => t('When selected, emails which initially fail to send are queued will be processed on each cron run.'),
          '#default_value' => $event->getConfig('commissions.q_enabled'),
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
      $event->setConfig("commissions.service", $input["commissions"]["service"]);
      $event->setConfig("commissions.enabled", $input["commissions"]["enabled"] ?? 0);
      $event->setConfig("commissions.q_enabled", $input["commissions"]["q_enabled"] ?? 0);
    }
  }

}
