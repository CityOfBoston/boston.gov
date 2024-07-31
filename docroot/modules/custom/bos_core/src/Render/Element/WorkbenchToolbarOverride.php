<?php

namespace Drupal\bos_core\Render\Element;

use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\workbench\Render\Element\WorkbenchToolbar;

/**
 * Controller for workbench menus.
 */
class WorkbenchToolbarOverride extends WorkbenchToolbar {

  /**
   * This is an override for the \Drupal\workbench\Render\Element\WorkbenchToolbar::preRenderTray().
   * The override is effected in bos_core.module->bos_core_toolbar_alter().
   *
   * Should anything go wrong here, we default to the original preRenderTray
   * code in the contributed module.
   *
   * The main intent of this override is to allow submenus on the workbench menus.
   * We increase the maxDepth from 1 to 3.
   *
   * @param array $element
   *
   * @return array
   */
  public static function preRenderTray(array $element) {
    try {
      $menu_tree = \Drupal::service('toolbar.menu_tree');
      $parameters = new MenuTreeParameters();
      $parameters->setMinDepth(1)->setMaxDepth(3); // This MaxDepth is different to what is in the parent code.
      $tree = $menu_tree->load('workbench', $parameters);
      $manipulators = [
        ['callable' => 'menu.default_tree_manipulators:checkAccess'],
        ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
        ['callable' => 'toolbar_menu_navigation_links'],
      ];
      $tree = $menu_tree->transform($tree, $manipulators);
      $element['administration_menu'] = $menu_tree->build($tree);
    }
    catch (\Exception $e) {
      // Anything goes wrong, call the original method in parent class.
      unset($element['administration_menu']);
      return parent::preRenderTray($element);
    }

    if (empty($element["administration_menu"])
      || empty($element["administration_menu"]["#items"])
      || empty($element["administration_menu"]["#items"]["workbench.content"])
      ) {
      // The render array was not built out promperly, defaut back to original
      // method in the parent (overridden) class.
      unset($element['administration_menu']);
      return parent::preRenderTray($element);
    }
    return $element;
  }

}
