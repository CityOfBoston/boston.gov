<?php

/**
 * REQUEST API OBJECT
 *
 * Google Cloud DiscoveryEngine
 *
 * The specification for personalization.
 *
 * @file src/Apis/v1alpha/personalizationSpec/PersonalizationSpec.php
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/personalizationSpec
 */

namespace Drupal\bos_google_cloud\Apis\v1alpha\personalizationSpec;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase;
use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsInterface;

class PersonalizationSpec extends GcDiscoveryEngineObjectsBase implements GcDiscoveryEngineObjectsInterface {

  public function __construct(array $settings = []) {
    $this->object = [
      "mode" => NULL,    // string - MODE_UNSPECIFIED or SUGGESTION_ONLY or AUTO
    ];
    $this->object = array_merge($this->object, $settings);
  }

}
