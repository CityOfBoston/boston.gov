<?php

namespace Drupal\bos_search\Model;

/**
* Base class for all AiSearch Model Objects.
* Provides a means to export the object as an array, or a json object.
*/
abstract class AiSearchObjectsBase implements AiSearchObjectsInterface {

  protected array $object;

  /**
   * @inheritDoc
   */
  public function set(string $key, mixed $value): AiSearchObjectsInterface {

//    if (!property_exists($this, $key)) {
//      // This causes the set function to silently fail if the $key being set
//      // does not yet exist - i.e. prevents dynamic class variables.
//      return $this;
//    }

    if (is_array($value)) {
      $this->{$key} = array_merge($this->{$key} ?? [], $value);
    }
    else {
      $this->{$key} = $value;
    }
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function get(string $key): mixed {
    return $this->{$key} ?? NULL;
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
        else {
          $output[$key] = $value;
        }
      }
    }
    return empty($output) ? NULL : $output;
  }

}
