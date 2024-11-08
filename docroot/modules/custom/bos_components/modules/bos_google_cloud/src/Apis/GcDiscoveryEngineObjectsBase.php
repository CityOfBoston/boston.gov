<?php

namespace Drupal\bos_google_cloud\Apis;

/**
* Base class for all GC DicoveryEngine Request Objects.
* Provides a means to export the pobject as an array, or a json object.
*/

abstract class GcDiscoveryEngineObjectsBase implements GcDiscoveryEngineObjectsInterface {

  protected array $object;

  /**
   * @inheritDoc
   */
  public function set(string $key, int|bool|string|array|GcDiscoveryEngineObjectsInterface $value): GcDiscoveryEngineObjectsInterface {
    if (array_key_exists($key, $this->object)) {
      if (is_array($value)) {
        $this->object[$key] = array_merge($this->object[$key] ?: [], $value);
      }
      else {
        $this->object[$key] = $value;
      }
    }
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function get(string $key): NULL|int|bool|string|array|GcDiscoveryEngineObjectsInterface {
    return $this->object[$key] ?? NULL;
  }

  /**
   * @inheritDoc
   */
  public function toArray(): array {
    return $this->trimArray($this->object) ?: [];
  }

  /**
   * @inheritDoc
   */
  public function toJson(): string {
    return json_encode($this->toArray());
  }

  /**
   * Removes elements in the array which been set to null.
   *
   * @param $array
   *
   * @return NULL|array
   */
  private function trimArray($array): NULL|array {
    $output = [];
    foreach ($array as $key => $value) {
      if ($value != NULL) {
        if (is_object($value)) {
          $newval = $this->trimArray($value->toArray());
          if ($newval !== NULL) {
            $output[$key] = $newval;
          }
        }
        elseif (is_array($value)) {
          $newval = $this->trimArray($value);
          if ($newval !== NULL) {
            $output[$key] = $newval;
          }
        }
        else {
          $output[$key] = $value;
        }
      }
    }
    return empty($output) ? NULL : $output;
  }

}
