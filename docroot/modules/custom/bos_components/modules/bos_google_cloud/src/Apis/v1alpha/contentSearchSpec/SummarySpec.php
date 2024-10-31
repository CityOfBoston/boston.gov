<?php

/**
 * REQUEST API OBJECT
 *
 * Google Cloud DiscoveryEngine
 *
 * A specification for configuring the behavior of content search.
  *
 * @file src/Apis/v1alpha/contentSearchSpec/SummarySpec.php
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/ContentSearchSpec#SummarySpec
 */

namespace Drupal\bos_google_cloud\Apis\v1alpha\contentSearchSpec;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase;
use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsInterface;

class SummarySpec extends GcDiscoveryEngineObjectsBase implements GcDiscoveryEngineObjectsInterface {

  public function __construct(array $settings = []) {
    $this->object = [
      "summaryResultCount" => NULL,   // int
      "includeCitations" => NULL,   // boolean
      "ignoreAdversarialQuery" => NULL,    // boolean
      "ignoreNonSummarySeekingQuery" => NULL,   // boolean
      "ignoreLowRelevantContent" => NULL,    // boolean
      "ignoreJailBreakingQuery" => NULL,    // boolean
      "modelPromptSpec" => NULL,    // object \Drupal\bos_google_cloud\modelPromptSpec
      "languageCode" => NULL,    // string
      "modelSpec" => NULL,    // object \Drupal\bos_google_cloud\modelSpec
      "useSemanticChunks" => NULL,    // boolean
    ];
    $this->object = array_merge($this->object, $settings);
  }

}

