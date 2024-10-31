<?php

/**
 * REQUEST API OBJECT
 *
 * Google Cloud DiscoveryEngine
 *
 * A specification for configuring the behavior of content search.
 *
 * @file src/Apis/v1alpha/contentSearchSpec/ContentSearchSpec.php
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/ContentSearchSpec
 */

namespace Drupal\bos_google_cloud\Apis\v1alpha\contentSearchSpec;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase;
use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsInterface;

class ContentSearchSpec extends GcDiscoveryEngineObjectsBase implements GcDiscoveryEngineObjectsInterface {

  public function __construct(array $settings = []) {
    $this->object = [
      "snippetSpec" => NULL,   // object \Drupal\bos_google_cloud\snippetSpec
      "summarySpec" => NULL,   // object \Drupal\bos_google_cloud\summarySpec
      "extractiveContentSpec" => NULL,    // object \Drupal\bos_google_cloud\extractiveSummarySpec
      "searchResultMode" => NULL,   // string - SEARCH_RESULT_MODE_UNSPECIFIED or DOCUMENTS or CHUNKS
      "chunkSpec" => NULL,    // object \Drupal\bos_google_cloud\chunkSpec
    ];
    $this->object = array_merge($this->object, $settings);
  }

}
