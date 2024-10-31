<?php

/**
 * REQUEST API OBJECT
 *
 * Google Cloud DiscoveryEngine
 *
 * Search result.
 *
 * @file src/Apis/v1alpha/searchSpec/SearchResult.php
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/SearchSpec#SearchResult
 */

namespace Drupal\bos_google_cloud\Apis\v1alpha\searchSpec;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase;

class SearchResult extends GcDiscoveryEngineObjectsBase {

  public function __construct(array $settings) {
    $this->object = [
      "unstructuredDocumentInfo" => NULL,    // object unstructuredDocumentInfo
      "chunkInfo" => NULL,    // object chunkInfo
    ];
    $this->object = array_merge($this->object, $settings);
  }

}
