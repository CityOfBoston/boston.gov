<?php
/**
 * RESPONSE API OBJECT
 *
 * Defines a user inputed query.
 *
 * Defines and answer.
 *
 * @file src/Apis/v1alpha/projects/locations/collections/datastores/sessions/Query.php
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/projects.locations.collections.dataStores.sessions#query
 */

namespace Drupal\bos_google_cloud\Apis\v1alpha\projects\locations\collections\dataStores\sessions;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase;
use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsInterface;

class Query extends GcDiscoveryEngineObjectsBase implements GcDiscoveryEngineObjectsInterface {

  public function __construct(array $settings = []) {
    $this->object = [
      "queryId" => NULL, // string
      "text" => NULL,   // string
    ];
    $this->object = array_merge($this->object, $settings);
  }

}
