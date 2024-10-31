<?php

/**
 * REQUEST API OBJECT
 *
 * Google Cloud DiscoveryEngine
 *
 * Answer generation specification.
 *
 * @file src/Apis/v1alpha/AnswerGenerationSpec/AnswerGenerationSpec.php
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/AnswerGenerationSpec
 */

namespace Drupal\bos_google_cloud\Apis\v1alpha\answerGenerationSpec;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase;

class AnswerGenerationSpec extends GcDiscoveryEngineObjectsBase {

  public function __construct(array $settings) {
    $this->object = [
      "modelSpec" => NULL,    // object \Drupal\bos_google_cloud\modelSpec
      "promptSpec" => NULL,    // object \Drupal\bos_google_cloud\modelPromptSpec
      "includeCitations" => NULL,   // boolean
      "answerLanguageCode" => NULL,    // string
      "ignoreAdversarialQuery" => NULL,    // boolean
      "ignoreNonAnswerSeekingQuery" => NULL,   // boolean
      "ignoreJailBreakingQuery" => NULL,    // boolean
      "ignoreLowRelevantContent" => NULL,    // boolean
    ];
    $this->object = array_merge($this->object, $settings);
  }

}
