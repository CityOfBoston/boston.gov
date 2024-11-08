<?php

namespace Drupal\bos_google_cloud;

use Drupal;

class GcGenerationURL {

  public const CONVERSATION = 0;

  public const SEARCH = 1;
  public const PREDICTION = 2;
  public const DATASTORE = 4;
  public const PROJECT = 8;
  public const SEARCH_ANSWER = 16;
  public const ENGINE = 32;

  /**
   * Creates the URL for the endpoint based on the specified $type.
   *
   * @param int $type
   *   The query type - use a constant from this class.
   * @param array $options
   *   Array of options with at minimum:
   *   - endpoint (search)(conversation)(prediction)(datastore)(engine)(project)
   *   - endpoint_version (search)
   *   - location_id (search)(conversation)(prediction)(datastore)(engine)
   *   - project_id (search)(conversation)(prediction)(datastore)(engine)
   *   - engine_id (search)
   *   - model_id (prediction)
   *   - datastore_id (conversation)
   *
   * @return string|bool
   *   The correctly formatted URL for the endpoint.
   */
  public static function build(int $type, array $options):string|bool {

    switch ($type) {

      case self::SEARCH_ANSWER:
      case self::SEARCH:
        if (empty($options["endpoint"]) || empty($options["project_id"])
          || empty($options["location_id"]) || empty($options["engine_id"])
          || empty($options["endpoint_version"])) {
          return FALSE;
        }
        // Ensure the correct API endpoint version is used.
        $options["endpoint"] = "{$options["endpoint"]}/{$options["endpoint_version"]}";
        return self::buildSearch($options["endpoint"], $options["project_id"], $options["location_id"], $options["engine_id"], $type);

      case self::CONVERSATION:
        if (empty($options["endpoint"]) || empty($options["project_id"])
          || empty($options["location_id"]) || empty($options["datastore_id"])) {
          return FALSE;
        }
        return self::buildConversation($options["endpoint"], $options["project_id"], $options["location_id"], $options["datastore_id"]);

      case self::PREDICTION:
        if (empty($options["endpoint"]) || empty($options["project_id"])
          || empty($options["location_id"]) || empty($options["model_id"])) {
          return FALSE;
        }
        return self::buildPrediction($options["endpoint"], $options["project_id"], $options["location_id"], $options["model_id"]);

      case self::DATASTORE:
        if (empty($options["endpoint"]) || empty($options["project_id"])
          || empty($options["location_id"])) {
          return FALSE;
        }
        return self::buildDataStore($options["endpoint"], $options["project_id"], $options["location_id"]);

      case self::ENGINE:
        if (empty($options["endpoint"]) || empty($options["project_id"])
          || empty($options["location_id"])) {
          return FALSE;
        }
        return self::buildEngine($options["endpoint"], $options["project_id"], $options["location_id"]);

      case self::PROJECT:
        if (empty($options["endpoint"])) {
          return FALSE;
        }
        return self::buildProject($options["endpoint"]);

      default:
        return FALSE;
    }

  }

  /**
   * Produces the standardized URL/endpoint for the conversations:converse
   * endpoint.
   *
   * @param string $endpoint
   * @param string $project_id
   * @param string $location_id
   * @param string $datastore_id
   *
   * @return string
   *
   * @see https://cloud.google.com/generative-ai-app-builder/docs/apis
   * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/projects.locations.collections.dataStores.conversations
   * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1/projects.locations.collections.dataStores.conversations/converse
   */
  private static function buildConversation(string $endpoint, string $project_id, string $location_id, string $datastore_id): string {

    $url = $endpoint;
    $url .= "/v1alpha/projects/$project_id";
    $url .= "/locations/$location_id";
    $url .= "/collections/default_collection/dataStores/$datastore_id";
    $url .= "/conversations/-:converse";
    return $url;

  }

  /**
   * Produces the standardized URL/endpoint for the projects.locations.collections.engines.servingConfigs.search
   * endpoint.
   *
   * @param string $endpoint
   * @param string $project_id
   * @param string $location_id
   * @param string $engine_id
   *
   * @return string
   *
   * @see https://cloud.google.com/generative-ai-app-builder/docs/apis
   * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/projects.locations.collections.engines.servingConfigs
   * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/projects.locations.collections.engines.servingConfigs/search
   */
  private static function buildSearch(string $endpoint, string $project_id, string $location_id, string $engine_id, int $type): string {

    $url = $endpoint;
    $url .= "/projects/$project_id";
    $url .= "/locations/$location_id";
    $url .= "/collections/default_collection/engines/$engine_id";
    $url .= "/servingConfigs/default_search:";
    $url .= ($type == self::SEARCH ? "search" : "answer");
    return $url;

  }

  /**
   *  Produces the standardized URL/endpoint for the models:streamGenerateContent
   *  endpoint.
   *
   * @param string $endpoint
   * @param string $project_id
   * @param string $location_id
   * @param string $model_id
   *
   * @return string
   */
  private static function buildPrediction(string $endpoint, string $project_id, string $location_id, string $model_id): string {

    $url = $endpoint;
    $url .= "/v1/projects/$project_id";
    $url .= "/locations/$location_id";
    $url .= "/publishers/google/models/$model_id:streamGenerateContent";
    return $url;

  }

  /**
   *  Produces the standardized URL/endpoint to query DataStores
   *
   * @param string $endpoint
   * @param string $project_id
   * @param string $location_id
   *
   * @return string
   */
  private static function buildDataStore(string $endpoint, string $project_id, string $location_id): string {

    $url = $endpoint;
    $url .= "/v1/projects/$project_id";
    $url .= "/locations/$location_id";
    $url .= "/collections/default_collection/dataStores";
    return $url;

  }

  /**
   *  Produces the standardized URL/endpoint to query Engines
   *
   * @param string $endpoint
   * @param string $project_id
   * @param string $location_id
   *
   * @return string
   */
  private static function buildEngine(string $endpoint, string $project_id, string $location_id): string {

    $url = $endpoint;
    $url .= "/v1/projects/$project_id";
    $url .= "/locations/$location_id";
    $url .= "/collections/default_collection/engines";
    return $url;

  }

  /**
   *  Produces the standardized URL/endpoint to query projects
   *
   * @param string $endpoint
   *
   * @return string
   */
  private static function buildProject(string $endpoint): string {

    // For this to work the service account needs resourcemanager.projects.list
    // permission on the organization. Right now, this has not been granted.
    $url = "https://cloudresourcemanager.googleapis.com/v3/projects";
    return $url;

  }

  /**
   * Check to see if we think the API quota has been exceeded.
   *
   * @param int $type
   *
   * @return bool
   *
   * @throws \Exception
   * @see https://console.cloud.google.com/apis/api/discoveryengine.googleapis.com/quotas?project=vertex-ai-poc-406419
   * @see https://console.cloud.google.com/apis/api/aiplatform.googleapis.com/quotas?project=vertex-ai-poc-406419
   * @see https://console.cloud.google.com/iam-admin/quotas?project=vertex-ai-poc-406419
   */
  public static function quota_exceeded(int $type): bool {

    switch ($type) {
      case self::PREDICTION:
        // Current limits found here:
        // @see https://console.cloud.google.com/iam-admin/quotas?project=vertex-ai-poc-406419&pageState=(%22allQuotasTable%22:(%22f%22:%22%255B%257B_22k_22_3A_22Name_22_2C_22t_22_3A10_2C_22v_22_3A_22_5C_22Generate%2520content%2520requests%2520per%2520minute%2520per%2520project%2520per%2520base%2520model%2520per%2520minute%2520per%2520region%2520per%2520base_model_5C_22_22_2C_22s_22_3Atrue_2C_22i_22_3A_22displayName_22%257D_2C%257B_22k_22_3A_22_22_2C_22t_22_3A10_2C_22v_22_3A_22_5C_22region_3Aus-east4_5C_22_22_2C_22s_22_3Atrue%257D%255D%22))
        $name = "google_cloud.vertexai.useast4";
        $id = "streamGenerateContent";
        $max_requests = Drupal::config("bos_google_cloud.settings")
          ->get("vertex_ai.quota") ?? 10;   // # requests allowed in the window.
        break;

      case self::SEARCH:
      case self::CONVERSATION:
        // Current limits found here:
        // @see https://console.cloud.google.com/iam-admin/quotas?project=vertex-ai-poc-406419&pageState=(%22allQuotasTable%22:(%22f%22:%22%255B%257B_22k_22_3A_22Name_22_2C_22t_22_3A10_2C_22v_22_3A_22_5C_22Conversation%2520other%2520operations%2520per%2520minute_5C_22_22_2C_22s_22_3Atrue_2C_22i_22_3A_22displayName_22%257D_2C%257B_22k_22_3A_22_22_2C_22t_22_3A10_2C_22v_22_3A_22_5C_22OR_5C_22_22_2C_22o_22_3Atrue_2C_22s_22_3Atrue%257D_2C%257B_22k_22_3A_22Name_22_2C_22t_22_3A10_2C_22v_22_3A_22_5C_22Conversational%2520search%2520read%2520requests%2520per%2520minute_5C_22_22_2C_22s_22_3Atrue_2C_22i_22_3A_22displayName_22%257D%255D%22))
        $name = "google_cloud.discovery";
        $id = "converse";
        $max_requests = Drupal::config("bos_google_cloud.settings")
          ->get("discovery_engine.quota") ?? 300;   // # requests allowed in the window.
        break;

      default:
        // Unknown call type,
        Drupal::logger("bos_google_cloud")
          ->error("Unknown Payload type $type");
        return TRUE;
    }

    $flood_window = 60;  // window in seconds

    /**
     * @var \Drupal\Core\Flood\DatabaseBackend $flood
     */
    $flood = Drupal::service("flood");

    // NOTE: expired flood records in table flood in the database are cleared
    // automatically by cron.
    if ($flood->isAllowed($name, $max_requests, $flood_window, $id)) {
      return FALSE;
    }

    Drupal::logger("bos_google_cloud")
      ->warning("Quota limit of ($max_requests per $flood_window seconds) reached for $name:$id");
    return TRUE;

  }
}
