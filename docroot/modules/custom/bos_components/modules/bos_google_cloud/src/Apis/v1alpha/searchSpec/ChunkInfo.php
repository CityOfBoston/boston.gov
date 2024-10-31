<?php

/**
 * REQUEST API OBJECT
 *
 * Google Cloud DiscoveryEngine
 *
 * Chunk information.
 *
 * @file src/Apis/v1alpha/searchSpec/ChunkInfo.php
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/SearchSpec#ChunkInfo
 */

namespace Drupal\bos_google_cloud\Apis\v1alpha\searchSpec;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase;

class ChunkInfo extends GcDiscoveryEngineObjectsBase {

  public function __construct(array $settings) {
    $this->object = [
      "chunk" => NULL, // string,
      "content" => NULL, // string,
      "documentMetadata>" => NULL, // array of object (DocumentMetadata)
    ];
    $this->object = array_merge($this->object, $settings);
  }

}
