<?php

namespace Drupal\bos_mnl\Plugin\QueueWorker;

use Drupal\bos_mnl\Controller\MnlUtilities;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\salesforce\Exception;

/**
 * Processes incremental update of nodes from queue.
 *
 * @QueueWorker(
 *   id = "mnl_update",
 *   title = @Translation("MNL Updates records / nodes."),
 * )
 */
class MNLProcessUpdate extends QueueWorkerBase {

  /**
   * Cache the queue object.
   *
   * @var \Drupal\Core\Queue\DatabaseQueue
   */
  private $queue;

  /**
   * Array of SAM objects from the database.
   *
   * @var array
   */
  private $mnl_cache;

  /**
   * Keeps queue statistics.
   *
   * @var array
   */
  private $stats = [
    "cache" => 0,
    "pre-entities" => 0,
    "post-entities" => 0,
    "processed" => 0,
    "updated" => 0,
    "inserted" => 0,
    "unchanged" => 0,
    "baddata" => 0,
    "duplicateSAM" => 0,
    "duplicateNID" => 0,
    "queue" => NULL,
    "starttime" => NULL,
  ];

  /**
   * Property indicating if a purger invalidation queue exists
   *
   * @var bool
   */
  private $purger = FALSE;

  /**
   * Reference the mnl.settings config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  private $settings;

  private $plugin_id;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {

    ini_set('memory_limit', '-1');
    ini_set("max_execution_time", "0");

    $this->plugin_id = $plugin_id;
    $this->queue = \Drupal::queue($plugin_id);
    $this->settings = \Drupal::configFactory()->getEditable('bos_mnl.settings');

    // If the queue is not empty, then prepare to process the queue
    if ($this->queue->numberOfItems() > 0) {

      $this->stats["queue"] = $this->queue->numberOfItems();
      $this->stats["starttime"] = strtotime("now");

      if ($this->settings->get("{$plugin_id}_import_status") == MnlUtilities::MNL_IMPORT_READY) {

        if ($this->settings->get("mnl_import_import_status") == MnlUtilities::MNL_IMPORT_PROCESSING) {
          // The other queue is processing, so we will not start this one at this time.
          \Drupal::logger("bos_mnl")->info("Wait for import queue to finish being processed");
          throw new \Exception("Wait for import queue to finish being processed");
        }

        // Queue is ready, so mark it as now being processed.
        $this->settings->set("{$plugin_id}_import_status", MnlUtilities::MNL_IMPORT_PROCESSING);
        $this->settings->set("{$plugin_id}_flag", strtotime("now"));
        \Drupal::logger("bos_mnl")->info("MNL Update Processor Starts");

        // Load and save a fresh set of stats.
        $query = \Drupal::database()->select("node", "n")
          ->condition("n.type", "neighborhood_lookup");
        $query->addExpression("count(n.nid)", "count");
        $result = $query->execute()->fetch();
        $this->stats["pre-entities"] = $result ? $result->count : 0;
        $this->settings->set('tmp_update', json_encode($this->stats));
        $this->settings->save();
      }
      elseif ($this->settings->get("{$plugin_id}_import_status") == MnlUtilities::MNL_IMPORT_PROCESSING) {
        // Check we are not already running an instance of this process
        if ($this->settings->get("{$plugin_id}_flag") == 0) {
          $this->settings->set("{$plugin_id}_flag", strtotime("now"));
        }
        else {
          if ((strtotime("now") - $this->settings->get("{$plugin_id}_flag") > (60 * 60))) {
            // flag was set more than an hour ago. Probably not still running.
            $this->settings->set("{$plugin_id}_flag", strtotime("now"));
          }
          else {
            // A process is still running, throw an exception to stop this
            // from starting a new process.
            throw new \Exception("Queue already being processed");
          }
        }
        // There are some stats that have been retained (persisted) from a
        // previous process of this queue.
        $this->stats = json_decode($this->settings->get('tmp_update'), TRUE);
      }

      // Determine if a purger service is installed
      $this->purger = \Drupal::hasService('purge.queue');

      // Fetch and load the mnl_cache.
      try {
        $this->mnl_cache = MnlUtilities::MnlCacheExistingSamIds();
        $this->stats['cache'] = count($this->mnl_cache);
      }
      catch (\Exception $e) {
        MnlUtilities::MnlBadData("Error building cache: {$e->getMessage()}");
      }

    }

    parent::__construct($configuration, $plugin_id, $plugin_definition);

  }

  /**
   * {@inheritdoc}
   */
  public function __destruct() {

    // Reset running flag - get this done first in case of errors.
    $this->settings->set("{$this->plugin_id}_flag", 0)->save();

    $status = $this->settings->get("{$this->plugin_id}_import_status");

    if (!empty($this->stats["processed"])) {
      if ($this->purger) {
        // We processed some records, purge is enabled, and we will have added
        // records to the purge queue.
        // Process the purge queue otherwise we risk queue overflow issues.
        MnlUtilities::MnlProcessPurgeQueue();
      }
      \Drupal::logger("bos_mnl")
        ->info("Queue: MNL Update queue worker terminates.");
    }

    if ($status == MnlUtilities::MNL_IMPORT_PROCESSING) {

      if ($this->queue->numberOfItems() == 0) {

        // The queue is now empty.
        $this->_finishProcessing(FALSE);
      }

      else {
        // If the queue is not fully processed, then persist the stats
        $this->settings->set('tmp_update', json_encode($this->stats))->save();
      }
    }
  }

  /**
   * Update node.
   *
   * @param mixed $existing_record
   *   The neighborhood lookup node.
   * @param array $new_record
   *   The import data object.
   */
  private function updateNode($existing_record, array $new_record) {

    try {
      $data_sam_record = json_encode($new_record["data"]);
      $data_sam_hash = hash("md5", $data_sam_record);
    }
    catch (\Exception $e) {
      $data_sam_record = NULL;
    }

    if (empty($data_sam_record)) {
      MnlUtilities::MnlBadData("Update Node: Bad Json or missing json.");
    }

    $cache_sam_hash = $existing_record->field_checksum_value ?: "";
    $data_sam_address = $new_record["full_address"];
    $cache_sam_address = $existing_record->field_sam_address_value;

    if ($data_sam_hash != $cache_sam_hash || $data_sam_address != $cache_sam_address) {

      if ($data_sam_hash != $cache_sam_hash) {
        // The SAM data has changed - update data and checksum.
        MnlUtilities::MnlUpdateSamData($existing_record->nid, $data_sam_record, $data_sam_hash);
      }

      if ($data_sam_address != $cache_sam_address) {
        // The SAM Address has changed, update the address
        MnlUtilities::MnlUpdateSamAddress($existing_record->nid, $data_sam_address);
      }

      // Something changed, so update the lastupdated record.
      MnlUtilities::MnlUpdateSamDate($existing_record->nid);

      // Force a save to invalidate the Drupal cache for this node.
      if ($this->purger) {
        MnlUtilities::MnlQueueInvalidation("node", $existing_record->nid);
      }

      $this->stats["updated"]++;

    }
    else {
      $this->stats["unchanged"]++;
    }

  }

  /**
   * Uses a Drupal entity manipulation to update the database.
   * Is a mirror of the functionality in UpdateNode.
   * Benchmarks show (without purger):
   *  - UpdateNode: 500 records ~= 4 sec
   *  - UpdateNodeEntity: 500 records ~= 30sec
   *
   * @param $existing_record
   * @param array $new_record
   *
   * @return void
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function updateNodeEntity(&$existing_record, array $new_record) {

    try {
      $data_sam_record = json_encode($new_record["data"]);
      $data_sam_hash = hash("md5", $data_sam_record);
    }
    catch (\Exception $e) {
      $data_sam_record = NULL;
    }

    if (empty($data_sam_record)) {
      MnlUtilities::MnlBadData("Update Node: Bad Json or missing json in update.");
    }

    $cache_sam_hash = $existing_record->field_checksum_value ?: "";
    $data_sam_address = $new_record["full_address"];
    $cache_sam_address = $existing_record->field_sam_address_value;

    if ($data_sam_hash != $cache_sam_hash || $data_sam_address != $cache_sam_address) {

      $fields = [
        "field_updated_date" => strtotime("now"),
      ];

      if ($data_sam_hash != $cache_sam_hash) {
        // The SAM data has changed - update data and checksum.
        $fields['field_sam_neighborhood_data'] = $data_sam_record;
        $fields['field_checksum'] = $data_sam_hash;
      }

      if ($data_sam_address != $cache_sam_address) {
        // The SAM Address has changed, update the address
        $fields['field_sam_address'] = $data_sam_address;
      }

      if (MnlUtilities::MnlUpdateSamNode($existing_record->nid, $fields)) {
        $this->stats["updated"]++;
      }
      else {
        $this->createNode($existing_record, $new_record);
      }

    }
    else {
      $this->stats["unchanged"]++;
    }

  }

  /**
   * Create new node.
   *
   * @param mixed $existing_record
   *   The current node from the mnl_cache.
   * @param array $new_record
   *   The imported data to insert.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function createNode(&$existing_record, array $new_record) {

    try {
      $json_data = json_encode($new_record['data']);
      $md5 = hash("md5", $json_data);
    }
    catch (\Exception $e) {
      $json_data = NULL;
    }

    if (empty($json_data)) {
      MnlUtilities::MnlBadData("Create Node: Bad Json or missing json in create.");
    }

    $node = MnlUtilities::MnlCreateSamNode($new_record, $json_data, $md5);

    // Add this new record to the mnl_cache.
    $existing_record->field_sam_id_value = $new_record['sam_address_id'];
    $existing_record->field_sam_address_value = $new_record['full_address'];
    $existing_record->field_checksum_value = $md5;
    $existing_record->nid = $node->id();

    $this->stats["inserted"]++;

  }

  /**
   * Process each queue record.
   */
  public function processItem($item) {

    if (empty($item)) {
      // The item supplied contained no data.
      MnlUtilities::MnlBadData("Queue Processor: Bad Json or missing json in queued item.");
    }

    $item = (array) $item;

    if (count($item) == 1 && !empty($item[0]) && $item[0] == "complete!") {
      // OK so this is the last record in the queue/import.
      $this->stats["processed"]++;
      $this->_finishProcessing(TRUE);
      return;
    }
    elseif (count($item) != 3) {
        // Probably not the data we were expecting.
      MnlUtilities::MnlBadData("Queue Processor: Unexpected data found in queued item.");
    }

    $cache = FALSE;
    if (!empty($this->mnl_cache[$item['sam_address_id']])) {
      $cache = $this->mnl_cache[$item['sam_address_id']];
    }

    try {
      if ($cache) {
        if (!empty($cache->processed)) {
          $this->stats["duplicateSAM"]++;
        }
        else {
          if ($this->settings->get("use_entity")) {
            $this->updateNodeEntity($cache, $item);
          }
          else {
            $this->updateNode($cache, $item);
          }
          $cache->processed = TRUE;
        }
      }
      else {
        $cache = new \stdClass();
        $this->createNode($cache, $item);
        $this->mnl_cache[$item['sam_address_id']] = $cache;
        $cache->processed = TRUE;
      }

      $this->stats["processed"]++;

    }
    catch (\Exception $e) {
      $this->stats["baddata"]++;
      throw new \Exception("MNLUpdate-{$e}");
    }

  }

  /**
   * Helper function to mark the app as idle and to set the logging for this
   * process cycle now completed.
   *
   * @return void
   */
  private function _finishProcessing(bool $inflight = FALSE) {

    // The queue is empty so make the app flag idle.
    // Save now just in case we get errors.
    $this->settings
      ->set("{$this->plugin_id}_import_status", MnlUtilities::MNL_IMPORT_IDLE)
      ->save();

    // Work out how many entities there now are (for reporting).
    try {
      $query = \Drupal::database()->select("node", "n")
        ->condition("n.type", "neighborhood_lookup");
      $query->addExpression("count(n.nid)", "count");
      $result = $query->execute()->fetch();
    }
    catch (\Exception $e) {
      $result = new \stdClass();
      $result->count = "Unknown";
    }
    $this->stats["post-entities"] = ($result->count ?: "Unknown");

    // Reset the persisted stats.
    $this->settings->set('tmp_update', "");

    // Log.
    $qcorrection = $inflight ? 1 : 0;
    $params = [
      "end_time" => date('Y-m-d H:i:s', strtotime('now')),
      "pre_entities" => number_format($this->stats['pre-entities'], 0),
      "cache" => number_format($this->stats['cache'], 0),
      "queue" => number_format($this->stats['queue'], 0),
      "inserted" => number_format($this->stats['inserted'], 0),
      "updated" => number_format($this->stats['updated'], 0),
      "unchanged" => number_format($this->stats['unchanged'], 0),
      "duplicate" => number_format($this->stats['duplicateSAM'], 0),
      "bad_records" => number_format($this->stats['baddata'], 0),
      "processed" => number_format(($this->stats['processed'] - 1), 0),
      "post_entities" => number_format($this->stats['post-entities'], 0),
      "cache_count" => empty($this->mnl_cache) ? 0 : number_format(count($this->mnl_cache), 0),
      "queue_end" => number_format(($this->queue->numberOfItems() - $qcorrection), 0),
      "duration" => gmdate('H:i:s', strtotime('now') - $this->stats['starttime']),
      "update_method" => ($this->settings->get('use_entity') ? 'Drupal entity object' : 'direct DB updating'),
    ];
    $output = "
      <table>
        <tr><td colspan='2'><b>Completed at {$params['end_time']} UTC</td></tr>
        <tr><td colspan='2'>== Start Condition =====================</td></tr>
        <tr><td><b>Addresses in DB at start</b></td><td>{$params['pre_entities']}</td></tr>
        <tr><td><b>Unique SAM ID's at start</b></td><td>{$params['cache']}</td></tr>
        <tr><td><b>Queue length at start</b></td><td>{$params['queue']} queued records.</td></tr>
        <tr><td colspan='2'>== Queue Processing ====================</td></tr>
        <tr><td><b>New addresses created</b></td><td>{$params['inserted']}</td></tr>
        <tr><td><b>Updated addresses</b></td><td>{$params['updated']}</td></tr>
        <tr><td><b>Unchanged addresses</b></td><td>{$params['unchanged']}</td></tr>
        <tr><td><b>Duplicate SAM ID's skipped</b></td><td>{$params['duplicate']}</td></tr>
        <tr><td><b>Bad records</b></td><td>{$params['bad_records']}</td></tr>
        <tr><td><b>Control records received</b></td><td>1</td></tr>
        <tr><td colspan='2'>== Result ==============================</td></tr>
        <tr><td><b>Addresses processed from queue</b></td><td>{$params['processed']}</td></tr>
        <tr><td><b>Addresses in DB at end</b></td><td>{$params['post_entities']}</td></tr>
        <tr><td><b>Unique SAM ID's at end</b></td><td>{$params['cache_count']}</td></tr>
        <tr><td><b>Queue length at end</b></td><td>{$params['queue_end']} queued records.</td></tr>
        <tr><td colspan='2'>== Runtime =============================</td></tr>
        <tr><td colspan='2'><b>Process duration {$params['duration']}</b></td></tr>
        <tr><td colspan='2'><i>Note: Update method {$params['update_method']}.</i></td></tr>
      </table>
    ";
    \Drupal::logger("bos_mnl")->info($output);
    $this->settings->set('last_mnl_update', $output);

    $this->settings->save();

  }

}
