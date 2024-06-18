<?php

namespace Drupal\bos_core\Plugin\QueueWorker;

use Drupal;
use Drupal\bos_core\BosCoreSyncIconManifest;
use Drupal\Core\Annotation\QueueWorker;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Exception;

/**
 * A Node Publisher that publishes nodes on CRON run.
 *
 * @QueueWorker(
 *   id = "cron_manifest_processor",
 *   title = @Translation("Cron Icon Manifest Processor"),
 *   cron = {"time" = 20}
 * )
 */

class CronManifestProcessor extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {

    // Load the manifest cache.
    $manifest_cache = Drupal::cache("icon_manifest");

    // Just use the next available fid/vid for any file entities created.
    $last = ["fid" => 0, "vid" => 0];

    $data = trim($data);

    if ($manifest_cache->get($data)) {
      // Item has already been processed (b/c it's in the cache).
      return TRUE;
    }

    try {
      BosCoreSyncIconManifest::processFileUri($data, $last);
      // Save cache for next time. (don't set expiry).
      $manifest_cache->set($data, TRUE, CacheBackendInterface::CACHE_PERMANENT);
    }
    catch (Exception $e) {
      throw new Exception($e->getMessage());
    }

    return TRUE;
  }

}
