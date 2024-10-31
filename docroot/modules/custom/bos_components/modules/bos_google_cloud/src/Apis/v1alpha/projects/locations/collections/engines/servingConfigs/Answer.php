<?php

/**
 * REQUEST API ENDPOINT OBJECT
 *
 * Google Cloud DiscoveryEngine
 *
 * This is the Vertex AI Builder answer query method.
 *
 * @file src/Apis/v1alpha/projects/locations/collections/engines/servingConfigs/answer.php
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/projects.locations.collections.engines.servingConfigs/answer
 */

/**************************************************************
 *  projects.locations.collections.engines.servingconfigs.answer
 **************************************************************/

namespace Drupal\bos_google_cloud\Apis\v1alpha\projects\locations\collections\engines\servingConfigs;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase;
use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsInterface;
use Drupal\bos_google_cloud\Apis\v1alpha\projects\locations\collections\dataStores\sessions\Query;

class Answer extends GcDiscoveryEngineObjectsBase implements GcDiscoveryEngineObjectsInterface {

  public function __construct() {
    $this->object = [
      "query" => NULL,    // object Query
      "session" => NULL,    // string
      "safetyspec" => NULL,    // object SafetySpec
      "relatedQuestionsSpec" => NULL,    // object RelatedQuestionsSpec
      "answerGenerationSpec" => NULL,    // object AnswerGenerationSpec
      "searchSpec" => NULL,    // object SearchSpec
      "queryUnderstandingSpec" => NULL,    // object QueryUnderstandingSpec
      "asynchronousMode" => NULL,    // boolean
      "usePseudoId" => NULL,    // string
      "userLabels" => NULL,   // array of strings
    ];
  }

  /**
   * Set the session for a given project, engine, and other optional parameters.
   *
   * @param string $project_id The ID of the project.
   * @param string $engine The engine name.
   * @param string $location The location (default is "global").
   * @param string $collection The collection name (default is "default_collection").
   * @param string $session_id The session ID (default is "-").
   *
   * @return string The formatted session string.
   */
  public function setSession(string $project_id, string $engine,
    string $session_id = "-",
    string $location = "global",
    string $collection = "default_collection",
    ): GcDiscoveryEngineObjectsInterface {
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
  public function setQuery(string $text, string $query_id , string $project_id,
    string $location = "global",
    ): GcDiscoveryEngineObjectsInterface {
    $this->object["query"] = new Query([
      "text" => $text,
      "queryId" => "projects/$project_id/locations/$location/questions/$query_id"
    ]);
    return $this;
  }

}
