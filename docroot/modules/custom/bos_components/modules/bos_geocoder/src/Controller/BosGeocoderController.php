<?php

namespace Drupal\bos_geocoder\Controller;

use Drupal\bos_geocoder\Services\ArcGisGeocoder;
use Drupal\bos_geocoder\Utility\BosGeoAddress;
use Drupal\bos_google_cloud\Services\GcGeocoder;

/* Note, the ControllerBase class instantiates this class with many core services
 *        preloaded as class variables, or available in the container object.
 *        @see ControllerBase.php
 *
 *  class BosGeocoderController
 *
 *  david 02 2024
 *  @file docroot/modules/custom/bos_components/modules/bos_geocoder/src/Controller/BosGeocoderController.php
 */

class BosGeocoderController extends BosGeoCoderBase {

  /**
   * @inerhitDoc
   */
  public function geocode(int $options = self::AREA_BOSTON_ONLY):BosGeoAddress|bool {

    // Note:
    // Completely replace the parent geocode() functionality in this controller.

    $result = FALSE;
    $this->options = $options;
    $this->warnings = [];

    if ($this->address->isProcessed()) {
      $this->warnings[] = "This address has already been processed.";
      return FALSE;
    }

    // Update address using info from the selected geocoder(s)
    if (!$this->optionsFlag(parent::AREA_GOOGLE_ONLY)) {
      $arcgis_geocoder = new ArcGisGeocoder($this->address);
      $result = $arcgis_geocoder->geocode($options);
    }
    if (!$result && !($this->optionsFlag(parent::AREA_ARCGIS_ONLY))) {
      $google_geocoder = new GcGeocoder($this->address);
      return $google_geocoder->execute(["mode" => self::GEOCODE_FORWARD, "options" => $options]);
    }

    return $this->address;

  }

  /**
   * The COB ArcGIS decoder is used to determine Boston addresses and
   * if the location is determined not to be in Boston, then Google is used to
   * reverse geocode the address.
   *
   * NOTE: will return FALSE if location is not in Boston and $boston_only is
   *      set, but may also return FALSE is the location does not match an
   *      address in the decodcer(s) being used.
   * @see BosGeoCoderBase
   */
  public function reverseGeocode(int $options = self::AREA_BOSTON_ONLY): BosGeoAddress|bool {

    // Note:
    // Completely replace the parent reverseGeocode() functionality in this
    // controller.


    $result = FALSE;
    $this->options = $options;
    $this->warnings = [];

    if (!$this->optionsFlag(self::AREA_GOOGLE_ONLY)) {
      $arcgis_geocoder = new ArcGisGeocoder($this->address);
      $result = $arcgis_geocoder->reverseGeocode($options);
    }
    if (!$result && !($this->optionsFlag(self::AREA_ARCGIS_ONLY))) {
      $google_geocoder = new GcGeocoder($this->address);
      return $google_geocoder->reverseGeocode($options);
    }

    return $this->address;

  }

}
