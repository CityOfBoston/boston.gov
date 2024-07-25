<?php

namespace Drupal\bos_core\Commands;

use Drupal;
use Drush\Commands\DrushCommands;
use Drupal\bos_core\BosCoreCssSwitcherService;
use Drupal\bos_core\BosCoreSyncIconManifest;
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
class BosCoreCommands extends DrushCommands {

  /**
   * Boston CSS Source Switcher. Set the source for the main public.css file.
   *
   * @param string $ord
   *   The ordinal for the server (use 'drush bcss' for list)
   *
   * @validate-module-enabled bos_core
   *
   * @command bos:css-source
   * @aliases bcss,bos-css-source
   */
  public function cssSource($ord = NULL) {
    // See bottom of https://weitzman.github.io/blog/port-to-drush9 for details
    // on what to change when porting a legacy command.
    $libs = Drupal::service('library.discovery')->getLibrariesByExtension('bos_theme');

    if (!isset($libs) || $libs == []) {
      $this->output()->writeln("Error: It appears that the theme \<bos_theme\> is not installed, or has no libraries - Please install bos_theme and retry.");
      return;
    }
    if (!isset($ord)) {
      $count = 0;
      $opts = "Boston CSS Source Switcher:\n Select server to switch to:\n\n";
      $opts .= " [" . $count++ . "]: Cancel\n";
      foreach ($libs as $libname => $lib) {
        if (!empty($lib['data']['name'])) {
          $opts .= " [" . $count++ . "]: " . $lib['data']['name'] . "\n";
        }
      }
      $ord = $this->io()->ask($opts, NULL);
    }

    $libArray = ["Cancel"];
    foreach ($libs as $libname => $lib) {
      if (isset($lib['data']) && isset($lib["remote"])) {
        $libArray[] = [
          $lib['data']['name'],
          $lib['remote'],
        ];
      }
    }

    if ($ord == 0) {
      $this->output()->writeln("Cancelled.");
    }
    elseif (BosCoreCssSwitcherService::switchSource($ord)) {
      Drupal::service('asset.css.collection_optimizer')
        ->deleteAll();
      $res = Drupal::translation()->translate("Success: Changed source to '@source' (@sourcePath).", [
        '@source' => $libArray[$ord-1][0],
        '@sourcePath' => $libArray[$ord-1][1],
      ])->render();
      $this->output()->writeln($res);
    }
    else {
      $this->output()->writeln(t("FAILED: Could not change source to '@source' (@sourcePath)."), [
        '@source' => $libArray[$ord-1][0],
        '@sourcePath' => $libArray[$ord-1][1],
      ]);
    }
  }

  /**
   * Automate common tasks related to bos componetization plan.
   *
   * @param string $module_name
   *   Machine name for output component module.
   * @param array $options
   *   Additional options for the command.
   *
   * @return string
   *   Stdout to console.
   *
   * @validate-module-enabled bos_core
   *
   * @command bos:componetize
   * @option components Comma seperated list of component machine names
   * @aliases componetize
   */
  public function componetize($module_name = NULL, array $options = ['components' => NULL]) {

    $module_path = Drupal::service('file_system')->realpath(Drupal::service('extension.path.resolver')->getPath('module', 'bos_components')) . '/modules/' . $module_name;
    if (file_exists($module_path)) {
      return 'This module directory already exists.';
    }
    else {
      mkdir($module_path);
    }

    $template_path = Drupal::service('file_system')->realpath(Drupal::service('extension.path.resolver')->getPath('module', 'bos_core')) . '/src/componentizer_templates';
    $template_files = array_diff(scandir($template_path), ['..', '.']);

    foreach ($template_files as $file) {
      $filename_parts = explode('.', $file);
      $filename_parts['0'] = $module_name;
      $new_filepath = $module_path . "/" . implode('.', $filename_parts);
      if (!copy($template_path . "/{$file}", $new_filepath)) {
        return 'File copy failed';
      }
      $file_contents = file_get_contents($new_filepath);
      $file_contents = str_replace("componentizer_template", $module_name, $file_contents);
      file_put_contents($new_filepath, $file_contents);
    }

    $module_config_dir = $module_path . '/config/install';
    mkdir($module_config_dir, 0777, TRUE);
    $site_install_directory = '/';
    foreach (explode('/', realpath(__FILE__)) as $part) {
      if ($part != 'docroot') {
        $site_install_directory .= $part;
      }
      else {
        break;
      }
    }

    $config_store_directory = $site_install_directory . '/config/default';
    // Adding to $this so we can access values in array_filter() callback.
    $this->components = explode(',', $options['components']);
    $component_configs = array_filter(scandir($config_store_directory), function ($file_name) {
      foreach ($this->components as $component) {
        if (preg_match("/\.($component)\./", $file_name)) {
          return TRUE;
        }
      }
      return FALSE;
    });

    $info_file_path = $module_path . "/{$module_name}.info.yml";
    $install_file = Yaml::parse(file_get_contents($info_file_path));
    $install_file['config_devel'] = [];

    foreach ($component_configs as $config_name) {
      copy($config_store_directory . "/{$config_name}", $module_config_dir . "/{$config_name}");
      $install_file['config_devel'][] = basename($config_name, '.yml');
    }

    file_put_contents($info_file_path, Yaml::dump($install_file, 2, 2));

    return 'Componentization complete!';
  }

  /**
   * Admin function to set the GA Measurement endpoint for REST tracking.
   *
   * @param string $endpoint
   *   The Google endpoint.
   *
   * @return string
   *   Message to stdout in console.
   *
   * @validate-module-enabled bos_core
   *
   * @command bos:google-measurement-endpoint
   * @aliases bgme
   */
  public function gaEndpoint($endpoint = NULL) {
    if ($endpoint == NULL) {
      return "PROBLEM: Please supply a new endpoint.";
    }
    $config = Drupal::configFactory()
      ->getEditable("bos_core.settings");
    $settings = $config->get("ga_settings");
    $settings["ga_endpoint"] = $endpoint;
    $config->set("ga_settings", $settings)
      ->save();
    return 'SUCCESS: Google Analytics/Measurement endpoint changed to ' . $endpoint;
  }

  /**
   * Admin function to disable REST GA Measurement/Tracking.
   *
   * @param string $enabled
   *   TRUE/FALSE to set REST hit tracking state.
   *
   * @return string
   *   Message to stdout in console.
   *
   * @validate-module-enabled bos_core
   *
   * @command bos:google-measurement
   * @aliases bgm
   */
  public function gaEnable(string $enabled = NULL) {
    if ($enabled == NULL) {
      return "PROBLEM: Please supply the enabled/disabled state.";
    }
    $config = Drupal::configFactory()
      ->getEditable("bos_core.settings");
    $settings = $config->get("ga_settings");
    $settings["ga_enabled"] = ($enabled == TRUE);
    $config->set("ga_settings", $settings)
      ->save();
    return 'SUCCESS: Google Analytics tracking for REST calls is now ' . ($enabled == TRUE ? "ON" : "OFF");
  }

  /**
   * Icon Manifest Manager: Process the manifest file and update/create
   * file/media entities for icons.
   *
   * @validate-module-enabled bos_core
   *
   * @command bos:import-icon-manifest
   * @aliases biim,bos-import-icon-manifest
   */
  public function importIconManifest() {
    return BosCoreSyncIconManifest::import();
  }

  /**
   * Icon Manifest Manager: Invalidate the cache used to speed file processing.
   *
   * @validate-module-enabled bos_core
   *
   * @command bos:icon-manifest-clear-cache
   * @aliases bimcc
   */
  public function clearCacheIconManifest() {
    // Invalidate the current manifest cache.
    Drupal::cache("icon_manifest")->invalidateAll();
    // Remove the legacy manifest cache.
    Drupal::state()->delete("bos_core.icon_library.manifest");
    // Reset the highwater mark.
    Drupal::configFactory()
      ->getEditable("bos_core.settings")
      ->set("icon.manifest_date", 0)
      ->save();
  }

  /**
   * Gen-AI Body Re-Summarizer: Using AI, resummarize fields of the selected Content Type,
   * replacing the ai-generated summary that already exists, ignoring any drupal-side caching.
   * This will overwrite any manually set summaries.
   *
   * @validate-module-enabled bos_core
   * @validate-module-enabled bos_google_cloud
   *
   * @command bos:resummarizer
   * @aliases bos:rs
   */
  public function reSummarizeFields() {
    /*
     * 1. Get a list of all nodes which have ai summarized fields
     * 2. Get all the nodes and load into a queue
     * 3. Create a queue worker which will process the queue and update the summary
     */
    $settings = \Drupal::config("bos_core.settings")->get('summarizer');
    $nodesWithAiSummarizedFields = $settings['content_types']??[];
    $count = 0;
    $done = 0;
    $map = [];

    $opts = "Boston CSS Source Switcher:\n Select server to switch to:\n\n";
    $opts .= " [" . $count++ . "]: Cancel\n";
    foreach ($nodesWithAiSummarizedFields as $ctname => $ctsettings) {
      if (!empty($ctsettings['enabled'])) {
        foreach($ctsettings['settings']['fields'] as $fieldname => $fieldenabled) {
          if ($fieldenabled == 1) {
            $map[$count] = [
              "content_type" => $ctname,
              "field_name" => $fieldname,
              "prompt" => $ctsettings["settings"]["prompt"],
              "cache" => $ctsettings["settings"]["cache"],
            ];
            $opts .= " [" . $count++ . "]: " . $ctname . "." . $fieldname . "\n";
          }
        }
      }
    }
    $ord = $this->io()->ask($opts, NULL);

    if ($ord == 0) {
      $this->output()->writeln("Cancelled.");
      return;
    }

    $resummarize = $map[$ord];

    $queue = \Drupal::queue('node_field_resummarizer');

    foreach(\Drupal::service('entity_type.manager')
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->condition('type', $resummarize["content_type"])
      ->execute() as $nid) {
      $queue->createItem([
        "nid" => $nid,                            // NID to resummarize
        "field" => $resummarize["field_name"],    // Field to be resummarized
        "nocache" => "1",                         // Ignore existing bos_google_cloud-cached content
        "prompt" => $resummarize["prompt"],
        "cache" => $resummarize["cache"],
        "override_manual" => "1"                  // (future feature) Overwrite any manually set summary
      ]);
      $done++;
    }

    $res = "Success: Queued $done nodes to be re-summarized by cron.";
    $this->output()->writeln($res);

  }

  /**
   *
   * Scans Staged Files in public:// (typically docroot/sites/default/files)
   * folders and identifies those which are not referenced by File Entities.
   * Using --archive causes these files to be relocated and then they can be
   * manually copied to an archive location (e.g. s3).
   *
   * @param array $options
   *   Additional options for the command.
   *
   * @return string
   *   Stdout to console.
   *
   * @validate-module-enabled bos_core
   *
   * @command bos:media_services_stagedfiles
   * @option archive Files will be archived into sites/default/files/media_service_archive retaining their directory structure for easy de-archival.
   * @option file_extensions Comma seperated list of extensions to scan/check. default = "jpg,jpeg,png,gif,pdf"
   * @option excluded_paths Comma seperated list of subdirectories (and filenames) to ignore. default = "private,styles,tmp,pdf_templates,election_results". Note: The archive folder is always ignored.
   * @option count The number of files to read from the system (i.e. for testing)
   *
   * @usage drush bos:mssf
   *    Dry run for all files using default settings - generates report
   * @usage drush bos:mssf --archive
   *    Archives all files using default settings
   * @usage drush bos:mssf --count=100
   *    Scans the first 100 files and generates report
   * @usage drush bos:mssf --file_extensions=jpg,jpeg
   *    Scans site/default/files looking for jpg/jpeg images only
   * @usage drush bos:mssf --excluded_paths=tmp,styles,deadpool.jpg
   *    Scans site/default/files ignoring files found in .../styles/.. and .../tmp/.. folders, and also ignores .../deadpool.jpg
   *
   * @aliases bos:mssf
   */
  public function cleanStagedFiles(array $options = ['file_extensions' => '', 'excluded_paths' => '', 'count' => 0, 'archive' => false]) {
    $file_ext = $options["file_extensions"] ?: "";
    $exclude_paths = $options["excluded_paths"] ?: "";
    $count = $options["count"] ?: 0;
    $archive = !empty($options["archive"]);

    if ($archive) {
      $opts = "This will archive files which do not have associated File Entities in Drupal.\nAre you sure you wish to archive files?:\n";
      $opts .= " [y/n]";
      $ord = $this->io()->ask($opts, "n");

      if (strtolower($ord) !== 'y') {
        $this->output()->writeln("Cancelled.");
        return;
      }
    }

    \Drupal::service("bos_core.media_services")->CheckStagedFiles($archive, $file_ext, $exclude_paths, $count, FALSE);

    $res = "Success: Check the file 'sites/default/files/StagedFiles_FileList.txt', and";
    $this->output()->writeln($res);
    $res = "         sites/default/files/StagedFiles_Stats.json' for outputs.";
    $this->output()->writeln($res);
    if ($archive) {
      $res = "Check 'sites/default/files/media_service_archive' for archived files.";
    }
    else {
      $res = "No files were altered.";
    }
    $this->output()->writeln($res);
  }

  /**
   *
   * Scans File Entity objects in Drupal and identifies those which do not
   * reference a physical file in the file system.
   * Using --cleanup causes these File Entities to be deleted and is irreversible.
   *
   * @param array $options
   *   Additional options for the command.
   *
   * @return string
   *   Stdout to console.
   *
   * @validate-module-enabled bos_core
   *
   * @command bos:media_services_fileentities
   * @option cleanup File Entities with no physical files will be removed.
   * @option count The number of File Entities to read from the system (i.e. for testing)
   *
   * @usage drush bos:msfe
   *    Dry run for all Files Entities using default settings - generates report
   * @usage drush bos:msfe --cleanup
   *    Removes File Entities - if ommitted nothing is removed, just reported.
   * @usage drush bos:msfe --count=100
   *    Scans the first 100 File Entities and generates report
   *
   * @aliases bos:msfe
   */
  public function FileEntityCheck(array $options = ['cleanup' => FALSE, 'count' => 0]) {
    $count = $options["count"] ?: 0;
    $cleanup = !empty($options["cleanup"]);

    if ($cleanup) {
      $opts = "This will remove File Entities which do not have associated physical files.\nAre you sure you wish to cleanup?:\n";
      $opts .= " [y/n]";
      $ord = $this->io()->ask($opts, "n");

      if (strtolower($ord) !== 'y') {
        $this->output()->writeln("Cancelled.");
        return;
      }
    }

    \Drupal::service("bos_core.media_services")->FileEntityIntegrityCheck($cleanup, $count, TRUE);

    $res = "Success: Check the file 'sites/default/files/FileEntity_FileList.txt', and";
    $this->output()->writeln($res);
    $res = "         sites/default/files/FileEntity_Stats.json' for outputs.";
    $this->output()->writeln($res);
    if (!$cleanup) {
      $res = "No Entities were altered.";
      $this->output()->writeln($res);
    }
  }

  /**
   *
   * Scans Media Entity objects in Drupal and identifies those which do not
   * reference a valid File Entity.
   * Using --cleanup causes these Media Entities to be deleted and is irreversible.
   *
   * @param array $options
   *   Additional options for the command.
   *
   * @return string
   *   Stdout to console.
   *
   * @validate-module-enabled bos_core
   *
   * @command bos:media_services_mediaentities
   * @option cleanup Media Entities with no File Entities will be removed.
   * @option count The number of Media Entities to read from the system (i.e. for testing)
   *
   * @usage drush bos:msme
   *    Dry run for all Media Entities using default settings - generates report
   * @usage drush bos:msme --cleanup
   *    Removes File Entities - if ommitted nothing is removed, just reported.
   * @usage drush bos:msme --count=100
   *    Scans the first 100 Media Entities and generates report
   *
   * @aliases bos:msme
   */
  public function MediaEntityCheck(array $options = ['cleanup' => FALSE, 'count' => 0]) {
    $count = $options["count"] ?: 0;
    $cleanup = !empty($options["cleanup"]);

    if ($cleanup) {
      $opts = "This will remove Media Entities which do not have associated File Entities.\nAre you sure you wish to cleanup?:\n";
      $opts .= " [y/n]";
      $ord = $this->io()->ask($opts, "n");

      if (strtolower($ord) !== 'y') {
        $this->output()->writeln("Cancelled.");
        return;
      }
    }

    \Drupal::service("bos_core.media_services")->MediaEntityIntergityCheck($cleanup, $count, TRUE);

    $res = "Success: Check the file 'sites/default/files/MediaEntity_List.txt', and";
    $this->output()->writeln($res);
    $res = "         sites/default/files/MediaEntity_Stats.json' for outputs.";
    $this->output()->writeln($res);
    if (!$cleanup) {
      $res = "No Entities were altered.";
      $this->output()->writeln($res);
    }
  }

}
