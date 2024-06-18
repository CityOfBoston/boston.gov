<?php

namespace Drupal\bos_geocoder\Utility;

use Drupal\bos_geocoder\Controller\BosGeocoderController;

class BosGeoAddress {

  public const SOURCE_ARCGIS = 1;
  public const SOURCE_GOOGLE = 2;

  // arcgis:Address, google:street_number+route
  private string $address;

  // arcgis:Neighborhood, google:neighborhood
  private string $neighborhood;

  // arcgis:City, google:locality
  private string $city;

  // arcgis:Region, google:administrative_area_level_1

  private string $state;

  // arcgis:Subregion, google:administrative_area_level_2
  private string $county;

  // arcgis:Postal, google:postal_code
  private string $zip;

  private string $singlelineaddress;

  // arcgis:SAM_ID, google:null
  private string $sam;

  // arcgis:null, google:place_id
  private string $place_id;

  // arcgis:PRIMARY_SAM_ADDRESS, google:null
  private string $sam_address;

  private string $country;

  public string|null $update_source;

  private BosGeoCoords $location;

  protected bool $processed = FALSE;

  public const US_STATES = [
    'AL' => 'Alabama',
    'AK' => 'Alaska',
    'AZ' => 'Arizona',
    'AR' => 'Arkansas',
    'CA' => 'California',
    'CO' => 'Colorado',
    'CT' => 'Connecticut',
    'DE' => 'Delaware',
    'FL' => 'Florida',
    'GA' => 'Georgia',
    'HI' => 'Hawaii',
    'ID' => 'Idaho',
    'IL' => 'Illinois',
    'IN' => 'Indiana',
    'IA' => 'Iowa',
    'KS' => 'Kansas',
    'KY' => 'Kentucky',
    'LA' => 'Louisiana',
    'ME' => 'Maine',
    'MD' => 'Maryland',
    'MA' => 'Massachusetts',
    'MI' => 'Michigan',
    'MN' => 'Minnesota',
    'MS' => 'Mississippi',
    'MO' => 'Missouri',
    'MT' => 'Montana',
    'NE' => 'Nebraska',
    'NV' => 'Nevada',
    'NH' => 'New Hampshire',
    'NJ' => 'New Jersey',
    'NM' => 'New Mexico',
    'NY' => 'New York',
    'NC' => 'North Carolina',
    'ND' => 'North Dakota',
    'OH' => 'Ohio',
    'OK' => 'Oklahoma',
    'OR' => 'Oregon',
    'PA' => 'Pennsylvania',
    'RI' => 'Rhode Island',
    'SC' => 'South Carolina',
    'SD' => 'South Dakota',
    'TN' => 'Tennessee',
    'TX' => 'Texas',
    'UT' => 'Utah',
    'VT' => 'Vermont',
    'VA' => 'Virginia',
    'WA' => 'Washington',
    'WV' => 'West Virginia',
    'WI' => 'Wisconsin',
    'WY' => 'Wyoming',
    'DC' => 'District of Columbia',
    'AS' => 'American Samoa',
    'GU' => 'Guam',
    'MP' => 'Northern Mariana Islands',
    'PR' => 'Puerto Rico',
    'UM' => 'United States Minor Outlying Islands',
    'VI' => 'Virgin Islands, U.S.',
  ];
  public const US_ALIAS = [
    "united states of america",
    "united states",
    "usa",
    "us"
  ];

  public function __construct(string $address = "", string $neighborhood = "", string $city = "", string $county = "", string $state = "", string $zip = "", string $country = "", string $singlelineaddress = "") {

    $this->setAddress($address, $neighborhood, $city, $county, $state, $zip, $country);
    if (!empty($singlelineaddress)) {
      $this->setSingleLineAddress($singlelineaddress);
    }
    else {
      $this->makeSingleLineAddress();
    }

  }

  /**
   * Add address elements to this object.
   *
   * @param string $address
   * @param string $neighborhood
   * @param string $city
   * @param string $county
   * @param string $state
   * @param string $zip
   *
   * @return BosGeoAddress
   */
  public function setAddress(string $address = "", string $neighborhood = "", string $city = "", string $county = "", string $state = "", string $zip = "", string $country = ""): BosGeoAddress {

    if (!empty($state) && array_key_exists($state, self::US_STATES)) {
      $state = self::US_STATES[$state];
    }

    $set = FALSE;
    !empty($address) && ($this->address = $address) && $set = TRUE;
    !empty($neighborhood) && ($this->neighborhood = $neighborhood) && $set = TRUE;
    !empty($city) && ($this->city = $city) && $set = TRUE;
    !empty($county) && ($this->county = $county) && $set = TRUE;
    !empty($state) && ($this->state = $state) && $set = TRUE;
    !empty($zip) && ($this->zip = $zip) && $set = TRUE;
    !empty($country) && ($this->country = $country) && $set = TRUE;

    if ($set) {
      $this->makeSingleLineAddress();
    }

    return $this;

  }

  /**
   * Set the lat/log for this address
   *
   * @param float $lat
   * @param float $long
   *
   * @return void
   */
  public function setLocation(float $lat, float $long): BosGeoAddress {
    $this->location = new BosGeoCoords($lat, $long);
    return $this;
  }

  public function location(): BosGeoCoords|bool {
    return $this->location ?? FALSE;
  }

  /**
   * Returns the address object as an assoc array.
   *
   * @return array
   */
  public function to_array(): array {
    return [
      "sinlgelineaddress" => $this->singlelineaddress ?? "",
      "address" => $this->address ?? "",
      "neighborhood" => $this->neighborhood ?? "",
      "city" => $this->city ?? "",
      "county" => $this->county ?? "",
      "state" => $this->state ?? "",
      "zip" => $this->zip ?? "",
      "country" => $this->country ?? "",
      "location" => $this->location ?? "",
      "samid" => $this->sam ?? "",
      "samaddress" => $this->sam_address ?? "",
      "google_place" => $this->place_id ?? "",
      "in_boston" => ($this->isInBoston($this->city) ?? NULL),
      "in_mass" => ($this->isInMass($this->state) ?? NULL),
      "in_us" => ($this->isInUs($this->country) ?? NULL),
    ];
  }

  /**
   * Returns the value of a field, or FALSE if the field does not exist.
   *
   * @param string $field
   *
   * @return string|bool|\Drupal\bos_geocoder\BosGeoCoords
   */
  public function getValue(string $field): string|bool|BosGeoCoords {
    if (isset($this->{$field})) {
      return $this->{$field};
    }
    return FALSE;
  }

  /**
   * Create a single line address from the supplied address parts.
   *
   * NOTE: For "forward" geocoder lookups (i.e. address=>location), this needs
   *        to be run before submitting to geocoders b/c we typically submit
   *        the single line address to the geocoder.
   *
   * @return BosGeoAddress
   */
  public function makeSingleLineAddress(): BosGeoAddress {
    $output = ($this->address ?? "");
    $output = trim($output,", ") . ", " . ($this->neighborhood ?? "");
    $output = trim($output,", ") . ", " . ($this->city ?? "");
    $output = trim($output,", ") . ", " . ($this->county ?? "");
    $output = trim($output,", ") . ", " . ($this->state ?? "");
    $output = trim($output,", ") . ", " . ($this->zip ?? "");
    $output = trim($output,", ") . ", " . ($this->country ?? "");
    $this->singlelineaddress = trim($output);
    return $this;
  }

  /**
   * Save a single line address to the object, and try to break out into
   * separate address parts.
   *
   * @param string $singlelineaddress The address as a comma delimited string.
   * @param bool $split Whether to save the address elements
   *
   * @return BosGeoAddress
   */
  public function setSingleLineAddress(string $singlelineaddress, bool $split  = FALSE):BosGeoAddress {
    $this->singlelineaddress = $singlelineaddress;
    if ($split) {
      $this->makeAddressFromSingleLine();
    }
    return $this;
  }

  /**
   * Take a comma separated address in a single line and parse out to its
   * component elements in this object.
   * NOTE: if there are no comma's, then nothing is processed - no warning.
   *
   * @return BosGeoAddress
   */
  private function makeAddressFromSingleLine(): BosGeoAddress {

    if (str_contains($this->singlelineaddress, ",")) {

      $address_bits = explode(",", $this->singlelineaddress);

      foreach($address_bits as $key => $element) {
        $element = strtolower(trim($element));
        if ($key == 0) {
          // Going to assume that the first element is the address.
          $this->address = $element;
          unset($address_bits[$key]);
        }

        else {

          if (is_numeric($element)) {
            $this->zip = $element;
            unset($address_bits[$key]);
          }

          elseif (in_array(ucwords($element),self::US_STATES)
            || array_key_exists(strtoupper($element), self::US_STATES)) {
            $this->state = $element;
            unset($address_bits[$key]);
          }

          elseif (in_array($element, self::US_ALIAS)) {
            // don't save, we are assuming a US address
            unset($address_bits[$key]);
          }
        }
      }

      if (!empty($address_bits)) {
        $address_bits = reset($address_bits);
        if (count($address_bits) == 1) {
          $this->city = $address_bits[0];
        }
        elseif (count($address_bits) == 2) {
          $this->neightborhood = $address_bits[0];
          $this->city = $address_bits[1];
        }
      }

    }

    return $this;

  }

  /**
   * Returns the address as a single line.
   *
   * @return string
   */
  public function singlelineaddress(): string {
    if (empty($this->singlelineaddress)) {
      if (empty($this->sam_address)) {
        $this->makeSingleLineAddress();
      }
      else {
        $this->singlelineaddress = $this->sam_address;
      }
    }
    return $this->singlelineaddress ?? "";
  }

  /**
   * The City of Boston SAM ID (Unique address ID)
   *
   * @return string
   */
  public function sam(): string {
    return $this->sam ?? "";
  }

  public function setSamInfo(int $sam_id, string $sam_address = ""): BosGeoAddress {
    $this->sam = $sam_id;
    $this->sam_address = $sam_address;
    return $this;
  }

  public function setGooglePlace(string $placeid): BosGeoAddress {
    $this->place_id = $placeid ?? "";
    return $this;
  }

  public function setProcessed(bool $state = TRUE): BosGeoAddress {
    $this->processed = $state;
    return $this;
  }

  public function isProcessed(): bool {
    return $this->processed;
  }

  public function isInBoston(string $city = ""): bool {
    // TODO: Extend by creating a list of neighborhoods and checking against that.
    if (!empty($city)) {
      return strtolower($city) == "boston";
    }
    return strtolower($this->city) == "boston";
  }

  public function isInMass(string $state = ""): bool {
    if (!empty($state)) {
      return in_array(strtolower($state), ["massachusetts", "ma"]);
    }
    return in_array(strtolower($this->state), ["massachusetts", "ma"]);
  }

  public function isInUs(string $country = ""): bool {
    if (!empty($country)) {
      return in_array(strtolower($country), self::US_ALIAS);
    }
    return in_array(strtolower($this->country), self::US_ALIAS);
  }

}
