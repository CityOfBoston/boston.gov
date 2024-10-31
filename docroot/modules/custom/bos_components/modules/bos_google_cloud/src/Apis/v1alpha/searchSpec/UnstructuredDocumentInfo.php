<?php

/**
 * REQUEST API OBJECT
 *
 * Google Cloud DiscoveryEngine
 *
 * Unstructured document information.
 *
 * @file src/Apis/v1alpha/searchSpec/UnstructuredDocumentInfo.php
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/SearchSpec#UnstructuredDocumentInfo
 */

namespace Drupal\bos_google_cloud\Apis\v1alpha\searchSpec;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase;

class UnstructuredDocumentInfo extends GcDiscoveryEngineObjectsBase {

  public function __construct(array $settings) {
    $this->object = [
      "document" => NULL, // string,
      "uri" => NULL, // string,
      "title" => NULL, // string,
      "documentContexts" => NULL, // array of object (DocumentContext)
      "extractiveSegments"=> NULL, // array of object (ExtractiveSegment)
      "extractiveAnswers"=> NULL, // array of object (ExtractiveAnswer)
    ];
    $this->object = array_merge($this->object, $settings);
  }

}
