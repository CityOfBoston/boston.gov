<?php

namespace Drupal\bos_core\Controllers\Curl;


use CurlHandle;
use Exception;

class BosCurlControllerBase {

  public const BAD_REQUEST = 0;
  public const NO_CURL = 1;
  public const CURL_OPT_ERROR = 2;
  public const CURL_GENERAL_ERROR = 3;
  public const BAD_RESPONSE = 4;
  public const PERMISSION_DENIED = 5;
  public const AUTHENTICATION_ERROR = 6;
  public const VENDOR_INTERNAL_ERROR = 7;
  public const NEEDS_VALIDATION = 8;
  public const CURL_EXECUTION_ERROR = 9;
  public const CURL_EMPTY_RESPONSE = 10;
  public const NOT_FOUND = 11;

  /**
   * @var bool Use for debugging so that response headers can be inspected.
   */
  private bool $get_response_headers;

  private array $default_headers = [
    "Cache-Control" => "no-cache",
    "Accept" => "*/*",
    "Content-Type" => "application/json",
    "Connection" => "keep-alive",
  ];

  /**
   * @var null|string Tracks errors which occur.
   */
  protected null|string $error;

  /**
   * @var array Retains the request
   */
  protected array $request;

  /**
   *  An associative array created from the most recent CuRL transaction and
   *  which can be extended by any service extending this class.
   *
   * @var array
   */
  protected array $response;

  private CurlHandle $handle;

  public function __construct(array $default_headers = [], bool $get_response_headers = FALSE) {
    if (!empty($default_headers)) {
      // Add to or alter the default headers for all CuRl transactions.
      $this->default_headers =  array_merge($this->default_headers, $default_headers);
    }
    // Useful for debugging.
    $this->get_response_headers = $get_response_headers;
  }

  /**
   * Creates a standardized CuRL object and returns its handle.
   *
   * If the post_fields are a urlencoded string (e.g. "a=hello+world&c=d&e=f")
   * then CuRL will default the content_type to xxx-url-encoded-form if a POST
   * is specified. Some euthentication processes require xxx-url-encoded-form
   * and the only way to be sure is to use a urlecoded string for post_fields
   * rather than an array.
   *
   * @param $post_url string The endpoint to post to.
   * @param $post_fields array|string Payload as an assoc array or urlencoded string.
   * @param $headers array (optional) Headers as an assoc array ([header=>value]).
   * @param $type string The request type lowercase (post, get etc). Default post.
   * @param bool $insecure bool Prevents certificate checking for testing against self or un-certified endpoints.
   *
   * @return CurlHandle
   * @throws Exception
   */
  public function makeCurl(string $post_url, array|string $post_fields, array $headers = [], string $type = "POST", bool $insecure = FALSE): CurlHandle {

    // Merge and encode the headers for CuRL.
    // Any supplied headers will overwrite the defaults headers.
    $this->request["headers"] = array_merge($this->default_headers, $headers);
    $_headers = [];
    foreach($this->request["headers"] as $key => $value) {
      $_headers[] = "{$key}: {$value}";
      if (strtolower($key) == "content-type") {
        $content_type = explode("/", $value);
        $content_type = array_pop($content_type);
      }
    }

    if ($type == "GET") {
      $post_url .= "?" . $post_fields;
      $post_fields = "";
    }
    else {
      $this->request["body"] = $post_fields;
      $this->encodePayload($post_fields, $content_type);
    }

    // Save/reset the request values for later.
    unset($this->handle);
    $urlparts = explode("/", $post_url, 4);
    $this->request["host"] = $urlparts[2];
    $this->request["protocol"] = $urlparts[0];
    $this->request["endpoint"] = $urlparts[3];
    if ($type == "GET") {
      $urlparts = explode("?", $urlparts[3],2);
      $this->request["endpoint"] = $urlparts[0];
      !empty($urlparts[1]) && $this->request["query"] = $urlparts[1];
    }
    $this->request["type"] = $type;
    $this->error = "";
    $this->response = ["headers" => []];

    // Make the CuRL object and return its handle.
    try {
      if (!$ch = curl_init()) {
        throw new  Exception("CuRL initialization error.", self::CURL_GENERAL_ERROR);
      }
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $type);
      curl_setopt($ch, CURLOPT_URL, $post_url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $_headers);
      curl_setopt($ch, CURLOPT_HEADER, $this->get_response_headers);
      curl_setopt($ch, CURLINFO_HEADER_OUT, $this->get_response_headers);

      if ($insecure) {
        /*
         * Allows CuRl to connect to less secure endpoints, for example ones
         * which have self-signed, or expired certs.
         */
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
      }

      $this->handle = $ch;
      return $ch;
    }
    catch (Exception $e) {
      if (!isset($ch)) {
        throw new Exception("CuRL not properly installed: {$e->getMessage()}", self::NO_CURL);
      }
      else {
        throw new Exception("CuRL options not set properly: {$e->getMessage()}", self::CURL_OPT_ERROR);
      }
    }

  }

  /**
   * Execute a CuRL transaction using a CuRL handle which was created with
   * $this->makeCurl.
   *
   * @param $retry bool Control flag to indicate if this is a retry of a
   * previous attempt.
   *
   * @return array The JSON string response from the curl request.
   * @throws Exception
   */
  public function executeCurl(bool $retry = FALSE): array {

    /**************************
     * EXECUTE
     **************************/
    try {

      $time = microtime(TRUE);
      $response = curl_exec($this->handle);
      $this->response["elapsedTime"] = microtime(TRUE) - $time;
      $this->extractHeaders( $response, $this->response["headers"]);

    }
    catch (Exception $e) {
      $this->error = $e->getMessage() ?? "Error executing CuRL";
      if ($err = curl_error($this->handle)) {
        $this->error .= ': ' . $err;
      }
      $this->writeError($this->error);
      throw new Exception($this->error, self::CURL_EXECUTION_ERROR);
    }

    $this->response["response_raw"] = $response;
    $this->response["http_code"] = curl_getinfo($this->handle, CURLINFO_HTTP_CODE);

    /**************************
     * PROCESS
     **************************/
    // If an error was found, then set now.
    $error = curl_error($this->handle);
    !empty($error) && $this->error = $error;

    if ($response === FALSE) {

      // Got false from curl - This is a problem of some sort.
      $this->error = curl_error($this->handle) ?? "HTTP_CODE: {$this->response["http_code"]}";

      if ($this->error == "Empty reply from server") {
        try {
          $retry = 1;
          while (!$response = curl_exec($this->handle)) {
            // Retrying 3 times with 3 sec spacing.
            $this->writeError($this->error);
            sleep(3);
            if ($retry++ >= 3) {
              break;
            }
          }
        }
        catch (Exception $e) {
          // CuRl itself threw an error.
          $this->error = "Error executing CuRL - {$e->getMessage()}";
          $this->writeError($this->error);
          throw new Exception($this->error, self::CURL_EXECUTION_ERROR);
        }
      }

      if ($response === FALSE) {
        // Still getting false ...
        $this->writeError($this->error);
        throw new Exception($this->error, self::CURL_EMPTY_RESPONSE);
      }

    }

    // Convert the response into an array.
    $content_type = curl_getinfo($this->handle, CURLINFO_CONTENT_TYPE);
    $is_json = str_contains($content_type, "/json");
    try {
      if ($is_json) {
        // Expecing JSON, so decode it.
        $this->response["body"] = json_decode($response, TRUE);
      }
      else {
        // see if we can json_decode the response even if it's not MIMEd json.
        $response = urldecode($response);
        try {
          $response = json_decode($response, TRUE);
          if (!empty($response)) {
            // Got json
            $this->response["body"] = $response;
          }
          else {
            throw new Exception();
          }
        }
        catch (Exception $e) {
          // Make an array out of the (hopefully) text/html/ response.
          $this->response["body"] = ["response" => $response];
        }
      }
    }
    catch (Exception $e) {
      if ($is_json) {
        $this->error = "Bad JSON response from curl:\n{$e->getMessage()}";
      }
      else {
        $this->error = "Error decoding response from curl:\n{$e->getMessage()}";
      }
      $this->writeError($this->error);
      throw new Exception($this->error, self::BAD_RESPONSE);
    }

    /**************************
     * VALIDATE
     **************************/
    if ($this->response["http_code"] == 500 ) {
      $this->writeError("Vendor API internal error: {$this->response["response_raw"]}");
      throw new Exception($this->error, self::VENDOR_INTERNAL_ERROR);
    }

    elseif ($this->response["http_code"] == 401) {
      $this->error = "Authentication/access error: {$this->response["response_raw"]}";
      $this->writeError($this->error);
      throw new Exception($this->error, self::AUTHENTICATION_ERROR);
    }

    elseif ($this->response["http_code"] == 403) {
      // Permission issue. Make one retry and then throw error.
      if ($retry) {
        // Something is very wrong, throw an error.
        $this->error = "Permission denied: {$this->response["response_raw"]}";
        $this->writeError($this->error);
        throw new Exception($this->error, self::PERMISSION_DENIED);
      }
      return $this->executeCurl(TRUE);
    }

    elseif ($this->response["http_code"] == 404) {
      // Endpoint location issue. Make one retry and then throw error.
      $this->error = "Endpoint not found: {$this->request["endpoint"]}";
      $this->writeError($this->error);
      throw new Exception($this->error, self::NOT_FOUND);
    }

    elseif ($this->response["http_code"] >= 300 || $this->response["http_code"] < 200) {
      // Got an error or non-200 code - throw error
      if (!$this->error()) {
        $this->error = "Endpoint Error (HTTP Code: {$this->response["http_code"]}): {$this->response["response_raw"]}";
      }
      $this->writeError($this->error);
      throw new Exception($this->error, self::BAD_REQUEST);
    }

    // Looks good, return the response body (an array).
    return  $this->response["body"];

  }

  /**
   * Wrapper function which sends a post to an endpoint and returns the results
   * as an array, or FALSE if anything fails.
   *
   * @param string $post_url The endpoint to post to.
   * @param array|string $post_fields Payload as an assoc array or urlencoded string
   * @param array $headers (optional) Headers as an assoc array ([header=>value])
   *
   * @return array|bool
   */
  public function post(string $post_url, array|string $post_fields, array $headers = []): array|bool {
    try {
      $this->makeCurl($post_url, $post_fields, $headers, "POST", FALSE);
      return $this->executeCurl(FALSE);
    }
    catch (Exception $e) {
      return FALSE;
    }

  }

  /**
   * Errors from most recent CuRL operation.
   *
   * @return string|bool A text string if errors, or FALSE if no errors.
   */
  public function error(): string|bool {
    return $this->error ?? FALSE;
  }

  /**
   * The HTTP_CODE received from the most recent CuRL transaction.
   *
   * @return int|bool - A valid HTTP_CODE or FALSE if nothing set.
   */
  public function http_code(): int|bool {
    return $this->response["http_code"] ?? FALSE;
  }

  /**
   * The current request parameters in the object.
   *
   * @return array Associative array of parameters.
   */
  public function request(): array {
    return $this->request ?? [];
  }

  /**
   * An associative array created from the most recent CuRL transaction and
   * which can be extended by the service.
   * Used to pass additional information back to the caller.
   *
   * @return array Associative array of response fields.
   */
  public function response(): array {
    return $this->response ?? [];
  }

  /**
   * Returns the last response from curl, saved as an associative array.
   *
   * @return array
   */
  public function result(): array {
    return (array) $this->response["body"] ?? [];
  }

  /**
   * Converts the payload into a text string formatted according to the
   * specified $content_type's expected format.
   *
   * @param array|string $payload (by ref) The payload
   * @param string $content_type The content type (Content-Type header value)
   *
   * @return void
   */
  private function encodePayload(array|string &$payload, string $content_type = "json"):void {

    if (is_array($payload)) {

      switch($content_type) {

        case "json":
          $payload = json_encode($payload);
          break;

        default:
        case "xxx-url-encoded-form":
          // Make a urlencode query string
          foreach($payload as &$value) {
            $value = rawurlencode(trim($value));
          }
          $payload = implode("&", $payload);
          break;

      }

    }

  }

  /**
   * Splits the headers out of a CuRL response when the
   * curl_setopt["CURLOPT_HEADER"] has been set to true.
   *
   * @param string $response The raw_response from curl_exec to be processed
   * @param array $headers An array to take the response headers.
   *
   * @return void
   */
  private function extractHeaders(string &$response, array &$headers): void {

    $headers = [];

    if ($this->get_response_headers && $response) {

      // Need to separate the headers and the response body.
      $headersize = curl_getinfo($this->handle, CURLINFO_HEADER_SIZE);
      $_headers = substr($response, 0, $headersize);
      $response = substr($response, $headersize);

      // Make response headers into an array.
      foreach(explode("\r\n", $_headers) as $header) {
        $bits = explode(":", $header, 2);
        if (!empty($bits[0])) {
          $headers[empty($bits[1]) ? 0 : $bits[0]] = $bits[1] ?? $bits[0];
        }
      }

      // Log
      \Drupal::logger("BosCurlHandler")->info("
        Headers have been extracted.<br>Complete response log:<br>
        <table>
          <tr><td>Endpoint</td><td>{$this->request["host"]}</td></tr>
          <tr><td>Response Headers</td><td>{$_headers}</td></tr>
          <tr><td>response Body</td><td>" . print_r($response, TRUE) . "</td></tr>
        </table>");

    }

  }

  /**
   * Writes a standardized log message when an error occurs.
   *
   * @param string $narrative
   * @param string $url
   * @param string $payload
   * @param array $response
   *
   * @return void
   */
  private function writeError(string $narrative = "Error"): void {
    \Drupal::logger("CurlControllerBase")
      ->error("Error Encountered.<br>
        <table>
          <tr><td>Issue</td><td>{$narrative}</td></tr>
          <tr><td>Endpoint</td><td>" . ($this->request["host"] ?? "unknown") . "</td></tr>
          <tr><td>JSON Payload</td><td>" . (json_encode($this->request["body"] ?? [])) . "</td></tr>
          <tr><td>JSON Response</td><td>" . print_r($this->response["response_raw"] ?? "", TRUE) . "</td></tr>
        </table>
      ");

  }

}
