<?php

/**
 * @file
 * Module to manage token replacements (for metatags).
 *
 * @see: https://michaeltroutt.com/custom-tokens-in-drupal-8/
 */

use Drupal\Core\Render\BubbleableMetadata;

/**
 * Implements hook_token_info().
 */
function node_topic_page_token_info() {
  $info = [];
  // Define our new token.
  $info['types']['node_topic_page'] = [
    'name' => t('Guide Substitutions'),
    'description' => t('Guide token replacements.'),
  ];
  $info['tokens']['boston'] = [
    'guide' => [
      'name' => t("Guide node"),
      'description' => t('Settings from an guide node'),
      'type' => 'node_topic_page',
    ],
  ];
  $info['tokens']['node_topic_page'] = [
    'guide-title' => [
      'name' => t('Guide Title'),
      'description' => t('title from node_topic_page.'),
    ],
    'guide-description' => [
      'name' => t('Guide Description'),
      'description' => t('body from node_topic_page.'),
    ],
    'guide-image-url' => [
      'name' => t('Guide Image'),
      'description' => t('field_intro_image from node_topic_page.'),
    ],
    'node' => [
      'name' => t("node"),
      'description' => t('The full guide node (take your chances ...)'),
      'type' => 'node',
    ],
  ];
  return $info;

}

/**
 * Implements hook_tokens().
 */
function node_topic_page_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  $replacements = [];

  if ($type != "boston") {
    return;
  }

  if (!isset($data['node'])) {
    // If node not passed, try to get from uri.
    $uri_parts = explode("/", \Drupal::request()->getRequestUri());
    if ($uri_parts[1] == "node") {
      $node = \Drupal::entityTypeManager()
        ->getStorage("node")
        ->load($uri_parts[2]);
      if (!empty($node)) {
        $data['node'] = $node;
      }
    }
  }

  if (isset($data['node']) && $data['node']->bundle() == "guide") {
    // Loop through the available tokens.
    foreach ($tokens as $name => $original) {
      // Find our custom tokens by name.
      $tok = explode(":", $name);
      if ($tok[0] == "guide") {
        $node = $data['node'];
        switch ($tok[1]) {
          case 'guide-title':
            // Give our token it's final value!
            try {
              $replacements[$original] = $node->getTitle();
            }
            catch (Exception $exception) {
              $replacements[$original] = "City of Boston Guide";
            }
            break;

          case 'guide-image-url':
            // Give our token it's final value!
            try {
              if ($node->get("field_intro_image")->count() > 0) {
                $replacements[$original] = $node->getTitle();
              }
              else {
                // TODO: return a stock image.
              }
            }
            catch (Exception $exception) {
              $replacements[$original] = "City of Boston Guide";
            }
        }
      }
    }
  }
}
