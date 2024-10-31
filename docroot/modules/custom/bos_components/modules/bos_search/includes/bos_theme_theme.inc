<?php
/**
 * @file includes/bos_theme_theme.inc
 */
/**
 * Contains theme definitions that should be part of the main bos_theme theme.
 *
 * @see ../bos_search.module
 * @see themes/custom/bos_theme/bos_theme.theme
 *
 */

/**
 * Adds in defintions for render components which should be included in the
 * bos_theme.theme file.
 *
 * @param array $existing Already defined theme definitions.
 *
 * @return void
 */
function _load_component_definitions(array &$existing):void  {
  $existing['quote_card'] = [
    'template' => 'components/quote-card',
    'variables' => [
      "content" => "",
      "person" => "",
      "show_quotes" => "",
      "picture" => "",
      "location" => "",
      "attributes" => [],
    ],
  ];
  $existing['card'] = [
    'template' => 'components/card',
    'variables' => [
      "attributes" => [],
      "link" => "",
      "image" => "",
      "title" => "",
      "title_attributes" => [],
      "subtitle" => "",
      "subtitle_attributes" => [],
      "content" => "",
      "content_attributes" => [],
      "parent" => "",
      "parent_array" => [],
    ],
  ];
  $existing['grid_of_cards'] = [
    'template' => 'components/grid-of-cards',
    'variables' => [
      "attributes" => [],
      "title" => "",
      "title_attributes" => [],
      "cards" => [],
      "type_array" => [],
      "type" => '',
    ],
    "render element" => "cards",
  ];
  $existing['search_bar'] = [
    'template' => 'components/search-bar',
    'variables' => [
      "wrapper_attributes" => [],
      "attributes" => [],
      "title" => "",
      "title_attributes" => [],
      "icon" => "/" . \Drupal::moduleHandler()->getModule("bos_search")->getPath() . "/img/search.svg",
      "value" => "",
      "default_value" => "",
      "description" => '',
      "description_display" => "after",
      "audio_search_input" => FALSE
    ],
  ];
}

/**
 * Implements hook_preprocess_HOOK().
 */
function bos_search_preprocess_grid_of_cards(&$variables):void  {

  // Give the grid of cardas a unique ID, this is useful for anchoring.
  $variables["attributes"]["id"] = $variables["attributes"]["data-drupal-selector"] ?? "grid_" . rand(100000,999999);

  // Give each card a unique ID within this grid.
  foreach($variables['cards'] as $key => &$card) {
    $variables["type_array"][] = $card["#type"];
    $card["#attributes"]["id"] =  "{$variables["attributes"]["id"]}_card_{$key}";
    $card["#attributes"]["class"][] = "card_{$key}";
    $card["#parent"] = $variables["attributes"]["id"];
    $card["#parent_array"][] = $variables["attributes"]["id"];
  }

}

function _bos_search_preprocess_search_bar(&$variables):void  {
  $variables['value'] = $variables['value'] ?: $variables['default_value'];
  $variables['wrapper_attributes'] = new \Drupal\Core\Template\Attribute();
  $variables["attributes"]["type"] = "text";
  $variables["attributes"]["id"] = $variables["attributes"]["aria-describedby"] ?? "edit-search-bar";
  $variables["attributes"]["name"] = str_replace("edit-", "", $variables["attributes"]["data-drupal-selector"] ?? "search-bar");
  $variables["title_attributes"]["for"] = $variables["attributes"]["id"];
}
