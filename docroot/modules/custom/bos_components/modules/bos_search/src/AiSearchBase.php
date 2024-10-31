<?php

namespace Drupal\bos_search;

use Drupal\bos_google_cloud\Services\GcAgentBuilderInterface;
use Drupal\bos_google_cloud\Services\GcServiceInterface;
use Drupal\Component\Plugin\PluginBase;

abstract class AiSearchBase extends PluginBase implements AiSearchInterface {

  /** @var GcServiceInterface Holds the injected AI service. */
  protected $service;
   /**
   * @inheritDoc
   */
  public function getServiceId() {
    // Return the service defined in the plugin annotation on the plugin class.
    return (string) $this->pluginDefinition['service'];
  }

  /**
   * @inheritDoc
   */
  public function getService(): GcAgentBuilderInterface|GcServiceInterface {
    if (empty($this->service)) {
      $serviceid = $this->getServiceId();
      $this->service = \Drupal::getContainer()->get($serviceid);
    }
    return $this->service;
  }

  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->getService();
  }

  /**
   * Reformats the metadata array into a better format for rendering in twig.
   *
   * @param array $metadata
   * @param array $map
   * @param array $exclude_elem
   *
   * @return void
   */
  protected function flattenMetadata(array &$metadata, array $map = [], array $exclude_elem = []):array {

    foreach ($metadata as $key => &$elem) {
      if (is_array($elem)) {
        $elem = $this->flatten_md($elem, $map, $exclude_elem);
      }
      else {
        $key = ucwords(str_replace("_", " ", $key));
        $metadata[$key] = $elem;
      }
    }
    return $metadata;

  }

  private function flatten_md(array $elements, array $map = [], array $exclude_elem = [], string $prefix = ''):?array {

    $output = [];

    foreach ($elements as $key => $value) {

      if ($value !== NULL) {

        $key = ($map[$key] ?? $key);
        $title = empty($prefix) ? $key : "$prefix.$key";

        if (!in_array($title, $exclude_elem)) {

          if (is_array($value)) {
            $output = array_merge($output, $this->flatten_md($value, $map, $exclude_elem, $title) ?: []);
          }
          else {
            $metatitle = str_replace(" ", "", ucwords(str_replace("_", " ", $title)));
            $output[$title] = [
              "key" => $metatitle,
              "value" => $value,
            ];
          }
        }
      }
    }

    return (empty($output) ? NULL : $output);

  }

}
