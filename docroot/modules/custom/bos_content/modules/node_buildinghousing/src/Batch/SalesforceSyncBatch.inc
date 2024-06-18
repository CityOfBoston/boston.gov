<?php

use Drupal\Component\Utility\Environment;
use Drupal\Core\Queue\DelayableQueueInterface;
use Drupal\Core\Queue\DelayedRequeueException;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\node_buildinghousing\BuildingHousingUtils;
use Drupal\node_buildinghousing\Form\SalesforceSyncSettings;
use Drupal\salesforce\SelectQuery;
use Drupal\salesforce_pull\QueueHandler;

/**
 * Batch process to completely deletes all objects imported during previous
 * Salesforce Sync processes.
 *
 * @param $context array The batch variable.
 *
 * @return int|null
 * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
 * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
 */
function bh_removeAllBatch($entity, &$context) {

  $config = \Drupal::config('node_buildinghousing.settings');
  $log = ($config->get("log_actions") === 1) ?? FALSE;
  $delete_parcel = ($config->get('delete_parcel') === 1) ?? FALSE;
  $lock = \Drupal::lock();
  $node_storage = \Drupal::entityTypeManager()->getStorage('node');

  if ($entity == "bh_parcel" && !$delete_parcel) {
    // Won't process parcels if settings form has this disabled.
    $context["finished"] = 1;
    return;
  }

  // Delete the requested entities
  $infos = [
    "bh_project" => [
      "chunk" => 10,
      "type" => "Project"
    ],
    "bh_update" => [
      "chunk" => 10,
      "type" => "orphaned Project Update"
    ],
    "bh_meeting" => [
      "chunk" => 15,
      "type" => "orphaned Community Meeting"
    ],
    "bh_parcel_project_assoc" => [
      "chunk" => 25,
      "type" => "orphaned Parcel-Project Association"
    ],
    "bh_parcel" => [
      "chunk" => 50,
      "type" => "orphaned Parcel"
    ],
  ];
  $info = $infos[$entity];

  $count = \Drupal::entityQuery('node')
    ->accessCheck(FALSE)
    ->condition("type", $entity)
    ->count()
    ->execute();

  // Initialize cleanup message for this node type.
  if (!isset($context["sandbox"]["{$entity}_total"])) {
    $context["sandbox"]["{$entity}_total"] = $count;
    $context["sandbox"]["{$entity}_deleted"] = 0;
    $context['message'] = t("Initializing cleanup for @type", [
      "@type" => $info["type"] . "s",
    ]);
    $context['sandbox']['progress'] = 0;
    $context['finished'] = 0;
    $log && BuildingHousingUtils::log("cleanup", "Removing {$count} {$info["type"]} records. \n");
    return;
  }

  if ($count) {

    $nodes = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition("type", $entity)
      ->range(0, $info["chunk"])
      ->execute();
    $nodes = $node_storage->loadMultiple($nodes);

    foreach ($nodes as $node) {
      $lock->acquire(SalesforceSyncSettings::lockname, 30);
      switch ($entity) {
        case "bh_project":
          BuildingHousingUtils::deleteProject($node, $delete_parcel, TRUE, $log);
          break;
        case "bh_update":
          // Delete any orphaned Updates and linked items.
          BuildingHousingUtils::deleteUpdate(NULL, $node, TRUE, $log);
          break;
        case "bh_meeting":
          // Delete any Community Meetings.
          BuildingHousingUtils::deleteMeeting($node, TRUE, $log);
          break;
        case "bh_parcel_project_assoc":
          // Delete orphaned Parcel Assocs.
          BuildingHousingUtils::deleteParcelAssoc($node, TRUE, $log, $delete_parcel);
          break;
        case "bh_parcel":
          // Delete orphaned Parcels.
          BuildingHousingUtils::deleteParcel($node, TRUE, $log, TRUE);
          break;
      }
      $context["sandbox"]["{$entity}_deleted"]++;
    }
    $node = NULL;

    $context['message'] = t("Deleted @progress of @total @type records", [
      "@progress" => number_format($context["sandbox"]["{$entity}_deleted"], 0),
      "@total" => number_format($context["sandbox"]["{$entity}_total"], 0),
      "@type" => $info["type"],
    ]);
    $context['sandbox']['progress'] = $context["sandbox"]["{$entity}_deleted"] / $context["sandbox"]["{$entity}_total"];
    $context['finished'] = ($context["sandbox"]["{$entity}_deleted"] / $context["sandbox"]["{$entity}_total"]);
  }

  if ($context["sandbox"]["{$entity}_total"] == 0) {
    $context['message'] = t("No @type records to delete", [
      "@type" => $info["type"],
    ]);
    $context['finished'] = 1;
  }

  if ($context['finished'] == 1) {
    BuildingHousingUtils::appendTemp("batch_messages", ["Removed  {$context["sandbox"]["{$entity}_deleted"]} {$info["type"]} Records"]);
  }

  $lock->release(SalesforceSyncSettings::lockname);

}

/**
 * Batch Process to import all records from Salesforce.
 *
 * @param $context array The batch variable.
 *
 * @return void
 */
function bh_queueAllBatch($mapping, $force, &$context) {

  $config = \Drupal::config('node_buildinghousing.settings');
  $log = ($config->get("log_actions") === 1) ?? FALSE;
  $delete_parcel = $config->get('delete_parcel') ?? FALSE;
  $lock = \Drupal::lock();
  $map = \Drupal::entityTypeManager()->getStorage("salesforce_mapping");

  if (!$lock->lockMayBeAvailable(SalesforceSyncSettings::lockname)) {
    $context["finished"] = 1;
    BuildingHousingUtils::appendTemp("batch_errors", ["There is already an Salesforce management process runnning"]);
    return;
  }

  if ($mapping == "building_housing_parcels" && !$delete_parcel) {
    $context['finished'] = 1;
    return;
  }

  $container = \Drupal::getContainer();
  $processor = new QueueHandler(
    $container->get('salesforce.client'),
    $container->get('entity_type.manager'),
    $container->get('queue.database'),
    $container->get('config.factory'),
    $container->get('event_dispatcher'),
    $container->get('datetime.time')
  );

  $text = [
    "building_housing_projects" => "Building Housing - Project",
    "bh_website_update" => "Building Housing - Website Update",
    "bh_community_meeting_event" => "BH Community Meeting",
    "bh_parcel_project_assoc" => "Building Housing - Parcels-Project Association",
    "building_housing_parcels" => "Building Housing - Parcel",
    "metrolist_development" => "Metrolist - Development",
    "metrolist_unit" => "Metrolist - Unit",
  ];
  $object = $text[$mapping];

  // Do this in an initial pass to update the progress page.
  if (!isset($context["sandbox"]["{$object}_queued"])) {
    $context["sandbox"]["{$object}_queued"] = 0;
    $context['message'] = "Queueing {$object}s from Salesforce";
    $context['sandbox']['progress'] = 0;
    $context['finished'] = 0;
    return;
  }

  try {

    $lock->acquire(SalesforceSyncSettings::lockname, 180);

    if ($force) {
      $start_date = "2018-01-01 00:00:00";
      $count = $processor->getUpdatedRecordsForMapping($map->load($mapping), TRUE, 1420070400, strtotime("now"));
    }
    else {
      $start_date = \Drupal::state()
        ->get('salesforce.mapping_pull_info')[$mapping]["last_pull_timestamp"];
      $count = $processor->getUpdatedRecordsForMapping($map->load($mapping), FALSE);
    }
    $context["sandbox"]["{$object}_queued"] = $count;
    $count = number_format($count, 0);

    $context['message'] = "Queued {$count} {$object}s from Salesforce";
    $context['sandbox']['progress'] = 1;
    $context['finished'] = 1;

    if ($count) {
      $log && BuildingHousingUtils::log("cleanup", "QUEUED {$count} {$object} record/s from Salesforce using '{$mapping}' mapping.\n");
      BuildingHousingUtils::appendTemp("batch_messages", ["Queued {$count} Salesforce {$object}s from {$mapping}"]);
    }
    else {
      $log && BuildingHousingUtils::log("cleanup", "No changed {$object} record/s found (Last modified after {$start_date} in Salesforce) using '{$mapping}' mapping.\n");
      BuildingHousingUtils::appendTemp("batch_messages", ["No Salesforce {$object}s found from {$mapping}"]);
    }

    $lock->release(SalesforceSyncSettings::lockname);

  }
  catch (\Exception $e) {
    // Have an error, so log it and then proceed.
    BuildingHousingUtils::appendTemp("batch_warnings", ["ERROR Queueing {$object} record/s from Salesforce using '{$mapping}' mapping: {$e->getMessage()}"]);
    $log && BuildingHousingUtils::log("cleanup", "***** ERROR Queueing {$object} record/s from Salesforce using '{$mapping}' mapping.\n");
    \Drupal::logger("BuildingHousing")->error("***** ERROR Queueing {$object} record/s from Salesforce using '{$mapping}' mapping.");
  }

}

function bh_processQueueBatch($type, &$context) {
  /**
   * @var \Drupal\Core\Queue\QueueInterface $queue
   */

  $config = \Drupal::config('node_buildinghousing.settings');
  $log = ($config->get("log_actions") === 1) ?? FALSE;
  $lock = \Drupal::lock();

  $type = $type ?? "ALL";

  try {
    $queue_name = QueueHandler::PULL_QUEUE_NAME;
    $lock->acquire(SalesforceSyncSettings::lockname, 15);
    $queue_factory = \Drupal::service('queue');
    $queue_manager = \Drupal::service('plugin.manager.queue_worker');
    $queue_worker = $queue_manager->createInstance($queue_name);
    $queue = $queue_factory->get($queue_name);
  }
  catch (\Exception $e) {
    BuildingHousingUtils::appendTemp("batch_warnings", ["ERROR attaching queue: {$e->getMessage()}"]);
  }

  if (!isset($context["sandbox"]["queue_total"])) {
    $context["sandbox"]["queue_total"] = $queue->numberOfItems();
    $context["sandbox"]["queue_processed"] = 0;
    $context['message'] = t("Preparing Queue");
    $context['sandbox']['progress'] = 0;
    $context['finished'] = 0;
    return;
  }

  $queue_exhaused = TRUE;
  $already_claimed = [];

  Environment::setTimeLimit(300);

  for ($loop = 1; $loop <= min(25, $queue->numberOfItems()); $loop++ ) {

    $lock->acquire(SalesforceSyncSettings::lockname, 30);

    if ($item = $queue->claimItem(30)) {

      $queue_exhaused = FALSE;

      if (isset($already_claimed[$item->item_id])) {
        $queue->delayItem($item, 60);
        continue;
      }
      if ($type != "ALL" && $item->data->getMappingId() != $type)  {
        $queue->releaseItem($item);
        $already_claimed[$item->item_id] = $item->item_id;
        if (count($already_claimed) == $queue->numberOfItems()) {
          // We have tried everything, so stop scanning the queue.
          $queue_exhaused = TRUE;
          break;
        }
        continue;
      }

      $item_type = str_replace("__c", "", $item->data->getSobject()->type());
      if (!isset($context["sandbox"]['q'])) {
        $context["sandbox"]['q'] = [];
      }
      if (!isset($context["sandbox"]['q'][$item_type])) {
        $context["sandbox"]['q'][$item_type] = 0;
      }

      try {
        $result = $queue_worker->processItem($item->data);
        $queue->deleteItem($item);
        $context["sandbox"]["queue_processed"]++;
        if ($result) {
          $context["sandbox"]['q'][$item_type]++;
        }
      }
      catch (DelayedRequeueException $e) {
        if ($queue instanceof DelayableQueueInterface) {
          $queue->delayItem($item, $e->getDelay());
        }
      }
      catch (RequeueException $e) {
        $queue->releaseItem($item);
      }
      catch (SuspendQueueException $e) {
        $queue->releaseItem($item);
        continue;
      }
      catch (\Exception $e) {
        // Some other sort of error - delay this item for 15mins
        BuildingHousingUtils::appendTemp("batch_warnings", ["Queue Item {$item->data->getSObject()->field("Name")} (a {$item->data->getSObject()->type()}) from {$item->data->getMappingId()} could not be processed.\n    Error: {$e->getMessage()}"]);
        $queue->delayItem($item, 900);
        $log && BuildingHousingUtils::log("cleanup", "Queue Item {$item->data->getSObject()->field("Name")} (a {$item->data->getSObject()->type()}) from {$item->data->getMappingId()} could not be processed.\n    Error: {$e->getMessage()}\n    - Retry item in 15mins.\n", TRUE);
        \Drupal::logger("BuildingHousing")->error("Queue Item {$item->data->getSObject()->field("Name")} (a {$item->data->getSObject()->type()}) from {$item->data->getMappingId()} could not be processed. ERROR: {$e->getMessage()}");
      }
    }
  }

  unset($already_claimed);
  $lock->release(SalesforceSyncSettings::lockname);

  if (!isset($item_type)) {
    $context['message'] = t("Nothing Synchronized");
  }
  else {
    $context['message'] = t("Imported @processed of @total queued entities -currently importing @type (@count)", [
      "@processed" => number_format($context["sandbox"]["queue_processed"], 0),
      "@total" => number_format($context["sandbox"]["queue_total"], 0),
      "@count" => number_format($context["sandbox"]['q'][$item_type] ?? 0, 0),
      "@type" => "{$item_type}s",
    ]);
  }

  if ($context["sandbox"]["queue_total"] == 0 || $queue_exhaused) {
    $context['sandbox']['progress'] = 1;
    $context['finished'] = 1;
  }
  else {
    $context['sandbox']['progress'] = $context["sandbox"]["queue_processed"] / $context["sandbox"]["queue_total"];
    $context['finished'] = ($context["sandbox"]["queue_processed"] / $context["sandbox"]["queue_total"]);
  }

  if ($context["finished"] == 1) {
    if ($context["sandbox"]["queue_processed"]) {
      BuildingHousingUtils::appendTemp("batch_messages", ["Processed {$context["sandbox"]["queue_processed"]} Salesforce items"]);
      foreach ($context["sandbox"]['q'] as $key => $val) {
        BuildingHousingUtils::appendTemp("batch_messages", [" => Sync'd {$val} {$key}s"]);
      }
    }

    if ($queue->numberOfItems() > 0) {
      BuildingHousingUtils::appendTemp("batch_warnings", ["Queue processing completed, but " . number_format($queue->numberOfItems(), 0) . " items remain in the {$queue_name} queue."]);
    }
  }
}

/**
 * Checks for, and removes Building Housing entities in Drupal which are deleted
 * in Salesforce.
 *
 * @param $entity_type string Drupal Entity
 * @param $context array Batch context variable.
 *
 * @return void
 */
function bh_removeDeletedBatch($bundle, &$context) {
  // PHASE 1: Scan Drupal looking for SF records that don't exist any longer.
  // Checks to see if all Drupal entity records exist in their mapped
  // object in Salesforce. Deletes Drupal records which do not.

  if (in_array($bundle, ["bh_parcel", "bh_parcel_project_assoc"])) {
    // Just abandon for these bundles, makes no sense to run for them.
    // NOTE adding bh_parcel (Parcel__c) will cause errors because the # of SF
    //      records exceeds the 2000limit on the API. Alternate paging strategy
    //      would be required to implement Parcel__c processing.
    $context["finished"] = 1;
    return;
  }

  $config = \Drupal::config('node_buildinghousing.settings');
  $log = ($config->get("log_actions") === 1) ?? FALSE;
  $delete_parcel = ($config->get("delete_parcel") === 1) ?? FALSE;

  if ($bundle == "bh_parcel" && !$delete_parcel) {
    $context["finished"] = 1;
    return;
  }

  $entity_map = [
    "bh_project" => [
      "sf_object" => "Project__c",
      "mapping" => "building_housing_projects",
      "conditions" => [
        [
          "field" => "RecordTypeID",
          "operator" => "IN",
          "value" => "('0120y0000007rw7', '012C0000000Hqw0')",
        ],
      ],
    ],
    "bh_update" => [
      "sf_object" => "Website_Update__c",
      "mapping" => "bh_website_update",
    ],
    "bh_meeting" => [
      "sf_object" => "Community_Meeting_Event__c",
      "mapping" => "bh_community_meeting_event",
    ],
    "bh_parcel_project_assoc" => [
      "sf_object" => "ParcelProject_Association__c",
      "mapping" => "bh_parcel_project_assoc",
    ],
    "bh_parcel" => [
      "sf_object" => "Parcel__c",
      "mapping" => "building_housing_parcels",
    ],
  ];
  $bundle_info = $entity_map[$bundle];
  $scan_chunk_size = 1000;
  $delete_chunk_size = 100;

  if (!isset($context["sandbox"]["clean_queue"])) {
    $log && BuildingHousingUtils::log("cleanup", "Scanning {$bundle} for orphans where a Drupal record has no matching Salesforce record.\n");
    $context['message'] = t("Scanning {$bundle}");
    $context['sandbox']['clean_queue'] = [];
    $context["sandbox"]["cleaned"] = 0;
    $context['sandbox']['progress'] = 0;
    $context['finished'] = 0;
    $context["sandbox"]["scan_chunk_last"] = 0;
    $context["sandbox"]["count"] = _loadNodeRange($bundle, TRUE);
    $context['sandbox']['salesforce'] = _loadSFRecords($bundle_info);
    return;
  }

  $nodes = _loadNodeRange($bundle, FALSE, $context["sandbox"]["scan_chunk_last"], $scan_chunk_size);

  $node_storage = \Drupal::entityTypeManager()->getStorage("node");

  if ($nodes) {

    // Select the salesforce deleted records.
    if ($context['sandbox']['scan_chunk_last'] < $context['sandbox']['count']) {

      $maptable = _loadSalesforceMappedObjects($bundle_info["mapping"]);

      foreach ($nodes as $node) {

        // Scan for unneeded records, and delete in batches.
        $sfrecords = $context['sandbox']['salesforce'];
        if (!isset($maptable[$node])) {
          // No record for this node in the Salesforce_mapping table.
          $node = $node_storage->load($node);
          $context['sandbox']['clean_queue'][] = $node;
          $log && BuildingHousingUtils::log("cleanup", "Removed orphan {$bundle} - No mapping entry for {$node->getTitle()}.\n");
        }
        else {
          // Found an entry in the salesforce_mapping table ...
          if (!isset($sfrecords[$maptable[$node]->salesforce_id])) {
            // ... but mapping has no associated salesforce_id
            $node = $node_storage->load($node);
            $context['sandbox']['clean_queue'][] = $node;
            $log && BuildingHousingUtils::log("cleanup", "Removed orphan {$bundle} - No salesforce_id in mapping table for {$node->getTitle()}.\n");
          }
        }

        // Delete batched records once max batch size reached.
        if (count($context['sandbox']['clean_queue']) >= $delete_chunk_size) {
          if ($bundle == "bh_project") {
            // If this is a project, then we will delete all the files etc
            // as well.
            foreach ($context['sandbox']['clean_queue'] as $_node) {
              BuildingHousingUtils::deleteProject($_node, $delete_parcel, TRUE, $log);
            }
          }
          else {
            $node_storage->delete($context['sandbox']['clean_queue']);
          }
          $context["sandbox"]["cleaned"] += count($context['sandbox']['clean_queue']);
          $context['sandbox']['clean_queue'] = [];
        }

        $context["sandbox"]["scan_chunk_last"]++;
      }

      $context['message'] = t("Removing Drupal {$bundle} records which don't exist in Salesforce.");
      $context['sandbox']['progress'] = ($context['sandbox']['scan_chunk_last'] / $context['sandbox']['count']);
      $context['finished'] = 0;
    }

    else {
      // finished.
      if (count($context['sandbox']['clean_queue']) >= $delete_chunk_size) {
        $context["sandbox"]["cleaned"] += count($context['sandbox']['clean_queue']);
        $node_storage->delete($context['sandbox']['clean_queue']);
        $context['sandbox']['clean_queue'] = [];
      }

      if ($context['sandbox']['cleaned'] > 0) {
        BuildingHousingUtils::appendTemp("batch_messages", ["Removed {$context['sandbox']['cleaned']} {$bundle} records which do not exist in Salesforce."]);
      }
      $log && BuildingHousingUtils::log("cleanup", "Removed {$context['sandbox']['cleaned']} orphan {$bundle} records.\n");
      $context['message'] = t("Finished {$bundle}");
      $context['sandbox']['progress'] = 1;
      $context['finished'] = 1;
    }

  }

}

/**
 * Removes records which have no associated "parent".
 *
 * @param $bundle string The bundle to process.
 * @param $context array Batch process variable
 *
 * @return void
 * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
 * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
 * @throws \Drupal\Core\Entity\EntityStorageException
 */
function bh_removeOrphansBatch($bundle, &$context) {
  // PHASE 2: Scan associated objects looking for orphans that have no
  // association back to an active project.

  if (in_array($bundle, ["bh_project"])) {
    // Just abandon this, makes no sense to run this bundle
    $context["finished"] = 1;
    return;
  }

  $config = \Drupal::config('node_buildinghousing.settings');
  $log = ($config->get("log_actions") === 1) ?? FALSE;

  $scan_chunk_size = 1000;
  $clean_chunk_size = 100;
  $dependency = [
    "bh_parcel" => [
      "parent_bundle" => "bh_parcel_project_assoc",
      "target" => [
        "table" => "node__field_bh_parcel_ref",
        "id_field" => "field_bh_parcel_ref_target_id",
      ]
    ],
    "bh_update" => [
      "parent_bundle" => "bh_project",
      "target" => [
        "table" => "node__field_bh_project_ref",
        "id_field" => "field_bh_project_ref_target_id",
      ]
    ],
    "bh_meeting" => [
      "parent_bundle" => "bh_update",
      "target" => [
        "table" => "node__field_bh_update_ref",
        "id_field" => "field_bh_update_ref_target_id",
      ]
    ],
    "bh_parcel_project_assoc" => [
      "parent_bundle" => "bh_project",
      "target" => [
        "table" => "node__field_bh_project_ref",
        "id_field" => "field_bh_project_ref_target_id",
      ]
    ],
  ];
  $parent = $dependency[$bundle];

  if (!isset($context["sandbox"]["count"])) {
    $log && BuildingHousingUtils::log("cleanup", "Scanning {$bundle} for orphans where a Drupal record has no parent Project record in Drupal.\n");
    $context["sandbox"]["scan_chunk_last"] = 0;
    $context['sandbox']['orphans_deleted'] = 0;
    $context['sandbox']['cleaned'] = 0;
    $context['sandbox']['clean_queue'] = [];
    $context["sandbox"]["count"] = _loadNodeRange($bundle, TRUE);
    $context['message'] = t("Preparing {$bundle}");
    $context['sandbox']['progress'] = 0;
    $context['finished'] = 0;
    return;
  }

  $nodes = _loadNodeRange($bundle, FALSE, $context["sandbox"]["scan_chunk_last"], $scan_chunk_size);

  if ($nodes && $context['sandbox']['scan_chunk_last'] < $context['sandbox']['count']) {

    $node_storage = \Drupal::entityTypeManager()->getStorage("node");

    // Find and delete orphans a chunk at a time
    foreach ($nodes as $node) {
      // This will find the parent target node id and see if it exists.
      $parent["id"] = $node;
      $result = _loadParentNode($bundle, $parent);

      if (!$result || !$result->fetchField()) {

        // Batch up records to be deleted
        try {
          $n = $node_storage->load($node);
        }
        catch (\GuzzleHttp\Exception\GuzzleException | \Exception $e) {
          $log && BuildingHousingUtils::log("cleanup", "Queueing orphan {$bundle}: {$e->getMessage()}.\n");
          \Drupal::logger("BuildingHousing")->error("Queueing orphan {$bundle}: {$e->getMessage()}");
          continue;
        }

        $context['sandbox']['clean_queue'][] = $n ;

        // Monitor and once we have 100 records to clean, then clean them.
        if (count($context['sandbox']['clean_queue']) >= $clean_chunk_size) {
          try {
            $node_storage->delete($context['sandbox']['clean_queue']);
          }
          catch (\Exception) {
            $log && BuildingHousingUtils::log("cleanup", "Removing orphan {$bundle}: {$e->getMessage()}.\n");
            \Drupal::logger("BuildingHousing")->error("Removing orphan {$bundle}: {$e->getMessage()}");
          }
          foreach ($context["sandbox"]['clean_queue'] as $n) {
            $log && BuildingHousingUtils::log("cleanup", "Removed orphan {$bundle} - No parent Project for {$n->getTitle()}.\n");
          }
          $context['sandbox']['cleaned'] += count($context['sandbox']['clean_queue']);
          $context['sandbox']['clean_queue'] = [];
        }
      }

      $context['sandbox']['scan_chunk_last']++;

    }

    $context['message'] = t("Scanning for orphan {$bundle} records ({$context['sandbox']['scan_chunk_last']}).");
    $context['sandbox']['progress'] = ($context['sandbox']['scan_chunk_last'] / $context['sandbox']['count']);
    $context['finished'] = 0;
  }

  else {
    // finished
    if (count($context['sandbox']['clean_queue']) > 0) {
      $node_storage = \Drupal::entityTypeManager()->getStorage("node");
      $node_storage->delete($context['sandbox']['clean_queue']);
      $context['sandbox']['cleaned'] += count($context['sandbox']['clean_queue']);
      $context['sandbox']['clean_queue'] = [];
    }
    if ($context['sandbox']['cleaned'] > 0) {
      $log && BuildingHousingUtils::log("cleanup", "Removed {$context['sandbox']['cleaned']} {$bundle} records which do not relate back to a project.\n");
      BuildingHousingUtils::appendTemp("batch_messages", ["Removed {$context['sandbox']['cleaned']} {$bundle} records which do not relate back to a project."]);
    }
    $context['message'] = t("Finished {$bundle} orphans.");
    $context['sandbox']['progress'] = 1;
    $context['finished'] = 1;
  }

}

function bh_initializeBatch($op, &$context) {

  $config = \Drupal::config('node_buildinghousing.settings');
  $log = ($config->get("log_actions") === 1) ?? FALSE;
  $lock = \Drupal::lock();
  \Drupal::messenger()->deleteAll();
  BuildingHousingUtils::resetTemp("batch_messages");
  BuildingHousingUtils::resetTemp("batch_errors");
  BuildingHousingUtils::resetTemp("batch_warnings");

  if (!$lock->lockMayBeAvailable(SalesforceSyncSettings::lockname)) {
    $context["finished"] = 1;
    BuildingHousingUtils::setTemp("batch_errors", ["There is already an {$op} Salesforce process runnning"]);
    return;
  }

  $context['message'] = ucwords($op);
  $_SESSION['sandbox']['start'] = strtotime("now");
  $_SESSION['sandbox']['op'] = $op;
  $context['finished'] = 1;

  $log && BuildingHousingUtils::log("cleanup", "==={$op} STARTS\n",TRUE);

}

function bh_finalizeBatch(&$context) {
  $config = \Drupal::config('node_buildinghousing.settings');
  $log = ($config->get("log_actions") === 1) ?? FALSE;
  $lock = \Drupal::lock();
  $op = $_SESSION['sandbox']['op'] ?? "UNKNOWN";

  foreach([ "batch_messages" => 'status',
            "batch_warnings" => 'warning',
            "batch_errors" => 'error'] as $tmpvar => $channel) {

    $messages = BuildingHousingUtils::getTemp($tmpvar) ?? [];

    if (!empty($messages)) {
      foreach ($messages as $message) {
        \Drupal::messenger()->addMessage($message, $channel);
      }
    }
    BuildingHousingUtils::resetTemp($tmpvar);

  }

  \Drupal::messenger()->addStatus("Process took " . number_format((strtotime("now") - $_SESSION['sandbox']['start']) / 60, 0) . " minutes");

  $context['message'] = "Complete";
  $context['finished'] = 1;

  $lock->release(SalesforceSyncSettings::lockname);

  $log && BuildingHousingUtils::log("cleanup", "==={$op} ENDS\n", TRUE);

}

/**
 * Queries Salesforce to return a filtered set of records from an object.
 *
 * @param $bundle_info array Parameters for the query
 *
 * @return mixed stdClass containing
 * @throws \Exception
 */
function _loadSFRecords($bundle_info) {

  $sfclient = \Drupal::service("salesforce.client");

  $query = new SelectQuery($bundle_info["sf_object"]);
  $query->fields = ["Id", "Name"];
  $query->addCondition("IsDeleted", 'false', "=");
  if(!empty($bundle_info["conditions"])) {
    foreach ($bundle_info["conditions"] as $condition) {
      $query->conditions[] = $condition;
    }
  }
  $sf = $sfclient->query($query);

  if ($sf->size() > 6000) {
    throw new \Exception("Too many records (>6,000) returned from Salesforce.");
  }

  $sfrecords = $sf->records();

  while (!$sf->done()) {
    $sf = $sfclient->queryMore($sf);
    $sfrecords += $sf->records();
  }

  return $sfrecords;
}

/**
 * Loads all records in the Drupal salesforce mapping table.
 * This provides a 1:1 mapping record for Drupal Node nids to Salesforce sfids.
 *
 * @param $mapping string The mapping to load.
 *
 * @return array Assoc array key=nid value=sfid.
 */
function _loadSalesforceMappedObjects($mapping) {

  return \Drupal::database()
    ->select("salesforce_mapped_object", "sf")
    ->fields("sf", ["id", "salesforce_id", "drupal_entity__target_id"])
    ->condition("salesforce_mapping", $mapping, "=")
    ->execute()
    ->fetchAllAssoc("drupal_entity__target_id");

}

/**
 * Load a paged associative array of Drupal nodes for a given type/bundle.
 *
 * @param $bundle string The Drupal node bundle or type.
 * @param $count bool FALSE returns an array, TRUE returns the # of records
 * @param $range_start int (optional) First record to return (sort-=nid)
 * @param $range_size int (optional) The number of records to return.
 *
 * @return array|int count of records or associative array key=vid, value=nid.
 */
function _loadNodeRange($bundle, $count = FALSE, $range_start = NULL, $range_size = NULL) {

  $query = \Drupal::entityQuery("node")
    ->accessCheck(FALSE)
    ->condition("type", $bundle, "=")
    ->sort("nid");

  if ($range_start && $range_size) {
    $query->range($range_start, ($range_start + $range_size));
  }

  if ($count) {
    $query->count();
  }

  return $query->execute();

}

/**
 * Attempt to get the parent object (in Building Housing terms) for a node.
 * @param $bundle string The node type/bundle
 * @param $parent array Parameters required for the query.
 *
 * @return \Drupal\Core\Database\StatementInterface|null
 */
function _loadParentNode($bundle, $parent) {

  $query = \Drupal::database()
    ->select($parent["target"]["table"], "target")
    ->fields('target', [$parent["target"]["id_field"]]);
  $query->innerJoin("node", "n", "n.nid = target.{$parent["target"]["id_field"]}");
  if ($bundle == "bh_parcel") {
    $query->condition('target.bundle', $parent["parent_bundle"], '=');
    $query->condition("target.". $parent["target"]["id_field"], $parent["id"], "=");
    $query->condition("n.type", $bundle, "=");  // redundant b/c nids are unique
  }
  else {
    $query->condition('target.bundle', $bundle, '=');
    $query->condition("target.entity_id", $parent["id"], "=");
    $query->condition("n.type", $parent["parent_bundle"], "=");  // redundant b/c nids are unique
  }
  return $query->execute();

}
