<?php

/**
 * RESPONSE API OBJECT
 *
 * Google Cloud DiscoveryEngine
 *
 * Defines and answer.
 *
 * @file src/Apis/v1alpha/projects/locations/collections/datastores/sessions/Answer.php
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/projects.locations.collections.dataStores.sessions.answers#Answer
 */

namespace Drupal\bos_google_cloud\Apis\v1alpha\projects\locations\collections\dataStores\sessions;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsResponseBase;

class Answer extends GcDiscoveryEngineObjectsResponseBase {

  protected array $template = [
    "answer" => NULL,
    "session" => NULL,
    "answerQueryToken" => NULL,
  ];

  public function __construct(array $response) {
    $this->object = $response;
  }

  /**
   * @inheritDoc
   * @return bool
   */
  public function validate(): bool {
    return TRUE;
  }

}
