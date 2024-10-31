<?php

namespace Drupal\bos_google_cloud\Services;

use Drupal\bos_core\Controllers\Curl\BosCurlControllerBase;
use Drupal\bos_core\Controllers\Settings\CobSettings;
use Drupal\bos_geocoder\Controller\BosGeoCoderBase;
use Drupal\bos_geocoder\Utility\BosGeoAddress;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Exception;

/**
 * This class extends BosGeoCoderBase from bos_geocoder because this service is
 * most likely to be used as an integration into that module.
 * @see BosGeoCoderBase
 *
 *  david 02 2024
 *  @file docroot/modules/custom/bos_components/modules/bos_google_cloud/src/Services/GcGeocoder.php
 */

class GcGeocoder extends BosGeoCoderBase implements GcServiceInterface {

  private string $error;

  /**
   * @inheritDoc
   */
  public static function id(): string {
    return "geocoder";
  }

  /**
   * @inheritDoc
   */
  public function execute(array $parameters = []): string {

    $options = $parameters["options"] ?? 0;
    $mode = $parameters["mode"] ?? self::GEOCODE_FORWARD;

    if ($mode == self::GEOCODE_FORWARD) {
      if (!$result = $this->geocode($options)) {
        if ($this->warnings) {
          return implode(": ", $this->warnings);
        }
        if ($this->error()) {
          return $this->error();
        }
        return "Address could not be resolved.";
      }
      return "{$result->location()->lat()},{$result->location()->long()}";
    }

    elseif ($mode == self::GEOCODE_REVERSE) {

      if (!$result = $this->reverseGeocode($options)) {
        if ($this->warnings) {
          return implode(": ", $this->warnings);
        }
        if ($this->error()) {
          return $this->error();
        }
        return "Coordinates could not be resolved to an address";
      }

      return $result->singlelineaddress();
    }

    else {
      $this->error = "Unknown geocode mode";
      return $this->error;
    }

  }

  /**
   * Takes the address supplied or the address already stored in $this->address
   * and runs it through the geocoder to get the location (lat/long co-ords).
   *  If an address is found, it is loaded into the $this->address object and
   *  returned - if not FALSE is returned.
   *
   * NOTE: Will return FALSE if address not found in the decoder(s) being used.
   *
   * NOTE: inBoston = False means the address is not found in the COB ArcGIS
   *        geocoder. While this could be because it's not a Boston address, it
   *        also could just be b/c it's not a "findable" address ....
   *
   * @param int $options Geocoder options from Constants in BosGeoCoderBase.
   *
   * @return bool|BosGeoAddress An updated address object, or FALSE is no address matched.
   *
   */
  public function geocode(int $options = 0):BosGeoAddress|bool {

    if (!parent::reverseGeocode($options)) {
      $this->error = $this->warnings[array_key_last($this->warnings)];
      return FALSE;
    }

    // Update address using info from the selected geocoder(s)
    return $this->lookupGoogleGeocoder(self::GEOCODE_FORWARD);

  }

  /**
   * Takes the lat/long location and tries to reverse geocode into a street
   * address.
   *
   * Lookup the location (coords) for an address loaded into $this->address.
   *  If a location is found, it is loaded into the $this->address object and
   *  returns TRUE - if not FALSE is returned.
   *
   * NOTE: will return FALSE if location is not in Boston and $boston_only is
   *      set, but may also return FALSE is the location does not match an
   *      address in the decodcer(s) being used.
   *
   *
   * @param int $options Geocoder options from Constants in BosGeoCoderBase
   *
   * @return BosGeoAddress|bool An array if the co-ords found, FALSE if the co-ords are
   *                    not found, or if they are not in Boston and the
   *                    $boston_only flag is set.
   */
  public function reverseGeocode(int $options = 0): BosGeoAddress|bool {

    if (!parent::reverseGeocode($options)) {
      $this->error = $this->warnings[array_key_last($this->warnings)];
      return FALSE;
    }

    return $this->lookupGoogleGeocoder(self::GEOCODE_REVERSE);

  }

  /**
   * @inheritDoc
   */
  public function buildForm(array &$form): void {

    $settings = CobSettings::getSettings(
      "GEOCODER_SETTINGS",
      "bos_geocoder"
    );

    if (empty($settings)) {
      $form['#markup'] = Markup::create("<p style='color:red'><b>These configurations are managed by the Geocoder Service (bos_geocoder), but there do not appear to be any settings configured at the moment. Click here <a href='/admin/config/system/boston/geocoder'>to edit</a>.</b></p>");
    }
    else {
      $form = $form + [
        'geocoder' => [
          '#type' => 'details',
          '#title' => 'Google Cloud Geocoder',
          '#markup' => Markup::create("<p style='color:red'><b>These configurations are managed by the Geocoder Service (bos_geocoder). Click here <a href='/admin/config/system/boston/geocoder'>to edit</a>.</b></p>"),
          '#open' => FALSE,
          'base_url' => [
            '#type' => 'textfield',
            '#title' => t('Base URL for geocoder'),
            '#description' => t(''),
            '#default_value' => $settings['google']['base_url'] ?? "",
            '#disabled' => TRUE,
            '#required' => FALSE,
            '#attributes' => [
              "placeholder" => 'e.g. https://maps.googleapis.com',
            ],
          ],
          'find_location' => [
            '#type' => 'textfield',
            '#title' => t('Endpoint for finding Lat/Long from an address (forward geocode)'),
            '#description' => t('see https://developers.google.com/maps/documentation/geocoding'),
            '#default_value' => $settings['google']['find_location'] ?? "",
            '#disabled' => TRUE,
            '#required' => FALSE,
            '#attributes' => [
              "placeholder" => 'e.g. maps/api/geocode/json',
            ],
          ],
          'find_address' => [
            '#type' => 'textfield',
            '#title' => t('Endpoint for finding address from Lat/Long (reverse geocode)'),
            '#description' => t('see https://developers.google.com/maps/documentation/geocoding/requests-reverse-geocoding'),
            '#default_value' => $settings['google']['find_address'] ?? "",
            '#disabled' => TRUE,
            '#required' => FALSE,
            '#attributes' => [
              "placeholder" => 'e.g. maps/api/geocode/json',
            ],
          ],
          'token' => [
            '#type' => 'textfield',
            '#title' => t('The Google API token'),
            '#description' => t('see https://developers.google.com/maps/documentation/geocoding/get-api-key'),
            '#default_value' => CobSettings::obfuscateToken($settings['google']['token'] ?? ""),
            '#disabled' => TRUE,
            '#required' => FALSE,
            '#attributes' => [
              "placeholder" => '',
            ],
          ],
          'test_wrapper' => [
            'test_button' => [
              '#type' => 'button',
              "#value" => t('Test Geocoder'),
              '#attributes' => [
                'class' => ['button', 'button--primary'],
                'title' => "Test the provided configuration for this service"
              ],
              '#access' => TRUE,
              '#ajax' => [
                'callback' => '::ajaxHandler',
                'event' => 'click',
                'wrapper' => 'edit-geocoder-result',
                'disable-refocus' => TRUE,
                'progress' => [
                  'type' => 'throbber',
                ]
              ],
              '#suffix' => '<span id="edit-geocoder-result"></span>',
            ],
          ],
        ],
      ];
    }
  }

  /**
   * @inheritDoc
   */
  public function submitForm(array $form, FormStateInterface $form_state): void {
    // Not required - config handled in bos_geocoder
  }

  /**
   * @inheritDoc
   */
  public function validateForm(array $form, FormStateInterface &$form_state): void {
    // Not required
  }

  /**
   * Uses Google Geocoder to forward or reverese lookup addresses/locations
   * outside the City of Boston boundaries, but still within the US.
   *
   *  Alters the $this->address object
   *
   * @param string $direction
   *
   * @return BosGeoAddress|bool
   */
  private function lookupGoogleGeocoder(string $direction): BosGeoAddress|bool {

    $config = $this->settings["google"];

    $curl = new BosCurlControllerBase(get_response_headers:  FALSE);
    $base = $config["base_url"];
    $token = $config["token"];

    switch ($direction) {
      case self::GEOCODE_FORWARD:
        $post_fields = "key=" . $token . "&address=" . urlencode($this->address->singlelineaddress());
        $headers = ["Content-Type" => "application/x-www-form-urlencoded"];
        $endpoint = $config["find_location"];
        try {
          if ($curl->makeCurl("$base/$endpoint", $post_fields, $headers, "GET")) {
            if ($curl->executeCurl() && $curl->http_code() == 200 && empty($curl->result()["error_message"])) {
              $this->address->update_source = BosGeoAddress::SOURCE_GOOGLE;
              $result = $curl->result()["results"][0];
              $location = $result["geometry"]["location"];
              $address = $this->parseGoogleAddress($result);
              if (!empty($location)) {
                $this->address->setSingleLineAddress($result["formatted_address"]);
                $this->address->setGooglePlace($result["place_id"]);
                if ($this->optionsFlag(self::AREA_BOSTON_ONLY) && !$this->address->isInBoston($address["locality"])) {
                  $this->warnings[] = "Boston address flag is set, but address not in Boston";
                  return FALSE;
                }
                elseif ($this->optionsFlag(self::AREA_MA_ONLY) && !$this->address->isInMass($address["administrative_area_level_1"])) {
                  $this->warnings[] = "Massachussets address flag is set, but address not in Mass";
                  return FALSE;
                }
                elseif ($this->optionsFlag(self::AREA_US_ONLY) && !$this->address->isInBoston($address["country"])) {
                  $this->warnings[] = "US address flag is set, but address not in USA";
                  return FALSE;
                }
                $this->address->setLocation($location["lat"], $location["lng"]);
                $this->address->setProcessed();
                return $this->address;
              }
            }
            elseif (!empty($curl->result()["error_message"])) {
              throw new Exception($curl->result()["error_message"]);
            }
          }
          $this->error = $curl->error() ?? "Could not make CURL session.";
          return FALSE;
        }
        catch (Exception $e) {
          $this->warnings[] = "Error: " . $e->getMessage();
          $this->error = $e->getMessage();
          return FALSE;
        }

      case self::GEOCODE_REVERSE:
        $loc = $this->address->location();
        $post_fields = "key=" . $token . "&result_type=street_address&latlng=" . $loc->lat() . "," . $loc->long();
        $endpoint = $config["find_address"];
        try {
          if ($curl->makeCurl("$base/$endpoint", $post_fields, [], "GET")) {
            if ($curl->executeCurl() && $curl->http_code() == 200) {
              $this->address->update_source = BosGeoAddress::SOURCE_GOOGLE;
              $result = $curl->result()["results"][0];
              $this->address->setGooglePlace($result["place_id"]);
              $address = $this->parseGoogleAddress($result);
              if ($this->optionsFlag(self::AREA_BOSTON_ONLY) && !$this->address->isInBoston($address["locality"])) {
                $this->warnings[] = "Boston address flag is set, but address not in Boston";
                return FALSE;
              }
              elseif ($this->optionsFlag(self::AREA_MA_ONLY) && !$this->address->isInMass($address["administrative_area_level_1"])) {
                $this->warnings[] = "Massachussets address flag is set, but address not in Mass";
                return FALSE;
              }
              elseif ($this->optionsFlag(self::AREA_US_ONLY) && !$this->address->isInBoston($address["country"])) {
                $this->warnings[] = "US address flag is set, but address not in USA";
                return FALSE;
              }
              $this->address->setAddress(
                $address["address"] ?? "",
                $address["neightborhood"] ?? "",
                ucwords($address["locality"] ?? ""),
                $address["administrative_area_level_2"] ?? "",
                $address["administrative_area_level_1"] ?? "",
                $address["postal_code"] ?? "",
                $address["country"] ?? ""
              );
              $this->address->setSingleLineAddress($result["formatted_address"]);
              $this->address->setProcessed();
              return $this->address;
            }
          }
          $this->error = $curl->error() ?? "Could not make CURL session.";
          return FALSE;
        }
        catch (Exception $e) {
          $this->warnings[] = "Error: " . $e->getMessage();
          $this->error = $e->getMessage();
          return FALSE;
        }

      default:
        $this->error = "Unknown geocoder request" ;
        $this->warnings[] = $this->error;
        return FALSE;

    }
  }

  /**
   * Process the awkward Google array into a better key:value arrangement.
   *
   * @param array $result
   *
   * @return array
   */
  private function parseGoogleAddress(array $result): array {
    $address = [];
    foreach ($result["address_components"] as $component) {
      foreach ($component["types"] as $type) {
        if ($type != "political") {
          $address[$type] = $component["long_name"];
        }
      }
    }

    $address["address"] = ($address["street_number"] ?? "") . " " . $address["route"];
    unset($address["street_number"]);
    unset($address["route"]);

    return $address;
  }

  /**
   * Ajax callback to test Search
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  public static function ajaxTestService(array &$form, FormStateInterface $form_state): array {

    $address = new BosGeoAddress();
    $address->setSingleLineAddress("1 Cityhall plaza, Boston, MA");

    $settings = [];
    $values = $form_state->getValues();
    if ($form['#form_id'] == "bos_geocoder_GeocoderConfigForm") {
      // Can only change config settings on the bos_geocoder config form, so if
      // this form is not that, then don't try to pass through any changed
      // settings.
      $settings = ["google" => $values["bos_geocoder"]["google"]];
      unset($settings["google"]["test_wrapper"]);
      if (str_contains($settings["google"]["token"], "*****")) {
        // Don't ever send an obvuscated token, it won't work!
        unset($settings["google"]["token"]);
      }
    }

    $geocoder = new GcGeocoder($address, $settings);
    $result = $geocoder->geocode($geocoder::AREA_GOOGLE_ONLY);

    if ($result && !$geocoder->getWarnings()) {

      $address = new BosGeoAddress();
      $address->setLocation(42.360300000003122,-71.058271500000757);
      $geocoder->setAddress($address);
      $result = $geocoder->reverseGeocode($geocoder::AREA_GOOGLE_ONLY);

      if ($result) {
        return ["#markup" => Markup::create("<span id='edit-google-result' style='color:green'><b>&#x2714; Success:</b> Service Config is OK.</span>")];
      }
      elseif ($geocoder->getWarnings()) {
        return ["#markup" => Markup::create("<span id='edit-google-result' style='color:red'><b>&#x2717; Failed:</b> " . implode(":", $geocoder->getWarnings()) . "</span>")];
      }
      else {
        return ["#markup" => Markup::create("<span id='edit-google-result' style='color:red'><b>&#x2717; Failed:</b> Check Reverse Geocoder Endpoint.</span>")];
      }
    }

    elseif ($geocoder->getWarnings()) {
      return ["#markup" => Markup::create("<span id='edit-google-result' style='color:red'><b>&#x2717; Failed:</b> " . implode(":", $geocoder->getWarnings()) . "</span>")];
    }

    else {
      return ["#markup" => Markup::create("<span id='edit-google-result' style='color:red'><b>&#x2717; Failed:</b> Check Base URL and/or Forward Geocoder Endpoint.</span>")];
    }

  }

  /**
   * @inheritDoc
   */
  public function error(): string|bool {
    return (empty($this->error) ? FALSE : $this->error);
  }

  /**
   * @inheritDoc
   */
  public function setServiceAccount(string $service_account): GcServiceInterface {
    throw new Exception("There is no service account conmcept for geocoder");
  }

  /**
   * @inheritDoc
   */
  public function hasFollowup(): bool {
    // Not applicable
    return FALSE;
  }

  /**
   * @inheritDoc
   */
  public function getSettings(): array {
    return $this->settings[$this->id()];
  }

  /**
   * @inheritDoc
   */
  public function availablePrompts(): array {
    // Not implemented
    return [];
  }

}
