<?php

use Drupal\bos_core\BosCoreSyncIconManifest;

/**
 * Reads the latest icon manifest file and loads into a queue for later
 * processing.
 *
 * @return void
 */
function icon_manifest_cron():void {

  if (!Drupal::config("bos_core.settings")->get("icon.cron") ?? FALSE) {
    // If manifest cron processing is off, then stop here.
    return;
  }

  BosCoreSyncIconManifest::loadQueue();

}
