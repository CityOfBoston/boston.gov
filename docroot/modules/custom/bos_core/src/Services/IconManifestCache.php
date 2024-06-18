<?php

namespace Drupal\bos_core\Services;

use Drupal;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Logger\LoggerChannelInterface;

/**
class GcCacheAI
Creates service to manage caching of AI requests

david 02 2024
@file docroot/modules/custom/bos_components/modules/bos_google_cloud/src/Services/GcCacheAI.php
 */

class IconManifestCache {

  public function __construct(LoggerChannelFactory $logger, ConfigFactory $config, CacheBackendInterface $cache) {
    $this->log = $logger->get('bos_google_cloud');
    $this->config = $config->get("bos_google_cloud.settings");
    $this->cache = $cache;
    $this->info = [
      "enabled" => TRUE,
      "expiry" => NULL,
      "bin" => "gen_ai"
    ];
  }

}
