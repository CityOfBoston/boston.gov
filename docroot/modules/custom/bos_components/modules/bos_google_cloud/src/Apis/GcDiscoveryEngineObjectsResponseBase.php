<?php

namespace Drupal\bos_google_cloud\Apis;

/**
 * Base class for all GC Dicovery Engine Response Objects.
 * Extends Request Base class for request objects
 * Provides a means to export the pobject as an array, or a json object.
 */
abstract class GcDiscoveryEngineObjectsResponseBase extends GcDiscoveryEngineObjectsBase implements GcDiscoveryEngineObjectsResponseInterface {

  protected array $errors;

  /**
   * @inheritDoc
   */
  public function hasErrors():bool {
    return !empty($this->errors);
  }

  /**
   * @inheritDoc
   */
  public function getErrors(): array {
    return $this->errors;
  }

  /**
   * @inheritDoc
   */
  public function setError(string $error): void {
    $this->errors[] = $error;
  }

}
