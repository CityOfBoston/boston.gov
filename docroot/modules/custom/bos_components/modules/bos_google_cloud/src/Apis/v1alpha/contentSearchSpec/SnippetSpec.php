<?php

/**
 * REQUEST API OBJECT
 *
 * Google Cloud DiscoveryEngine
 *
 * A specification for configuring snippets in a search response.
 *
 * @file src/Apis/v1alpha/contentSearchSpec/SnippetSpec.php
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/ContentSearchSpec#SnippetSpec
 */

namespace Drupal\bos_google_cloud\Apis\v1alpha\contentSearchSpec;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase;
use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsInterface;

class SnippetSpec extends GcDiscoveryEngineObjectsBase implements GcDiscoveryEngineObjectsInterface {

  public function __construct(array $settings = []) {
    $this->object = [
      "maxSnippetCount" => NULL,   // int
      "referenceOnly" => NULL,   // boolean
      "returnSnippet" => NULL,    // boolean
    ];
    $this->object = array_merge($this->object, $settings);
  }

}
