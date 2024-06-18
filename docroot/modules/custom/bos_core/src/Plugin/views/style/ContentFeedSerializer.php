<?php

namespace Drupal\bos_core\Plugin\views\style;

use Drupal\rest\Plugin\views\style\Serializer;

/**
 * The style plugin for serialized output formats.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "content_feed_serializer",
 *   title = @Translation("Content Feed Serializer"),
 *   help = @Translation("Serializes views row data using the Content Feed Serializer component."),
 *   display_types = {"data"}
 * )
 */
class ContentFeedSerializer extends Serializer {

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

      $row = $this->view->rowPlugin->render($row);

      // Loop through the rows fields and if the string value is a number then set as an int value.
      foreach ($row as $fieldName => $field) {

        if ($fieldName != 'id') {
          $value = is_string($field) || is_array($field) ? $field : $field->__toString();

          if ($value === 'null') {
            $row[$fieldName] = NULL;
          }elseif ($value === "true") {
            $row[$fieldName] = true;
          }elseif ($value === "false") {
            $row[$fieldName] = false;
          }
          else {
            $row[$fieldName] = is_numeric($value) ? intval($value) : $field;
          }

          // Fake the language support for the 311 app
          if ($fieldName === "title" || $fieldName == "body" || $fieldName == "category") {
            $row[$fieldName] = ['en' => $value];
          }

        }
      }
      $rows[] = $row;
    }
    unset($this->view->row_index);

    // Get the content type configured in the display or fallback to the default.
    if ((empty($this->view->live_preview))) {
      $content_type = $this->displayHandler->getContentType();
    }
    else {
      $content_type = !empty($this->options['formats']) ? reset($this->options['formats']) : 'json';
    }

    return $this->serializer->serialize($rows, $content_type, ['views_style_plugin' => $this]);
  }

}
