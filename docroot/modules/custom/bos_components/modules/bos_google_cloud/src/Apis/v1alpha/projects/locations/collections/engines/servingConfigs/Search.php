<?php

namespace Drupal\bos_google_cloud\Apis\v1alpha\projects\locations\collections\engines\servingConfigs;

/**
 * REQUEST API ENDPOINT OBJECT.
 *
 * Google Cloud DiscoveryEngine.
 * projects.locations.collections.engines.servingconfigs.search.
 *
 * This is the Vertex AI Builder search Request Body structure.
 *
 * @file src/Apis/v1alpha/projects/locations/collections/engines/servingConfigs/search.php
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/projects.locations.collections.engines.servingConfigs/search
 */

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase;
use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsInterface;
use Drupal\bos_google_cloud\Apis\v1alpha\projects\locations\evaluations\SessionSpec;

/**
 * Class Search.
 *
 * Class representing a DiscoveryEngine search configuration.
 */
class Search extends GcDiscoveryEngineObjectsBase implements GcDiscoveryEngineObjectsInterface {

  public function __construct() {
    $this->object = [
      // string.
      "branch" => NULL,
      // string.
      "query" => NULL,
      // Drupal\bos_google_cloud\imageQuery.
      "imageQuery" => NULL,
      // int.
      "pageSize" => NULL,
      // string.
      "pageToken" => NULL,
      // int.
      "offset" => NULL,
      // Array of Drupal\bos_google_cloud\dataStoreSpec.
      "dataStoreSpecs" => NULL,
      // string.
      "filter" => NULL,
      // string.
      "canonicalFilter" => NULL,
      // string.
      "orderBy" => NULL,
      // Drupal\bos_google_cloud\userInfo.
      "userInfo" => NULL,
      // string.
      "languageCode" => NULL,
      // string.
      "regionCode" => NULL,
      // Array of Drupal\bos_google_cloud\facetSpec.
      "facetSpecs" => NULL,
      // Drupal\bos_google_cloud\boostSpec.
      "boostSpec" => NULL,
      "params" => NULL,
      // Drupal\bos_google_cloud\queryExpansionSpec.
      "queryExpansionSpec" => NULL,
      // Drupal\bos_google_cloud\spellCorrectionSpec.
      "spellCorrectionSpec" => NULL,
      // string.
      "usePseudoId" => NULL,
      // Must be NULL if Session provided else
      // Drupal\bos_google_cloud\contentSearchSpec.
      "contentSearchSpec" => NULL,
      // Drupal\bos_google_cloud\embeddingSpec.
      "embeddingSpec" => NULL,
      // string.
      "rankingExpression" => NULL,
      // bool.
      "safeSearch" => NULL,
      "userLabels" => NULL,
      // Drupal\bos_google_cloud\naturalLanguageQueryUnderstandingSpec.
      "naturalLanguageQueryUnderstandingSpec" => NULL,
      // Drupal\bos_google_cloud\searchAsYouTypeSpec.
      "searchAsYouTypeSpec" => NULL,
      // Drupal\bos_google_cloud\customFineTuningSpec.
      "customFineTuningSpec" => NULL,
      // Must be NULL if contentSearchSpec.summarySpec provided else string.
      "session" => NULL,
      // Drupal\bos_google_cloud\sessionSpec.
      "sessionSpec" => NULL,
      // LOWEST, LOW, MEDIUM, HIGH, RELEVANCE_THRESHOLD_UNSPECIFIED.
      "relevanceThreshold" => NULL,
      // Drupal\bos_google_cloud\personalizationSpec.
      "personalizationSpec" => NULL,
    ];
  }

  /**
   * Set the session for a given project, engine, and other optional parameters.
   *
   * @param string $project_id
   *   The ID of the project.
   * @param string $engine
   *   The engine name.
   * @param string $session_id
   *   The conversation ID (default is "-").
   * @param string $location
   *   The location (default is "global").
   * @param string $collection
   *   The collection name (default is "default_collection").
   *
   * @return \Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase
   *   The formatted session string.
   */
  public function setSession(
    string $project_id,
    string $engine,
    string $session_id = "-",
    string $location = "global",
    string $collection = "default_collection",
  ): GcDiscoveryEngineObjectsInterface {
    if (empty($session_id)) {
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
  public function setQuery(
    string $query_id,
    string $project_id,
    string $location = "global",
    int $searchResultPersistenceCount = 5,
  ): GcDiscoveryEngineObjectsInterface {
    $this->object["sessionSpec"] = new SessionSpec([
      "queryId" => "projects/$project_id/locations/$location/questions/$query_id",
      "searchResultPersistenceCount" => $searchResultPersistenceCount,
    ]);
    return $this;
  }

}
