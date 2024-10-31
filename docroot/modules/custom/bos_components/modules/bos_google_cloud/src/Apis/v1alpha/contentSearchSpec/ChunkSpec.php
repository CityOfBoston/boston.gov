<?php

/**
 * REQUEST API OBJECT
 *
 * Google Cloud DiscoveryEngine
 *
 * Specifies the chunk spec to be returned from the search response.
 *
 * @file src/Apis/v1alpha/contentSearchSpec/ChunkSpec.php
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/ContentSearchSpec#chunkspec
 */

namespace Drupal\bos_google_cloud\Apis\v1alpha\contentSearchSpec;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase;
use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsInterface;

class ChunkSpec extends GcDiscoveryEngineObjectsBase implements GcDiscoveryEngineObjectsInterface {

  public function __construct(array $settings = []) {
    $this->object = [
      "numPreviousChunks" => NULL,   // int Max 3 default 0
      "numNextChunks" => NULL,   // int Max 3 default 0
    ];
    $this->object = array_merge($this->object, $settings);
  }

}

