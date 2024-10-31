<?php

/**
 * REQUEST API OBJECT
 *
 * Google Cloud DiscoveryEngine
 *
 * Query classification specification.
 *
 * @file src/Apis/v1alpha/queryUnderstandingSpec/QueryClassificationSpec.php
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/QueryUnderstandingSpec#QueryClassificationSpec
 */

namespace Drupal\bos_google_cloud\Apis\v1alpha\queryUnderstandingSpec;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase;

class QueryClassificationSpec extends GcDiscoveryEngineObjectsBase {

  public function __construct(array $settings) {
    $this->object = [
      "types" => NULL,   // array of TYPE_UNSPECIFIED, ADVERSARIAL_QUERY, NON_ANSWER_SEEKING_QUERY, JAIL_BREAKING_QUERY, NON_ANSWER_SEEKING_QUERY_V2
    ];
    $this->object = array_merge($this->object, $settings);
  }

}
