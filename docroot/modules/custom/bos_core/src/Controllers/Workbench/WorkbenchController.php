<?php

namespace Drupal\bos_core\Controllers\Workbench;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for workbench menus.
 */
class WorkbenchController extends ControllerBase {


  public function createContent(): array {
    return [
      '#type' => 'markup',
      '#markup' => $this->t("Select a listing from the dopdown menu."),
    ];
  }

}
