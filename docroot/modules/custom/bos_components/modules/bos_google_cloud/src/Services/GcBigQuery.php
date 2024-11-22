<?php

namespace Drupal\bos_google_cloud\Services;

use Drupal\bos_core\Controllers\Curl\BosCurlControllerBase;
use Exception;
use Google\Cloud\BigQuery\BigQueryClient;
use Google\Cloud\BigQuery\QueryResults;

// @see Drupal\webform\WebformSubmissionInterface\GcBigQueryHandler for example
// implementation.
// @see https://developers.google.com/analytics/devguides/collection/protocol/ga4
// @see https://developers.google.com/analytics/devguides/collection/protocol/ga4/sending-events?client_type=firebase
// for measurement protocol (GA).
/**
 * GcBigQuery class to handle operations related to Google Cloud BigQuery.
 *
 * It includes methods for querying data, handling datasets, and managing table
 * operations within the BigQuery environment.
 * This class is designed to simplify the interaction with the Google Cloud
 * BigQuery API.
 */
class GcBigQuery extends BosCurlControllerBase {

  private const INSERT_ROWS_SINGLE = FALSE;
  private const INSERT_ROWS_MULTIPLE = TRUE;

  /**
   * Google Cloud Authenication Service.
   *
   * @var GcAuthenticator
   */
  protected GcAuthenticator $authenticator;

  /**
   * Configuration: The service account for credentials.
   *
   * @var string
   */
  private string $account = "";

  /**
   * Configuration: The project identifier.
   *
   * @var string
   */
  private string $project = "";

  /**
   * Configuration: The dataset identifier.
   *
   * @var string
   */
  private string $dataset = "";

  public function __construct(int $service_account, string $project, string $dataset) {
    $this->account = GcAuthenticator::SVS_ACCOUNT_LIST[$service_account];
    $this->project = $project;
    $this->dataset = $dataset;
    // Initialize authentication. Create an evnvar in this session with creds.
    $this->authenticator = new GcAuthenticator($this->account, GcAuthenticator::SCOPE_BIG_QUERY);
    // Initialize CuRL services.
    parent::__construct();
  }

  /**
   * Switches Service Account.
   *
   * Note: Causes re-authentication.
   *
   * @param string|int $service_account
   *   The service account to switch to.
   *
   * @return \Drupal\bos_google_cloud\Services\GcBigQuery
   *   This class.
   */
  public function setSvsAccount(string|int $service_account):GcBigQuery {

    try {
      if (is_numeric($service_account)) {
        $service_account = GcAuthenticator::SVS_ACCOUNT_LIST[$service_account];
      }

      if ($this->authenticator = new GcAuthenticator($service_account, GcAuthenticator::SCOPE_BIG_QUERY)) {
        $this->account = $service_account;
      }
      else {
        $this->error = "Could not switch service account.";
      }
      return $this;

    }
    catch (Exception $e) {
      return $this;
    }

  }

  /**
   * Switch projects.
   *
   * **Note**: Take care that the $service account has the correct permissions
   * on this new project.
   *
   * @param string $project
   *   The project to switch to.
   *
   * @return \Drupal\bos_google_cloud\Services\GcBigQuery
   *   This class.
   */
  public function setProject(string $project): GcBigQuery {
    $this->project = $project;
    return $this;
  }

  /**
   * Switch dataset.
   *
   * @param string $dataset
   *   The datsset to switch to.
   *
   * @return \Drupal\bos_google_cloud\Services\GcBigQuery
   *   This class.
   */
  public function setDataset(string $dataset) {
    $this->dataset = $dataset;
    return $this;
  }

  /**
   * Inserts a record into a specified BigQuery table.
   *
   * @param string $table
   *   The name of the BigQuery table where the record will be inserted.
   * @param array $record
   *   An associative array representing the record to be inserted.
   *
   * @return bool
   *   Returns TRUE if the insertion is successful.
   *
   * @throws \Exception
   *   If any error occurs during the insertion process.
   */
  public function insertRow(string $table, array $record): bool {
    return $this->dataInsert($table, $record, self::INSERT_ROWS_SINGLE);
  }

  /**
   * Inserts multiple records into a specified BigQuery table.
   *
   * @param string $table
   *   The name of the BigQuery table where the records will be inserted.
   * @param array $records
   *   An array of associative arrays representing the records to be inserted.
   *
   * @return bool
   *   Returns TRUE if the insertion is successful.
   *
   * @throws \Exception
   *   If any error occurs during the insertion process.
   */
  public function insertRows(string $table, array $records): bool {
    return $this->dataInsert($table, $records, self::INSERT_ROWS_MULTIPLE);
  }

  /**
   * Inserts one or multiple records into a specified BigQuery table.
   *
   * @param string $table
   *   The name of the BigQuery table where the record(s) will be inserted.
   * @param array $records
   *   An associative array representing the record(s) to be inserted.
   * @param bool $multiple
   *   A flag indicating whether to insert multiple records at once.
   *
   * @return bool
   *   Returns TRUE if the insertion is successful.
   *
   * @throws \Exception
   *   If any error occurs during the insertion process.
   */
  private function dataInsert(string $tablename, array $records, bool $multiple): bool {

    try {

      $bigQuery = new BigQueryClient([
        'projectId' => $this->project,
      ]);
      $dataset = $bigQuery->dataset($this->dataset);
      $table = $dataset->table($tablename);

      if ($multiple) {
        $rows = [["data" => $records]];
      }
      else {
        $rows = [["data" => $records]];
      }

      $response = $table->insertRows($rows, []);

      if ($response->isSuccessful()) {
        return TRUE;
      }

      foreach ($response->failedRows() as $row) {
        foreach ($row['errors'] as $error) {
          $this->error = $error['reason'] . ': ' . $error['message'] . PHP_EOL;
        }
      }
      throw new Exception($this->error());

    }
    catch (Exception $e) {
      throw new Exception($e->getMessage());
    }

  }

  /**
   * Run a generic BQ SQL query.
   *
   * Note the dataset needs to be specified in the SQL.
   *
   * @param string $query
   *   The query to be executed.
   *
   * @return \Google\Cloud\BigQuery\QueryResults
   *   Results output from the BQ Client.
   *
   * @see https://cloud.google.com/bigquery/docs/reference/rest/v2/jobs/getQueryResults#response-body
   *
   * @throws \Exception
   */
  public function runQuery(string $query): QueryResults {
    try {
      $bigQuery = new BigQueryClient([
        'projectId' => $this->project,
      ]);

      /**
       * @var \Google\Cloud\BigQuery\JobConfigurationInterface $jobConfiguration
       */
      $jobConfiguration = $bigQuery->query($query);

      /**
       * @var \Google\Cloud\BigQuery\QueryResults $response
       */
      $response = $bigQuery->runQuery($jobConfiguration);

      if (count($response->info()["errors"]) > 0) {
        $error = implode(": ", $response->info()["errors"]);
        throw new Exception("BigQuery Error: $error");
      }
      return $response;
    }
    catch (Exception $e) {
      throw new Exception($e->getMessage());
    }
  }

  /**
   * Generates the body of a GA4 event for tracking Google Analytics events.
   *
   * @return array
   *   Returns an associative array representing the GA4 event body, including
   *   details about the event, user, device, user properties, geographic
   *   information, and traffic sources.
   */
  public function getGa4EventBody(): array {
    // This is the basic structure of the BigQuery event table used to track
    // Google Analytics events.
    // @see schema();

    /**
     * @var \Drupal\bos_user_info\Services\UserAgentAnalysis $detector
     */
    $ua_detector = \Drupal::service('bos_user_info.user_agent');
    $ua = $ua_detector->read();
    /**
     * @var \Drupal\bos_user_info\Services\IpAddressScanner $ip_detector
     */
    $ip_detector = \Drupal::service('bos_user_info.ip_address_scanner');
    $ip = $ip_detector->read();

    $output = [
      "event_date" => date_format(new \DateTime(), 'Ymd'),
      "event_timestamp" => intval(microtime(TRUE) * 1000000),
      "event_params" => [
        [
          "key" => "handler",
          "value" => [
            "string_value" => "Drupal.GcBigQuery",
          ],
        ],
        [
          "key" => 'page_referrer',
          "value" => [
            "string_value" => \Drupal::requestStack()->getMainRequest()->headers->get("referer"),
          ],
        ],
      ],
      "user_id" => session_id(),
      "user_pseudo_id" => session_id(),
    ];

    $output["device"] = [
      "category" => $ua["device_name"],
      "mobile_brand_name" => $ua["brand_name"],
      "operating_system" => $ua["os"]["name"],
      "language" => "en-us",
      "vendor_id" => $ua["model"],
      "browser" => NULL,
      "browser_version" => NULL,
      "web_info" => [
        "browser" => $ua["client"]["name"],
        "browser_version" => $ua["client"]["version"],
        "hostname" => "www.boston.gov",
      ],
    ];
    $output["user_properties"] = [
      [
        "key" => "IPAddress",
        "value" => [
          "string_value" => $ip["ipaddress"],
        ],
      ],
    ];
    $output["geo"] = [
      "city" => $ip["city"],
      "country" => $ip["country"],
      "continent" => $ip["continent"],
      "metro" => $ip["metro"],
    ];
    $output["traffic_source"] = [
      "name" => "(direct)",
      "medium" => "(none)",
      "source" => "(direct)",
    ];

    return $output;

  }

  /**
   * Creates a new table schema in the specified dataset.
   *
   * @param string $dataset
   *   The name of the dataset where the table schema will be created.
   *
   * @return bool
   *   Indicates if table was created.
   */
  public function schema($dataset): bool {
    $sql = "CREATE TABLE {$dataset}.events (
      event_date STRING,
      event_timestamp INT64,
      event_name STRING,
      event_params ARRAY<STRUCT<
        key STRING,
        value STRUCT<
          string_value STRING,
          int_value INT64
        >
      >>,
      user_id STRING,
      user_pseudo_id STRING,
      user_properties ARRAY<STRUCT<
        key STRING,
        value STRUCT<
          string_value STRING,
          int_value INT64,
          set_timestamp_micros INT64
        >
      >>,
      device STRUCT<
          category STRING,
          mobile_brand_name STRING,
          operating_system STRING,
          vendor_id STRING,
          language STRING,
          browser STRING,
          browser_version STRING,
          web_info STRUCT<
            browser STRING,
            browser_version STRING,
            hostname STRING
            >
        >,
      geo STRUCT<
          city STRING,
          country STRING,
          continent STRING,
          region STRING,
          sub_continent STRING,
          metro STRING
        >,
      traffic_source STRUCT<
          name STRING,
          medium STRING,
          source STRING
        >,
    );";
    try {
      $this->runQuery($sql);
      return TRUE;
    }
    catch (Exception $e) {
      return FALSE;
    }

  }

}
