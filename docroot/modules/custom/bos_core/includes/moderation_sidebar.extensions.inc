<?php

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Render\Markup;
use Drupal\Component\Utility\Html;
use Drupal\Core\Url;

/**
 * Implements theme_preprocess_moderation_sidebar_revision.
 *
 * Called when revisions button is clicked on moderation sidebar menu, and
 * sets up the revisions panel fo the sidebar.
 */
function bos_core_preprocess_moderation_sidebar_revision(array &$variables) {
  // Theme the revisions sidebar panel.
  $attributes = new Attribute();
  $route = $variables["revision_link"]->getUrl()->getRouteParameters(["node"]);
  $mod = _bos_core_moderation_decode($route);
  $attributes->addClass($mod["class"]);
  if ($mod['moderation_state'] != "unknown") {
    $variables["revision_mod_type"] = [
      "#attributes" => $attributes,
      "#markup" => Markup::create($mod['title']),
    ];
  }
}

/**
 * Extends hook_preprocess_toolbar from bos_core.module.
 */
function moderation_sidebar_extensions_preprocess_toolbar($variables) {
  if (isset($variables["tabs"]["moderation_sidebar"])) {
    $status = Html::cleanCssIdentifier(strtolower($variables["tabs"]["moderation_sidebar"]["link"]["#attributes"]["data-label"]));
    $variables["tabs"]["moderation_sidebar"]["attributes"]->addClass("status-" . $status);
    if ($status == "draft-available") {
      $variables["tabs"]["moderation_sidebar"]["attributes"]->setAttribute("title", "Draft available.");
    }
  }
}

/**
 * Implements hook_moderation_sidebar_alter().
 *
 * @see https://www.drupal.org/files/issues/2018-09-17/moderation_sidebar-edit-access-check-3000387-2.patch
 */
function bos_core_moderation_sidebar_alter(array &$build, EntityInterface $entity) {
  // Remove salesforce if there is no permission.
  if (!\Drupal::currentUser()->hasPermission("view salesforce mapping")) {
    unset($build["actions"]['salesforce_mapping.entities:node.salesforce_tab']);
  }
  // Remove devel if there is no permission.
  if (!\Drupal::currentUser()->hasPermission("access devel information")) {
    unset($build["actions"]['devel.entities:node.devel_tab']);
  }
  // Remove Content Access if there are no permissions.
  if (!\Drupal::currentUser()->hasPermission("bypass node access")) {
    unset($build["actions"]["entity.node.content_access"]);
  }
  // Remove Clone if there are no permissions.
  if (!\Drupal::currentUser()->hasPermission("clone node entity")) {
    unset($build["actions"]["entity_clone.clone:node.clone_tab"]);
  }
  // Remove Usage if there are no permissions.
  if (!\Drupal::currentUser()->hasPermission("access entity usage statistics")) {
    unset($build["actions"]["entity_usage.entities:node.entity_usage"]);
  }

  if (isset($entity)) {
    // Remove Translation if there are no permissions.
    if (!\Drupal::currentUser()
        ->hasPermission("administer content")
      && !\Drupal::currentUser()
        ->hasPermission("translate any entity")
      && !\Drupal::currentUser()
        ->hasPermission("create content translations")
      && !\Drupal::currentUser()
        ->hasPermission("translate " . str_replace("_", " ", $entity->bundle()) . " node")) {
      unset($build["actions"]["content_translation.local_tasks:entity.node.content_translation_overview"]);
    }
    // Remove Delete option if there are no permissions.
    if (!\Drupal::currentUser()
        ->hasPermission("administer content")
      && !\Drupal::currentUser()
        ->hasPermission("delete any " . $entity->bundle() . " content")
      && (!(\Drupal::currentUser()
          ->hasPermission("delete own " . $entity->bundle() . " content")
        && $entity->getOwnerId() == \Drupal::currentUser()->id()))
    ) {
      unset($build["actions"]["entity.node.delete_form"]);
    }
    // Remove Edit option if there are no permissions.
    if (!\Drupal::currentUser()
        ->hasPermission("administer content")
      && !\Drupal::currentUser()
        ->hasPermission("edit any " . $entity->bundle() . " content")
      && (!(\Drupal::currentUser()
          ->hasPermission("edit own " . $entity->bundle() . " content")
        && $entity->getOwnerId() == \Drupal::currentUser()->id()))
    ) {
      unset($build["actions"]["entity.node.edit_form"]);
    }
    // Remove Revisions option if there are no permissions.
    if (!\Drupal::currentUser()
        ->hasPermission("administer content")
      && !\Drupal::currentUser()
        ->hasPermission("view all revisions")
      && !\Drupal::currentUser()
        ->hasPermission("view " . $entity->bundle() . " revisions")
    ) {
      unset($build["actions"]["entity.node.delete_form"]);
    }
  }

  $name = $build["info"]["#revision_author_link"]["#url"]->getOption('entity')->realname;
  $build["info"]["#revision_author"] = $name;
  $build["info"]["#revision_author_link"]["#title"] = $name;

  /*
   * TODO: Revise this to manage buttons on moderation sidebar according to the
   * moderation state.
   * -> Create an array and implement on moderation workflow config page to set
   * a default state to move to from the state the entity is currently in.
   * e.g. When moderation state is "published", the default next state should be
   * "draft" etc.
   *
   */
  // Add direct link to edit latest version.
  if (!empty($build["actions"]["view_latest"])) {
    $url = Url::fromRoute("entity.node.edit_form", [
      'node' => $entity->id(),
    ],
      [
        'entity' => $entity,
        'language' => $entity->language(),
        'entity_type' => "node",
      ]);

    $build["actions"]["edit_latest"] = [
      "#title" => Markup::create("Edit existing draft"),
      "#attributes" => [
        "class" => [
          "moderation-sidebar-link",
          "button",
        ],
      ],
      "#url" => $url,
      "#weight" => -1,
      "#type" => "link",
    ];
    // Set some colors on the sidebar.
    $build["actions"]["view_latest"]["#weight"] = -2;
  }
  // Set some colors on the sidebar.
  if (isset($build["actions"]["view_latest"])) {
    $build["actions"]["view_latest"]["#attributes"]["class"][] = "button--view-draft clearfix";
  }
  if (isset($build["actions"]["edit"])) {
    $build["actions"]["edit"]["#attributes"]["class"][] = "button--edit-draft clearfix";
  }
  if (isset($build["actions"]["edit_latest"])) {
    $build["actions"]["edit_latest"]["#attributes"]["class"][] = "button--edit-draft clearfix";
  }
  if (isset($build["actions"]["view_default"])) {
    $build["actions"]["view_default"]["#attributes"]["class"][] = "button--view-live clearfix";
  }
  if (isset($build["actions"]["delete"])) {
    $build["actions"]["delete"]["#attributes"]["class"][] = "button--delete clearfix";
  }
  if (isset($build["actions"]["quick_draft_form"]["discard_draft"])) {
    $build["actions"]["quick_draft_form"]["discard_draft"]["#attributes"]["class"][] = "button--delete clearfix";
  }
  if (isset($build["actions"]["quick_draft_form"]["submit_for_review"])) {
    $build["actions"]["quick_draft_form"]["submit_for_review"]["#attributes"]["class"][] = "button--review clearfix";
  }

  $build["actions"]["primary"]["quick_draft_form"]["revision_log_toggle"]["#value"] = TRUE;
  $build["actions"]["primary"]["quick_draft_form"]["revision_log_toggle"]["#checked"] = TRUE;
  $build["actions"]["primary"]["quick_draft_form"]["#attached"]["library"] = ['bos_core/moderation_sidebar'];
}
