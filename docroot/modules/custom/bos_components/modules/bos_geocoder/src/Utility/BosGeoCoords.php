<?php

namespace Drupal\bos_geocoder\Utility;

/**
 * Class to manage a Geocode Lat/Long and perform some basic lookups.
 */
class BosGeoCoords {
  private float $lat;
  private float $long;

  public function __construct(float $lat = 0, float $long = 0) {
    if (!empty($lat) && !empty($long)) {
      $this->setCoords($lat, $long);
    }
  }

  public function setLat(float $lat) {
    $this->lat = $lat;
  }
  public function setLong(float $long) {
    $this->long = $long;
  }
  public function setCoords(float $lat, float $long): array {
    $this->setLat($lat);
    $this->setLong($long);
    return $this->getCoords();
  }

  public function getCoords(): array {
    return ['latitude'=>$this->lat, 'longitude'=>$this->long];
  }

  public function lat(): float|bool {
    return $this->lat ?? FALSE;
  }
  public function long(): float|bool {
    return $this->long ?? FALSE;
  }

}
