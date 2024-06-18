<?php

namespace Drupal\bos_bibblio\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Site\Settings;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Bibblio class for API.
 */
class BibblioAPI extends ControllerBase {

  /**
   * Current request object for this class.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  public $request;

  /**
   * Public construct for Request.
   */
  public function __construct(RequestStack $request) {
    $this->request = $request;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
      // Load the service required to construct this class.
      $container->get('request_stack')
    );
  }

  /**
   * Checks allowed domains to access API.
   */
  public function checkDomain() {
    $allowed = [
      'https://www.boston.gov',
    ];

    if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed)) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Prepares the POSTed fields for Bibblio hosted API.
   *
   * @param array $items
   *   The array containing Bibblio creds.
   *
   * @return string
   *   The string value prepared for CURL.
   */
  public function preparePostFieldsToken(array $items) {
    $params = [];
    foreach ($items as $key => $value) {
      $params[] = $key . '=' . urlencode($value);
    }
    return implode('&', $params);
  }

  /**
   * Get token for Bibblio hosted API auth.
   */
  public function getToken() {
    $ch = curl_init();
    $data = [
      'client_id' => Settings::get('bibblio_id'),
      'client_secret' => Settings::get('bibblio_secret'),
    ];
    curl_setopt($ch, CURLOPT_URL, "https://api.bibblio.org/v1/token");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $this->preparePostFieldsToken($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      "Content-Type: application/x-www-form-urlencoded",
    ]);
    $responseToken = curl_exec($ch);
    curl_close($ch);
    return $responseToken;
  }

  /**
   * Get item from Bibblio library.
   *
   * @param array $data_update
   *   The array containing Bibblio API specific values.
   *
   * @return array
   *   The response array for user.
   */
  public function getItem(array $data_update) {
    $ch = curl_init();
    $token = $this->getToken();
    $token = json_decode($token);
    curl_setopt($ch, CURLOPT_URL, "https://api.bibblio.org/v1/content-items/" . $data_update['contentItemId']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      "Content-Type: application/json",
      "Authorization: Bearer " . $token->{'access_token'},
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $responseGetItem = [
      "status" => $http_code,
      "response" => $response,
    ];
    return $responseGetItem;
  }

  /**
   * Create item in Bibblio library.
   *
   * @param array $data_update
   *   The array containing Bibblio API specific values.
   *
   * @return array
   *   The response array for user.
   */
  public function createItem(array $data_update) {
    $ch = curl_init();
    $data_json = json_encode($data_update['fields']);
    $token = $this->getToken();
    $token = json_decode($token);
    curl_setopt($ch, CURLOPT_URL, "https://api.bibblio.org/v1/content-item-url-ingestions");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      "Content-Type: application/json",
      "Authorization: Bearer " . $token->{'access_token'},
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $responseCreateItem = [
      "status" => $http_code,
      "response" => $response,
    ];
    return $responseCreateItem;
  }

  /**
   * Update item in Bibblio library.
   *
   * @param array $data_update
   *   The array containing Bibblio API specific values.
   *
   * @return array
   *   The response array for user.
   */
  public function updateItem(array $data_update) {
    $ch = curl_init();
    $data = $data_update;
    $data_json = json_encode($data_update['fields']);
    $token = $this->getToken();
    $token = json_decode($token);
    curl_setopt($ch, CURLOPT_URL, "https://api.bibblio.org/v1/content-items/" . $data['contentItemId']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      "Content-Type: application/json",
      "Authorization: Bearer " . $token->{'access_token'},
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $responseUpdateItem = [
      "status" => $http_code,
      "response" => $response,
    ];
    return $responseUpdateItem;
  }

  /**
   * Begin script and API operations.
   */
  public function beginApi() {
    /* IMPORTANT -- set to true to test Bibblio API in local env. Setting to FALSE helps reduce duplicate content in Bibblio Library due to Drupal redirect URLSs in a non-production env. */
    $testing = FALSE;
    if ($this->checkDomain() == TRUE || $testing == TRUE) :
      // Get POST data and perform API request to specific Bibblio endpoint.
      $request_method = $this->request->getCurrentRequest()->getMethod();
      if ($request_method == "POST") :
        $data = $this->request->getCurrentRequest()->getContent();
        $getPostData = json_decode($data, TRUE);
        // Get operation and perform needed task / function.
        if ($getPostData['operation'] == "update") :
          $response_array = $this->updateItem($getPostData);

        elseif ($getPostData['operation'] == "create") :
          $response_array = $this->createItem($getPostData);

        elseif ($getPostData['operation'] == "get") :
          $response_array = $this->getItem($getPostData);

        else :
          $response_array = [
            "status" => "error",
            "response" => "no post operation found",
          ];
        endif;

        // End operation check/conditons.
      else :
        $response_array = [
          'status' => 'error',
          'response' => 'no post data',
        ];
      endif;

    else :
      $response_array = [
        'status' => 'error',
        'response' => 'not authorized',
      ];
    endif;

    $response = new CacheableJsonResponse($response_array);
    return $response;
  }

  // End checkEnv().
}

// End BibblioAPI class.
