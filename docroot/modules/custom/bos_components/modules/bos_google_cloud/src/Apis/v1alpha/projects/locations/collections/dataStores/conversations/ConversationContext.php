<?php
/**
 * REQUEST API OBJECT
 *
 * Google Cloud DiscoveryEngine
 *
 * Defines context of the conversation
 *
 * @file src/Apis/v1alpha/projects\locations\collections\dataStores\conversations\ConversationContext.php
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/projects.locations.collections.dataStores.conversations#conversationcontext
 */

namespace Drupal\bos_google_cloud\Apis\v1alpha\projects\locations\collections\dataStores\conversations;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase;
use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsInterface;

class ConversationContext extends GcDiscoveryEngineObjectsBase implements GcDiscoveryEngineObjectsInterface {

  public function __construct(array $settings = []) {
    $this->object = [
      "contextDocuments" => NULL, // array of strings
      "activeDocument" => NULL, // string
    ];
    $this->object = array_merge($this->object, $settings);

  }

}
