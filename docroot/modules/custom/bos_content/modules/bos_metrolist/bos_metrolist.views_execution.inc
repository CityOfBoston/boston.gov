<?php

/**
 * @file
 * Sort for Events and Notices Views Block - Referenced.
 */

use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\query\Sql;

/**
 * Implements hook_views_query_alter().
 *
 * @param \Drupal\views\ViewExecutable $view
 *   Active View - Metrolist.
 * @param \Drupal\views\Plugin\views\query\Sql $query
 *   Active Views DB query.
 */
function bos_metrolist_views_query_alter(ViewExecutable $view, Sql $query) {

  // Sort for Events and Notices View Block - Referenced
  // Combine two different date fields of two node types into one and showing the soonest to come items first.
  if ($view->id() == 'events_and_notices' && $view->current_display == 'referenced') {

    $orderByEventNoticeDateField = 'CASE WHEN node__field_public_notice_date.field_public_notice_date_value
     THEN DATE_FORMAT(node__field_public_notice_date.field_public_notice_date_value, \'%Y%m%d%H\')
     WHEN node__field_event_date_recur.field_event_date_recur_value
     THEN DATE_FORMAT(node__field_event_date_recur.field_event_date_recur_value, \'%Y%m%d%H\')
     ELSE DATE_FORMAT(node_field_data.created, \'%Y%m%d%H\')
     END';

    $query->addOrderBy(NULL, $orderByEventNoticeDateField, 'ASC', 'node__field_event_notice_date');
  }

}
