<?php
/**
 * REQUEST API OBJECT
 *
 * Google Cloud DiscoveryEngine
 *
 * Boost applies to documents which match a condition.
 *
 * @file src/Apis/v1alpha/projects\locations\evaluations\TextInput.php
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/projects.locations.evaluations#conditionBoostSpec
 */

namespace Drupal\bos_google_cloud\v1alpha\contentSearchSpec;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase;
use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsInterface;

class ConditionBoostSpec extends GcDiscoveryEngineObjectsBase implements GcDiscoveryEngineObjectsInterface {

  public function __construct(array $settings = []) {
    $this->object = [
      "condition" => NULL,    // string
      "boost" => NULL,    // number
      "boostControlSpec" => NULL,    // array of \Drupal\bos_google_cloud\boostControlSpec
    ];
    $this->object = array_merge($this->object, $settings);
  }

}


