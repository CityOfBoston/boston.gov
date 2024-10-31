<?php

/**
 * REQUEST API OBJECT
 *
 * Google Cloud DiscoveryEngine
 *
 * Search specification.
 *
 * @file src/Apis/v1alpha/searchSpec/SearchSpec.php
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/SearchSpec
 */

namespace Drupal\bos_google_cloud\Apis\v1alpha\searchSpec;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase;

class SearchSpec extends GcDiscoveryEngineObjectsBase {

  public function __construct(array $settings) {
    $this->object = [
      "searchParams" => NULL,    // object SearchParams
      "searchResultList" => NULL,    // object SearchResultsList
    ];
    $this->object = array_merge($this->object, $settings);
  }

}
