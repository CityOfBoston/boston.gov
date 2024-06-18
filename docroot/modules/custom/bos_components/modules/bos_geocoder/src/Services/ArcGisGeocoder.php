<?php

namespace Drupal\bos_geocoder\Services;

use Drupal\bos_core\Controllers\Curl\BosCurlControllerBase;
use Drupal\bos_geocoder\Controller\BosGeoCoderBase;
use Drupal\bos_geocoder\Utility\BosGeoAddress;
use Exception;

/*  *
 *  class BosGeocoder
 *
 *  david 02 2024
 *  @file docroot/modules/custom/bos_components/modules/bos_geocoder/src/Services/BosGeocoder.php
 */

class ArcGisGeocoder extends BosGeoCoderBase {

  /**
   * Takes the address supplied or the address already stored in $this->address
   * and runs it through the geocoder(s) to get the location (lat/long co-ords).
   * The COB ArcGIS geocoder is first used to determine Boston addresses and if
   * the address is not found in Boston, if the boston_only flag is FALSE then
   * an additional search will be run in the Google geocoder.
   *
   * If an address is found, it is loaded into the $this->address object and
   * returns TRUE - if not FALSE is returned.
   *
   * NOTE: Will return FALSE if address not found in the decoder(s) being used.
   *
   * NOTE: inBoston = False means the address is not found in the COB ArcGIS
   *        geocoder. While this could be because it's not a Boston address, it
   *        also could just be b/c it's not a "findable" address ....
   *
   * @param string|BosGeoAddress|null $address An address as a string or object or FALSE.
   *
   * @return bool|BosGeoAddress An updated address object, or FALSE is no address matched.
   *
   */
  public function geocode(int $options = self::AREA_BOSTON_ONLY):BosGeoAddress|bool {

    if (!parent::geocode($options)) {
      return FALSE;
    }

    // Update address using info from the selected geocoder(s)
    return $this->lookupArcGISGeocoder(self::GEOCODE_FORWARD);

  }

  /**
   * Takes the lat/long location and tries to reverse geocode into a street
   * address. The COB ArcGIS decoder is used to determine Boston addresses and
   * if the location is determined not to be in Boston, then Google is used to
   * reverse geocode the address.
   * NOTE: will return FALSE if location is not in Boston and $boston_only is
   *      set, but may also return FALSE is the location does not match an
   *      address in the decodcer(s) being used.
   *
   *
   * @param float $lat
   * @param float $long
   * @param bool $boston_only Restricts search to boston geocoder only
   *
   * @return BosGeoAddress|bool An array if the co-ords found, FALSE if the co-ords are
   *                    not found, or if they are not in Boston and the
   *                    $boston_only flag is set.
   */
  public function reverseGeocode(int $options = self::AREA_BOSTON_ONLY): BosGeoAddress|bool {

    if (!parent::reverseGeocode($options)) {
      return FALSE;
    }

    // Update address using info from the selected geocoder(s)
    return $this->lookupArcGISGeocoder(self::GEOCODE_REVERSE);

  }

  /**
   * Uses ArcGIS Geocoder to forward or reverse lookup addresses/locations
   * within the City of Boston boundaries.
   *
   * Alters the $this->address object
   *
   * @return BosGeoAddress|bool The Address or FALSE if no match.
   */
  private function lookupArcGISGeocoder(string $direction): BosGeoAddress|bool {

    $config = $this->settings["arcgis"];

    $curl = new BosCurlControllerBase([], FALSE);
    $base = $config["base_url"];

    switch ($direction) {
      case self::GEOCODE_FORWARD:
        $endpoint = $config["find_location"];
        $post_fields = "SingleLine=" . $this->address->getValue("singlelineaddress") . "&outFields=PRIMARY_SAM_ID,PRIMARY_SAM_ADDRESS&matchOutOfRange=true&outSR=4326&f=pjson";
        $headers = ["Content-Type" => "application/x-www-form-urlencoded", "Accept" => "application/json"];
        try {
          if ($curl->makeCurl("{$base}/{$endpoint}", $post_fields, $headers, "POST", FALSE)) {
            if ($curl->executeCurl() && $curl->http_code() == 200) {
              $candidate = $this->bestMatch($curl->result()["candidates"] ?? [],70);
              if (!empty($candidate)) {
                $this->address->update_source = BosGeoAddress::SOURCE_ARCGIS;
                $this->address->setLocation($candidate["location"]["y"], $candidate["location"]["x"]);
                $this->address->setSingleLineAddress($candidate["address"]);
                $this->address->setSamInfo($candidate["attributes"]["PRIMARY_SAM_ID"] ?? "", $candidate["attributes"]["PRIMARY_SAM_ADDRESS"]) ?? "";
                $this->address->setProcessed();
                return $this->address;
              }
              else {
                $this->warnings[] = "Address not found in ArcGIS Geocoder";
                return FALSE;
              }
            }
            else {
              if (empty($curl->result())) {
                $this->warnings[] = "Address not found in ArcGIS Geocoder";
              }
              else {
                $this->warnings[] = "Error requesting from ArcGIS service";
              }
              return FALSE;
            }
          }
          else {
            $this->warnings[] = "Error constructing ArcGIS endpoint request.";
            return FALSE;
          }
        }
        catch (Exception $e) {
          $this->warnings[] = "Error: " . $e->getMessage();
          return FALSE;
        }
        break;

      case self::GEOCODE_REVERSE:
        $endpoint = $config["find_address"];
        $loc = $this->address->location();
        $location = urlencode('{x:' . $loc->long() .',y:' . $loc->lat() . ',spatialReference:{"wkid":4326}}');
        $post_fields = "location=" . $location . "&distance=1&locationType=street&returnIntersection=false&f=pjson";
        $headers = ["Content-Type" => "application/x-www-form-urlencoded"];
        try {
          if ($curl->makeCurl("{$base}/{$endpoint}", $post_fields, $headers, "POST", FALSE)) {
            if ($curl->executeCurl() && $curl->http_code() == 200 && isset($curl->result()["address"])) {
              $this->address->update_source = BosGeoAddress::SOURCE_ARCGIS;
              $address = $curl->result()["address"];
              $state = BosGeoAddress::US_STATES[$address["Region"]] ?? $address["Region"];
              $this->address->setAddress(
                $address["Address"],
                $address["Neighborhood"],
                ucwords($address["City"]),
                "",
                $state,
                $address["Postal"],
                $address["CntryName"]
              );
              $this->address->setSingleLineAddress($address["LongLabel"]);
              $this->address->setSamInfo($address["PRIMARY_SAM_ID"],$address["PRIMARY_SAM_ADDRESS"]);
              $this->address->setProcessed();
              return $this->address;
            }
            else {
              if (empty($curl->result())) {
                $this->warnings[] = "Address not found in ArcGIS Geocoder";
              }
              elseif ($curl->result() && !empty($curl->result()["error"])) {
                $this->warnings[] = reset($curl->result()["error"]["details"]);
              }
              else {
                $this->warnings[] = "Error requesting from ArcGIS service";
              }
              return FALSE;
            }
          }
          else {
            $this->warnings[] = "Error constructing ArcGIS endpoint request.";
            return FALSE;
          }
        }
        catch (Exception $e) {
          $this->warnings[] = "Error: " . $e->getMessage();
          return FALSE;
        }
        break;

      default:
        $this->warnings[] = "Unknown geocoder request" ;
        return FALSE;

    }

  }

  /**
   * Scans thru' the results from an ArcGIS search and returns the best match.
   *
   * @param array $candidates An array of candidate objects
   * @param int $threshold the minimum score to accept as a valid match
   *
   * @return array The best candidate as an assoc array.
   */
  private function bestMatch(array $candidates = [], int $threshold = 70): array {
    $bestscore = ["score" => 0];
    foreach($candidates as $candidate) {
      if ($candidate["score"] > $bestscore["score"]) {
        $bestscore = [
          "score" => $candidate["score"],
          "candidate" => $candidate
        ];
      }
    }
    if ($bestscore["score"] > $threshold) {
      return $bestscore["candidate"];
    }

    return [];

  }


}
