<?php

/**
 * REQUEST API OBJECT
 *
 * Google Cloud DiscoveryEngine
 *
 * Document metadata contains the information of the document of the current chunk.
 *
 * @file src/Apis/v1alpha/searchSpec/DocumentMetadata.php
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/SearchSpec#DocumentMetadata
 */

namespace Drupal\bos_google_cloud\Apis\v1alpha\searchSpec;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase;

class DocumentMetadata extends GcDiscoveryEngineObjectsBase {

  public function __construct(array $settings) {
    $this->object = [
      "uri" => NULL, // string,
      "title" => NULL, // string,
    ];
    $this->object = array_merge($this->object, $settings);
  }

}
