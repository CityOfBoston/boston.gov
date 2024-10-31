<?php

/**
 * REQUEST API OBJECT
 *
 * Google Cloud DiscoveryEngine
 *
 * A specification for configuring the extractive content in a search response.
 *
 * @file src/Apis/v1alpha/contentSearchSpec/ExtractiveContentSpec.php
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/ContentSearchSpec#extractivecontentspec
 */

namespace Drupal\bos_google_cloud\Apis\v1alpha\contentSearchSpec;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase;
use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsInterface;

class ExtractiveContentSpec extends GcDiscoveryEngineObjectsBase implements GcDiscoveryEngineObjectsInterface {

  public function __construct(array $settings = []) {
    $this->object = [
      "maxExtractiveAnswerCount" => NULL,   // int
      "maxExtractiveSegmentCount" => NULL,   // int
      "returnExtractiveSegmentScore" => NULL,    // boolean
      "numPreviousSegments" => NULL,   // int
      "numNextSegments" => NULL,    // int
    ];
    $this->object = array_merge($this->object, $settings);
  }

}

