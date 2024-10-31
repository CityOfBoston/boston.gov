<?php
/**
 * REQUEST API OBJECT
 *
 * Google Cloud DiscoveryEngine
 *
 * Boost specification to boost certain documents.
 *
 * @file src/Apis/v1alpha/projects\locations\evaluations\TextInput.php
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/projects.locations.evaluations#boostspec
 */

namespace Drupal\bos_google_cloud\v1alpha\projects\locations\evaluations;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase;
use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsInterface;

class BoostSpec extends GcDiscoveryEngineObjectsBase implements GcDiscoveryEngineObjectsInterface {

  public function __construct(array $settings = []) {
    $this->object = [
      "conditionBoostSpecs" => NULL,    // array of \Drupal\bos_google_cloud\conditionBoostSpec
    ];
    $this->object = array_merge($this->object, $settings);
  }

}


