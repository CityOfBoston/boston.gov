<?php
/**
 * REQUEST API OBJECT
 *
 * Google Cloud DiscoveryEngine
 *
 * Specification to determine under which conditions query expansion should occur.
 *
 * @file src/Apis/v1alpha/projects\locations\evaluations\TextInput.php
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/projects.locations.evaluations#QueryExpansionSpec
 */

namespace Drupal\bos_google_cloud\Apis\v1alpha\projects\locations\evaluations;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase;
use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsInterface;

class QueryExpansionSpec extends GcDiscoveryEngineObjectsBase implements GcDiscoveryEngineObjectsInterface {

  public function __construct(array $settings = []) {
    $this->object = [
      "condition" => NULL,    // string - CONDITION_UNSPECIFIED or DISABLED or AUTO
      "pinUnexpandedResults" => NULL,   // bool
    ];
    $this->object = array_merge($this->object, $settings);
  }

}


