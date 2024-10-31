<?php

/**
 * REQUEST API ENDPOINT OBJECT
 *
 * Google Cloud DiscoveryEngine
 *
 * This is the Vertex AI Builder conversation request body structure.
 *
 * @file src/Apis/v1alpha/projects/locations/collections/dataStores/conversations/Converse.php
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/projects.locations.collections.dataStores.conversations/converse
 */

/**************************************************************
 *  projects.locations.collections.datastores.conversations.converse
 **************************************************************/

namespace Drupal\bos_google_cloud\Apis\v1alpha\projects\locations\collections\dataStores\conversations;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase;
use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsInterface;

class Converse extends GcDiscoveryEngineObjectsBase implements GcDiscoveryEngineObjectsInterface {
  public function __construct() {
    $this->object = [
      "query" => NULL,
      "servingConfig" => NULL,
      "conversation" => NULL,
      "safeSearch" => NULL, // control the level of explicit content that the system can display in the results. This is similar to the feature used in Google Search, where you can modify your settings to filter explicit content, such as nudity, violence, and other adult content, from the search results.
      "userLabels" => NULL,
      "summarySpec" => NULL,
      "filter" => NULL,
      "boostSpec" => NULL,
    ];
  }

}
