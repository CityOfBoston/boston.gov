<?php

namespace Drupal\bos_search\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an AiSearch item annotation object.
 *
 * @see \Drupal\bos_search\Plugin\AiSearch\AiSearchPluginManager
 * @see plugin_api
 *
 * @Annotation
 */
class AiSearchAnnotation extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The service referenced by this plugin.
   *
   * @var string
   */
  public $service;

  /**
   * A description for this plugin.
   *
   * @var string
   */
  public $description;

}
