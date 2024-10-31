<?php

/**
 * REQUEST API OBJECT
 *
 * Google Cloud DiscoveryEngine
 *
 * Related questions specification.
 *
 * @file src/Apis/v1alpha/relatedQuestionsSpec.php
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/RelatedQuestionsSpec
 */

namespace Drupal\bos_google_cloud\Apis\v1alpha\relatedQuestionsSpec;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase;

class RelatedQuestionsSpec extends GcDiscoveryEngineObjectsBase {

  public function __construct(array $settings) {
    $this->object = [
      "enable" => NULL,   // boolean
    ];
    $this->object = array_merge($this->object, $settings);
  }

}
