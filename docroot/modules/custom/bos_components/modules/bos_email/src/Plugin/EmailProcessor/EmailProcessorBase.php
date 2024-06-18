<?php

namespace Drupal\bos_email\Plugin\EmailProcessor;

use Drupal\bos_core\Event\BosCoreFormEvent;
use Drupal\bos_email\EmailProcessorInterface;
use Drupal\bos_email\EmailServiceInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\bos_email\CobEmail;

class EmailProcessorBase implements EmailProcessorInterface {

  /**
   * Defines standard css rules for CoB emails.
   *
   * @return string
   */
  public static function getCss() {
    return "
body {
  font-family: Lora, serif;
  font-size: normal;
}
.txt {}
.txt-h {
  font-size: larger;
}
.txt-b {
  font-weight: bold;
}
.button {
  background-color: #1871bd;
  color: #ffffff !important;
  font-family: Montserrat,Arial,sans-serif;
  font-size: normal;
  font-weight: 700;
  letter-spacing: 1px;
  line-height: 16px;
  line-height: 1rem;
  margin: 0;
  padding: 20px;
  padding: 1.25rem;
  text-transform: uppercase;
  text-decoration: none;
  border: none;
  cursor: pointer;
  display: inline-block;
}
a.button:link {
  text-decoration: none;
}
a.button:hover {
  background-color: #d22d23;
}
.visually-hidden {
  position: absolute!important;
  height: 1px;
  width: 1px;
  overflow: hidden;
  clip: rect(1px,1px,1px,1px);
}
    ";
  }

  /**
   * @inheritDoc
   */
  public static function getEmailService(string $group_id): EmailServiceInterface {
    $config = \Drupal::service("config.factory")->get("bos_email.settings");
    $email_service = $config->get("{$group_id}.service");
    $email_service = "Drupal\\bos_email\\Services\\{$email_service}";
    return new $email_service;
  }

  /**
   * @inheritDoc
   */
  public static function parseEmailFields(array &$payload, CobEmail &$email_object): void {
    // Create a nicer sender address if possible.
    if (isset($payload["sender"]) && isset($payload["from_address"])) {
      $payload["modified_from_address"] = $payload["from_address"];
      $payload["modified_from_address"] = "{$payload["sender"]}<{$payload["from_address"]}>";
    }
    // Try to map the payload fields into the mail_object.
    $email_object->setField("Tag", ($payload['tag'] ?? ""));
    $email_object->setField("To", ($payload["to_address"] ?? ($payload["to"] ?? ($payload["recipient"] ?? ""))));
    $email_object->setField("From", ($payload["modified_from_address"] ?? ($payload["from_address"] ?? "")));
    $email_object->setField("TextBody", ($payload["TextBody"] ?: ($payload["message"] ?: ($payload["body"] ?: ""))));
    $email_object->setField("HtmlBody", ($payload["HtmlBody"] ?: ($payload["message"] ?: ($payload["body"] ?: ""))));
    $email_object->setField("ReplyTo", ($payload["reply_to"] ?? ($payload["from_address"] ?? "")));
    !empty($payload['subject']) && $email_object->setField("Subject", $payload["subject"]);
    !empty($payload['cc']) && $email_object->setField("Cc", $payload['cc']);
    !empty($payload['bcc']) && $email_object->setField("Bcc", $payload['bcc']);
    !empty($payload['headers']) && $email_object->setField("Headers", $payload['headers']);
  }

  /**
   * Decodes the payload from the request into an associative array.
   *
   * @param string $payload
   *
   * @return void
   */
  public static function fetchPayload(Request $request): array {

    if ($request->getContentTypeFormat() == "form") {
      $_payload = $request->getPayload();
      if ($_payload->has("email")) {
        $payload = $request->get("email");
        $_payload->remove("email");
      }
      $payload = array_merge(($_payload->all() ?? []), ($payload ?? []));
      return $payload;
    }
    elseif ($request->getContentTypeFormat() == "json") {
      if ($_payload = $request->getContent()) {
        $_payload = json_decode($_payload, TRUE);
        foreach ($_payload as $key => $value) {
          if (str_contains($key, "email")) {
            $payload[preg_replace('~email\[(.*)\]~', '$1', $key)] = $value;
          }
          else {
            $payload[$key] = $value;
          }
        }
        return $payload;
      }
    }
    return [];

  }

  /**
   * @inheritDoc
   */
  public static function formatInboundEmail(array $payload, CobEmail &$email_object): void {}

  /**
   * @inheritDoc
   */
  public static function templatePlainText(array &$payload, CobEmail &$email_object): void {}

  /**
   * @inheritDoc
   */
  public static function templateHtmlText(array &$payload, CobEmail &$email_object): void {}

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
    return "default";
  }

  /**
   * @inheritDoc
   */
  public static function buildForm(BosCoreFormEvent $event): void {}

  /**
   * @inheritDoc
   */
  public static function submitForm(BosCoreFormEvent $event): void {}

  /**
   * @inheritDoc
   */
  public static function validateForm(BosCoreFormEvent $event): void {}

}
