<?php

/**
 * REQUEST API OBJECT
 *
 * Google Cloud DiscoveryEngine
 *
 * Extractive answer.
 *
 * @file src/Apis/v1alpha/searchSpec/ExtractiveAnswer.php
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/SearchSpec#ExtractiveAnswer
 * @see https://cloud.google.com/generative-ai-app-builder/docs/snippets#get-answers
 */

namespace Drupal\bos_google_cloud\Apis\v1alpha\searchSpec;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase;

class ExtractiveAnswer extends GcDiscoveryEngineObjectsBase {

  public function __construct(array $settings) {
    $this->object = [
      "pageIdentifier" => NULL, // string,
      "content" => NULL, // string,
    ];
    $this->object = array_merge($this->object, $settings);
  }

}