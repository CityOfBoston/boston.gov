<?php
/**
 * REQUEST API OBJECT
 *
 * Google Cloud DiscoveryEngine
 *
 * A struct to define data stores to filter on in a search call and configurations for those data stores
 *
 * @file src/Apis/v1alpha/projects\locations\evaluations\TextInput.php
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/projects.locations.evaluations#DataStoreSpec
 */

namespace Drupal\bos_google_cloud\Apis\v1alpha\projects\locations\evaluations;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase;
use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsInterface;

/**
 * Class DataStoreSpec.
 *
 * This class extends GcDiscoveryEngineObjectsBase and implements
 * GcDiscoveryEngineObjectsInterface.
 * It is used to handle the specifications for a data store including optional
 * configurations for dataStore and filter.
 *
 * @param array $settings
 *   Optional settings to initialize the data store specifications.
 */
class DataStoreSpec extends GcDiscoveryEngineObjectsBase implements GcDiscoveryEngineObjectsInterface {

  public function __construct(array $settings = []) {
    $this->object = [
      "dataStore" => NULL,
      "filter" => NULL,
    ];
    $this->object = array_merge($this->object, $settings);

  }

}
