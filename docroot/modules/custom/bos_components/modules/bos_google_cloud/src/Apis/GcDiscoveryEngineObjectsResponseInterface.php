<?php

namespace Drupal\bos_google_cloud\Apis;

/**
 * Interface for all GC DicoveryEngine Response Objects.
 */

interface GcDiscoveryEngineObjectsResponseInterface {

  /**
   * Validate whether or not the supplied response is complete and in the
   * expected class object format.
   *
   * @return bool
   */
  public function validate(): bool;

  /**
   * Reports if the validation had errors.
   *
   * @return bool
   */
  public function hasErrors(): bool;

  /**
   * Retrieves the list of error messages.
   *
   * @return array An array containing all error messages.
   */
  public function getErrors(): array;

  /**
   * Adds an error message to the errors array.
   *
   * @param string $error The error message to be added.
   *
   * @return void
   */
  public function setError(string $error): void;

}
