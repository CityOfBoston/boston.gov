<?php

namespace Drupal\bos_mnl\Commands;

use Drush\Commands\DrushCommands;
use Drupal\bos_mnl\Controller\MnlUtilities;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class MnlDrushCommands extends DrushCommands {

  /**
   * My Neighborhood Lookup: SAM node cache clear.
   *
   * @param string $samid
   *   The SAM ID to clear, or blank for all
   *
   * @validate-module-enabled bos_mnl
   *
   * @usage drush bos:mnl-clear-cache
   *   Clear the cache for all SAM nodes (takes a long time).
   * @usage drush bos:mnl-clear-cache <SAM_ID>
   *   Clear the cache for this SAM record - (note the SAMID not the nid).
   *
   * @command bos:mnl-clear-cache
   * @aliases bmnlcc,
   */
  public function cacheClear($samid = NULL) {

    if (empty($samid)) {
      $storage = \Drupal::entityTypeManager()->getStorage("node");
      $count = $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition("type", "neighborhood_lookup")
        ->count()
        ->execute();

      $check = "Are you sure you want to update all " . number_format($count, 0, ".",",") . " SAM Records (Y/N) ?";
      $check = $this->io()->ask($check, NULL);
      if (strtoupper($check) != "Y") {
        $this->output()->writeln("Cancelled.");
        return;
      }

      $samid = [];
    }
    elseif (is_string($samid)) {
      $samid = preg_split("/[, ]/", $samid);
    }
    if (!is_array($samid)) {
      $samid = [trim($samid)];
    }

    $affected = MnlUtilities::MnlCacheClear($samid);

    $this->output()->writeln("Caches for " . $affected . " SAM Data records were refreshed.");


  }

  /**
   * My Neighborhood Lookup: Purge old SAM records.
   *
   * @validate-module-enabled bos_mnl
   *
   * @usage drush bos:mnl-purge
   *   Will purge records not updated in last 2 days from the Neighborhood Lookup module.
   * @usage drush bos:mnl-purge <DAYS>
   *   Will purge records not updated in last <DAYS> days from the Neighborhood Lookup module.
   *
   * @command bos:mnl-purge
   * @aliases bmnlp,
   *
   * @param int $days Age in days since record was last updated (i.e. age after which purge occurs).
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function cleanUpSamTable(int $days = 2) {
    // Get a count of records which will be purged.
    $cutdate = "${days} days ago";
    $count = MnlUtilities::MnlSelectCleanUpRecords($cutdate, TRUE);

    if ($count == 0) {
      $this->output()->writeln("There are no SAM records matching purge filter.");
      return;
    }

    $check = "Are you sure you want to purge " . number_format($count, 0, ".",",") . " SAM Records (Y/N) ?";
    $check = $this->io()->ask($check, NULL);
    if (strtoupper($check) != "Y") {
      $this->output()->writeln("Cancelled.");
      return;
    }

    try {
      $result = MnlUtilities::MnlCleanUp($cutdate);
    }
    catch (\Exception $e) {
      $this->output()->writeln($e->getMessage());
    }
    $this->output()
      ->writeln("Purged ${result} SAM Data records not updated in the last ${days}.");
    \Drupal::logger("drush")
      ->info("Purged ${result} SAM Data records not updated in the last ${days}.");

  }

}
