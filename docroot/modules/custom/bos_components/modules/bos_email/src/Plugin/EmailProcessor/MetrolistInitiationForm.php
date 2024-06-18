<?php

namespace Drupal\bos_email\Plugin\EmailProcessor;

use Drupal\bos_core\Event\BosCoreFormEvent;
use Drupal\bos_email\CobEmail;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * EmailProcessor class for Metrolist Initiation Form Emails.
 */
class MetrolistInitiationForm extends EmailProcessorBase implements EventSubscriberInterface {

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
  public static function templatePlainText(array &$payload, CobEmail &$email_object):void {

    $plain_text = trim($payload["message"]);
    $plain_text = html_entity_decode($plain_text);
    // Replace html line breaks with carriage returns
    $plain_text = str_ireplace(["<br>", "</p>", "</div>"], ["\n", "</p>\n", "</div>\n"], $plain_text);
    $plain_text = strip_tags($plain_text);

    $plain_text = "
Click the link below to submit information about your available property or access past listings.
IMPORTANT: Do not reuse this link. If you need to submit listings for additional properties, please request a new form.\n
Metrolist Listing Form: {$plain_text} \n
Questions? Feel free to email metrolist@boston.gov\n
--------------------------------
This message was requested from " . urldecode($payload['url']) . ".
 The request was initiated by {$payload['to_address']}.
--------------------------------
";

    $email_object->setField("TextBody", $plain_text);

  }

  /**
   * @inheritDoc
   */
  public static function templateHtmlText(array &$payload, CobEmail &$email_object):void {

    $html = trim($payload["message"]);
    // Replace carriage returns with html line breaks
    $html = str_ireplace(["\n", "\r\n"], ["<br>"], $html);

    // $payload["message"] received the link url. We can change it into a
    // button here.
    $html = "<a class=\"button\" href=\"{$html}\" tabindex=\"-1\" target=\"_blank\">
               Launch metrolist listing form
             </a>";
    $form_url = urldecode($payload['url']);

    $html = "
<img class='ml-icon' height='34' src='https://assets.boston.gov/icons/metrolist/metrolist-logo_email.png' />\n
<p class='txt'>Click the button below to submit information about your available property or access past listings.</p>\n
<p class='txt'><span class='txt-b'>Important: Do not reuse this link.</span> If you need to submit listings for additional properties, please request a new form.</p>\n
<p class='txt'>{$html}</p>\n
<p class='txt'>Questions? Feel free to email metrolist@boston.gov</p>\n
<p class='txt'><br /><table class='moh-signature' cellpadding='0' cellspacing='0' border='0'><tr>\n
<td><a href='https://content.boston.gov/departments/housing' target='_blank'>
  <img height='34' class='ml-icon' src='https://assets.boston.gov/icons/metrolist/neighborhood_development_logo_email.png' />
  </a></td>\n
<td>Thank you<br /><span class='txt-b'>The Mayor's Office of Housing</span></td>\n
</tr></table></p>\n
<hr>\n
<p class='txt'>This message was requested from the <a target='_blank' href='{$form_url}'>Metrolist Listing</a> service on Boston.gov.</p>\n
<p class='txt'>The request was initiated by {$payload['to_address']}.</p>
<hr>\n
";

    // check for complete-ness of html
    if (!str_contains($html, "<html")) {
      $html = "<html>\n<head></head>\n<body>{$html}</body>\n</html>";
    }
    // Add a title
    $html = preg_replace(
      "/\<\/head\>/i",
      "<title>Metrolist Listing Link</title>\n</head>",
      $html);
    // Add in the css
    $css = self::getCss();
    $html = preg_replace(
      "/\<\/head\>/i",
      "{$css}\n</head>",
      $html);

    $email_object->setField("HtmlBody", $html);

  }

  /**
   * @inheritDoc
   */
  public static function parseEmailFields(array &$payload, CobEmail &$email_object): void {

    // Do the base email fields processing first.
    parent::parseEmailFields($payload, $email_object);

    $email_object->setField("Tag", "metrolist form initiation");

    self::templatePlainText($payload, $email_object);
    if (!empty($payload["useHtml"])) {
      self::templateHtmlText($payload, $email_object);
    }

  }

  /**
   * @inheritDoc
   */
  public static function getCss():string {
    $css = parent::getCss();
    return "<style>
        {$css}
        .ml-icon {
          height: 34px;
        }
        table.moh-signature {
          border: none;
          border-collapse: collapse;
        }
        table.moh-signature tr,
        table.moh-signature td {
          padding: 3px;
          border-collapse: collapse;
        }
      </style>";
  }

  /**
   * @inheritDoc
   */
  public static function getGroupID(): string {
    return "metrolist";
  }

  /**
   * @inheritDoc
   */
  public static function buildForm(BosCoreFormEvent $event): void {

    if ($event->getEventType() == "bos_email_config_settings") {
      $form = $event->getForm();
      $form["bos_email"]["metrolist"] = [
        '#type' => 'fieldset',
        '#title' => 'Metrolist Listing Form',
        '#markup' => 'Emails sent from Metrolist Listing Form processes.',
        '#collapsible' => FALSE,
        '#weight' => 2,

        "service" => [
          "#type" => "select",
          '#title' => t('Metrolist Email Service'),
          '#description' => t('The Email Service which is currently being used.'),
          "#options" => $form["service_options"],
          '#default_value' => $event->getConfig('metrolist.service')
        ],
        "enabled" => [
          '#type' => 'checkbox',
          '#title' => t('Metrolist email service enabled'),
          '#default_value' => $event->getConfig('metrolist.enabled'),
        ],
        "q_enabled" => [
          '#type' => 'checkbox',
          '#title' => t('Metrolist queue processing enabled'),
          '#description' => t('When selected, emails which initially fail to send are queued will be processed on each cron run.'),
          '#default_value' => $event->getConfig('metrolist.q_enabled'),
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
      $event->setConfig("metrolist.service", $input["metrolist"]["service"]);
      $event->setConfig("metrolist.enabled", $input["metrolist"]["enabled"] ?? 0);
      $event->setConfig("metrolist.q_enabled", $input["metrolist"]["q_enabled"] ?? 0);
    }
  }

}
