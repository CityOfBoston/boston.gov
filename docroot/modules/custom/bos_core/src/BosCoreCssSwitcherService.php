<?php

namespace Drupal\bos_core;

/**
 * Class BosCoreCssSwitcherService.
 *
 *    Switches the source for the main site css file(s).
 *    The library selection is from:
 *        themes/custom/bos_theme/bos_theme.libraries.yml
 *    Drush command to switch is: drush bos:css-source (alias bcss)
 *
 * @see modules/custom/bos_core/src/Commands/BosCoreCommands.php
 *
 * @package Drupal\bos_core
 */
class BosCoreCssSwitcherService {

  /**
   * Save the requested asset source to bos_theme config.
   *
   * @param int $ord
   *   The library ordinal from bos_theme.libraries.yml.
   *
   * @return bool
   *   Whether the change was sucessfully saved to the bos_theme configuration.
   */
  public static function switchSource(int $ord = 0) {
    if ($ord == 0) {
      return TRUE;
    }

    $libs = \Drupal::service('library.discovery')->getLibrariesByExtension('bos_theme');
    $opts = ["Cancel"];
    foreach ($libs as $libname => $lib) {
      if (!empty($lib['data']['name'])) {
        $opts[] = $libname;
      }
    }
    if (!empty($opts[$ord])) {
      $res = \Drupal::translation()->translate("Switching CSS source to: '@this'", [
        "@this" => $opts[$ord],
      ])->render();
      \Drupal::logger('bos-core')->info($res);
      try {
        \Drupal::configFactory()
          ->getEditable("bos_theme.settings")
          ->set("asset_source", $opts[$ord])
          ->save();
        return TRUE;
      }
      catch (\Exception $e) {
        return FALSE;
      }
    }
    else {
      return FALSE;
    }
  }

}
