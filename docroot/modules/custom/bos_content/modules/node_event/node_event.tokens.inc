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
function node_event_token_info() {
  $info = [];
  // Define our new token.
  $info['types']['node_event'] = [
    'name' => t('Event Substitutions'),
    'description' => t('Event token replacements.'),
  ];
  $info['tokens']['boston'] = [
    'event' => [
      'name' => t("Event node"),
      'description' => t('Settings from an event node'),
      'type' => 'node_event',
    ],
  ];
  $info['tokens']['node_event'] = [
    'event-title' => [
      'name' => t('Event Title'),
      'description' => t('title from node_event.'),
    ],
    'event-description' => [
      'name' => t('Event Description'),
      'description' => t('body from node_event.'),
    ],
    'event-image-url' => [
      'name' => t('Event Image'),
      'description' => t('field_intro_image from node_event.'),
    ],
    'event-date' => [
      'name' => t('Event Date'),
      'description' => t('field_event_dates from node_event.'),
    ],
    'event-location-name' => [
      'name' => t('Event Location Name'),
      'description' => t('field_event_dates from node_event.'),
    ],
    'event-location-address-street' => [
      'name' => t('Event Location Street Address'),
      'description' => t('field_event_dates from node_event.'),
    ],
    'event-location-address-locality' => [
      'name' => t('Event Location Locality'),
      'description' => t('field_event_dates from node_event.'),
    ],
    'event-location-address-region' => [
      'name' => t('Event Location Region'),
      'description' => t('field_event_dates from node_event.'),
    ],
    'event-location-address-postcode' => [
      'name' => t('Event Location Postcode'),
      'description' => t('field_event_dates from node_event.'),
    ],
    'event-location-address-country' => [
      'name' => t('Event Location Country'),
      'description' => t('field_event_dates from node_event.'),
    ],
    'node' => [
      'name' => t("node"),
      'description' => t('The full event node (take your chances ...)'),
      'type' => 'node',
    ],
  ];
  return $info;

}

/**
 * Implements hook_tokens().
 */
function node_event_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
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

  if (isset($data['node']) && $data['node']->bundle() == "event") {
    // Loop through the available tokens.
    foreach ($tokens as $name => $original) {
      // Find our custom tokens by name.
      $tok = explode(":", $name);
      if ($tok[0] == "event") {
        $node = $data['node'];
        switch ($tok[1]) {
          case 'event-title':
            // Give our token it's final value!
            try {
              $replacements[$original] = $node->getTitle();
            }
            catch (Exception $exception) {
              $replacements[$original] = "City of Boston Event";
            }
            break;

          case 'event-image-url':
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
              $replacements[$original] = "City of Boston Event";
            }
        }
      }
    }
  }
}
