<?php

/**
 * @file
 * Contains node_buildinghousing\node_buildinghousing.views.inc..
 *
 * Provide a custom views field data that isn't tied to any other module. */

/**
 * Implements hook_views_data().
 */
function node_buildinghousing_views_data() {

  $data['views']['table']['group'] = t('Custom Global');
  $data['views']['table']['join'] = [
    // #global is a special flag which allows a table to appear all the time.
    '#global' => [],
  ];

  $data['views']['building_housing_project_type_views_field'] = [
    'title' => t('Building housing project type views field'),
    'help' => t('My awesome custom views field plugin.'),
    'field' => [
      'id' => 'building_housing_project_type_views_field',
    ],
  ];

  $data['views']['building_housing_project_type_marker_views_field'] = [
    'title' => t('Building housing project type marker views field'),
    'help' => t('My awesome custom views field plugin.'),
    'field' => [
      'id' => 'building_housing_project_type_marker_views_field',
    ],
  ];

  $data['views']['building_housing_project_type_views_field_with_text'] = [
    'title' => t('Building housing project type marker views field with text'),
    'help' => t('My awesome custom views field plugin.'),
    'field' => [
      'id' => 'building_housing_project_type_views_field_with_text',
    ],
  ];

  return $data;
}
