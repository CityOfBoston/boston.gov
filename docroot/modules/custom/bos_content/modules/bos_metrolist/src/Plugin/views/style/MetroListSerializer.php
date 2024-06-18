<?php

namespace Drupal\bos_metrolist\Plugin\views\style;

use Drupal\rest\Plugin\views\style\Serializer;
use Drupal\views\Annotation\ViewsStyle;

/**
 * The style plugin for serialized output formats.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "metrolist_serializer",
 *   title = @Translation("MetroList Serializer"),
 *   help = @Translation("Serializes views row data using the MetroList Serializer component."),
 *   display_types = {"data"}
 * )
 */
class MetroListSerializer extends Serializer {

  /**
   * {@inheritdoc}
   */
  public function render() {
    $rows = [];
    $options = [];
    // If the Data Entity row plugin is used, this will be an array of entities
    // which will pass through Serializer to one of the registered Normalizers,
    // which will transform it to arrays/scalars. If the Data field row plugin
    // is used, $rows will not contain objects and will pass directly to the
    // Encoder.
    foreach ($this->view->result as $row_index => $row) {
      $this->view->row_index = $row_index;

      if ($this->view->current_display == 'rest_export_nested_1') {
        $this->occupancyType = 'rent';
      }
      $row = $this->view->rowPlugin->render($row);

      // Loop through the rows fields and if the string value is a number then set as an int value.
      foreach ($row as $fieldName => $field) {

        if ($fieldName == 'units') {
          $offer = (string) $row['offer'];
          $assignment = (string) $row['assignment'];
          $unitGroups = json_decode(trim((string) $field));

          foreach ($unitGroups as $groupKey => $unitGroup) {
            if ($unitGroup->occupancyType != $offer || $unitGroup->assignmentType != $assignment) {
              unset($unitGroups[$groupKey]);
            }
            else {
              unset($unitGroups[$groupKey]->occupancyType);
              unset($unitGroups[$groupKey]->assignmentType);
            }
          }

          $field = json_encode(array_values($unitGroups));
        }

        if ($fieldName == 'maxIncomeByHouseholdSize') {
          $fields = explode(', ', (String) $field);
          foreach ($fields as $key => $value) {
            $fields[$key] = is_numeric($value) ? intval($value) : $value;
          }
          $field = $fields;
        }

        if ($fieldName != 'id') {
          $value = is_string($field) || is_array($field) ? $field : $field->__toString();

          if ($value === 'null') {
            $row[$fieldName] = NULL;
          }
          else {
            $row[$fieldName] = is_numeric($value) ? intval($value) : $field;
          }

        }
      }
      $rows[] = $row;
    }
    unset($this->view->row_index);

    // Get the content type configured in the display or fallback to the
    // default.
    if ((empty($this->view->live_preview))) {
      $content_type = $this->displayHandler->getContentType();
    }
    else {
      $content_type = !empty($this->options['formats']) ? reset($this->options['formats']) : 'json';
    }

    if ($this->view->current_display == 'rest_export_nested_1') {
      // json_decode(trim((string)$rows[0]["units"]))
      $this->occupancyType = 'rent';
    }

    return $this->serializer->serialize($rows, $content_type, ['views_style_plugin' => $this]);
  }

}
