<?php
/**
 * REQUEST API OBJECT
 *
 * Google Cloud DiscoveryEngine
 *
 * The specification for query spell correction.
 *
 * @file src/Apis/v1alpha/projects\locations\evaluations\TextInput.php
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/projects.locations.evaluations#spellcorrectionspec
 */

namespace Drupal\bos_google_cloud\v1alpha\projects\locations\evaluations;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase;
use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsInterface;

class SpellCorrectionSpec extends GcDiscoveryEngineObjectsBase implements GcDiscoveryEngineObjectsInterface {

  public function __construct(array $settings = []) {
    $this->object = [
      "mode" => NULL,   // string - MODE_UNSPECIFIED or SUGGESTION_ONLY or AUTO
      "filter" => NULL,
    ];
    $this->object = array_merge($this->object, $settings);
  }

}

