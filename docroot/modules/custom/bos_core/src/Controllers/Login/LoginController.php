<?php

namespace Drupal\bos_core\Controllers\Login;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Render\HtmlResponse;

/**
 * Controller for user login.
 */
class LoginController extends ControllerBase {

  /**
   * Cache query params.
   */
  public static function cacheParam(string $param, HtmlResponse $response) {
    // @var $cache_metadata Drupal\Core\Cache\CacheableMetadata.
    $cache_metadata = (new CacheableMetadata())->addCacheContexts([$param]);
    $response->addCacheableDependency($cache_metadata);
  }

}
