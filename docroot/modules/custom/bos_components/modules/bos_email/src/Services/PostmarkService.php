<?php

namespace Drupal\bos_email\Services;

use Drupal\bos_core\Controllers\Curl\BosCurlControllerBase;
use Drupal\bos_email\CobEmail;
use Drupal\bos_email\EmailServiceInterface;
use Drupal\Core\Site\Settings;
use Exception;

/**
 * EmailService class for sedning via PostMark.
 */
class PostmarkService extends BosCurlControllerBase implements EmailServiceInterface {

  const DEFAULT_ENDPOINT = 'https://api.postmarkapp.com/email';
  const TEMPLATE_ENDPOINT = "https://api.postmarkapp.com/email/withTemplate";

  // Make this protected var from BosCurlControllerBase public
  public null|string $error;

//  public array $response;

  /**
   * @inheritDoc
   */
  public function id():string {
    return "bos_email.PostmarkService";
  }

  /**
   * Get vars for Postmark servers.
   */
  public function getVars(): array {

    $postmark_env = [];
    if (getenv('POSTMARK_SETTINGS')) {
      $get_vars = explode(",", getenv('POSTMARK_SETTINGS'));
      foreach ($get_vars as $item) {
        $json = explode(":", $item);
        if (!empty($json[0]) && !empty($json[1])) {
          $postmark_env[$json[0]] = $json[1];
        }
      }
    }
    else {
      $postmark_env = Settings::get('postmark_settings') ?? [];
    }

    return $postmark_env;
  }

  /**
   * @inheritDoc
   */
  public function updateEmailObject(CobEmail &$email_object): void {
    if (empty($email_object->getField("TemplateID"))) {
      $email_object->setField("endpoint", $this::DEFAULT_ENDPOINT);
      $email_object->delField("TemplateID");
      $email_object->delField("TemplateModel");
    }
    else {
      if (!is_numeric($email_object->getField("TemplateID"))) {
        // For PostMark Templates, the tmeplate can be referred to using a
        // numeric ID or an alias.
        // Looks like an alias is being used, so update the $email_object to
        // send the alias not the ID.
        $email_object->addField("TemplateAlias", $email_object::FIELD_STRING, $email_object->getField("TemplateID"));
        $email_object->delField("TemplateID");
      }
      $email_object->setField("endpoint", $this::TEMPLATE_ENDPOINT);

      // If the EmailProcessor (in ::parseEmailFields()) has set the
      // TemplateModel (a set of arguments to pass to the template) then merge
      // these defaults in, but retain the existing.
      $model = [
        "subject" => $email_object->getField("Subject"),
        "ReplyTo" => $email_object->getField("ReplyTo"),
      ];
      $model = array_merge($model, $email_object->getField("TemplateModel"));
      $email_object->setField("TemplateModel", $model);
      // Cleanup
      $email_object->delField("ReplyTo");
      $email_object->delField("Subject");
      $email_object->delField("TextBody");
      $email_object->delField("HtmlBody");
    }
  }

  /**
   * Send email to Postmark.
   *
   * @param array $item
   * @throws Exception
   */
  public function sendEmail(array $item):void {

    $this->error = NULL;

    try {
      $server_token = $item["server"] . "_token";
      $server_token = $this->getVars()[$server_token];
      if (empty($server_token)) {
        throw new \Exception("Cannot find token for {$item['server']}");
      }

      $headers = [
        "Accept" => "application/json",
        "Content-Type" => "application/json",
        "X-Postmark-Server-Token" => $server_token,
      ];
      if (\Drupal::request()->headers->has("X-PM-Bounce-Type")) {
        $headers["X-PM-Bounce-Type"] =  \Drupal::request()->headers->get("X-PM-Bounce-Type");
      }

      try {
        $response = $this->post($item["endpoint"], $item, $headers);
      }
      catch (Exception $e) {
        $headers = json_encode($this->request["headers"]);
        $item = json_encode($item);
        $response = json_encode($this->response);
        $this->error = "Error posting to Postmark - {$e->getMessage()}";
        throw new \Exception("Posting Error {$this->response["http_code"]}<br>HEADERS: {$headers}<br>PAYLOAD: {$item}<br>RESPONSE:{$response}");
      }

      if (!$response && !empty($this->response["response_raw"])) {
        $response = json_decode($this->response["response_raw"], TRUE);
        $this->error = "Error code ({$response["ErrorCode"]}) returned from Postmark - {$response["Message"]}";
        $headers = json_encode($headers);
        $item = json_encode($item);
        $response = json_encode($this->response);
        throw new \Exception("Return Error Code: {$this->response["http_code"]}<br>HEADERS: {$headers}<br>PAYLOAD: {$item}<br>RESPONSE:{$response}");
      }

    }
    catch (Exception $e) {
      $this->error = $e->getMessage();
      throw new Exception($this->error);
    }
  }

}
