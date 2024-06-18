<?php

namespace Drupal\bos_core\Services;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Logger\LoggerChannelFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\TransferException;

/**
 * Class to manage posting pageviews to Google.
 *
 * @see https://developers.google.com/analytics/devguides/collection/protocol/v1/reference
 */

/**
 * Class BosCoreGAPost.
 *
 *    Posts to the Google Analytics vX endpoint.
 *
 * @package Drupal\bos_core\Services
 */
class BosCoreGAPost {

  /**
   * Logger object for class.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $log;

  /**
   * Config object fore class.
   *
   * @var \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * BosCoreGAPost constructor.
   *
   * @inheritdoc
   */
  public function __construct(LoggerChannelFactory $logger, ConfigFactory $config) {
    $this->log = $logger->get('EmergencyAlerts');
    $this->config = $config->get("bos_core.settings");
  }

  /**
   * Logs API actions to Google Analytics.
   *
   * @param string $page_id
   *   This is the document path for API tracking.
   * @param string $page_title
   *   This is the document title for API tracking.
   *
   *   Format: api/endpoint_module/type/description.
   *   E.g. api/cityscore/list/summary.
   *   E.g. api/cityscore/list/html.
   *
   * @return bool
   *   True if posted OK else false.
   */
  public function pageview(string $page_id, string $page_title = NULL) {
    $settings = $this->config->get("ga_settings");

    if (!$settings["ga_enabled"]) {
      return TRUE;
    }

    if (!isset($page_title)) {
      $page_title = "CoB REST | " . str_replace("/", "-", trim($page_id, "/api/"));
    }

    $payload = [
      "v" => 1,
      "tid" => $settings["ga_tid"],
      "t" => "pageview",
      "dp" => $page_id,
      "dt" => $page_title,
      "ni" => 1,
      "cid" => $settings["ga_cid"],
      "uip" => \Drupal::request()->getClientIp(),
      "cg1" => "API",
    ];
    foreach ($payload as $key => &$value) {
      if ($key != "dt") {
        $value = utf8_encode($value);
        $value = urlencode($value);
      }
    }

    $endpoint = (isset($settings["ga_endpoint"]) ? $settings["ga_endpoint"] : "https://www.google-analytics.com/collect");
    try {
      $client = new Client();
      $client->request('GET', $endpoint, [
        'query' => $payload,
        'headers' => [
          "Content-type: text/plain",
        ],
      ]);
      return TRUE;

    }
    catch (GuzzleException | TransferException | \Exception $except) {
      // Static function so cannot use $this->>log() ...
      $this->log->error("Google Analytics Post error.", []);
    }

    return FALSE;
  }

  /**
   * Return the endpoint currently set in configs and being used by this class.
   *
   * @return string
   *   The currently set endpoint.
   */
  public static function endpoint() {
    $settings = \Drupal::config("bos_core.settings")->get("bos_core.settings");
    return $settings["ga_endpoint"];
  }

}
