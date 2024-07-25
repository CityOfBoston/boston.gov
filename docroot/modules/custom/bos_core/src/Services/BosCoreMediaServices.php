<?php

namespace Drupal\bos_core\Services;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\file\FileUsage\DatabaseFileUsageBackend;
use Drupal\media\Entity\Media;
use Drupal\file\Entity\File;
use Symfony\Component\Finder\Finder;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;

/**
 * Service to manage Media Entities.
 */

class BosCoreMediaServices {

  use DependencySerializationTrait;

  const STAGED_FILES = "StagedFiles";
  const FILE_ENTITY = "FileEntity";
  const MEDIA_ENTITY = "MediaEntity";

  /**
   * Logger object for class.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannel $log;

  /**
   * Config object for class.
   *
   * @var \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;
  protected DatabaseFileUsageBackend $file_usage;
  protected FileSystem $file_system;

  /** @var string The root directory where files are stored */
  protected string $files_path;
  protected string $archive_path;

  /**
   * Media Manager constructor.
   *
   * @inheritdoc
   */
  public function __construct(LoggerChannelFactory $logger, ConfigFactory $config, DatabaseFileUsageBackend $file_usage, FileSystem $file_system) {
    $this->log = $logger->get('FileCleanup');
    $this->config = $config->get("bos_core.settings");
    $this->file_usage = $file_usage;
    $this->file_system = $file_system;
    $this->files_path = $file_system->realpath("public://");
    $this->archive_path = $this->files_path . "/media_service_archive";
    if(!file_exists($this->archive_path)) {
      mkdir($this->archive_path);
    }
  }

  /**
   * Checks each file in the public:// folder and subfolders (recursively) to
   * see if the file is loaded into the media services for Drupal.
   *
   * Outputs 2 files, one with stats and one with files that:
   *   1. Are not registered as file entities in Drupal (probably safe to delete)
   *   2. Are File entities but are not used in any other entity (page) in boston.gov
   *
   * **Note**: Files are not ever deleted, they are moved to the same subfolder location in the archive folder.
   *
   * @param bool $archive If False changes are just logged to file. If True files are moved to archive folder.
   * @param string $file_ext Comma separated list of file extensions to check for
   * @param string $exclude_paths Commas separated list of folder to exclude when searching.
   * @param int $count Maximum number of files to search. 0 = All
   *
   * @return void
   */
  public function CheckStagedFiles (bool $archive = FALSE, string $file_ext = "", string $exclude_paths = "", int $count = 0, $hasUI = TRUE): void {

    // Use the finder object to recursively find files in folder.
    $finder = Finder::create()
      ->files()
      ->in($this->files_path);

    // If excluded paths were provided, use them now. Or else use default.
    $excluded_paths_array = ["private", "styles", "tmp", "pdf_templates", "election_results"];
    if (!empty($exclude_paths)) {
      $excluded_paths_array = explode(",", $exclude_paths);
      array_walk($excluded_paths_array, function (&$value, $key) {
        $value = trim($value);
      });
    }
    // Always exclude the archive path ... otherwise get recursive copying.
    $tmp = explode("/", $this->archive_path);
    $excluded_paths_array[] = trim(array_pop($tmp));
    $finder->exclude($excluded_paths_array);

    // If file extensions were provided, use them now. Or else use default.
    $extensions_array = ["jpg", "jpeg", "png", "gif", "pdf"];
    if (!empty($file_ext)) {
      $extensions_array = explode(",", $file_ext);
      array_walk($extensions_array, function (&$value, $key) {
        $value = trim($value);
      });

    }
    $extensions = implode("|", $extensions_array);
    $finder->name('/\.(' . $extensions . ')$/i');

    // Write archive records to file.
    $archive_report = fopen("$this->files_path/" . $this::STAGED_FILES ."_List.txt", "w");
    fwrite($archive_report, "\"Status\", \"Filename\", \"Action\"" . PHP_EOL);
    $finder->notName($this::STAGED_FILES . "_FileList.txt");
    // Write stats to file.
    $stats_report = fopen("$this->files_path/" . $this::STAGED_FILES . "_Stats.json", "w");
    $finder->notName($this::STAGED_FILES . "_Stats.json");

    // Need to make an array out of the finder so the files can be serialized
    // when passed to the callback. Non-serializable objects throw errors.
    foreach ($finder as $file) {
      $items[] = [
        "RealPath" => $file->getRealPath(),
        "RelativePathname" => $file->getRelativePathname(),
      ];
      if ($count !== 0 && $count <= count($items)) {
        // Just process the number requested.
        break;
      }
    }

    $this->setBatch($items, self::STAGED_FILES, $archive, $hasUI);

    fclose($archive_report);
    fclose($stats_report);

  }

  /**
   * Checks each File Entity to see that the actual file referenced in the
   * object exists on the physical file system.
   *
   * @param bool $cleanup Remove File entities which have no physical file.
   * **Note: May create a broken reference for any Media entity associated with the File Entity**
   * @param int $count Max number of file entities to check. 0 = All
   *
   * @return void
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function FileEntityIntegrityCheck(bool $cleanup = FALSE, int $count = 0, bool $hasUI = TRUE): void {

    // Write archive records to file.
    $file_entity_report = fopen($this->files_path . "/" . $this::FILE_ENTITY . "_List.txt", "w");
    fwrite($file_entity_report, "\"Status\", \"DrupalURI\", \"Action\"" . PHP_EOL);
    // Write stats to file.
    $stats_report = fopen($this->files_path . "/" . $this::FILE_ENTITY . "_Stats.json", "w");

    // Fetch the set of FileEntities in the DB
    $qry = \Drupal::entityQuery('file')
      ->accessCheck(FALSE);
    if ($count != 0) {
      $qry->range(0, $count);
    }
    $fids = $qry->execute();

    // load the batch.
    $this->setBatch($fids, self::FILE_ENTITY);

    fclose($file_entity_report);
    fclose($stats_report);

  }

  /**
   * Checks each Media Entity to see that its linked File Entity exists.
   *
   * @param bool $cleanup
   *
   * @return void
   */
  public function MediaEntityIntergityCheck(bool $cleanup = FALSE, int $count = 0, bool $hasUI = TRUE): void {

    // Write archive records to file.
    $media_entity_report = fopen("$this->files_path/" . self::MEDIA_ENTITY ."_List.txt", "w");
    fwrite($media_entity_report, "\"Status\", \"MediaID\", \"Media Name\", \"Action\"" . PHP_EOL);
    // Write stats to file.
    $stats_report = fopen($this->files_path . "/" . $this::MEDIA_ENTITY . "_Stats.json", "w");

    // Fetch a block of Entities.
    $qry = \Drupal::entityQuery('media')
      ->accessCheck(FALSE);
    if ($count != 0) {
      $qry->range(0, $count);
    }
    $mids = $qry->execute();

    // load the batch.
    $this->setBatch($mids, self::MEDIA_ENTITY);

    fclose($media_entity_report);
    fclose($stats_report);

  }

  /**
   * Find Empty folders and remove them.
   *
   * @return void
   */
  public function FindEmptyFolders():void {}

  /**
   * Moves the selected file to the archive folder.
   *
   * @param string $filename The file to be archived
   *
   * @return void
   */
  private function archive(string $filename): void {
    $arc_name = str_ireplace($this->files_path, $this->archive_path, $filename);
    $tmp = explode("/", $arc_name);
    array_pop($tmp);
    $path = implode("/", $tmp);
    if(!file_exists($path)) {
      mkdir($path,0777, TRUE);
    }
    rename($filename, $arc_name);
  }

  /**
   * Check if the given file is used in any Entity.
   *
   * @param \Drupal\file\Entity\File $file
   *   Drupal file entity to check its usage.
   *
   * @return bool|string
   *   The entitytype if the file is used in an entity, False otherwise.
   */
  private function isFileUsed(File $file): bool|string {

    if (!$usage = $this->file_usage->listUsage($file)) {
      // The file is not referenced by any other Entity.
      return FALSE;
    }

    return (count($usage) > 0);

  }

  /**
   * Check if the given file is used in any node.
   *
   * @param \Drupal\file\Entity\File $file
   *   Drupal file entity to check its usage.
   *
   * @return bool|string
   *   The entitytype if the file is used in an entity, False otherwise.
   */
  private function isFileUsedInNode(File $file): bool|string {

    if (!$usage = $this->file_usage->listUsage($file)) {
      // The file is not referenced by any other Entity.
      return FALSE;
    }

    foreach ($usage as $module => $usage_info) {

      if (in_array($module, ['file'])) {  //file,webform,editor

        foreach ($usage_info as $type => $usage_details) {

          // Nodes are nodes, so they are always true
          // Paragraphs must ultimately exist on a node (or taxonomy), so they
          // are true.
          // Media objects should be retained.
          if (in_array($type, ['node', 'paragraph', 'media'])) {   //node, media, paragraph, user
            return TRUE;
          }

        }
      }

      else {
        // This File is used in some other less common Entity, e.g. Webforms.
        return TRUE;
      }

    }

    return FALSE;

  }

  /**
   * Creates a batch for processing.
   *
   * @param mixed $items An iterable object/array of items to batch process
   * @param string $type The type of process to be performed
   * @param bool $hasUI Should we show a progress dialog.
   *
   * @return void
   */
  public function setBatch(array $items, string $type, bool $process = FALSE, bool $hasUI = TRUE): void {

    // Sets up our batch.
    $batch_builder = new BatchBuilder();
    $batch_builder
      ->setErrorMessage('Media Services has encountered an error.')
      ->setFinishCallback([$this, 'finish']);

    foreach ($items as $item) {
      switch ($type) {
        case self::STAGED_FILES:
          $batch_builder->setTitle('Checking Staged Media Files...')
            ->setInitMessage('Fun Stuff is Happening...')
            ->addOperation([$this, 'processStagedFiles'], [$item, $process]);
          break;
        case self::FILE_ENTITY:
          $batch_builder->setTitle('Validating Media Entities...')
            ->setInitMessage('Fun Stuff is Happening...')
            ->addOperation([$this, 'processFileEntities'], [$item, $process]);
          break;
        case self::MEDIA_ENTITY:
          $batch_builder->setTitle('Validating Media Entities...')
            ->setInitMessage('Fun Stuff is Happening...')
            ->addOperation([$this, 'processMediaEntities'], [$item, $process]);
          break;
      }

    }

    // Engage.
    batch_set($batch_builder->toArray());

    // For drush, no ui.
    if ($hasUI) {
      batch_process("node/1");
    }
    else {
      $batch = &batch_get();
      $batch['progressive'] = FALSE;

      // Start the process.
      drush_backend_batch_process();
    }
  }

  /**
   * Batch call-back for Managed files checking.
   * This is where the checking and archiving occurs.
   *
   * @param array $stagedFile An array containing filename variants
   * @param bool $archive Flag whether files should actually be processed
   * @param array $context Batch progress array.
   *
   * @return void
   */
  public function processStagedFiles(array $stagedFile, bool $archive, array &$context): void {

    $action = $archive ? "archived" : "skipped";

    if (empty($context['results']['stats'])) {
      $context['results']['stats'] = [
        "total" => 0,
        "total_size" => 0,
        "{$action}_count" => 0,
        "orphan" => 0,
        "orphan_size" => 0,
        "not_used" => 0,
        "not_used_size" => 0,
        "not_published" => 0,
      ];
    }

    $context["results"]["type"] = $this::STAGED_FILES;
    $report = fopen("$this->files_path/" . $this::STAGED_FILES . "_List.txt", "a");

    // Get a full filename and path.
    $filename = $stagedFile["RelativePathname"];

    $context['results']['stats']["total"]++;
    $context['results']['stats']["total_size"] += filesize($stagedFile["RealPath"]);

    // Fetch the File Entity for this URI.
    $fid = \Drupal::entityQuery('file')
      ->condition('uri', 'public://' . $filename)
      ->accessCheck(FALSE)
      ->execute();

    if (!$fid) {
      // Could not find a File Entity, so the original file is a candidate
      // for archiving
      $archive && $this->archive($stagedFile["RealPath"]);
      $context['results']['stats']["orphan"]++;
      $context['results']['stats']["{$action}_count"]++;
      $context['results']['stats']["orphan_size"] += filesize($stagedFile["RealPath"]);
      fwrite($report, "\"orphan\",\"$filename\",\"{$action}\"" . PHP_EOL);
    }
    else {
      // Found a File Entity, so load it.
      if ($managed_file = File::load(reset($fid))) {
        if (!$this->isFileUsed($managed_file)) {
          // This file is not used by any other entity - so it is a candidate
          // for archiving.
          $archive && $this->archive($stagedFile["RealPath"]);
          $context['results']['stats']["not_used"]++;
          $context['results']['stats']["{$action}_count"]++;
          $context['results']['stats']["not_used_size"] += $managed_file->get("filesize")->value;
          fwrite($report, "\"not-used\",\"$filename\",\"{$action}\"" . PHP_EOL);
        }
        unset($managed_file);
      }
      else {
        // We could not load the File Entity - this is unexpected.
        fwrite($report, "\"internal-error: file entity would not load\",\"$filename\",\"error\"" . PHP_EOL);
      }
    }
    unset($fid);
    fclose($report);

  }

  public function processFileEntities(array $fid, bool $cleanup, array &$context): void {

    $action = $cleanup ? "deleted": "skipped";

    if (empty($context['results']['stats'])) {
      $context['results'] = [
        "total" => 0,
        "total_size" => 0,
        "broken" => 0,
        "broken_size" => 0,
        "not_used" => 0,
        "not_used_size" => 0,
        "used" => 0,
        "used_size" => 0,
      ];
    }

    $context["results"]["type"] = $this::FILE_ENTITY;
    $report = fopen("$this->files_path/" . $this::STAGED_FILES . "_List.txt", "a");

    $file = File::load($fid);

    $context['results']["total"]++;
    $context['results']["total_size"] += $file->getSize();

    // Get the full file path + name from Uri.
    $filepath = $this->file_system->realpath($file->getFileUri());

    if (!$filepath || !file_exists($filepath)) {

      // The file in this Entity does not resolve/cannot be physically found.
      $context['results']["broken"]++;
      $context['results']["broken_size"] += $file->getSize();

      // Files can be reference by Media Entities, or by other Entities in
      // Drupal (e.g. Node, Paragraph).  Need to check and see if this File
      // Entity is referenced by anything in Drupal before removing.
      if ($this->isFileUsed($file)) {
        // This file is referenced by something(s), we can still delete it
        // because the actual file does not exist.
        // However, we need to be aware that we are creating a broken-link
        // in the referencing Entity.
        $context['results']["used"]++;
        $context['results']["used_size"] += $file->getSize();
        $type = $file->bundle();  // image/document/audio/video/undefined
        fwrite($report, "\"broken-{$type}\",\"{$file->getFileUri()}\",\"{$action}\"" . PHP_EOL);
      }
      else {
        // This file is not referenced by anything, so it can be safely deleted.
        $context['results']["not_used"]++;
        $context['results']["not_used_size"] += $file->getSize();
        fwrite($report, "\"not-used\",\"{$file->getFileUri()}\",\"{$action}\"" . PHP_EOL);
      }
      $cleanup && $file->delete();
    }

    unset($file);
    fclose($report);

  }

  public function processMediaEntities(array $mid, bool $cleanup, array &$context): void {

    $action = $cleanup ? "deleted": "skipped";

    if (empty($context['results']['stats'])) {
      $context['results']['stats'] = [
        "total" => 0,
        "orphan_media" => 0,
      ];
    }

    $context["results"]["type"] = $this::MEDIA_ENTITY;
    $report = fopen("$this->files_path/" . $this::MEDIA_ENTITY . "_List.txt", "a");

    $media = Media::load($mid);

    $context['results']['stats']["total"]++;

    $media_type = $media->bundle();

    // Find the File Entity referenced by this Media Entity.
    switch ($media_type) {
      case "icon":
      case "image":
        $fid = $media->image->target_id;
        break;
      case "document":
        $fid = $media->field_document->target_id;
        break;
    }

    // See if we can get the linked File Entity.
    $files = \Drupal::entityQuery('file')
      ->accessCheck(FALSE)
      ->condition("fid", $fid, "=")
      ->execute();
    if (empty($files)) {
      // Cannot find the File Entity referenced by this Media Entity.
      // We can delete this media entity, but we should consider if it is
      // being linked at all.
      $context['results']['stats']["orphan_media"]++;
      fwrite($report, "\"broken-{$media_type}\",\"{$media->name->value}\",\"{$media->id()}\",\"{$action}\"" . PHP_EOL);
      $cleanup && $media->delete();
    }

}

  public function finish($success, $results, $operations) {

    $archive_report = fopen("$this->files_path/" . $results["type"] . "_List.txt", "a");
    if ($results["type"] == self::STAGED_FILES) {
      fwrite($archive_report, "\"NOTE\",\"Orphans are physical files which do not have File entities in Drupal. These files are probably safe to delete, but there may be physical links to these files (tpically pdf's) from content, so a chck should be made before deleting. Use admin/config/system/boston/query to search for filenames.\",\"\"" . PHP_EOL);
      fwrite($archive_report, "\"NOTE\",\"Not-Used files are physical files which have File entities in Drupal but the entity is not embedded on any Drupal Page. These files are probably safe to delete. You can still use admin/config/system/boston/query to search for filenames.\",\"\"" . PHP_EOL);
    }
    elseif ($results["type"] == self::FILE_ENTITY) {
      fwrite($archive_report, "\"NOTE\",\"Broken-xxxx are File Entities which do not have physical files on the webserver but the File Entity is linked to some other Entity. Removing these file entities will orphan the referencing entity, which can be cleaned up with CheckLoadedMediaEntities().\",\"\"" . PHP_EOL);
      fwrite($archive_report, "\"NOTE\",\"Not-Used are File Entities which do not have physical files on the webserver and the File Entity is not embedded on any Drupal Page. These files are probably safe to delete.\",\"\"" . PHP_EOL);
    }
    elseif ($results["type"] == self::MEDIA_ENTITY) {
      fwrite($archive_report, "\"NOTE\",\"Broken-xxx are Media Entities where the referenced File Entity is not found.\",\"\"" . PHP_EOL);
    }
    fclose($archive_report);

    // Write out the stats.
    $stats_report = fopen("$this->files_path/" . $results["type"] . "_Stats.json", "a");
    fwrite($stats_report, json_encode($results['stats']));
    fclose($stats_report);

  }

}
