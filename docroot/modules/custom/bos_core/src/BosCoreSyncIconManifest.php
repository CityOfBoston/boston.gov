<?php

namespace Drupal\bos_core;

use Drupal;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\file\Entity\File;
use Drupal\file_entity\Entity\FileEntity;
use Exception;

/**
 * Class BosCoreSyncIconManifestService.
 *
 *    Reads a manifest file and creates media (icon) entries assoc with files.
 *    Drush command to switch is: drush import-icon-manifest (alias biim)
 *
 * @see modules/custom/bos_core/src/Commands/BosCoreCommands.php
 *
 * @package Drupal\bos_core
 */
class BosCoreSyncIconManifest {

  /**
   * Read the manifest file and create media and file entities.
   *
   * @param int $mode
   *   Determine if we are using this during a migration.
   *
   * @return bool
   *   Whether the manifest file was sucessfully processed.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function import($mode = BosCoreMediaEntityHelpers::SYNC_NORMAL) {

    // Load the manifest file.
    try {
      $manifest = self::loadManifestFile();
    }
    catch (\Exception $e) {
      printf("[warning] %s\n", $e->getMessage());
      Drupal::logger("drush")->warning($e->getMessage());
      return FALSE;
    }

    // Extract only icons not in the cache.
    $numIcons = count($manifest);
    $manifest = self::findNewManifestIcons($manifest);

    if (empty($manifest)) {
      printf("[notice] All icons in manifest are already in the media library !.\n");
      return TRUE;
    }

    // Keep track of what's been done.
    $cnt = [
      "Total" => 0,
      "Imported" => 0,
      "Media" => 0,
      "Found" => 0,
      "mFound" => 0,
    ];

    // Find the max fid in the files table and the max vid from the media table.
    $last = ["fid" => 0, "vid" => 0];
    try {
      BosCoreMediaEntityHelpers::findLastIds($last["fid"], $last["vid"], $mode);
    }
    catch (\Exception $e) {
      printf("[error] %s\n", $e);
      Drupal::logger("drush")->error($e);
      return FALSE;
    }

    // Process each row of the manifest file in turn.
    $manifest_cache = Drupal::cache("icon_manifest");
    foreach ($manifest as $icon_uri) {
      if (!empty($icon_uri)) {
        self::processFileUri($icon_uri, $last, $cnt);
        $manifest_cache->set($icon_uri, TRUE, CacheBackendInterface::CACHE_PERMANENT);
      }
    }

    // Save the new highwater mark (=file timestamp from when it was loaded).
    $working_date = Drupal::config("bos_core.settings")->get("working_date") ?? 0;
    Drupal::configFactory()->getEditable("bos_core.settings")
      ->set("icon.manifest_date", $working_date)
      ->save();

    // Log some stats.
    $manifest_file = Drupal::config("bos_core.settings")
      ->get("icon.manifest");

    printf("\nImports icons from %s\n", $manifest_file);
    printf("Manifest defines %d icon files.\n", $numIcons);
    printf("---------------- ---------- --------- ----------\n");
    printf("Group            Manifest   Updated   Added \n");
    printf("---------------- ---------- --------- ----------\n");
    printf("Icon Library       %s         %s        %s\n", count($manifest), $cnt["mFound"], $cnt["Media"]);
    printf("---------------- ---------- --------- ----------\n");

    return TRUE;

  }

  public static function loadQueue() {
    // Load the manifest file.
    try {
      $manifest = self::loadManifestFile();
    }
    catch (Exception $e) {
      return;
    }

    if (!empty($manifest)) {

      // Find only icons not already in the cache.
      $manifest = self::findNewManifestIcons($manifest);

      // Get the manifest cache and queue
      $queue = Drupal::service('queue')->get('cron_manifest_processor');

      $count = 0;
      $log = [];

      // Load up the queue with new icons found in file.
      foreach ($manifest as $icon) {
        $queue->createItem($icon);
        if ($count++ <= 10) {
          $log[] = "Icon '$icon' found in manifest and queued,";
        }
      }

      // Save the new highwater mark (=file timestamp from when it was loaded).
      $working_date = Drupal::config("bos_core.settings")->get("working_date") ?? 0;
      Drupal::configFactory()->getEditable("bos_core.settings")
        ->set("icon.manifest_date", $working_date)
        ->save();

      // Do some logging.
      $log[] = "... total of $count icons found in manifest and queued.";
      $log = implode("<br>", $log);
      Drupal::logger("cron")->info($log);

    }

  }


  /**
   * Create or Update File and Media entities.
   *
   * @param string $icon_uri
   *   The file URI.
   * @param array $last
   *   The max File Entity ID known.
   * @param array $cnt
   *   The max File Entity Revision ID known.
   * @param int $migration_enabled
   *   Is this running during a migration.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function processFileUri(string $icon_uri, array &$last = [], array &$cnt = []) {
    $icon_uri = trim($icon_uri);
    if (empty($icon_uri)) {
      return;
    }
    $icon_filename = self::cleanFilenameFolder($icon_uri);

    empty($cnt) ?: $cnt["Total"]++;

    // Try to get (or create) the file from/in the file_managed table.
    $file = NULL;
    $file_ids = self::retrieveFileEntities($icon_uri, $icon_filename, $file, $last, $cnt);

    // We should now have files in file_managed for all $file_ids.
    // Try to find a media entity attached to each file_id.
    foreach ($file_ids as $key => $file_id) {
      $result = BosCoreMediaEntityHelpers::getMediaEntityFromFile($file_id, "icon");
      if (empty($result)) {
        // Need to create a matching media entity for this file entity.
        if (NULL == $file || $file->id() != $file_id) {
          $file = File::load($file_id);
        }

        $last["mid"] = 0;
        $last["vid"] = 0;
        $media = BosCoreMediaEntityHelpers::createMediaEntity($file->id(), $file->getOwnerId(), $icon_filename, "icon", $last["mid"], $last["vid"]);
        empty($cnt) ?: $cnt["Media"]++;
        // Only put the first media item into the library.
        BosCoreMediaEntityHelpers::updateMediaLibrary($media->id(), ($key == 0));
      }
      else {
        // Already is a media item for this file.
        foreach ($result as $media_entity) {
          empty($cnt) ?: $cnt["mFound"]++;
          BosCoreMediaEntityHelpers::updateMediaLibrary($media_entity->mid, ($key == 0));
          if ($key == 0) {
            break;
          }
        }
      }
      if ($key == 0) {
        break;
      }
    }
  }

  /**
   * Makes a clean filename with its containing folder.
   *
   * @param string $path
   *   The filename and full path.
   *
   * @return string
   *   The filename and its containing folder.
   */
  private static function cleanFilenameFolder($path) {
    $filename = BosCoreMediaEntityHelpers::cleanFilename($path);
    $path_bits = explode("/", $path);
    $path_bits = array_reverse($path_bits);
    if (!empty($path_bits[1])) {
      $filename = BosCoreMediaEntityHelpers::cleanFilename($path_bits[1]) . " " . $filename;
    }
    return $filename;
  }

  /**
   * Find the manifest file and load into an array.
   *
   * @return array
   *   An array with each element containing a row of the manifest file.
   *
   * @throws \Exception
   */
  public static function loadManifestFile() {
    // Get the manifest file name as set in the settings form.
    $manifest_file = Drupal::config("bos_core.settings")->get("icon.manifest");

    if (empty($manifest_file)) {
      $manifest_file = "https://assets.boston.gov/manifest/icons_manifest.txt";
      Drupal::configFactory()->getEditable('bos_core.settings')
        ->set('icon.manifest', $manifest_file)
        ->set('icon.manifest_date', 0)
        ->save();
    }

    // Fetch the contents of the manifest file.
    $manifest = file_get_contents($manifest_file);
    if (empty($manifest)) {
      throw new \Exception("Icon manifest file not found!");
    }

    // Check timestamp.
    $highwater = (Drupal::config("bos_core.settings")->get("icon.manifest_date") ?? 0);
    foreach($http_response_header as $header) {
      $parts = explode(":", $header, 2);
      if (strtolower(trim($parts[0])) == "last-modified") {
        $timestamp = strtotime($parts[1]);
        if ($timestamp > $highwater) {
          // Set a flag using the current file timestamp.
          Drupal::configFactory()->getEditable("bos_core.settings")
            ->set("working_date", $timestamp)
            ->save();
          // Explode each line in the file into a row in an array and return.
          return explode("\n", $manifest);
        }
        else {
          // This file is older than or equal to the highwater mark. Remove the
          // working date timestamp/flag and return an empty set.
          Drupal::configFactory()->getEditable("bos_core.settings")
            ->set("working_date", "0")
            ->save();
          return [];
        }
      }
    }

    // No Last-Modified date found in headers. Have to check the file again.
    Drupal::configFactory()->getEditable("bos_core.settings")
      ->set("working_date", $highwater)
      ->save();
    return [];

  }

  /**
   * Scans the manifest array and checks to see which elements are not in
   * the cache.
   *
   * @param array $manifest
   *
   * @return array
   */
  public static function findNewManifestIcons(array $manifest): array {

    $output = [];

    if (!empty($manifest)) {
      // Get the manifest cache.
      $manifest_cache = Drupal::cache("icon_manifest");
      foreach ($manifest as $icon) {
        if (!$manifest_cache->get($icon)) {
          $output[] = trim($icon);
        }
      }
    }

    return $output;

  }

  /**
   * Retrieve all file entitie which have this uri.
   *
   * If there are no entities, then create a new one.
   *
   * @param string $icon_uri
   *   The URI for the icon file.
   * @param string $icon_filename
   *   The filename to assign this file.
   * @param object $file
   *   This will be populated if a new file is created.
   * @param array $last
   *   Array with the last known file ID and revsision.
   * @param array $cnt
   *   The process counter array.
   * @param int $migration_enabled
   *   Whether this is being called during a migration.
   *
   * @return array|null
   *   An array of file entities.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function retrieveFileEntities(string $icon_uri, string $icon_filename, ?FileEntity &$file, array &$last = [], array $cnt = []):array|NULL {
    // Fetch all file entities which reference this uri.
    $file_ids = BosCoreMediaEntityHelpers::getFileEntities($icon_uri);

    $multiple = (!empty($file_ids) && count($file_ids) > 1);

    if (empty($file_ids)) {
      if (!isset($last["fid"])) {
        $last["fid"] = 0;
      }
      // The file does not exist in file_managed table, so create it.
      $file = BosCoreMediaEntityHelpers::createFileEntity($icon_uri, $last["fid"]);
      // Now add the file to the list of files.
      $file_ids[] = $file->id();
      $last["fid"] = $file->id() + 1;
      empty($cnt) ?: $cnt["Imported"]++;
    }
    else {
      // Icon does already exist (at least once) in the file_managed table.
      $file_ids = array_values($file_ids);
      empty($cnt) ?: ($cnt["Found"] = $cnt["Found"] + count($file_ids));
      // The file already exists - check and update filename if needed.
      if (!$multiple) {
        BosCoreMediaEntityHelpers::updateFilename($file_ids[0], $icon_filename);
      }
      else {
        foreach ($file_ids as $file_id) {
          BosCoreMediaEntityHelpers::updateFilename($file_id, $icon_filename);
        }
      }
    }
    return $file_ids;
  }

}
