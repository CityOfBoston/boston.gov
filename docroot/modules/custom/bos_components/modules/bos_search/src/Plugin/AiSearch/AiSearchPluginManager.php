<?php

namespace Drupal\bos_search\Plugin\AiSearch;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

class AiSearchPluginManager extends DefaultPluginManager {

  /**
   * Constructor for AiSearchPluginManager objects.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/AiSearch', $namespaces, $module_handler, 'Drupal\bos_search\AiSearchInterface', 'Drupal\bos_search\Annotation\AiSearchAnnotation');
    $this->alterInfo('aisearch_info');
    $this->setCacheBackend($cache_backend, 'aisearch_plugins');
  }

}
