<?php
/**
 * @file includes/search_form_theme
 */
 /**
 * Contains hooks and custom functions to allow theming of the main (modal)
 * search form.
 *
 * @see ../bos_search.module
 *
 */
use Drupal\bos_search\AiSearch;

function _bos_search_autodiscover_theme(array &$existing):void  {

  // Auto-discover main theme templates:
  $themes = AiSearch::getFormThemes();
  foreach($themes as $theme => $name) {

    $templates = AiSearch::getFormTemplates($theme);
    foreach($templates as $template => $template_name) {
      $idx_file = str_replace(["_", " "], "-", $template);
      $idx = str_replace(["-", " "], "_", $template);
      if ($idx == "results") {
        $existing["{$idx}__{$theme}"] = [
          'template' => "presets/$theme/$idx_file",
          'variables' => [
            "response" => NULL,
            "items" => NULL,
            "metadata" => NULL,
            "references" => NULL,
            "citations" => NULL,
            "content" => NULL,
            "id" => NULL,
            "feedback" => NULL,
          ],
        ];
      }
      else {
        $existing["{$idx}__{$theme}"] = [
          'template' => "presets/$theme/$idx_file",
          'base_hook' => $idx,
          'render element' => 'children',
        ];
      }
    }

  }

}

function _bos_search_snippet_theme(array &$existing):void  {
  $existing['modal_close'] = [
    'template' => 'snippets/modal-close',
    'variables' => [
    ],
  ];
  $existing["aisearch_feedback"] = [
    'template' => 'snippets/aisearch-feedback',
    'variables' => [
      'thumbsup' => FALSE,
      'thumbsdown' => FALSE,
    ],
  ];
}

/**
 * Implements hook_preprocess().
 */
function bos_search_preprocess(&$variables, $hook, $info) {

  if (!AiSearch::isBosSearchThemed()) {
    return;
  }

  switch ($hook) {

    case "block":
      template_preprocess_block($variables);
      if ($variables["elements"]["#plugin_id"] == "Ai-enabled-search-button") {
        $template = explode("/" ,$info["template"]);
        $theme = $template[1];
        $variables["content"]['#preset_theme'] = $theme;
      }
      break;

    case "form":
      if ($variables["element"]["#form_id"] == "bos_search_AISearchForm") {
        template_preprocess_form($variables);

        // Add some extra configuration information
        $config = \Drupal::request()->query->all();
        if (empty($config)){
          $config["preset"] = $variables["element"]["AiSearchForm"]["content"]["preset"]["#value"];
          $config["display"] = "block";
        }
        if ($variables["configuration"] = $config) {
          $variables["preset"] = AiSearch::getPresetValues($variables["configuration"]["preset"] ?? 'default') ?? [];

          // If required, add in the modal close header .
          if (($variables["configuration"]["display"] ?? "block") == "modal"
            || $variables["preset"]["searchform"]["searchbar"]["allow_reset"]) {
            $variables["form_header"] = [
              '#type' => 'modal_close',
              '#theme' => 'modal_close',
            ];
          }

          // Include any custom styles and scripts.
          $custom_theme_path = "/modules/custom/bos_components/modules/bos_search/templates/presets/{$variables['preset']['searchform']['theme']}";
          $variables["#attached"]["drupalSettings"]["bos_search"] = [
            'dynamic_script' => "$custom_theme_path/js/preset.js",
            'dynamic_style' => "$custom_theme_path/css/preset.css",
            'waiting_text' => $variables["preset"]["searchform"]['searchbar']["waiting_text"],
          ];
          // Include script to load custom scripts and styles.
          $variables['#attached']['library'][] = 'bos_search/dynamic-loader';
        }
      }
      break;

    case "fieldset":
      template_preprocess_fieldset($variables);
      break;


    case "container":
      template_preprocess_container($variables);
      break;

    case "input":
      _bos_search_preprocess_input($variables, $hook);
      break;

    case "textarea":
      template_preprocess_textarea($variables);
      $variables["attributes"]["class"][] = "txt-f";
      break;

    case "form_element":
      template_preprocess_form_element($variables);
      break;

    case "form_element_label":
      template_preprocess_form_element_label($variables);
      break;

    default:
      break;
  }
}

function _bos_search_preprocess_input(&$variables, $hook):void  {

  template_preprocess_input($variables);
  $variables["attributes"] += $variables["element"]["#attributes"];
  $variables["children"] = $variables['element'];

}

/**
 * Implements hook_theme_suggestions_alter().
 */
function _search_form_suggestions(array &$suggestions, array &$variables, $hook):void  {

  if (!empty($variables["element"])) {
    // Get the form theme being used by the active preset, or else use 'default'.
    $node = \Drupal::request()->attributes->get('node', NULL);
    $preset = AiSearch::getPreset(node: $node);
    $form_theme = AiSearch::getPresetValues($preset)["searchform"]["theme"];

    switch ($hook) {
      case "form":
        if (isset($variables["element"]["#errors"])) {
          return;
        }
        if ($variables["element"]["#form_id"] == 'bos_search_AISearchForm') {
          $suggestions[] = "form__$form_theme";
        }
        break;
      case "form_element":
      case "form_element_label":
        if (in_array("AiSearchForm", $variables["element"]["#array_parents"] ?? [])) {
          $suggestions[] = "{$hook}__$form_theme";
        }
        break;
      default:
        if (in_array("AiSearchForm", $variables["element"]["#array_parents"] ?? [])) {
          if (in_array($variables["element"]["#type"], [
            "hidden",
            "button",
            "submit",
          ])) {
            $suggestions[] = "input__$form_theme";
          }
          else {
            $suggestions[] = $variables["element"]["#type"] . "__$form_theme";
          }
        }
        break;
    }
  }

  // Adds suggestions to allow themeing the AI Search Blocks.
  // The block will use a template based on the preset being used.
  if ($hook == "block") {

    if ($variables["elements"]["#plugin_id"] == "Ai-enabled-search-button") {
      // Get the theme from the preset - or use 'default'
      if ($preset = $variables["elements"]["#configuration"]["aisearch_config_preset"]  ?? FALSE) {
        $form_theme = AiSearch::getPresetValues($preset)["searchform"]["theme"] ?? $form_theme;
      }
      $suggestions[] = "block__button__{$form_theme}";
    }
    elseif ($variables["elements"]["#plugin_id"] == "Ai-enabled-search-form") {
      $preset = AiSearch::getPreset();
      $form_theme = AiSearch::getPresetValues($preset)["searchform"]["theme"];
      $suggestions[] = "block__form__{$form_theme}";
    }
  }

}
