<?php

namespace Drupal\bos_mnl\Controller;

use Exception;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\node\Entity\Node;

/**
 * Class to provide various utilities used elsewhere in the module.
 */
class MnlUtilities {

  /** @var int Queue is empty and doing nothing */
  public const MNL_IMPORT_IDLE = 0;
  /** @var int Queue is filling with data imported from REST endpoint */
  public const MNL_IMPORT_IMPORTING = 1;
  /** @var int Import from REST endpoint is complete: Queue is ready */
  public const MNL_IMPORT_READY = 2;
  /** @var int Queue is being processed */
  public const MNL_IMPORT_PROCESSING = 4;
  /** @var int Queue is ready to be cleaned up */
  public const MNL_IMPORT_CLEANUP = 8;

  /**
   * This loads one or more neighborhood lookup nodes which have the provided
   * samids.  It then saves these nodes.
   * The action of loading and then saving the node clears the various caches,
   * including the low-level agressive json::api cache.
   *
   * @param $samid array An array of one or more SAM ID's.
   *                      If empty, then works on all SAMIDs in the DB.
   *
   * @return int
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function MnlCacheClear(array $samid = []): int {

    $count = 0;
    $storage = \Drupal::entityTypeManager()->getStorage("node");

    // Remove duplicate SAMID's in list to be processed.
    $samid = array_unique($samid);

    // Convert SAMID's into node ID's
    if ($samid == []) {

      // Clear the cache for everything.
      $storage->resetCache();

      return $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition("type", "neighborhood_lookup")
        ->count()
        ->execute();
    }
    else {
      $nodes = $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition("type", "neighborhood_lookup")
        ->condition("field_sam_id", $samid, "IN")
        ->execute();
    }

    // Process in chunks of 1,000.
    foreach (array_chunk($nodes, 1000) as $chunk) {

      if (!empty($chunk) && count($chunk) >= 1) {

        try {
          $storage->resetCache($chunk);
          $count = $count + count($chunk);
        }
        catch (Exception $e) {
          $error++;
        }

      }
    }

    return $count;

  }

  public static function MnlSelectCleanUpRecords(string $cutdate = "2 days ago", bool $count_only = FALSE, int $start = 0, int $limit = 0 ) {

    try {
      $unixdate = strtotime($cutdate);
    }
    catch (Exception $e) {
      throw new Exception("MNLUtilities: Could not evaluate date ${cutdate} (strtotime)");
    }

    try {
      $storage = \Drupal::entityTypeManager()->getStorage("node");
      $q = $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition("type", "neighborhood_lookup")
        ->condition("field_updated_date", $unixdate, "<");
      if ($count_only) {
        $q->count();
      }
      if ($limit) {
        $q->range($start, $limit);
      }
      $nodes = $q->execute();
    }
    catch (Exception $e) {
      throw new Exception($e->getMessage());
    }

    return $nodes;

  }

  /**
   * Purges (deletes) all SAM Records which have not been updated for more than
   *   the number of days provided.
   *
   * @param string $cutdate Description of period to use as cutoff.
   *   e.g. "2 days ago" will purge records with last updated date less (earlier)
   *   than 2 days ago.
   *
   * @return int|mixed|void Number of records purged.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  public static function MnlCleanUp(string $cutdate = "2 days ago") {

    $count = 0;
    try {
      $nodes = self::MnlSelectCleanUpRecords($cutdate, FALSE);
    }
    catch (Exception $e) {
      \Drupal::logger("bos_mnl")->error($e->getMessage());
      \Drupal::messenger()->addError($e->getMessage());
      throw new Exception($e->getMessage());
    }

    // Process in chunks of 1000.
    $storage = \Drupal::entityTypeManager()->getStorage("node");
    foreach (array_chunk($nodes, 1000) as $chunk) {
      if (!empty($chunk) && count($chunk) >= 1) {
        $entities = $storage->loadMultiple($chunk);
        $storage->delete($entities);
        $count = $count + count($chunk);
      }
    }

    return $count;
  }

  /**
   * Adds node to the purge queue - Acquia cloud purger clears Varnish.
   *
   * @param $nid int The entity ID for this node (neighborhood_lookup)
   *
   * @return void
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function MnlQueueInvalidation($entity_type, $nid) {
    // Force a save to invalidate the Drupal cache for this node.
    \Drupal::entityTypeManager()
      ->getStorage($entity_type)
      ->load($nid)
      ->addCacheableDependency((new CacheableMetadata())
        ->setCacheMaxAge(0))
      ->save();
  }

  /**
   * Invalidates a chiunk of queued tags in the purge queue.
   *
   * @return void
   */
  public static function MnlProcessPurgeQueue() {
    try {
      if (\Drupal::hasService('purge.queue')) {
        $purgeQueue = \Drupal::service('purge.queue');
        //        $purgeQueue->emptyQueue();
        $purgeProcessors = \Drupal::service('purge.processors');
        $purgePurgers = \Drupal::service('purge.purgers');
        while ($purgeQueue->numberOfItems() > 0) {
          $claims = $purgeQueue->claim();
          $processor = $purgeProcessors->get('drush_purge_queue_work');
          $purgePurgers->invalidate($processor, $claims);
          $purgeQueue->handleResults($claims);
        }
      }
    }
    catch (Exception $e) {
      \Drupal::logger("bos_mnl")->error("Could not process purge queue. {$e->getMessage()}");
    }
  }

  /**
   * Loads all SAM nodes into memory to speed processing.
   * Used by QueueWorkers
   *
   * @return array
   */
  public static function MnlCacheExistingSamIds(bool $full_record = FALSE, bool $has_no_checksum = FALSE, int $start = 0, int $limit = 0) {
    $query = \Drupal::database()->select("node", "n")
      ->fields("n", ["nid", "vid"])
      ->condition("n.type", "neighborhood_lookup");
    $query->join("node__field_sam_id", "id", "n.nid = id.entity_id");
    $query->join("node__field_sam_address", "addr", "n.nid = addr.entity_id");
    if ($has_no_checksum){
      $query->leftjoin("node__field_checksum", "checksum", "n.nid = checksum.entity_id");
      $query->condition("checksum.entity_id", NULL, "IS");
    }
    else {
      $query->join("node__field_checksum", "checksum", "n.nid = checksum.entity_id");
    }
    $query->condition("id.deleted", FALSE)
      ->condition("addr.deleted", FALSE)
      ->fields("id", ["field_sam_id_value"])
      ->fields("checksum", ["field_checksum_value"])
      ->fields("addr", ["field_sam_address_value"]);

    if ($full_record) {
      $query->leftJoin("node__field_sam_neighborhood_data", "dat", "n.nid = dat.entity_id");
      $query->fields("dat", ["field_sam_neighborhood_data_value"]);
      $or = $query->orConditionGroup()
        ->condition("dat.deleted", FALSE)
        ->isNull("dat.deleted");
      $query->condition($or);
    }
    if ($limit) {
      $query->range($start, $limit);
    }

    return $query->execute()->fetchAllAssoc("field_sam_id_value");

  }

  /**
   * @param $sam
   * @param $json_data
   * @param $md5
   *
   * @return \Drupal\Core\Entity\ContentEntityBase|\Drupal\Core\Entity\EntityBase|\Drupal\Core\Entity\EntityInterface|\Drupal\node\Entity\Node
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function MnlCreateSamNode($sam, $json_data, $md5) {
    // Create the node.
    $node = Node::create([
      'type'                        => 'neighborhood_lookup',
      'title'                       => $sam['sam_address_id'],
      'field_sam_id'                => $sam['sam_address_id'],
      'field_sam_address'           => $sam['full_address'],
      'field_sam_neighborhood_data' => $json_data,
      'field_checksum'              => $md5,
      "field_updated_date"          => strtotime("now"),
    ]);
    $node->addCacheableDependency((new CacheableMetadata())->setCacheMaxAge(0));
    $node->save();

    return $node;
  }

  /**
   * Updates the json data and checksum for the SAM ID node.
   *   Directly manipulates the DB table for speed.
   *
   * @param $nid int The entity ID for this node (neighborhood_lookup)
   * @param $json string The json string to save.
   * @param $checksum string The MD5 checksum for the json field.
   *
   * @return void
   */
  public static function MnlUpdateSamData($nid, $json, $checksum) {
    // Update the (json) SAM Data.
    try {
      $result = \Drupal::database()->update("node__field_sam_neighborhood_data")
        ->condition("entity_id", $nid)
        ->fields([
          "field_sam_neighborhood_data_value" => $json,
        ])->execute();
    }
    catch (Exception $e) {
      $result = 0;
    }
    if (!$result) {
      // Edge case, the SAM record has been deleted from the table.
      $node = Node::load($nid);
      $node->set('field_sam_neighborhood_data', $json);
      $node->set('field_checksum', $checksum);
      $node->save();
      // Have updated both the data and checksum, so can exit here.
      return;
    }

    // Update the checksum too.
    \Drupal::database()->update("node__field_checksum")
      ->condition("entity_id", $nid)
      ->fields([
        "field_checksum_value" => $checksum,
      ])->execute();
  }

  /**
   * Updates the SAM Address (mailing address) for the SAM ID node.
   *   Directly manipulates the DB table for speed.

   * @param $nid int The entity ID for this node (neighborhood_lookup).
   * @param $address string The Physical (mailing) Address for the SAM ID.
   *
   * @return void
   */
  public static function MnlUpdateSamAddress($nid, $address) {
    // The SAM Address has changed.
    \Drupal::database()->update("node__field_sam_address")
      ->condition("entity_id", $nid)
      ->fields(["field_sam_address_value" => $address])
      ->execute();

  }

  /**
   * Sets the updated date to now for the SAM ID node.
   *   Directly manipulates the DB table for speed.
   *
   * @param $nid int The entity ID for this node (neighborhood_lookup)
   *
   * @return void
   */
  public static function MnlUpdateSamDate($nid) {
    // Something changed, so update the lastupdated record.
    \Drupal::database()->update("node__field_updated_date")
      ->condition("entity_id", $nid)
      ->fields([
        "field_updated_date_value" => strtotime("now"),
      ])->execute();

  }

  /**
   * Do entire update in one pass using the entity manager.
   *
   * @param int $nid
   * @param array $fields
   *
   * @return bool
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function MnlUpdateSamNode(int $nid, array $fields) {
    $node = Node::load($nid);

    if ($node) {

      try {
        if (!empty($fields)) {
          foreach ($fields as $field => $value) {
            if ($node->hasField($field)) {
              $node->set($field, $value);
            }
          }
        }

        $node->addCacheableDependency((new CacheableMetadata())
          ->setCacheMaxAge(0))
          ->save();

        return TRUE;
      }
      catch (Exception $e) {
        \Drupal::logger("bos_mnl")->error("Mnl:Error - {$e->getMessage()}");
        return FALSE;
      }

    }
    else {
      return FALSE;
    }
  }

  /**
   * Prints a message to the log (use to debuig if dblog and syslog are enabled)
   * and to the std out for drush output re-routed to a log file.
   *
   * @param string $message Message to be logged.
   *
   * @return void
   * @throws \Exception If allowed to bubble up then item will remain
   *                    unprocessed in queue.
   */
  public static function MnlBadData(string $message) {
    // Log the issue.
    $message = trim(str_ireplace("\n", "<br/>", trim($message)));
    \Drupal::logger("bos_mnl")->warning($message);

    // Print to the std output so this gets reflected back to drush.
    $stdmessage = trim(str_ireplace(["<br>", "<br/>", "<br />"], "\n", $message));
    $stdmessage = strip_tags($stdmessage);
    fwrite(STDOUT, $stdmessage);

    // Throw this exception so that the queue processor does not mark this
    // item as done. (if being used by a queueworker)
    throw new Exception($stdmessage);
  }
}
