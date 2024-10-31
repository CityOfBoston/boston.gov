<?php
/**
 * REQUEST API OBJECT
 *
 * Google Cloud DiscoveryEngine
 *
 * A floating point interval.
 *
 * @file src/Apis/v1alpha/projects\locations\evaluations\TextInput.php
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/projects.locations.evaluations#Interval
 */

namespace Drupal\bos_google_cloud\v1alpha\projects\locations\evaluations;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase;
use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsInterface;

class MaxInterval extends GcDiscoveryEngineObjectsBase implements GcDiscoveryEngineObjectsInterface {

  public function __construct(array $settings = []) {
    $this->object = [
      "maximum" => NULL,    // number
      "exclusiveMaximum" => NULL,  // number
    ];
    $this->object = array_merge($this->object, $settings);
  }

}


