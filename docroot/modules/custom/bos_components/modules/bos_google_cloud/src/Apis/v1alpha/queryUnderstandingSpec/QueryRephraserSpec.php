<?php

/**
 * REQUEST API OBJECT
 *
 * Google Cloud DiscoveryEngine
 *
 * Query understanding specification.
 *
 * @file src/Apis/v1alpha/queryUnderstandingSpec/QueryRephraserSpec.php
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/QueryUnderstandingSpec#QueryRephraserSpec
 */

namespace Drupal\bos_google_cloud\Apis\v1alpha\queryUnderstandingSpec;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase;

class QueryRephraserSpec extends GcDiscoveryEngineObjectsBase {

  public function __construct(array $settings) {
    $this->object = [
      "enable" => NULL,   // boolean
    ];
    $this->object = array_merge($this->object, $settings);
  }

}
