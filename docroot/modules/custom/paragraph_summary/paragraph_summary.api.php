<?php

/**
 * @file
 * API for hooks for paragraph_summary module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Implements paragraph summary for paragraph EXPEREMENTAL widget.
 *
 * @param array $form_widget
 *   The widget for this paragraph item.
 * @param array $para
 *   The paragraph entity.
 * @param array $attributes
 *   The attributes array for this paragraphs item.
 * @param string $hook
 *   The paragraph type (bundle).
 *
 * @return array
 *   In the format ['attributes' => [attributes array], content => [markup]].
 */
function hook_paragraph_summary_alter(array $form_widget, array $para, array $attributes, string $hook) {
  if ($hook = "para_type") {
    return [
      'attributes' => "",
      'content' => [],
    ];
  }
}

/**
 * Implements paragraph summary for paragraph EXPEREMENTAL widget.
 *
 * @param array $form_widget
 *   The widget for this paragraph item.
 * @param array $para
 *   The paragraph entity.
 * @param array $attributes
 *   The attributes array for this paragraphs item.
 *
 * @return array
 *   In the format ['attributes' => [attributes array], content => [markup]].
 */
function hook_paragraph_hook_summary_alter(array $form_widget, array $para, array $attributes) {
  return [
    'attributes' => "",
    'content' => [],
  ];
}
