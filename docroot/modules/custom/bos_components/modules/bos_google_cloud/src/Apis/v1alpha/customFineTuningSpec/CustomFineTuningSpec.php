<?php
/**
 * REQUEST API OBJECT
 *
 * Google Cloud DiscoveryEngine
 *
 * Defines custom fine tuning spec.
 *
 * @file src/Apis/v1alpha/customFineTuningSpec/CustomFineTuningSpec.php
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/CustomFineTuningSpec
 */
namespace Drupal\bos_google_cloud\Apis\v1alpha\customFineTuningSpec;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase;
use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsInterface;

class CustomFineTuningSpec extends GcDiscoveryEngineObjectsBase implements GcDiscoveryEngineObjectsInterface {

  public function __construct(array $settings = []) {
    $this->object = [
      "enableSearchAdaptor" => NULL,    // bool
    ];
    $this->object = array_merge($this->object, $settings);
  }

}

