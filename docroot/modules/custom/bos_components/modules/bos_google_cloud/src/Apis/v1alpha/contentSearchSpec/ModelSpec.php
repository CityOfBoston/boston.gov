<?php

/**
 * REQUEST API OBJECT
 *
 * Google Cloud DiscoveryEngine
 *
 * Specification of the model.
 *
 * @file src/Apis/v1alpha/contentSearchSpec/ModelSpec.php
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/ContentSearchSpec#modelspec
 */

namespace Drupal\bos_google_cloud\Apis\v1alpha\contentSearchSpec;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase;
use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsInterface;

class ModelSpec extends GcDiscoveryEngineObjectsBase implements GcDiscoveryEngineObjectsInterface {

  public function __construct(array $settings = []) {
    $this->object = [
      "version" => NULL,   // string    One of "stable" or "preview"
    ];
    $this->object = array_merge($this->object, $settings);
  }

}
