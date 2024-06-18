<?php

/**
 * @file
 * Drush command for Boston Core.
 */

use Drupal\bos_core\BosCoreCssSwitcherService;
use Drupal\bos_core\BosCoreSyncIconManifest;

/**
 * Implements hook_drush_command().
 */
function bos_core_drush_command() {
  return [
    'bos-css-source' => [
      'description' => 'Boston CSS Source Switcher. Set the source for the main public.css file.',
      'aliases' => ['bcss'],
      'core' => ['8+'],
      'drupal dependencies' => ['bos_core'],
      'arguments' => [
        'ord' => "The ordinal for the server (use 'drush bcss' for list)",
      ],
    ],
    'bos-import-icon-manifest' => [
      'description' => 'Boston Icon Manifest Import. Load Icons in manifest.txt into media library.',
      'aliases' => ['biim'],
      'core' => ['8+'],
      'drupal dependencies' => ['bos_core'],
    ],
  ];
}

/**
 * D7 Drush command.
 */
function drush_bos_core_bos_css_source($ord = NULL) {

  $libs = \Drupal::service('library.discovery')->getLibrariesByExtension('bos_theme');

  $opts = ["Cancel"];
  foreach ($libs as $libname => $lib) {
    if (!empty($lib['data']['name'])) {
      $opts[] = $lib['data']['name'];
    }
  }
  $type = drush_choice($opts, dt('Boston CSS Source Switcher:\n Select server to switch to:\n\n'));
  BosCoreCssSwitcherService::switchSource($type);

}

/**
 * D7 Drush command.
 */
function drush_bos_import_icon_manifest() {

  BosCoreSyncIconManifest::import();

}
