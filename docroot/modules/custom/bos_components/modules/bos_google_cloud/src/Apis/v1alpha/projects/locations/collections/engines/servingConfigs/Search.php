<?php

/**
 * REQUEST API ENDPOINT OBJECT
 *
 * Google Cloud DiscoveryEngine
 *
 * This is the Vertex AI Builder search Request Body structure.
 *
 * @file src/Apis/v1alpha/projects/locations/collections/engines/servingConfigs/search.php
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/projects.locations.collections.engines.servingConfigs/search
 */

/**************************************************************
 *  projects.locations.collections.engines.servingconfigs.search
 **************************************************************/

namespace Drupal\bos_google_cloud\Apis\v1alpha\projects\locations\collections\engines\servingConfigs;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase;
use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsInterface;
use Drupal\bos_google_cloud\Apis\v1alpha\projects\locations\evaluations\SessionSpec;

class Search extends GcDiscoveryEngineObjectsBase implements GcDiscoveryEngineObjectsInterface {

  public function __construct() {
    $this->object = [
      "branch" => NULL,   // string
      "query" => NULL,    // string
      "imageQuery" => NULL,   // Drupal\bos_google_cloud\imageQuery
      "pageSize" => NULL,   // int
      "pageToken" => NULL,    // string
      "offset" => NULL,     // int
      "dataStoreSpecs" => NULL, // array of Drupal\bos_google_cloud\dataStoreSpec
      "filter" => NULL,   // string
      "canonicalFilter" => NULL,    // string
      "orderBy" => NULL,    // string
      "userInfo" => NULL,     // Drupal\bos_google_cloud\userInfo
      "languageCode" => NULL,   // string
      "regionCode" => NULL,   // string
      "facetSpecs" => NULL,   // array of Drupal\bos_google_cloud\facetSpec
      "boostSpec" => NULL,  // Drupal\bos_google_cloud\boostSpec
      "params" => NULL,
      "queryExpansionSpec" => NULL,   // Drupal\bos_google_cloud\queryExpansionSpec
      "spellCorrectionSpec" => NULL,    // Drupal\bos_google_cloud\spellCorrectionSpec
      "usePseudoId" => NULL,    // string

      // Must be NULL if Session provided
      "contentSearchSpec" => NULL,    // Drupal\bos_google_cloud\contentSearchSpec

      "embeddingSpec" => NULL,    // Drupal\bos_google_cloud\embeddingSpec
      "rankingExpression" => NULL,    // string
      "safeSearch" => NULL,   // bool
      "userLabels" => NULL,
      "naturalLanguageQueryUnderstandingSpec" => NULL,  // Drupal\bos_google_cloud\naturalLanguageQueryUnderstandingSpec
      "searchAsYouTypeSpec" => NULL,    // Drupal\bos_google_cloud\searchAsYouTypeSpec
      "customFineTuningSpec" => NULL,   // Drupal\bos_google_cloud\customFineTuningSpec

      // Must be null if contentSearchSpec.summarySpec provided
      "session" => NULL,    // string

      "sessionSpec" => NULL,    // Drupal\bos_google_cloud\sessionSpec
      "relevanceThreshold" => NULL,  // LOWEST, LOW, MEDIUM, HIGH, RELEVANCE_THRESHOLD_UNSPECIFIED
      "personalizationSpec" => NULL,    // Drupal\bos_google_cloud\personalizationSpec
    ];
  }

  /**
   * Set the session for a given project, engine, and other optional parameters.
   *
   * @param string $project_id The ID of the project.
   * @param string $engine The engine name.
   * @param string $location The location (default is "global").
   * @param string $collection The collection name (default is "default_collection").
   * @param string $session_id The conversation ID (default is "-").
   *
   * @return string The formatted session string.
   */
  public function setSession(string $project_id, string $engine,
    string $session_id = "-",
    string $location = "global",
    string $collection = "default_collection",
    ): GcDiscoveryEngineObjectsInterface {
    if (empty($session_id)){
      $session_id = "-";
    }
    $this->object["session"] = "projects/$project_id/locations/$location/collections/$collection/engines/$engine/sessions/$session_id";
    return $this;
  }

  /**
   * Sets the query details for the object.
   *
   * @param string $text The text of the query.
   * @param string $project_id The project ID associated with the query.
   * @param string $query_id The optional query ID, defaults to "-".
   * @param string $location The location for the query, defaults to "global".
   *
   * @return GcDiscoveryEngineObjectsInterface The current instance of the object.
   */
  public function setQuery(string $query_id , string $project_id,
    string $location = "global",
    int $searchResultPersistenceCount = 5
  ): GcDiscoveryEngineObjectsInterface {
    $this->object["sessionSpec"] = new SessionSpec([
      "queryId" => "projects/$project_id/locations/$location/questions/$query_id",
      "searchResultPersistenceCount" => $searchResultPersistenceCount,
    ]);
    return $this;
  }
}
