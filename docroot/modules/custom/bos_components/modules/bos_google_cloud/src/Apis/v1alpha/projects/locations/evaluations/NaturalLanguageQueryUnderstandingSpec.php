<?php
/**
 * REQUEST API OBJECT
 *
 * Google Cloud DiscoveryEngine
 *
 * Specification to enable natural language understanding capabilities for search requests
 *
 * @file src/Apis/v1alpha/projects\locations\evaluations\TextInput.php
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/projects.locations.evaluations#NaturalLanguageQueryUnderstandingSpec
 */

namespace Drupal\bos_google_cloud\v1alpha\projects\locations\evaluations;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase;
use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsInterface;

class NaturalLanguageQueryUnderstandingSpec extends GcDiscoveryEngineObjectsBase implements GcDiscoveryEngineObjectsInterface {

  public function __construct(array $settings = []) {
    $this->object = [
      "filterExtractionCondition" => NULL,    // string CONDITION_UNSPECIFIED or DISABLED or ENABLED
      "geoSearchQueryDetectionFieldNames" => NULL,    // array of strings
    ];
    $this->object = array_merge($this->object, $settings);
  }

}

