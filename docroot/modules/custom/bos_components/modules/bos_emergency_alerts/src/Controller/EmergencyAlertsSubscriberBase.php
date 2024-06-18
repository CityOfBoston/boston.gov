<?php

namespace Drupal\bos_emergency_alerts\Controller;

use CurlHandle;
use Drupal\bos_core\Controllers\Curl\BosCurlControllerBase;
use Drupal\bos_core\Controllers\Settings\CobSettings;
use Drupal\Core\Controller\ControllerBase;
use Exception;

/**
 * This base class provides functionality that is likely to be needed by any
 * custom subscribers created for use in the bos_emergency_alerts component.
 */
class EmergencyAlertsSubscriberBase extends ControllerBase {

  /**
   * This is set when the ApiRouter->route() routing function is called from
   * the route attached to the endpoint.
   *
   * Allows the subscriber class to access Request, LoggerChannel, MailManager
   * and BosCoreGAPost objects.;
   */
  protected ApiRouter $router;

  /**
   * @var bool Use for debugging so that response headers can be inspected.
   */
  protected bool $debug_headers;

  /**
   * @var string Tracks errors which occur.
   */
  protected string $error;

  /**
   * @var array Retains the request
   */
  protected array $request;

  /**
   * @var array Stores response.
   */
  protected array $response;

  /**
   * @var \Drupal\bos_geocoder\Controller\BosCurlControllerBase Stores curl component
   */
  protected BosCurlControllerBase $curl;

  /**
   * @see \Drupal\bos_geocoder\Controller\BosCurlControllerBase->makeCurl()
   */
  protected function makeCurl(string $post_url, array|string $post_fields, array $headers = [], string $type = "POST", bool $insecure = FALSE): CurlHandle {

    try {
      $this->curl = new BosCurlControllerBase([], $this->debug_headers);
      return $this->curl->makeCurl($post_url, $post_fields, $headers, $type, $insecure);
    }
    catch (Exception $e) {
      throw new Exception($e->getMessage(), $e->getCode());
    }

  }

  /**
   * @see \Drupal\bos_geocoder\Controller\BosCurlControllerBase->executeCurl()
   */
  protected function executeCurl(bool $retry = FALSE): array {
    if (!isset($this->curl)) {
      throw new Exception("Error: Must call makeCurl() first.");
    }
    try {
      return $this->curl->executeCurl($retry);
    }
    catch (Exception $e) {
      throw new Exception($e->getMessage(), $e->getCode());
    }
  }
}
