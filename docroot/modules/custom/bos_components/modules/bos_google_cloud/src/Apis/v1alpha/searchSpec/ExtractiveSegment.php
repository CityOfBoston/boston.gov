<?php

/**
 * REQUEST API OBJECT
 *
 * Google Cloud DiscoveryEngine
 *
 * Extractive segment. Guide Answer generation will only use it if documentContexts is empty. This is supposed to be shorter snippets.
 *
 * @file src/Apis/v1alpha/searchSpec/ExtractiveSegment.php
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/SearchSpec#ExtractiveSegment
 * @see https://cloud.google.com/generative-ai-app-builder/docs/snippets#extractive-segments
 */

namespace Drupal\bos_google_cloud\Apis\v1alpha\searchSpec;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase;

class ExtractiveSegment extends GcDiscoveryEngineObjectsBase {

  public function __construct(array $settings) {
    $this->object = [
      "pageIdentifier" => NULL, // string,
      "content" => NULL, // string,
    ];
    $this->object = array_merge($this->object, $settings);
  }

}
