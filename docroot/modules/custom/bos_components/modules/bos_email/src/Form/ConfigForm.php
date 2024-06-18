<?php

namespace Drupal\bos_email\Form;

use Drupal\bos_core\Event\BosCoreFormEvent;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class ConfigForm extends ConfigFormBase {

  /**
   * @inheritDoc
   */
  protected function getEditableConfigNames() {
    return ["bos_email.settings"];
  }

  /**
   * @inheritDoc
   */
  public function getFormId() {
    return "bos_email_config_settings";
  }

  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->configFactory->getEditable(self::getEditableConfigNames()[0]);

    $form["service_options"] = [
      "DrupalService" => "Drupal",
      "PostmarkService" => "Postmark"
    ];

    $form["bos_email"] = [
      '#type' => 'fieldset',
      '#title' => 'City of Boston Emailer',
      '#markup' => 'Fine-grain management for emails sent via City of Boston REST API.',
      "#tree" => TRUE,

      "enabled" => [
        '#type' => 'checkbox',
        '#title' => t('Email Service Enabled'),
        '#description' => t('When selected, emails will be sent via the indicated email service. When unselected all emails are added to the queue.'),
        '#default_value' => $config->get('enabled'),
        '#weight' => -10,
      ],
      "q_enabled" => [
        '#type' => 'checkbox',
        '#title' => t('Email-fail Queue Enabled'),
        '#description' => t('When selected, emails that the email service cannot process will be queued and there will be attempts to be resend. When unselected failed emails are discarded.'),
        '#default_value' => $config->get('q_enabled'),
        '#weight' => -9
      ],

      "alerts" => [
        '#type' => 'details',
        '#title' => 'Email Service monitoring',
        '#description' => 'Configure internal alert emails for issues which arise during operations.',
        '#open' => FALSE,
        '#weight' => -8,

        "conditions" => [
          '#type' => 'fieldset',
          '#title' => 'Service Abuse',
          '#markup' => 'Emails will be sent to the recipient below when these potential abuse events occur:',
          '#collapsible' => FALSE,

          "token" => [
            '#type' => 'checkbox',
            '#title' => t('An incorrect API authentication token is provided. This could indicate a hacking attempt or attempted spam/relay abuse.'),
            '#default_value' => $config->get('alerts.token') ?? 0,
          ],
          "honeypot" => [
            '#type' => 'checkbox',
            '#title' => t('The honeypot field (a hidden input field a \'person\' cannot see or update) in a submitted form has data in it. This could indictate hacking attempt or attempted spam/relay abuse.'),
            '#default_value' => $config->get('alerts.honeypot') ?? 0,
          ],
          "recipient" => [
            '#type' => 'textfield',
            "#title" => "Email recipient",
            "#description" => "The email (or email group) to receive hardbounce alerts.",
            "#attributes" => ["placeholder" => "someone@boston.gov"],
            "#default_value" => $config->get('alerts.recipient') ?? "",
          ],
        ],

        "monitoring" => [
          '#type' => 'fieldset',
          '#title' => 'Service Monitoring',
          '#markup' => 'Emails will be sent to the recipient below when these unexpected service error events occur:',
          '#collapsible' => FALSE,
          "all" => [
            '#type' => 'checkbox',
            '#title' => t('All non-abuse failures.'),
            '#default_value' => $config->get('monitor.all') ?? 0,
          ],
          "recipient" => [
            '#type' => 'textfield',
            "#title" => "Email recipient",
            "#description" => "The email (or email group) to receive service error emails.",
            "#attributes" => ["placeholder" => "someone@boston.gov"],
            "#default_value" => $config->get('monitor.recipient') ?? "",
          ],
        ],

        "hb" => [
          '#type' => 'fieldset',
          '#title' => 'Hard Bounce / Recipient Supression',
          '#markup' => 'Emails will be sent to the recipient below when the following normal conditions occur:',
          '#collapsible' => FALSE,

          "hardbounce" => [
            '#type' => 'checkbox',
            '#title' => t('The intended recipient is suppressed by Email Service.'),
            '#default_value' => $config->get('hardbounce.hardbounce') ?? 0,
          ],
          "recipient" => [
            '#type' => 'textfield',
            "#title" => "Email recipient",
            "#description" => "The email (or email group) to receive hardbounce alerts.",
            "#attributes" => ["placeholder" => "someone@boston.gov"],
            "#default_value" => $config->get('hardbounce.recipient') ?? "",
          ],
        ],

        "footnote" => ['#markup' => "NOTE: These email alerts are sent via Drupal mail."],
      ],

    ];

    // Dispatch an event to form listeners so that they can add their configs
    // to this form.
    $event = new BosCoreFormEvent($this->getFormId(), $form, $form_state, $config);
    $dispatcher = \Drupal::service('event_dispatcher');
    $dispatcher->dispatch($event, BosCoreFormEvent::CONFIG_FORM_BUILD);

    $form = $event->getForm();
    unset($form["service_options"]);

    $form = parent::buildForm($form, $event->getFormState());
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {

    if ($input = $form_state->getUserInput()["bos_email"]) {
      $config = $this->configFactory->getEditable(self::getEditableConfigNames()[0]);
      $config
        ->set("enabled", $input["enabled"])
        ->set("q_enabled", $input["q_enabled"])
        ->set("alerts.recipient", $input["alerts"]["conditions"]["recipient"] ?? "")
        ->set("hardbounce.hardbounce", $input["alerts"]["hb"]["hardbounce"] ?? 0)
        ->set("hardbounce.recipient", $input["alerts"]["hb"]["recipient"] ?? "")
        ->set("alerts.recipient", $input["alerts"]["conditions"]["recipient"] ?? 0)
        ->set("alerts.token", $input["alerts"]["conditions"]["token"] ?? 0)
        ->set("alerts.honeypot", $input["alerts"]["conditions"]["honeypot"] ?? 0)
        ->set("monitor.recipient", $input["alerts"]["monitoring"]["recipient"] ?? 0)
        ->set("monitor.all", $input["alerts"]["monitoring"]["all"] ?? 0)
        ->save();
    }

    // Dispatch an event to form listeners so that they can save their configs
    // to this form.
    $event = new BosCoreFormEvent($this->getFormId(), $form, $form_state, $config);
    $dispatcher = \Drupal::service('event_dispatcher');
    $dispatcher->dispatch($event, BosCoreFormEvent::CONFIG_FORM_SUBMIT);

  }

}
