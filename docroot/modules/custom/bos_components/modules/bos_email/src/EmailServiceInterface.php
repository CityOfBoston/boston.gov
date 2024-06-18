<?php

namespace Drupal\bos_email;

interface EmailServiceInterface {

  const DEFAULT_ENDPOINT = '';
  const TEMPLATE_ENDPOINT = '';

  /**
   * Returns the ID for this service.
   * @return string
   */
  public function id():string;

  /**
   * Modify the email parameters for service requirements, e.g. templates.
   *
   * @param array $email_object
   *
   * @return void
   */
  public function updateEmailObject(CobEmail &$email_object): void;

  /**
   * Send email via the Service.
   *
   * @param array $item Containing email fields for the service to send.
   *
   * @returns void
   * @throws \Exception
   */
  public function sendEmail(array $item): void;

  /**
   * Fetches any specific settings for this service.
   * @return array
   */
  public function getVars():array;

  public function response(): array;

}
