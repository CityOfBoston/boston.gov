<?php

/**
 * REQUEST API OBJECT
 *
 * Google Cloud DiscoveryEngine
 *
 * Safety specification.
 *
 * @file src/Apis/v1alpha/safetySpec/SafetySpec.php
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/SafetySpec
 */

namespace Drupal\bos_google_cloud\Apis\v1alpha\safetySpec;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase;

class SafetySpec extends GcDiscoveryEngineObjectsBase {

  public function __construct(array $settings) {
    $this->object = [
      "enable" => NULL,   // boolean
    ];
    $this->object = array_merge($this->object, $settings);
  }

}
