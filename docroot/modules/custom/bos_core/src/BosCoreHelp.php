<?php

namespace Drupal\bos_core;

/**
 * Class BosCoreHelp.
 *
 *    Dsiplays Help page.
 *
 * @package Drupal\bos_core
 */
class BosCoreHelp {

  /**
   * Provides the help page for the module.
   *
   * @return array
   *   Array to be\Drupal::service('renderer')->rendered as the help page.
   */
  public static function helpPage() {
    return [
      'help_page' => [
        '#tree' => TRUE,
        '#type' => 'fieldset',
        '#title' => "Boston Core.",
        '#markup' => "
<p>Bos_core Drupal 8 module provides common services for the boston.gov website.</p>
<h3>Permissions</h3><p>Module defines 'administer boston' permission which is used by many custom modules
that contain settings forms.  This permission should only be assigned to Administrators because it allows
access to critical settings and configs.</p>
<h3>Google Analytics</h3><p>Module provides functionality which automatically send tracking information to
Google when endpoints provided by views are accessed. Developers need to manually add code to track endpoints
which are provided using custom routing.  the code is:
<pre></pre></p>",
      ],
    ];
  }

}
