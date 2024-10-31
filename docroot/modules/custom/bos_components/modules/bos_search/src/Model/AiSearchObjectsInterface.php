<?php

namespace Drupal\bos_search\Model;

interface AiSearchObjectsInterface {

  /**
   * Sets a value into the object
   *
   * @return mixed
   */
  public function get(string $key): mixed;

  /**
   * Gets a value from the object
   *
   * @param string $key
   * @param mixed $value
   *
   * @return \Drupal\bos_search\Model\AiSearchObjectsInterface
   */
  public function set(string $key, mixed $value): AiSearchObjectsInterface;

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
