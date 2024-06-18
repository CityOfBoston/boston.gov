<?php

namespace Drupal\node_buildinghousing\Commands;

use Drupal\node_buildinghousing\BuildingHousingUtils;
use Drush\Commands\DrushCommands;
use Symfony\Component\Yaml\Yaml;

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
class BHCommands extends DrushCommands {

  /**
   * Boston BH Project on-site state switcher. Alter the visibility and banners on BH Properties.
   *
   * @option sfid A comma separated list of sfid's for BH Projects to be altered in Drupal.
   * @option state When sfid is provided, this is the on-site state to change the BH Project to. Defaults to "active".
   * @option filepath Local server path or URL to a json file containing sfid and state key-values pairs.
   *
   * @validate-module-enabled node_buildinghousing
   *
   * @command bos:bhstate
   * @aliases bbhs,bos-bh-state
   */
  public function alterState($options = ['sfid'=>NULL, 'state'=>'active', 'filepath'=>NULL]): void {

    // Determine the request type and validate
    if ($options["sfid"]) {
      $data = explode(",", $options["sfid"]);
      if ($options["state"]) {
        $state = strtolower($options["state"]);
        if (!BuildingHousingUtils::isAllowedState($state)) {
          $allowed_states = implode(", ", BuildingHousingUtils::getAllowedStates());
          $this->output()->writeln("[ERROR] State {$state} must be one of '{$allowed_states}'.");
          return;
        }
      }
    }

    if ($options["filepath"]) {
      $filepath = $options["filepath"];
      if (!file_exists($filepath)) {
        $this->output()->writeln("[ERROR] Cannot open file at {$filepath}");
        return;
      }
    }

    if (!empty($options["sfid"]) && !empty($options["filepath"])) {
      $this->output()->writeln("[ERROR] Cannot provide both sfid and filepath !");
      return;
    }
    elseif (empty($options["sfid"]) && empty($options["filepath"])) {
      $this->output()->writeln("[ERROR] Need to provide either sfid or filepath");
      return;
    }

    $results = [[], [], []];

    if (!empty($data)) {

      // Process the supplied sfid's.
      foreach($data as $sfid) {

        $sfid = trim($sfid);
        $result = $this->updateNode($sfid, $state, TRUE);
        $results[$result][] = $sfid;

      }

    }

    elseif (!empty($filepath)) {

      // Process the file.
      $data = file_get_contents($filepath);

      foreach($data as $record) {

        // Check the record has state
        if (empty($record["state"])) {
          $record["state"] = "active";
        }

        // Check the record has a valid state
        if (!BuildingHousingUtils::isAllowedState($record["state"])) {
          $allowed_states = implode(", ", BuildingHousingUtils::getAllowedStates());
          $this->output()->writeln("[WARNING] State for {$record["sfid"]} cannot be {$record["state"]}, it must be one of '{$allowed_states}'.");
          $results[0][] = $record["sfid"];
          continue;
        }

        // Update the Drupal record.
        $result = $this->updateNode($record["sfid"], $record["state"], TRUE);
        $results[$result][] = $record["sfid"];

      }

    }

    $this->output()->writeln("Update complete");
    $count0 = count($results[0]);
    $count1 = count($results[1]);
    $count2 = count($results[2]);
    $count3 = $count0 + $count1 + $count2;
    $this->output()->writeln("[SUMMARY] {$count3} SFID's processed: {$count1} updated, {$count2} skipped, {$count0} failed to update.");

  }

  /**
   * Finds the Drupal record which is mapped to the SFID and then changes the
   * moderation state and field_banner according to the supplied state.
   * Always creates a new revision.
   * DIG-1055
   *
   * @param string $sfid The salesforce ID
   * @param string $state The state to use (one of $this->valid_states)
   * @param bool $drush Flag when called from drush (for console outputs)
   *
   * @return int where 0=failed, 1=updated, 2=nothing done
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function updateNode(string $sfid, string $state, bool $drush = FALSE): int {



    // Find Entity mapped to this SFID
    $nids = BuildingHousingUtils::findEntityIdBySFID($sfid, "bh_project");
    if (!$nids) {
      $drush && $this->output()->writeln("[WARNING] A record with SFID {$sfid} could not be found in Drupal.");
      return 0;
    }

    $state = BuildingHousingUtils::findBannerTaxonomyByName($state);

    // Update the Entity with state/banner info.
    // Even though this is a foreach loop, only the first returned bh_project
    // nid will be processed because there should not be multiple maps for a
    // bh_project.
    foreach ($nids as $nid) {

      // Load the bh_project node.
      $nid = $nid->drupal_entity__target_id;
      $project = \Drupal::entityTypeManager()
        ->getStorage("node")
        ->load($nid);

      if (!$project) {
        $drush && $this->output()->writeln("[WARNING] The BH_Project '{$sfid}' is marked as mapped from SF but the record (nid={$nid}) could not be found in Drupal.");
        return 0;
      }

      // Now find and load the linked bh_update so the banner can be set.
      $update_nid = \Drupal::entityQuery("node")
        ->accessCheck(FALSE)
        ->condition("type", "bh_update", "=")
        ->condition("field_bh_project_ref", $nid, "=")
        ->execute();
      if (!$update_nid) {
        $drush && $this->output()->writeln("[WARNING] The BH_Project '{$nid}' does not have a website update");
        return 0;
      }
      $update_nid = reset($update_nid);

      $update = \Drupal::entityTypeManager()
        ->getStorage("node")
        ->load($update_nid);
      if (!$update) {
        $drush && $this->output()->writeln("[WARNING] The bh_update '{$update_nid}' is linked to a bh_project {$nid}, but cannot be loaded.");
        return 0;
      }

      // Check if values need updating.
      if (strtolower($project->get("moderation_state")->value) == strtolower($state["mod_state"])
        && $update && $update->get("field_bh_banner_status")->target_id == $state["tid"]) {
        // These nodes already have this state.
        return 2;
      }

      // Set values on node and save.
      if (strtolower($project->get("moderation_state")->value) != strtolower($state["mod_state"])) {

        // Be sure to make a new revision.
        $project->setNewRevision(TRUE);
        $project->set("moderation_state", strtolower($state["mod_state"]));

        try {
          if ($project->save() != 2) {
            // Houston, we have a problem (2 = updated).
            $drush && $this->output()
              ->writeln("[WARNING] SFID {$sfid} did not update bh_project {$nid} to {$state}.");
            return 0;
          }
        }
        catch (\Exception $e) {
          $this->output()->writeln("[WARNING] SFID {$sfid} attempt to update {$nid} to {$state["name"]} threw error:");
          $this->output()->writeln($e->getMessage());
          return 0;
        }
      }

      if ($update && $update->get("field_bh_banner_status")->target_id != $state["tid"]) {

        $update->set("field_bh_banner_status", [$state["term"]]);

        try {
          if ($update->save() != 2) {
            // Houston, we have a problem (2 = updated).
            $drush && $this->output()
              ->writeln("[WARNING] SFID {$sfid} did not update bh_update {$nid} to {$state}.");
            return 0;
          }
        }
        catch (\Exception $e) {
          $this->output()->writeln("[WARNING] SFID {$sfid} attempt to update {$nid} to {$state} threw error:");
          $this->output()->writeln($e->getMessage());
          return 0;
        }
      }

      return 1;

    }

  }

}
