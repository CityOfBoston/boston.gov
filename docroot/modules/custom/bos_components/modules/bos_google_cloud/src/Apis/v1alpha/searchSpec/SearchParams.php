<?php

/**
 * REQUEST API OBJECT
 *
 * Google Cloud DiscoveryEngine
 *
 * Search parameters.
 *
 * @file src/Apis/v1alpha/searchSpec/SearchParams.php
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/SearchSpec#SearchParams
 */

namespace Drupal\bos_google_cloud\Apis\v1alpha\searchSpec;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase;

class SearchParams extends GcDiscoveryEngineObjectsBase {

  public function __construct(array $settings) {
    $this->object = [
      "maxReturnResults" => NULL,   // int
      "filter" => NULL,     // string
      "boostSpec" => NULL,    // object Apis/v1alpha/projects/locations/evaluations/BoostSpec
      "orderBy" => NULL,    // string
      "searchResultMode" => NULL, // string One of SEARCH_RESULT_MODE_UNSPECIFIED, DOCUMENTS or CHUNKS
      "customFineTuningSpec" => NULL, //   object Apis/v1alpha/customFineTuningSpec/CustomFineTuningSpec
      "dataStoreSpecs" => NULL, // array of object Apis/v1alpha/projects/locations/evaluationsDataStoreSpec
      "naturalLanguageQueryUnderstandingSpec"=> NULL, // object Apis/v1alpha/projects/locations/evaluations/NaturalLanguageQueryUnderstandingSpec
    ];
    $this->object = array_merge($this->object, $settings);
  }

}
