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
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/projects.locations.evaluations#BoostControlSpec
 */

namespace Drupal\bos_google_cloud\v1alpha\projects\locations\evaluations;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase;
use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsInterface;

class BoostControlSpec extends GcDiscoveryEngineObjectsBase implements GcDiscoveryEngineObjectsInterface {

  public function __construct(array $settings = []) {
    $this->object = [
      "fieldName" => NULL,    // string
      "attributeType" => NULL,    // string - ATTRIBUTE_TYPE_UNSPECIFIED or NUMERICAL or FRESHNESS
      "interpolationType" => NULL,    // string - INTERPOLATION_TYPE_UNSPECIFIED or LINEAR
      "controlPoints" => NULL,    // array of \Drupal\bos_google_cloud\controlPoint
    ];
    $this->object = array_merge($this->object, $settings);
  }

}


