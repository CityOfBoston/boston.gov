<?php

namespace Drupal\bos_mnl\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Cache\CacheableJsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * MNLRest class for endpoint.
 *
 * All POSTS to endpoint must have format:
 *   /rest/mnl/YYY?api_key=XXX
 *   Where YYYY is one of 'update', 'import' or 'manual'.
 *   Where api_key XXX is the token defined at /admin/config/services/mnl.
 *
 * Note: 'import' and 'update' endpoint usage must include a json string in the
 *   body in the format:
 *   [
 *     {"sam_address_id":123,"full_address":"address","data": jsonstring"},
 *     {"sam_address_id":123,"full_address":"address","data": jsonstring"}
 *   ]
 *   The 'jsonstring' must be a valid json object in string form and can contain
 *   any data provided it represents a single record and can be decoded by
 *   PHP's json_decode function.
 *
 * Note: provide a querystring for "manual" endpoint in format:
 *   ?api_key=XXXXX&path=/path-on-server/file.json&limit=N&mode=import
 *   Required:
 *     'path' is path on remote server (or local container) to import file.
 *   Optional:
 *     'limit' process the first N records in the import file (defaults all).
 *     'mode' can be either 'import' or 'update' (defaults to 'update').
 *
 * Note:
 *  - UPDATE updates "full address" and "data" for SAM records in DB with
 *    "full address" and "data" from records with a matchin "sam_address_id"
 *    from the json payload. New records will be created if an existing
 *    "sam_address_id" is not found in the DB. If there are duplicate
 *    "sam_address_id"s in the import, only the first of the duplicate records
 *    will be processed. No records will be deleted or marked for deletion.
 *  - IMPORT also updates "full address" and "data" for SAM records in DB with
 *    "full address" and "data" from records with a matchin "sam_address_id"
 *    from the json payload. New records will also be created if an existing
 *    "sam_address_id" is not found in the DB. If there are duplicate
 *    "sam_address_id"s in the import, only the first of the duplicate records
 *    will be processed.  If the database contains a record (ie. a
 *    "sam_address_id") which is not in the import file, then after the import
 *    completes, those "orphaned" records will be cleaned, by removing them
 *    from the database.
 *
 * Note: Actual updating and deleting (when done) will be handled by queues that
 *   are processed overnight by a scheduled task, so the endpoint only queues
 *   tasks for later execution, it does not actually change data itself.
 *
 * Benchmarks 16 May 2020:
 *   - Acquia/AWS Uploaded 1.4GB file with 400,000 records in 12 secs.
 *   - Drupal: 400,000 records queued in less than 00:03:30 (3.5 mins).
 *   - mnl_import queue processes (local) so acquia will be 5-10x faster:
 *        - processed 395,037 records with 5,000 updates in 10 mins
 *        - processed 395,037 records with 395,037 updates in 15 mins
 *        - processed 395,037 records with 395,037 inserts (new nodes) - 58 min
 *        - processed 50 deletes in 10sec
 */
class MNLRest extends ControllerBase {

  /* Civis API Key
  https://api.civisanalytics.com
  z_pUmFpEyRm1h_qRBOq2ZFc8oxOkh9LoyyCe1MnqAGw
  */

  /**
   * Class var.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  public $request;

  private $stats = [];

  /**
   * Public construct for Request.
   */
  public function __construct(RequestStack $request) {
    $this->request = $request;
    $this->stats = [
      "starttime" => strtotime("now"),
      "count" => 0,
      "duplicates" => 0,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
      // Load the service required to construct this class.
      $container->get('request_stack')
    );
  }

  /**
   * Load the payload from the rest endpoint into the appropriate queue.
   *
   * @param string $operation
   *   The operation from the endpoint call.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   A json response to send back to the caller.
   */
  public function beginUpdateImport(string $operation = "") {
    ini_set('memory_limit', '-1');
    ini_set("max_execution_time", "10800");

    // Validate the key and token.
    $apiKey = $this->request->getCurrentRequest()->get('api_key');
    $token = \Drupal::config("bos_mnl.settings")->get("auth_token");
    if ($apiKey !== $token || $apiKey == NULL) {
      return new CacheableJsonResponse([
        'status' => 'error',
        'response' => 'Could not authenticate',
      ], 401);
    }

    // Validate this is a POST request.
    if ($this->request->getCurrentRequest()->getMethod() != "POST") {
      return new CacheableJsonResponse([
        'status' => 'error',
        'response' => 'request must be POST',
      ], 405);
    }

    switch ($operation) {
      case "manual":
        $path = \Drupal::request()->get("path", FALSE);
        $limit = \Drupal::request()->get("limit", FALSE);
        $operation = (\Drupal::request()->get("mode", FALSE) ?: "update");
        if ($path && file_exists($path)) {
          $payload = file_get_contents($path);
          $payload = json_decode($payload);
          if ($limit) {
            $payload = array_slice($payload, 0, $limit);
          }
          $this->queuePayload("mnl_${operation}", $payload);
          return new CacheableJsonResponse([
            'status' => $operation . ' complete - ' . count($payload) . ' items queued',
            'response' => 'authorized'
          ], 200);        }
        else {
          return new CacheableJsonResponse([
            'status' => 'error',
            'response' => 'file not found at path',
          ], 400);
        }

      case "update":
      case "import":
        $payload = $this->request->getCurrentRequest()->getContent();
        $payload = json_decode(strip_tags($payload), TRUE);

        if (empty($payload)) {
          return new CacheableJsonResponse([
            'status' => 'Require a payload.',
            'response' => 'error'
          ], 400);
        }

        try {
          $this->queuePayload("mnl_${operation}", $payload);
        }
        catch (Exception $e) {
          return new CacheableJsonResponse([
            'status' => $e->getMessage(),
            'response' => 'error'
          ], 400);
        }
        return new CacheableJsonResponse([
          'status' => $operation . ' complete - ' . count($payload) . ' items queued',
          'response' => 'success'
        ], 200);

      case "purge":
        $cutoff = \Drupal::request()->get("purgedate", FALSE);
        try {
          $count = MnlUtilities::MnlCleanUp($cutoff);
          if ($count == 0) {
            return new CacheableJsonResponse([
              'status' => "There are no SAM records matching purge filter (last updated < ${cutoff}).",
              'response' => 'success'
            ], 200);
          }
          else {
            return new CacheableJsonResponse([
              'status' => "Purged ${count} old SAM records.",
              'response' => 'success'
            ], 200);
          }
        }
        catch (\Exception $e) {
          return new CacheableJsonResponse([
            'status' => $e->getMessage(),
            'response' => 'error'
          ], 400);
        }

      default:
        return new CacheableJsonResponse([
          'status' => 'error',
          'response' => 'unknown endpoint requested',
        ], 403);
    }

  }

  /**
   * Add payload to queue.
   *
   * @param string $queue_name The queue to append to.
   * @param array|object $data The records to queue.
   *
   * @return void
   */
  private function queuePayload(string $queue_name, mixed $data): void {
    $queue = \Drupal::queue($queue_name);

    $this->setTerminator($queue_name, $data);

    foreach ($data as $item) {
      // Add item to queue.
      $queue->createItem($item);
      $this->stats["count"]++;
    }

    $log_entry = "
        <b>Date: " . date("Y-m-d H:i:s", strtotime("now")) . " (EST)</b><br>
        <b>Queue</b>: ". $queue_name . "<br>
        <b>Received</b>: " . number_format(count($data), 0) . " SAM ID's (records)<br>
        <b>Processed</b>: " . number_format($this->stats["count"], 0) . " SAM ID's<br>
        <b>Queue Size</b>: ". number_format($queue->numberOfItems(), 0) . " at end <br>
        <b>Duration</b>: " . gmdate("H:i:s", strtotime("now") - $this->stats["starttime"]);

    \Drupal::logger("bos_mnl")->info($log_entry);

    $this->doLog($log_entry, $queue_name);

  }

  /**
   * Rotate the log elements and write a new log entry into the settings file.
   *   (for display on settings form)
   *
   * @param $log_message string The message to write.
   * @param $queue_name string Queue name being loaded.
   *
   * @return void
   */
  private function doLog($log_message, $queue_name) {
    $config_field = "last_inbound_{$queue_name}";
    $config = \Drupal::configFactory()->getEditable('bos_mnl.settings');
    if ($config) {
      if ($config->get("{$config_field}_2") ?: FALSE) {
        $config->set("{$config_field}_3", $config->get("{$config_field}_2"))->save();
      }
      if ($config->get("{$config_field}_1") ?: FALSE) {
        $config->set("{$config_field}_2", $config->get("{$config_field}_1"))->save();
      }
      if ($config->get($config_field) ?: FALSE) {
        $config->set("{$config_field}_1", $config->get($config_field))->save();
      }
    }
    $config->set($config_field, $log_message)->save();
  }

  /**
   * If the record is the terminator record, then as well a queueing, we want
   * to set a flag that the import is completed.
   *
   * @param $item
   *
   * @return void
   */
  private function setTerminator($queue_name, $item) {
    if ($settings = \Drupal::configFactory()->getEditable('bos_mnl.settings')) {
      if (count($item) == 1 && !empty($item["status"]) && $item["status"] == "complete!") {
        $settings->set("{$queue_name}_import_status", MnlUtilities::MNL_IMPORT_READY)
        ->save();
      }
      elseif (count($item) != 1) {
        $settings->set("{$queue_name}_import_status", MnlUtilities::MNL_IMPORT_IMPORTING)
        ->save();
      }
    }

  }

}
