<?php

namespace Drupal\bos_google_cloud\Apis;

/**
 * Interface for all GC DicoveryEngine Request Objects.
 */

interface GcDiscoveryEngineObjectsInterface {

  /**
   * Sets a value into the object
   *
   * @return mixed
   */
  public function get(string $key): NULL|int|bool|string|array|GcDiscoveryEngineObjectsInterface;

  /**
   * Gets a value from the object
   *
   * @param string $key
   * @param int|bool|string|array $value
   *
   * @return \Drupal\bos_google_cloud\GcDiscoveryEngineObjectsInterface
   */
  public function set(string $key, int|bool|string|array|GcDiscoveryEngineObjectsInterface $value): GcDiscoveryEngineObjectsInterface;

  /**
   * Returns the object as an array.
   *
   * @return array
   */
  public function toArray(): array;

  /**
   * Returns the object as a json string.
   *
   * @return string
   */
  public function toJson(): string;

}
