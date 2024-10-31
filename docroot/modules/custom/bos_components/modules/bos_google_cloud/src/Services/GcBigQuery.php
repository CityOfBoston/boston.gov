<?php

namespace Drupal\bos_google_cloud\Services;

use Drupal\bos_core\Controllers\Curl\BosCurlControllerBase;
use Exception;

// TODO: This module is mostly completed.  The authentication does not work for
//    some reason. I suspect that it's a permissions issue in GC, but it could
//    also be that the BigQuery app in the project needs a bit more setting up.
//
// This module was abandonned because we will not be writing to BigQuery from
// the AISearcn (bos_search) module at this time (Oct 2024).
// I have left it in because we may revisit this decision later, or in a
// different app.
//
// @see Drupal\webform\WebformSubmissionInterface\GcBigQueryHandler for example
// implementation.

/**
 * GcBigQuery class to handle operations related to Google Cloud BigQuery.
 *
 * It includes methods for querying data, handling datasets, and managing table
 * operations within the BigQuery environment.
 * This class is designed to simplify the interaction with the Google Cloud
 * BigQuery API.
 */
class GcBigQuery extends BosCurlControllerBase {

  /**
   * Google Cloud Authenication Service.
   *
   * @var GcAuthenticator
   */
  protected GcAuthenticator $authenticator;

  /**
   * Configuration array for the connection string.
   *
   * @var array
   *
   * Contains the following keys:
   * - account: The service account for credentials.
   * - project: The project identifier.
   * - dataset: The dataset identifier.
   */
  private array $connectionString = [
    'account' => "",
    'project' => "",
    'dataset' => "",
  ];

  public function __construct(string $service_account, string $project, string $dataset) {
    $this->connectionString["account"] = $service_account;
    $this->connectionString["project"] = $project;
    $this->connectionString["dataset"] = $dataset;
    parent::__construct();
  }

  /**
   * Inserts multiple records into a specified BigQuery table.
   *
   * @param string $table
   *   The name of the BigQuery table where the records will be inserted.
   * @param array $records
   *   An array of associative arrays representing the records to be inserted.
   * @param int $retry
   *   (Optional) The retry attempt counter. Defaults to 0.
   *
   * @return bool
   *   Returns TRUE if the insertion is successful.
   *
   * @throws \Exception
   *   If any error occurs during the insertion process.
   */
  public function insertAll(string $table, array $records, int $retry = 0): bool {

    try {
      $headers = [];
      $headers = array_merge($headers, $this->authenticate());
      $url = $this->buildUrl("insertAll", $table);
      $payload = $this->buildPayload("insertAll", $records);

      $results = $this->post($url, $payload, $headers);

      if ($this->error()) {
        throw new Exception($this->error());
      }
      elseif (!$results) {
        $this->error = "Post Failed: Code:{$this->response["http_code"]}";
        throw new Exception($this->error());
      }
      elseif ($this->http_code() == 401) {
        if (empty($retry)) {
          // The token is invalid, because we are caching for the lifetime of
          // the token, this probably means it has been refreshed elsewhere.
          $this->authenticator->invalidateAuthToken($this->connectionString["account"]);
          $this->insertAll($table, $records, 1);
        }
        $this->error = "Could not Authenticate.";
        throw new Exception($this->error());
      }
      elseif ($this->http_code() == 403) {
        $this->error = "No permission.";
        throw new Exception($this->error());
      }
      elseif ($this->http_code() == 200) {
        return TRUE;
      }
      else {
        $this->error = "Unknown Error: code:{$this->response["http_code"]}";
        throw new Exception($this->error());
      }

    }
    catch (Exception $e) {
      throw new Exception($e->getMessage());
    }
  }

  /**
   * Authenticates the user and retrieves the authorization token.
   *
   * @return array
   *   An associative array containing the authorization token with the key
   *   'Authorization'.
   *
   * @throws \Exception
   *   If there is an error obtaining the access token.
   */
  private function authenticate(): array {
    // Get token.
    try {
      $this->authenticator = new GcAuthenticator();
      return [
        "Authorization" => $this->authenticator
          ->getAccessToken($this->connectionString["account"], "Bearer"),
      ];
    }
    catch (Exception $e) {
      $this->error = $e->getMessage();
      throw new Exception("Error getting access token.");
    }
  }

  /**
   * Constructs a Google Cloud BigQuery URL based on the provided parameters.
   *
   * @param string $action
   *   The specific action endpoint to be performed (e.g., 'insert', 'query').
   * @param string $table
   *   The name of the table within the dataset.
   * @param array $connection_string
   *   An associative array containing the connection details, specifically
   *   keys for 'project' and 'dataset'. Defaults to class connectionString.
   *
   * @return string
   *   The constructed URL for the specified BigQuery operation.
   *
   * @throws \Exception
   *   If any of the required connection details or parameters are missing.
   */
  private function buildUrl(string $action, string $table, array $connection_string = []):string {
    $project = $connection_string["project"] ?: ($this->connectionString["project"] ?: NULL);
    $dataset = $connection_string["dataset"] ?: ($this->connectionString["dataset"] ?: NULL);
    if ($project && $dataset && $table && $action) {
      return "https://bigquery.googleapis.com/bigquery/v2/projects/$project/datasets/$dataset/tables/$table/$action";
    }
    throw new Exception("Missing connection detail/s: Unable to build GC Big Query url");
  }

  /**
   * Constructs the payload based on the provided action and records.
   *
   * @param string $action
   *   The action to be performed (e.g., 'insert', 'update').
   * @param array $records
   *   An array of records that form the payload.
   *
   * @return array
   *   The constructed payload.
   */
  private function buildPayload(string $action, array $records = []):array {
    switch ($action) {
      case "insertAll":
      default:
        return $records;
    }
  }

}
