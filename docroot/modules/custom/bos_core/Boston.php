<?php

class Boston {

  const DRUPAL_VERSION = '9';

  const LOCAL_DEVELOPMENT_DOMAIN = "lndo.site";

  /**
   * Flag to indicate if code is running on a local environment.
   *
   * @return bool
   */
  public static function is_local() {
    return static::current_environment() == "local";
  }

  /**
   * Flag to indicate if code is running on a production environment
   *
   * @return bool
   */
  public static function is_production() {
    return static::current_environment() == "prod";
  }

  /**
   * Returns the current environment.
   *
   * @return string
   */
  public static function current_environment() {
    if (file_exists('/var/www/site-php')
      && $env = getenv('AH_SITE_ENVIRONMENT')) {

      return $env;

    }

    else {

      if (file_exists('/app/docroot')
        || file_exists('/opt/project')
        || str_contains($_SERVER["HTTP_HOST"] ?: "", static::LOCAL_DEVELOPMENT_DOMAIN)) {
        $env = "local";
      }

      elseif (file_exists('/home/travis/build')) {
        // This is the travis build environment.
        // TODO: If we switch to codebuild or github actions, then update here.
        $env = "build";
      }

      else {
        // No matches
        if (file_exists('/var/www/site-php')) {
          // If we are on an Acquia envisonment, default to "prod" for safety.
          $env = "prod";
        }
        else {
          $env = "unknown";
        }
      }

    }
    return  $env;
  }

  public static function get_aquia_vars() {
    $out = [];
    foreach(getenv() as $key => $value) {
      if (str_contains($key, "AH_")) {
        $out[$key] = $value;
      }
    }
    return $out ?: FALSE;
  }

}
