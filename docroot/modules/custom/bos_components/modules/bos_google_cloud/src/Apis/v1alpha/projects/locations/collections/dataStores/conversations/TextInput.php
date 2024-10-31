<?php
/**
 * REQUEST API OBJECT
 *
 * Google Cloud DiscoveryEngine
 *
 * Defines text input.
 *
 * @file src/Apis/v1alpha/projects\locations\collections\dataStores\conversations\TextInput.php
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/projects.locations.collections.dataStores.conversations#textinput
 */

namespace Drupal\bos_google_cloud\Apis\v1alpha\projects\locations\collections\dataStores\conversations;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase;
use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsInterface;

class TextInput extends GcDiscoveryEngineObjectsBase implements GcDiscoveryEngineObjectsInterface {

  public function __construct(array $settings = []) {
    $this->object = [
      "input" => NULL, // string
      "context" => NULL, // object ConversationContext
    ];
    $this->object = array_merge($this->object, $settings);
  }

}
