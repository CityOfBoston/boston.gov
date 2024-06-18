<?php

namespace Drupal\bos_email\Plugin\EmailProcessor;

use Drupal\bos_email\CobEmail;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;

/**
 * EmailProcessor class for Default Email.
 */
class DefaultEmail extends EmailProcessorBase {

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

    // if using a Template, we can set the template arguments/parameters in this
    // function by setting:
    //   $email_object->setField("TemplateModel", [assoc array of params]);

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
  public static function getGroupID(): string {
    return "default";
  }

}
