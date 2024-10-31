<?php

namespace Drupal\bos_google_cloud\Controller;

use Drupal;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Exception;
use Symfony\Component\HttpFoundation\Request;

/**
class GcApiEndpoint
Controller for the gen-ai REST endpoint in bos_google_cloud

david 02 2024
@file docroot/modules/custom/bos_components/modules/bos_google_cloud/src/Controller/GcApiEndpoint.php
 */
class GcApiEndpoint extends ControllerBase {

  public Request $request;
  public CacheableJsonResponse $response;
  public array $payload;

  public function __construct() {
    $this->response = new CacheableJsonResponse();
  }

  /**
   * This is the entrypoint for the endpoint.
   *
   * After basic validation, service requests are redirected here.
   *
   * @param string $action The action called via the endpoint
   *
   * @return CacheableJsonResponse Json returned to caller.
   */
  public function entry(string $action): CacheableJsonResponse {

    $action = strtolower($action);
    $this->request = Drupal::request();
    $payload = $this->getPayload();

    return $this->{$action}($payload);

  }

  /**
   * AI Search of boston.gov endpoint.
   *
   * Requires an array with "search" and optionally "prompt" in its JSON payload
   *
   * @param array $payload
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   */
  private function search(array $payload): CacheableJsonResponse {

    if (empty($payload["search"])) {
      return $this->error("Must have a search string");
    }

    $options = [
      "search" => $payload["search"],
      "prompt" => $payload["prompt"]
    ];
    $search = Drupal::service("bos_google_cloud.GcSearch");
    $result = $search->execute($options);

    if ($search->error()) {
      return $this->error($search->error());
    }

    return $this->output($result, $search->response()["http_code"]);

  }

  /**
   * AI Conversation of boston.gov endpoint.
   *
   * Requires an array with "text" and optionally "prompt" in its JSON payload,
   * and possibly a session_id to continue a previous conversation.
   *
   * @param array $payload
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   */
  private function converse(array $payload): CacheableJsonResponse {

    if ($payload["session_id"]) {
      $payload["allow_conversation"] = TRUE;
    }

    $converse = Drupal::service("bos_google_cloud.GcConversation");
    $result = $converse->execute($payload);

    if ($converse->error()) {
      return $this->error($converse->error());
    }

    $response = $converse->response();
    if ($payload["allow_conversation"]) {
      return $this->output($result . "\r\nid: " . $response["session_id"], $response["http_code"]);
    }
    return $this->output($result, $response["http_code"]);

  }

  /**
   * Use GenAI to summarize text endpoint.
   *
   * Requires an array with "text" and optionally "prompt" in the payload.
   *
   * @param array $payload
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   */
  private function summarize(array $payload): CacheableJsonResponse {

    if (empty($payload["text"])) {
      return $this->error("Must have a text string to summarize");
    }

    $options = [
      "text" => $payload["text"],
      "prompt" => $payload["prompt"] ?? "default",
    ];
    $summarizer = Drupal::service("bos_google_cloud.GcTextSummarizer");
    $result = $summarizer->execute($options);

    if ($summarizer->error()) {
      return $this->error($summarizer->error());
    }

    return $this->output($result, $summarizer->response()["http_code"]);

  }

  /**
   * Use GenAI to rewrite text endpoint.
   *
   * Requires an array with "text" and optionally "prompt" in the payload.
   *
   * @param array $payload
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   */
  private function rewrite(array $payload): CacheableJsonResponse {

    if (empty($payload["text"])) {
      return $this->error("Must have a text string to rewrite");
    }

    $options = [
      "text" => $payload["text"],
      "prompt" => $payload["prompt"] ?? "default",
    ];
    $rewriter = Drupal::service("bos_google_cloud.GcTextRewriter");
    $result = $rewriter->execute($options);

    if ($rewriter->error()) {
      return $this->error($rewriter->error());
    }

    return $this->output($result, $rewriter->response()["http_code"]);

  }

  /**
   * Use GenAI to translate text endpoint.
   *
   * Requires an array with "text", "lang" and optionally "prompt" in the
   * payload.
   *
   * @param array $payload
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   */
  private function translate(array $payload): CacheableJsonResponse {

    if (empty($payload["text"])) {
      return $this->error("Must have some text to translate");
    }

    if (empty($payload["lang"])) {
      return $this->error("Must have a language to translate to!");
    }

    $options = [
      "text" => $payload["text"],
      "lang" => $payload["lang"],
      "prompt" => $payload["prompt"] ?? "default",
    ];
    $translator = Drupal::service("bos_google_cloud.GcTranslate");
    $result = $translator->execute($options);

    if ($translator->error()) {
      return $this->error($translator->error());
    }

    return $this->output($result, $translator->response()["http_code"]);

  }

  /**
   * Fetches the payload or Body as an array.
   *
   * @return array
   */
  private function getPayload(): array {
    try {
      $payload = $this->request->getPayload();
      $this->payload = $payload->all();
    }
    catch (Exception) {
      return [];
    }
    return $this->payload;
  }

  /**
   * Creates a standardized error response in JSON.
   *
   * @param string $message The error message
   * @param int $code The HTTP Code to be returned.
   *
   * @return CacheableJsonResponse JSON response to be returned to caller
   */
  private function error(string $message, int $code = 400): CacheableJsonResponse {
    return $this->response
      ->setContent(json_encode([
        "status" => "Error",
        "result" => $message
      ]))
      ->setStatusCode($code);
  }

  /**
   * Creates a standardized successful response in JSON.
   *
   * @param string $result The response message
   * @param int $code The HTTP Code to be returned.
   *
   * @return CacheableJsonResponse JSON response to be returned to caller
   */
  private function output(string $result, int $code = 200): CacheableJsonResponse {
    return $this->response
      ->setContent(json_encode([
        "status" => "success",
        "result" => $result
      ]))
      ->setStatusCode($code);
  }

}
