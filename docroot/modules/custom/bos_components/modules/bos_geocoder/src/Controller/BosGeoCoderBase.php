<?php

namespace Drupal\bos_geocoder\Controller;

use Drupal\bos_core\Controllers\Settings\CobSettings;
use Drupal\bos_geocoder\Utility\BosGeoAddress;
use Drupal\Core\Controller\ControllerBase;

/**
 *  base class BosGeoCoderBase
 *
 *  david 02 2024
 *  @file docroot/modules/custom/bos_components/modules/bos_geocoder/src/BosGeoCoderBase.php
 */

/**
 * Controller class BosGeoCoder defines the basic functionality for standardized
 * geocoding and reverse-geocoding actions on boston.gov.
 * Manipulates a BosGeoAddress which is a class containing fields and function
 * adapted for geocoding use.
 * This class will be extended by controllers or services which implement a
 * resverse/geocode action against some endpoint or service.
 * @see BosGeoAddress
 */
class BosGeoCoderBase extends ControllerBase implements BosGeoCoderInterface {

  public const GEOCODE_FORWARD = "forward";

  public const GEOCODE_REVERSE = "reverse";

  public const AREA_BOSTON_ONLY = 1;

  public const AREA_MA_ONLY = 2;

  public const AREA_US_ONLY = 4;

  public const AREA_ARCGIS_ONLY = 8;

  public const AREA_GOOGLE_ONLY = 16;

  public const AREA_ANYWHERE = 32;

  protected BosGeoAddress $address;

  protected array $warnings = [];

  protected array $settings;

  protected int $options;

  /**
   * Creates a new BosGeoCoderBase instance storing the bos_geocoder module
   * settings and optionally loads an address.
   *
   * Note: Any overriding function can replace or add to $this->settings var.
   *
   * @param BosGeoAddress|NULL $address [optional] A populated address object.
   * @param array $settings [optional] Override settings to use for connection.
   */
  public function __construct(BosGeoAddress $address = NULL, array $settings = []) {
    if ($address) {
      $this->setAddress($address);
    }
    else {
      $this->address = new BosGeoAddress();
    }

    $this->settings = $this->setSettings(
      "GEOCODER_SETTINGS",
      "bos_geocoder");

    // Allow customized settings to be used (useful for testing).
    $this->settings = CobSettings::array_merge_deep($this->settings, $settings);
  }

  /**
   * Used to override the settings in this base class.
   *
   * @param string $envar_name The name of the Environment Variable to read
   * @param string $module_name The module name so settings object can be read
   * @param string $config_root (opt) The root key to read from the settings object
   * @param array $envar_list (opt) a list of fields which are allowed to come from the envar. Empty array means all fields are read.   *
   *
   * @return array
   */
  public function setSettings(string $envar_name, string $module_name, string $config_root = "", array $envar_list = []): array {
    return $this->settings = CobSettings::getSettings(
      $envar_name,
      $module_name,
      $config_root,
      $envar_list
    );
  }

  /**
   * @inheritDoc
   */
  public function setAddress(BosGeoAddress $address): BosGeoCoderBase {
    $this->address = $address;
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function geocode(int $options): BosGeoAddress|bool {
    // Typically, classes extending this module will override this function.
    $this->warnings = [];
    $this->options = $options;

    if ($this->address->isProcessed()) {
      $this->warnings[] = "This address has already been processed.";
      return FALSE;
    }

    return $this->address;
  }

  /**
   * @inheritDoc
   */
  public function reverseGeocode(int $options): BosGeoAddress|bool {
    // Typically, classes extending this module will override this function.
    $this->warnings = [];
    $this->options = $options;

    if ($this->address->isProcessed()) {
      $this->warnings[] = "This address has already been processed.";
      return FALSE;
    }

    return $this->address;
  }

  public function getWarnings(): array|bool {
    return empty($this->warnings) ? FALSE : $this->warnings;
  }

  /**
   * Check if a particular area flag is set within the $this->area variable.
   *
   * @param integer $flag
   *
   * @return boolean
   */
  protected function optionsFlag(int $flag): bool {
    return $this->options & $flag;
  }

}
