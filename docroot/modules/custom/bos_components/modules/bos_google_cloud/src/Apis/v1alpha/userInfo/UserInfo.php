<?php
/**
 * REQUEST API OBJECT
 *
 * Google Cloud DiscoveryEngine
 *
 * Information of an end user.
 *
 * @file src/Apis/v1alpha/userInfo/UserInfo.php
 *
 * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/UserInfo
 */

namespace Drupal\bos_google_cloud\Apis\v1alpha\userInfo;

use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsBase;
use Drupal\bos_google_cloud\Apis\GcDiscoveryEngineObjectsInterface;

class UserInfo extends GcDiscoveryEngineObjectsBase implements GcDiscoveryEngineObjectsInterface {

  public function __construct(array $settings = []) {
    $this->object = [
      "userId" => NULL,
      "filter" => NULL,
    ];
    $this->object = array_merge($this->object, $settings);
  }

}
