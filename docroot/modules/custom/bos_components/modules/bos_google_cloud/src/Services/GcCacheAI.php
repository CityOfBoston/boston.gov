<?php

namespace Drupal\bos_google_cloud\Services;

use Drupal;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Logger\LoggerChannelInterface;

/**
class GcCacheAI
Creates service to manage caching of AI requests

david 02 2024
@file docroot/modules/custom/bos_components/modules/bos_google_cloud/src/Services/GcCacheAI.php
 */

class GcCacheAI {

  const CACHE_EXPIRY_1HOUR = "+1 hour";
  const CACHE_EXPIRY_1DAY = "+1 day";
  const CACHE_EXPIRY_1WEEK = "+1 week";
  const CACHE_EXPIRY_1MONTH = "+1 month";
  const CACHE_EXPIRY_3MONTH = "+3 months";
  const CACHE_EXPIRY_6MONTH = "+6 months";
  const CACHE_EXPIRY_12MONTH = "+12 months";
  const PERMANENT = "permanent";
  const CACHE_EXPIRY_NO_CACHE = "no-cache";
  const CACHE_STATUS_HIT = "HIT";
  const CACHE_STATUS_MISS = "MISS";

  private array $info;
  protected CacheBackendInterface $cache;
  protected LoggerChannelInterface $log;
  protected ImmutableConfig $config;

  public function __construct(LoggerChannelFactory $logger, ConfigFactory $config, CacheBackendInterface $cache) {
    $this->log = $logger->get('bos_google_cloud');
    $this->config = $config->get("bos_google_cloud.settings");
    $this->cache = $cache;
    $this->info = [
      "enabled" => TRUE,
      "expiry" => NULL,
      "bin" => "gen_ai"
    ];
  }

  /**
   * Get a list of cache options, based on the constants defined in this class.
   *
   * @return string[] Array suitable for use as options in a select form field.
   */
  public static function getCacheExpiryOptions(): array {
    return [
      self::CACHE_EXPIRY_NO_CACHE => "No caching",
      self::CACHE_EXPIRY_1HOUR => "1 Hour",
      self::CACHE_EXPIRY_1DAY => "1 Day",
      self::CACHE_EXPIRY_1WEEK => "1 Week",
      self::CACHE_EXPIRY_1MONTH => "1 Month",
      self::CACHE_EXPIRY_3MONTH => "3 Months",
      self::CACHE_EXPIRY_6MONTH => "6 Months",
      self::CACHE_EXPIRY_12MONTH => "12 Months",
      self::PERMANENT => "Permenant",
    ];
  }

  /**
   * Set the expiry time for cache, or disable the cache.
   *
   * @param string $expiry Set expiry of cache. Use constant from GcCacheAPI, or
   *   any string which can be evaluated by PHP strtotime function.
   *
   * @return void
   */
  public function setExpiry(string $expiry): void {
    $this->info["expiry"] = $expiry;
    $this->info["enabled"] = !($expiry == self::CACHE_EXPIRY_NO_CACHE);
  }

  /**
   * Get the expiry time for cache, CACHE_EXPIRY_NO_CACHE means cache is not used.
   *
   * @return string|null
   */
  public function getExpiry(): string|null {
    return $this->info["expiry"];
  }

  /**
   * Determine if cache is enabled/active.
   *
   * @return bool TRUE use the cache, FALSE if to bypass/not use cache.
   */
  public function is_active(): bool {
    return $this->info["enabled"];
  }

  /**
   * Set the cache-hit status.
   *
   * @param string $status
   *
   * @return void
   */
  public function setStatus(string $status): void {
      $this->info["status"] = $status;
  }

  /**
   * Get the cache-hit status.
   *
   * @return string
   */
  public function getStatus(): string {
    return $this->info["status"];
  }

  /**
   * Returns the complete cache settings and status.
   *
   * @return array
   */
  public function info(): array {
    return $this->info;
  }

  /**
   * Checks if an AI request is cached.
   * If no parameters are passed, then the id created from the most recent
   * makeId() call is used.
   * If there are no parameters and no id was created, then FALSE is always
   * returned.
   *
   * If the expiry is self::CACHE_EXPIRY_NO_CACHE then always return FALSE.
   *
   * @param string|NULL $service
   * @param string|NULL $prompt
   * @param string|NULL $text
   *
   * @return bool
   */
  public function is_cached(string $service = NULL, string $prompt = NULL, string $text = NULL): bool {
    if (!$this->is_active()) {
      // If the cache is not being used, then report that the cache was missed.
      return FALSE;
    }

    if ($service && $prompt && $text) {
      // Creates an ID and saves in $this->info.
      $this->makeId($service, $prompt, $text);
    }

    // Use no parameters so that the id from $this->info is always used.
    $cache = $this->get();

    if ($cache === FALSE) {
      $this->info["status"] = self::CACHE_STATUS_MISS;
      return FALSE;
    }
    else {
      $this->info["status"] = self::CACHE_STATUS_HIT;
      return TRUE;
    }
  }

  /**
   * Checks the cache for this item, and returns it if found, otherwise returns
   * FALSE. If no parameters are passed, then use the most recently generate id
   * from makeId().  If no id is found or generated, then always return FALSE.
   *
   * If the expiry is self::CACHE_EXPIRY_NO_CACHE, then always return FALSE.
   *
   * @param string|NULL $service The requesting service.
   * @param string|NULL $prompt The prompt being used.
   * @param string|NULL $text The "seed" text being processed.
   *
   * @return bool|object The cache item or FALSE if not yet cached.
   */
  public function get(string $service = NULL, string $prompt = NULL, string $text = NULL): bool|object {
    if (!$this->is_active()) {
      // If the cache is not being used, then report that the cache was missed.
      $this->info["status"] = self::CACHE_STATUS_MISS;
      return FALSE;
    }

    if ($service && $prompt && $text) {
      // Make the id now.
      $this->makeId($service, $prompt, $text);
    }

    if (empty($this->info["id"])) {
      // No id has been created at this point, so return FALSE.
      $this->info["status"] = self::CACHE_STATUS_MISS;
      return FALSE;
    }

    $this->info["entry"] = $this->cache->get($this->info["id"]);

    if ($this->info["entry"] === FALSE) {
      $this->info["status"] = self::CACHE_STATUS_MISS;
      return FALSE;
    }
    else {
      $this->info["status"] = self::CACHE_STATUS_HIT;
      return $this->info["entry"];
    }

  }

  /**
   * Adds a new item to the cache.
   * Uses the service and prompt as tags.
   * Creates a unique id by concatinating and encoding the service:prompt:text.
   * The cache expiry (a date) is set using PHP strtotime($duration).
   *
   * If the expiry is self::CACHE_EXPIRY_NO_CACHE, then does nothing
   *
   * @param string $service The requesting service.
   * @param string $prompt The prompt being used.
   * @param string $text The "seed" tect being processed.
   * @param string $data The data to be cached.
   * @param string|null $duration Cache item duration a strtotime-parsable string.
   *
   * @return void
   */
  public function set(string $service, string $prompt, string $text, string $data, ?string $duration = NULL): void {

    if (NULL === $duration) {
      if (NULL == $this->getExpiry()) {
        // Read expiry from config for this service. Finally, default to no-cache.
        $duration = $this->config->get("$service.cache") ?? self::CACHE_EXPIRY_NO_CACHE;
        $this->setExpiry($duration);
      }
    }

    // Only proceed if the cache is being used.
    if ($this->is_active()) {
      $id = $this->makeId($service, $prompt, $text);
      $expiry = $this->makeExpiryDate($this->getExpiry());
      // Need to make our tags unique to this project (google cloud) because the
      // CacheTagsInvalidator->invalidateTags() process works across all bins.
      // We don't want our invalidations to accidentially invalidate Drupal core
      // caches and vise-versa.
      $tags = Cache::mergeTags(["gc.$service", "$service.$prompt"]);
      $this->cache->set($id, $data, $expiry, $tags);
    }

  }

  /**
   * Invaildates a specific cache item
   *
   * @param array $ids This is a base64 encoded string "$text:$prompt"
   *
   * @return void
   */
  public function invalidateCacheById(array $ids = []): void {
    if (empty($ids)) {
      $this->cache->invalidateAll();
    }
    foreach($ids as $id) {
      $this->cache->invalidate($id);
    }
  }

  /**
   * Invalidates all cache items tagged with the service and prompts specified.
   *
   * @param string $service The service to delete tags for.
   * @param array $prompts An array of tags to invalidate items.
   *   [optionally] can use Cache::mergeTags to sanitize array before passing
   *   to this function.
   *
   * @return void
   */
  public function invalidateCacheByPrompts(string $service, array $prompts): void {
    foreach($prompts as &$prompt) {
      $prompt = "$service.$prompt";
    }
    $this->invalidateCacheByTags($prompts);
  }

  /**
   * Invalidates all cache items for the specified service.
   *
   * @param string $service The service to invalidate all cached items for.
   *
   * @return void
   */
  public function invalidateCacheByService(string $service): void {
    $this->invalidateCacheByTags([$service]);
  }

  /**
   * Invalidates all cache items (from all bins) based on the tag.
   *
   * @param array $tags An array of tags.
   *
   * @return void
   */
  private function invalidateCacheByTags(array $tags): void {
    /**
     * @var \Drupal\Core\Cache\CacheTagsInvalidator $cache
     */
    $cache = Drupal::service('cache_tags.invalidator');
    /** Marks cache items from all bins with any of the specified tags as
     * invalid.
     * @see https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cache%21Cache.php/function/Cache%3A%3AinvalidateTags/10
     */
    $cache->invalidateTags($tags);
  }

  /**
   * Makes a unique ID for caching.
   *
   * @param string $service The Google Cloud service.
   * @param string $prompt The prompt key being used see GcGenerationPrompt consts.
   * @param string $text The text query being cached.
   *
   * @return string a unique string id (base64encode of parameters)
   */
  public function makeId(string $service, string $prompt, string $text):string {
    $this->info["id"] = base64_encode($service . "|" . $prompt . "|" . $text);
    return $this->info["id"];
  }

  /**
   * Returns the cache_id as an array.
   *
   * @param string $cache_id
   * @param bool $associative [optional]
   *
   * @return array
   */
  public function decodeCacheId(string $cache_id, bool $associative = FALSE): array {
    $cache_id = base64_decode($cache_id);
    $cache = explode("|", $cache_id, 3);
    if ($associative) {
      return array_combine(["service", "prompt", "text"], $cache);
    }
    else {
      return $cache;
    }
  }

  /**
   * Convert the cache duration to an actual expiry date.
   *
   * @param string $duration String understood by PHP strtotime.  Used to create
   *  a date which is an offset from "now".
   *
   * @return int
   */
  private function makeExpiryDate(string $duration): int {
    $expiry = Cache::PERMANENT;
    if ($duration != self::PERMANENT) {
      if (!$expiry = strtotime($duration)) {
        // If the supplied string cannot be changed to a date, use +1 month.
        return strtotime(self::CACHE_EXPIRY_1MONTH);
      }
    }
    return $expiry;
  }

}
