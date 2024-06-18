<?php

namespace Drupal\bos_email\Services;

use Drupal;
use Drupal\bos_email\EmailServiceInterface;

/**
 * Class to create (and save), remove, and get/verify a session token.
 */
class TokenOps {

  public $session;

  public function __construct() {
    $this->session = Drupal::request()->getSession();
  }

  /**
   * Creates an expirable one-use token. The token will expire after 60 minutes.
   *
   * @return array The new Session Token just created.
   */
  public function sessionTokenCreate(): array {
    $date_time = Drupal::time()->getCurrentTime();
    Drupal::service("keyvalue.expirable")
      ->get("client_rest_token")
      ->setWithExpire("token_session_{$date_time}", $date_time, (12 * 60 * 60));
    //    $this->session->set("token_session_{$date_time}", $date_time);
    return ['token_session' => $date_time];
  }

  /**
   * Removes the token from the session object.
   *
   * @param string $data The session Token to remove.
   *
   * @return string[] Status.
   */
  public function sessionTokenRemove(string $data): array {
    Drupal::service("keyvalue.expirable")
      ->get("client_rest_token")
      ->delete("token_session_{$data}");
//    if ($this->session->remove("token_session_{$data}") !== null) {
//    if ($del) {
      return ['token_session' => "removed"];
//    }
//    return ['token_session' => "not found"];
  }

  /**
   * Returns an array with token_session indicating if it exists.
   * This is usually used to check if a session token is valid. The field
   * token_value is populated if the token is found.
   *
   * @param string $data The Session Token.
   *
   * @return array
   */
  public function sessionTokenGet(string $data): array {
    $result = Drupal::service("keyvalue.expirable")
      ->get("client_rest_token")
      ->get("token_session_{$data}") ?? FALSE;

//    if ($this->session->get("token_session_{$data}", FALSE)) {
    if ($result) {
      return [
        'token_session' => TRUE,
        'token_value' => "token_session_{$data}",
      ];
    }
    return  ['token_session' => FALSE];


  }

  /**
   * Check bearer token and authenticate.
   */
  public static function checkBearerToken(string $bearer_token, EmailServiceInterface $email_service) {

    $matches = [];
    // Read the auth key from settings.
    $token = $email_service->getVars()["auth"];
    // Fetch the token from the posted form.
    $post_token = explode("Token ",$bearer_token);
    $quad_chunk = str_split($post_token[1], 4);

    foreach ($quad_chunk as $item) {
      if (str_contains($token, $item)) {
        array_push($matches, $item);
      }
    }

    return count(array_unique($matches)) == 15;
  }

}
