<?php

/**
 * REQUEST API OBJECT
 *
 * Google Cloud DiscoveryEngine
 *
 * Specification of the prompt to use with the model.
 *
 * @file src/Apis/v1alpha/answerGenerationSpec/PromptSpec.php
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/AnswerGenerationSpec#PromptSpec
 */

namespace Drupal\bos_google_cloud\Apis\v1alpha\answerGenerationSpec;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase;
use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsInterface;

class PromptSpec extends GcDiscoveryEngineObjectsBase implements GcDiscoveryEngineObjectsInterface {

  public function __construct(array $settings = []) {
    $this->object = [
      "preamble" => NULL,   // string
    ];
    $this->object = array_merge($this->object, $settings);
  }

}
