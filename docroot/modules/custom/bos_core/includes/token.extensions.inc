<?php

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\WidgetInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Implements hook_contact_token_info().
 */
function bos_core_token_info() {
  $info = [];
  // Define a new token type for boston.
  $info['types']['boston'] = [
    'name' => t('Boston'),
    'description' => t('A token type for Boston.'),
  ];
  // Add custom tokens.
  $info['tokens']['custom']['schema_image'] = [
    'name' => t('Schema Image'),
    'description' => t('Image to be used for Schema.org metatag'),
  ];
  $info['tokens']['custom']['schema_start_date'] = [
    'name' => t('Schema Start Date'),
    'description' => t('Start Date for Events'),
  ];
  $info['tokens']['custom']['schema_end_date'] = [
    'name' => t('Schema End Date'),
    'description' => t('End Date for Events'),
  ];
  $info['tokens']['custom']['schema_how_to_titles'] = [
    'name' => t('Schema How To Titles'),
    'description' => t('Titles for How To Steps'),
  ];
  $info['tokens']['custom']['schema_how_to_details'] = [
    'name' => t('Schema How To Details'),
    'description' => t('Details for How To Steps'),
  ];

  return $info;
}

/**
 * Implements hook_tokens().
 * Populate token data.
 */
function bos_core_tokens($type, $tokens, array $data = [], array $options = []) {

  $custom_tokens = [];

  // Get field data.
  if (isset($data['node'])) :
    // Get info for intro image.
    $schema_img_val = "https://patterns.boston.gov/images/global/icons/b-logo-large.png";
    if ($data['node']->hasField('field_intro_image')) :
      if (!empty($data['node']->get('field_intro_image')->getValue())) :
        // Set default image.
        $img_id = $data['node']->get('field_intro_image')->getValue();
        if (!empty($img_id[0]['target_id'])) {
          $img_id = $img_id[0]['target_id'];
          if ($img_url = File::load($img_id)) {
            $img_url = \Drupal::service('file_url_generator')->generateAbsoluteString($img_url->getFileUri());
            $schema_img_val = $img_url;
          }
        }
      endif;
    endif;

    $dates = [
      "value" => (new DateTime("now"))->format("Y-m-d\TH:i:s"),
      "end_value" => (new DateTime("now"))->format("Y-m-d\TH:i:s"),
    ];

    if ($data['node']->hasField('field_date_range') && !empty($data['node']->get('field_date_range')[0])) :
      $dates = $data['node']->get('field_date_range')[0]->getValue();
    endif;

    if ($data['node']->hasField('field_public_notice_date') && !empty($data['node']->get('field_public_notice_date')[0])) :
      $dates = $data['node']->get('field_public_notice_date')[0]->getValue();
    endif;

    if ($data['node']->hasField('field_address') && !empty($data['node']->get('field_address')[0])) :
      $address = $data['node']->get('field_address')[0]->getValue();
    endif;

    if ($type == 'custom') {
      foreach ($tokens as $name => $original) {
        if (strpos($name, 'how_to') !== FALSE) {
          $tab_token_type = '';
          $tab_token_titles = '';
          $tab_token_details = '';

          if ($data['node']->hasField('field_how_to_tabs')) {
            $tabs = $data['node']->get('field_how_to_tabs')->getValue();

            foreach ($tabs as $ht_tab) {
              if ($entities = Paragraph::load($ht_tab['target_id'])) {

                // Get Tab Title info.
                $tab_items = $entities->field_how_to_title->getValue();
                $tab_token_type = $tab_items[0]['value'];

                // Get Tab Step info.
                $field_items = $entities->field_how_to_steps->getValue();
                foreach ($field_items as $ht_step) {
                  $step_id = $ht_step['target_id'];

                  if ($step = Paragraph::load($step_id)) {

                    // Get title of step.
                    $title = $step->field_title->getValue();
                    foreach ($title as $ht_step_title) {
                      $tab_token_titles .= $tab_token_type . ": " . $ht_step_title['value'] . ",";
                    }

                    // Get summary of step.
                    $details = $step->field_step_details->getValue();
                    foreach ($details as $ht_step_details) {
                      $details_trim = str_replace(',', '', $ht_step_details['value']);
                      $tab_token_details .= strip_tags($details_trim) . ",";
                    }
                  }
                }
              }
            }
          }
        }
        switch ($name) {

          case 'schema_image':
            $custom_tokens[$original] = $schema_img_val;
            break;

          case 'schema_start_date':
            $custom_tokens[$original] = $dates['value'];
            break;

          case 'schema_end_date':
            $custom_tokens[$original] = ($dates['end_value'] !== $dates['value'] ? $dates['end_value'] : NULL);
            break;

          case 'schema_how_to_titles':
            $custom_tokens[$original] = $tab_token_titles;
            break;

          case 'schema_how_to_details':
            $custom_tokens[$original] = $tab_token_details;
            break;
        }
      }
    }
  endif;
  // Return the custom and updated tokens.
  return $custom_tokens;
}

/**
 * Check if we should display the token tree or not.
 *
 * @param  WidgetInterface $widget
 *   Widget.
 *
 * @return bool
 *   TRUE or FALSE.
 */
function _cob_formatter_show_token_tree($widget) {
  return $widget->getThirdPartySetting('cob_formatter', 'show_token_tree');
}

/**
 * Extends hook_field_widget_settings_summary_alter() from bos_core.module().
 */
function token_extensions_field_widget_settings_summary_alter(&$summary, $context) {
  $widget      = $context['widget'];
  $widget_type = $widget->getPluginId();

  switch ($widget_type) {
    case 'string_textfield':
    case 'string_textarea':
    case 'text_textfield':
    case 'text_textarea':
    case 'text_textarea_with_summary':
      if (_cob_formatter_show_token_tree($widget)) {
        $summary[] = t("Show available tokens in field's description");
      }

      break;
  }

}

/**
 * Extends hook_field_widget_third_party_settings_form() from bos_core.module.
 */
function token_extensions_field_widget_third_party_settings_form(WidgetInterface $plugin, FieldDefinitionInterface $field_definition, $form_mode, $form, FormStateInterface $form_state) {

  switch ($plugin->getPluginId()) {
    case 'string_textfield':
    case 'string_textarea':
    case 'text_textfield':
    case 'text_textarea':
    case 'text_textarea_with_summary':
      $element['show_token_tree'] = array(
        '#type'          => 'checkbox',
        '#title'         => t("Show available tokens in field's description"),
        '#return_value'  => 1,
        '#default_value' => _cob_formatter_show_token_tree($plugin),
      );

      break;
    default:
      $element = [];
  }

  return $element;
}
