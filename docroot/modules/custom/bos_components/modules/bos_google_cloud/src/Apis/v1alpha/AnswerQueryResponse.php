<?php

/**
 * RESPONSE API OBJECT
 *
 * Google Cloud DiscoveryEngine
 *
 * Response message for SearchService.Search method.
 *
 * @file src/Apis/v1alpha/AnswerQueryResponse.php
 *
 * @see Drupal\bos_google_cloud\Apis\v1alpha\projects\locations\collections\engines\servingConfigs\answer
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/AnswerQueryResponse
 */

namespace Drupal\bos_google_cloud\Apis\v1alpha;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsResponseBase;

/**
 * The specification for personalization.
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/personalizationSpec
 */
class AnswerQueryResponse extends GcDiscoveryEngineObjectsResponseBase {

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
