<?php

namespace Drupal\bos_core;

use Drupal\Core\Database\Database;
use Drupal\file\Entity\File;
use Drupal\file_entity\Entity\FileEntity;
use Drupal\media\Entity\Media;

/**
 * Module to provide common Media entity functions.
 */
class BosCoreMediaEntityHelpers {

  // Constants defined for use here.
  const SYNC_NORMAL = 0;
  const SYNC_IN_MIGRATION = 1;

  // Constants copied from \Drupal\migrate\Plugin\MigrateIdMapInterface.
  const MIGRATE_STATUS_IMPORTED = 0;
  const MIGRATE_ROLLBACK_DELETE = 0;

  /************************************************
   * FILE ENTITIES.
   */

  /**
   * Changes the filename of an existing file entity.
   *
   * @param int $fid
   *   The fid for the entity to be updated.
   * @param string $filename
   *   The new filename to be assigned.
   */
  public static function updateFilename($fid, $filename) {
    // Update the filename if necessary.
    if (NULL != ($file = File::load($fid))) {
      if ($filename != $file->getFilename()) {
        \Drupal::database()->update("file_managed")
          ->condition("fid", $fid)
          ->fields(["filename" => $filename])
          ->execute();
      }
    }
  }

  /**
   * Updates supplied arguments with the maximum observed file entity ID.
   *
   * @param int $last_file_fid
   *   Maximum file entity ID already found.
   * @param int $last_media_vid
   *   Maximum file entity revision ID already found.
   * @param int $mode
   *   Identifies if this is running during a migration or not.
   *
   * @throws \Exception
   */
  public static function findLastIds(&$last_file_fid, &$last_media_vid, $mode) {
    // Get starting value for the fid in files_managed. We need to make this
    // larger than anything we expect to avoid having these overwritten later,
    // or these overwriting files that will be exported later.
    $offset = 1;
    if ($mode == self::SYNC_IN_MIGRATION) {
      // First, set to a big number.
      $last_file_fid = $last_media_vid = 500000;
      $offset = 100;
    }

    try {
      // Second, get the maximum observed fid for files_managed from d7.
      $result = Database::getConnection("default", "migrate")
        ->query("SELECT max(fid) last_fid FROM file_managed")
        ->fetchAssoc();
      if (!empty($result)) {
        $last_file_fid = $result["last_fid"] + $offset;
      }
    }
    catch (\Exception $e) {
      if ($mode == self::SYNC_IN_MIGRATION) {
        throw new \Exception("Cannot access the D7 database");
      }
    }

    // Last, get the maximum observed fid for files_managed from d8 and see
    // if bigger than the number used in d7.
    $result = Database::getConnection("default", "default")
      ->query("select max(fid) last_fid from file_managed")
      ->fetchAssoc();
    if (!empty($result) && $result["last_fid"] >= $last_file_fid) {
      $last_file_fid = $result["last_fid"] + $offset;
      $last_media_vid = $last_file_fid;
    }

    $result = Database::getConnection("default", "default")
      ->query("select max(vid) last_vid from media_field_data")
      ->fetchAssoc();
    if (!empty($result) && $result["last_vid"] >= $last_file_fid) {
      $last_media_vid = $result["last_vid"] + $offset;
    }

  }

  /**
   * Attempts to build a meaningful filename from a given file path and name.
   *
   * Note: This is a copy of FilesystemReorganizationTrait:cleanFilename().
   *
   * @param string $path
   *   The filename and path.
   *
   * @return string
   *   Reformatted filename.
   */
  public static function cleanFilename($path) {
    $filename = explode("/", $path);
    $extension = end($filename);
    $extension = explode(".", $extension);
    $extension = end($extension);
    $filename = array_pop($filename);
    $filename = str_replace([
      "icons",
      "logo",
      ".svg",
      ".jpg",
      ".gif",
      ".jpeg",
      ".png",
      ".txt",
      ".xlsx",
      ".pdf",
    ], "", $filename);
    $filename = str_replace("icon", "", $filename);
    $filename = str_replace(["-", "_", "."], " ", $filename);
    $filename = preg_replace("~\s+~", " ", $filename);
    if (in_array($extension, ["pdf", "xls", "xlsx", "txt"])) {
      $filename .= " (" . $extension . ")";
    }
    return strtolower($filename);
  }

  /**
   * Retrieves fid(s) for corresponding file entity/ies.
   *
   * Note: This is a copy of FilesystemReorganizationTrait:getFileEntities().
   *
   * Best effort search.
   * Assumes the uri have not changed from d7 to d8 during migration, which is
   * just an heuristic.
   *
   * @param string $uri
   *   File uri.
   *
   * @return array|null
   *   Array of file entity objects (\Drupal\file\Entity\File).
   */
  public static function getFileEntities($uri): array|NULL {
    $query = \Drupal::entityQuery("file")
      ->accessCheck()
      ->condition("uri", $uri, "=");
    $entities = $query->execute();
    if (empty($entities)) {
      return NULL;
    }
    return $entities;
  }

  /**
   * Create a new File object and save (in table file_managed).
   *
   * Note: This is a copy of FilesystemReorganizationTrait:createFileEntity().
   *
   * @param string $uri
   *   The uri to create in the file_managed table.
   * @param int $fid
   *   Force the fid value.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The file object just created.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function createFileEntity(string $uri, int $fid = 0): FileEntity {
    $filename = self::cleanFilename($uri);
    $fields = [
      'uri' => $uri,
      'uid' => '1',
      'filename' => $filename,
      'status' => '1',
    ];
    if ($fid != 0) {
      $fields["fid"] = $fid;
    }
    $file_entity = \Drupal::entityTypeManager()
      ->getStorage('file')
      ->create($fields);
    $file_entity->save();
    return $file_entity;
  }

  /************************************************
   * MEDIA ENTITIES.
   */

  /**
   * Fetch one or more media entities which refer to a file entity.
   *
   * @param int $fid
   *   The fid filter. Media entities referring to this file will be returned.
   * @param string|null $type
   *   The media bundle type to filter.
   *
   * @return array|null
   *   An assoc array of filtered media objects.
   */
  public static function getMediaEntityFromFile($fid, $type = NULL) {
    $query = \Drupal::database()->select("media_field_data", "fd")
      ->fields("fd", ["mid", "thumbnail__target_id"]);
    $query->innerJoin("media__image", "i", "fd.mid = i.entity_id");
    $query->innerJoin("file_managed", "f", "i.image_target_id = f.fid");
    $query->condition("f.fid", $fid, "=");
    if (!empty($type)) {
      $query->condition("i.bundle", $type, "=");
    }
    if ($result = $query->execute()->fetchAll()) {
      return $result;
    }
    else {
      return NULL;
    }
  }

  /**
   * Creates a new Media entity.
   *
   * @param int $fid
   *   The file entity ID this media entity references.
   * @param int $uid
   *   The username creating (i.e. author) this new media entity.
   * @param string $filename
   *   A filename to associate with this new media entity.
   * @param string $type
   *   A bundle type for this new media entity.
   * @param int $mid
   *   Media Entity ID - If not provided, an auto entity ID will be assigned.
   * @param int $vid
   *   Media Entity revision ID - If not provided, an auto ID will be assigned.
   * @param bool $in_library
   *   Should this media entity be included in the media library.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The new media entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function createMediaEntity($fid, $uid, $filename, $type, $mid = 0, $vid = 0, $in_library = TRUE) {
    $media_fields = [
      'bundle' => $type,
      'uid' => $uid ?? 1,
      'status' => 1,
      'name' => $filename,
      'image' => [
        'target_id' => $fid,
      ],
    ];
    if ($mid != 0) {
      // If mid provided then set mid and vid to initially be the same.
      $media_fields["mid"] = $mid++;
      $media_fields["vid"] = $mid;
    }
    if ($vid != 0) {
      // If vid provided then specify that.
      $media_fields["vid"] = $vid++;
    }
    $media = Media::create($media_fields);
    $media->set("field_media_in_library", ($in_library ? 1 : 0));
    $media->save();
    return $media;
  }

  /**
   * Force a media entity into the library via a DB call.
   *
   * @param int $mid
   *   The media entity id.
   * @param bool $in_library
   *   Should this media entity be included in the media library.
   */
  public static function updateMediaLibrary($mid, $in_library = TRUE) {
    \Drupal::database()->update("media__field_media_in_library")
      ->condition("entity_id", $mid)
      ->fields(["field_media_in_library_value" => ($in_library ? 1 : 0)])
      ->execute();
  }

  /************************************************
   * MIGRATION UTITLITIES.
   */

  /**
   * Creates an entry in the migrations mapping table for use during migrations.
   */
  public static function createMigrateMap() {
    $result = \Drupal::database()->query("SHOW TABLES LIKE 'migrate_map_d7_file';")
      ->fetchAll();
    if (empty($result)) {
      \Drupal::database()->query("CREATE TABLE `migrate_map_d7_file` (
      `source_ids_hash` varchar(64) NOT NULL COMMENT 'Hash of source ids. Used as primary key',
      `sourceid1` int(11) NOT NULL,
      `destid1` int(10) unsigned DEFAULT NULL,
      `source_row_status` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT 'Indicates current status of the source row',
      `rollback_action` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT 'Flag indicating what to do for this item on rollback',
      `last_imported` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'UNIX timestamp of the last time this row was imported',
      `hash` varchar(64) DEFAULT NULL COMMENT 'Hash of source row data, for detecting changes',
      PRIMARY KEY (`source_ids_hash`),
      KEY `source` (`sourceid1`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Mappings from source identifier value(s) to destination…';");
    }
  }

  /**
   * Creates a migration message for use during migrations.
   */
  public static function createMigrateMessage() {
    $result = \Drupal::database()->query("SHOW TABLES LIKE 'migrate_message_d7_file';")
      ->fetchAll();
    if (empty($result)) {
      \Drupal::database()->query("CREATE TABLE `migrate_message_d7_file` (
      `msgid` int(10) unsigned NOT NULL AUTO_INCREMENT,
      `source_ids_hash` varchar(64) NOT NULL COMMENT 'Hash of source ids. Used as primary key',
      `level` int(10) unsigned NOT NULL DEFAULT '1',
      `message` mediumtext NOT NULL,
      PRIMARY KEY (`msgid`)
      ) ENGINE=InnoDB AUTO_INCREMENT=143506 DEFAULT CHARSET=utf8mb4 COMMENT='Messages generated during a migration process';");
    }
  }

  /**
   * Creates simple source:dest entry in the migrate_map_xxx table for lookups.
   *
   * Note: This is a copy of:
   *    FilesystemReorganizationTrait:createSimpleMappingEntry().
   *
   * @param int $sourceid
   *   The oringinal ID to map.
   * @param string $destid
   *   The new ID to map.
   * @param string $mig_type
   *   The table type to write to.
   * @param string $hash
   *   The hash (optional) - Note row hash is created in-function.
   *
   * @throws \Exception
   */
  public static function createSimpleMappingEntry($sourceid, $destid, $mig_type, $hash = "") {
    try {
      $fields["sourceid1"] = $sourceid;
      $fields += [
        'source_row_status' => BosCoreMediaEntityHelpers::MIGRATE_STATUS_IMPORTED,
        'rollback_action' => BosCoreMediaEntityHelpers::MIGRATE_ROLLBACK_DELETE,
        'hash' => $hash,
      ];
      $fields["destid1"] = $destid;
      $fields["last_imported"] = 0;
      $row_hash = hash('sha256', serialize(array_map('strval', [$sourceid])));
      $keys = ["source_ids_hash" => $row_hash];

      $table_name = "migrate_map_" . $mig_type;

      \Drupal::database()->delete($table_name)
        ->condition("sourceid1", $fields["sourceid1"])
        ->condition("destid1", $destid)
        ->execute();

      \Drupal::database()->merge($table_name)
        ->keys($keys)
        ->fields($fields)
        ->execute();
    }
    catch (\Exception $e) {
      // Got an error. SQL-23000 is a duplicate row entry, thats OK so allow
      // it but dont allow anything else.
      if ($e->getCode() != 23000) {
        throw new \Exception($e->getMessage(), $e->getCode());
      }
    }
  }

}
