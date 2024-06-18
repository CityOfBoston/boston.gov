<?php

namespace Drupal\bos_geocoder\Controller;

use Drupal\bos_geocoder\Utility\BosGeoAddress;

/*
 *  interface class BosGeoCoderInterface
 *
 *  david 02 2024
 *  @file docroot/modules/custom/bos_components/modules/bos_geocoder/src/BosGeoCoderInterface.php
 */

interface BosGeoCoderInterface {

  /**
   * Set the address to be geocoded.
   *
   * @param BosGeoAddress $address
   *
   * @return BosGeoCoderInterface
   */
  public function setAddress(BosGeoAddress $address): BosGeoCoderInterface;

/**
 * Takes the address supplied or the address already stored in $this->address
 * and runs it through the geocoder(s) to get the location (lat/long co-ords).
 * The COB ArcGIS geocoder is first used to determine Boston addresses and if
 * the address is not found in Boston, if the boston_only flag is FALSE then
 * an additional search will be run in the Google geocoder.
 *
 * If an address is found, it is loaded into the $this->address object - if not
 * (or ineligible b/c of $options set) FALSE is returned.
 *
 * @param int $options Options for use when selecting/using geocoder services.
 *
 * @return bool|BosGeoAddress An updated address object, or FALSE is no address matched.
 *
 */
  public function geocode(int $options):BosGeoAddress|bool;

  /**
   * Takes the lat/long location in $this->address and tries to reverse geocode
   * into a street address.
   *
   * If coordinates are found, the address is loaded into $this->address -if not
   * (or ineligible b/c of $options set) then FALSE is returned .
   *
   * @param int $options Options for use when selecting/using geocoder services.
   *
   * @return BosGeoAddress|bool An array if the co-ords found, FALSE if the co-ords are
   *                    not found, or if they are not in Boston and the
   *                    $boston_only flag is set.
   */
  public function reverseGeocode(int $options): BosGeoAddress|bool;

}
