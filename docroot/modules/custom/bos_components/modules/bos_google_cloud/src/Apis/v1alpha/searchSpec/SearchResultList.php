<?php

/**
 * REQUEST API OBJECT
 *
 * Google Cloud DiscoveryEngine
 *
 * Search result list.
 *
 * @file src/Apis/v1alpha/searchSpec/SearchResultList.php
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/SearchSpec#SearchResultList
 */

namespace Drupal\bos_google_cloud\Apis\v1alpha\searchSpec;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase;

class SearchResultList extends GcDiscoveryEngineObjectsBase {

  public function __construct(array $settings) {
    $this->object = [
      "searchResult" => NULL,    // object SearchResult
    ];
    $this->object = array_merge($this->object, $settings);
  }

}
