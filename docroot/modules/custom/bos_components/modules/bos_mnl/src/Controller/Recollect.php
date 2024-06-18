<?php

namespace Drupal\bos_mnl\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Site\Settings;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Recollect class for API.
 */
class Recollect extends ControllerBase {

  /**
   * Class var.
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
   * Get place_id from Recollect.
   */
  public function getPlaceId($address_string) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.us.recollect.net/v2/areas/Boston/services/waste/address-suggest?q=" . $address_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      "Authorization: Bearer " . Settings::get('recollect_token'),
    ]);
    $response = curl_exec($ch);
    $response_json = json_decode($response);
    $place_id = $response_json[0]->{'place_id'};
    curl_close($ch);
    return $place_id;
  }

  /**
   * Get trash and recycling info in JSON format from Recollect.
   */
  public function getInfo($addressData) {
    $ch = curl_init();
    $address_formatted = preg_replace('/[[:space:]]+/', '+', $addressData);
    $place = $this->getPlaceId($address_formatted);
    date_default_timezone_set("America/New_York");
    $now = time();
    $future = date("Y-m-d", strtotime('+1 month', $now));
    $today = date("Y-m-d", $now);
    curl_setopt($ch, CURLOPT_URL, "https://api.us.recollect.net/v2/places/" . $place . "/services/waste/events?after=" . $today . "&before=" . $future);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      "Content-Type: application/json",
      "Authorization: Bearer " . Settings::get('recollect_token'),
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $responseInfo = [
      "status" => $http_code,
      "response" => json_decode($response)
    ];
    return $responseInfo;
  }

  /**
   * Begin script and API operations.
   */
  public function beginLookup() {
    $testing = TRUE;
    if ($this->checkDomain() == TRUE || $testing == TRUE) :
      // Get POST address data and perform API request to Recollect endpoints.
      $data = $this->request->getCurrentRequest()->getContent();
      $addressData = json_decode($data, TRUE);
      $response_array = $this->getInfo($addressData['address']);
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

// End RecollectAPI class.
