<?php

namespace Drupal\bos_user_info\Services;

use Drupal\Core\Cache\CacheBackendInterface;
use GeoIp2\Exception\AddressNotFoundException;
use GeoIp2\Exception\AuthenticationException;
use GeoIp2\Exception\GeoIp2Exception;
use GeoIp2\Exception\HttpException;
use GeoIp2\Exception\InvalidRequestException;
use GeoIp2\Exception\OutOfQueriesException;
use GeoIp2\WebService\Client;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class IpAddressScanner.
 *
 * Scans the IPAddress using the GeoIp2 package - MaxMind GeoLite2 web service.
 *
 * @see https://github.com/maxmind/GeoIP2-php/tree/v3.0.0
 * @see https://www.maxmind.com/en/accounts/1090188/people/b6ca6bd6-ac58-4531-92cb-2dddf4966bf9
 *
 * david 11 2024
 */
class IpAddressScanner {


  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  private Request $request;

  /**
   * The current users IP Address.
   *
   * @var string
   */
  public string $ipAddress = "";

  /**
   * Private array for information once decoded.
   *
   * @var array
   */
  private array $info = [
    "city" => "Unknown",
    "metro" => "Unknown",
    "country" => "Unknown",
    "continent" => "Unknown",
    "location" => [
      // In Boston Harbor.
      "lat" => 42.346197,
      "long" => -70.9940839,
    ],
  ];

  /**
   * Cache the IPAddress to relieve calls to MaxMind (max 1000 per day).
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  private CacheBackendInterface $cache;

  public function __construct(RequestStack $request_stack, CacheBackendInterface $cache) {
    $this->request = $request_stack->getCurrentRequest();
    $this->ipAddress = $this->request->getClientIp() ?? "";
    $this->cache = $cache;
  }

  /**
   * Retrieves whatever location details it can from the IPAddress.
   *
   * Uses the MaxMind endoints.
   *
   * @param bool $ipaddress
   *   If provided, overrides the currently determined user ipaddress.
   * @param bool $force
   *   (opt) Force bypass the cache (default = FALSE).
   *
   * @return array
   *   An associative array with keys:
   *   - IPAddress
   *   - city
   *   - metro
   *   - country
   *   - continent
   *   - location
   *     - lat
   *     - long
   */
  public function read(string $ipaddress = "", bool $force = FALSE):array {

    if (!empty($ipaddress)) {
      $this->ipAddress = $ipaddress;
    }

    // Check to see if this IPAddress has been previously cached.
    if (!$force && $info = $this->cache->get($this->ipAddress)) {
      // NOTE: record will be cached for a month. Re-accessing the IP does not
      // reset the cache timeout. This is because we want to allow dynamic IP's
      // to be re-cached every few weeks.
      $this->info = unserialize($info->data);
    }
    else {
      // Grab the creds from an envar.
      $envar = getenv("MAXMIND_GEOIP_SETTINGS") ?? "";
      $settings = [];
      foreach (explode(",", $envar) as $attribute) {
        $setting = explode(":", $attribute);
        $settings[$setting[0]] = $setting[1];
      }

      if (!empty($settings)) {
        try {
          $ip_coder = new Client($settings["account"], $settings["key"], ['en'], ['host' => 'geolite.info']);
          // Read the IPAddress info from MaxMind.
          if ($record = $ip_coder->city($this->ipAddress)) {
            $this->info['city'] = $record->city->name;
            $this->info['region'] = $record->mostSpecificSubdivision->name;
            $this->info['country'] = $record->country->name;
            $this->info['continent'] = $record->continent->name;
            $this->info['sub_continent'] = $record->continent->name;
            $this->info['location'] = [
              "lat" => $record->location->latitude,
              "long" => $record->location->longitude,
            ];
            // Cache info for a month.
            $this->cache->set($this->ipAddress, serialize($this->info), strtotime('+1 month'));
          }
        }
        catch (AddressNotFoundException | AuthenticationException |
          InvalidRequestException | HttpException | OutOfQueriesException |
          GeoIp2Exception $e) {
          if (str_contains($e->getMessage(), "reserved IP")) {
            // Because it's an internal or private address.
            // Cache info forever.
            $this->cache->set($this->ipAddress, serialize($this->info), CacheBackendInterface::CACHE_PERMANENT);
          }
        }
      }

    }

    return array_merge($this->info, ['ipaddress' => $this->ipAddress]);

  }

  /**
   * Retrieve the name of the city from the information array.
   *
   * @return string
   *   The name of the city.
   */
  public function city():string {
    return $this->info['city'];
  }

  /**
   * Retrieve the name of the metro area from the information array.
   *
   * @return string
   *   The name of the metro area.
   */
  public function metro():string {
    return $this->info['metro'];
  }

  /**
   * Retrieve the name of the country from the information array.
   *
   * @return string
   *   The name of the country.
   */
  public function country():string {
    return $this->info['country'];
  }

  /**
   * Retrieve the name of the continent from the information array.
   *
   * @return string
   *   The name of the continent.
   */
  public function continent():string {
    return $this->info['continent'];
  }

  /**
   * Retrieve the location information from the information array.
   *
   * @return array
   *   The location information.
   */
  public function location():array {
    return $this->info['location'];
  }

}
