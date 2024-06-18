<?php

namespace Drupal\bos_email;

use Drupal\bos_core\Event\BosCoreFormEvent;
use Symfony\Component\HttpFoundation\Request;

interface EmailProcessorInterface {

  /**
   * Custom mapping of the data in $payload into the structured array $emailFields.
   * We can create new fields in the $emailFields using ->addField but this
   * should be an unusal circumstance. More commnly, when using a Template, we
   * can set additional template arguments/parameters from the payload in this
   * function by setting:
   *   $email_object->setField("TemplateModel", [assoc array of params]);
   *
   * @param array $payload The payload from the request
   * @param CobEmail $email_object The structured email object used by mail services.
   *
   * @return void
   */
  public static function parseEmailFields(array &$payload, CobEmail &$email_object): void;

  /**
   * Read request and extract the payload - output as an associative array.
   * Typically, the code set in the EmailProcessorBase class is sufficient.
   * Overriding this function would allow you to extract the payload from a
   * request format/type which is not supported in the base class.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return array
   */
  public static function fetchPayload(Request $request): array;

  /**
   * Creates a message body for incoming emails.
   *
   * @param array $emailFields An array containing the fields supplied from a
   *        webhook callback.
   *
   * @return void
   */
  public static function formatInboundEmail(array $payload, CobEmail &$email_object): void;

  /**
   * Creates a message body for plain text message.
   * Adds/Updates field "TextBody" with formatted msg body to supplied array.
   *
   * @param array $emailFields An array containing the fields supplied from a
   *    form or the calling function needed by the template.
   * @return void
   */
  public static function templatePlainText(array &$payload, CobEmail &$email_object): void;

  /**
   * Creates a message body for html message.
   * Adds/Updates field "HtmlBody" with formatted msg body to supplied array.
   *
   * @param array $emailFields An array containing the fields supplied from a
   *    form or the calling function needed by the template.
   * @return void
   */
  public static function templateHtmlText(array &$payload, CobEmail &$email_object): void;

  /**
   * Returns the payload field which is a honeypot for the form submitted.
   * NOTE: Should return "" if there is no honeypot.
   *
   * @return string The name of the honeypot field on the form (if any).
   *
   */
  public static function getHoneypotField(): string;

  /**
   * Return the correct email service to use to relay the email.
   * Typically, the code set in the EmailProcessorBase class is sufficient
   * (it reads the configuration settings from the configForm).
   * Overriding this function would allow you to specifically define a
   * service/class in a way not supported by the base class.
   * @see buildForm
   * @see submitForm
   *
   * @param string $group_id The group ID from this Processor.
   *
   * @return \Drupal\bos_email\EmailServiceInterface
   */
  public static function getEmailService(string $group_id): EmailServiceInterface;

  /**
   * Return a group id to use in this email service.
   *
   * @return string The name of the groupid.
   *
   * This is used throughout the app and controls which outbound email server is
   * used in Postmark - There is a token in the ENVAR POSTMARK_SETTINGS
   * ([server]_token) which directs the email to the correct postmark server.
   * Other email services may require a similar concept.
   */
  public static function getGroupID(): string;

  /**
   * Inject configuration settings into config form.
   *
   * @param \Drupal\bos_core\Event\BosCoreFormEvent $event
   *
   * @return void
   */
  public static function buildForm(BosCoreFormEvent $event): void;

  /**
   * Save config values from config form
   *
   * @param \Drupal\bos_core\Event\BosCoreFormEvent $event
   *
   * @return void
   */
  public static function submitForm(BosCoreFormEvent $event): void;

  /**
   * Validate config form before it is saved.
   * @param \Drupal\bos_core\Event\BosCoreFormEvent $event
   *
   * @return void
   */
  public static function validateForm(BosCoreFormEvent $event): void;

}
