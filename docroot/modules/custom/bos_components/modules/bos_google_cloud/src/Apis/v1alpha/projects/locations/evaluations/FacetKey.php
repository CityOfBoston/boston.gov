<?php
/**
 * REQUEST API OBJECT
 *
 * Google Cloud DiscoveryEngine
 *
 * Specifies how a facet is computed.
 *
 * @file src/Apis/v1alpha/projects\locations\evaluations\TextInput.php
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/projects.locations.evaluations#FacetKey
 */

namespace Drupal\bos_google_cloud\v1alpha\projects\locations\evaluations;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase;
use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsInterface;

class FacetKey extends GcDiscoveryEngineObjectsBase implements GcDiscoveryEngineObjectsInterface {

  public function __construct(array $settings = []) {
    $this->object = [
      "key" => NULL,    // string
      "intervals" => NULL,  // array of \Drupal\bos_google_cloud\interval objects
      "restrictedValues" => NULL, // array of strings
      "prefixes" => NULL,    // array of strings
      "contains" => NULL, // array of strings
      "caseInsensitive" => NULL,  // boolean
      "orderBy" => NULL,    // string
    ];
    $this->object = array_merge($this->object, $settings);
  }

}

