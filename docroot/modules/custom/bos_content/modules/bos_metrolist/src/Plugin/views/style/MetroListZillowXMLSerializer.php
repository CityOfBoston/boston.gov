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
 *   id = "metrolist_zillow_xml_serializer",
 *   title = @Translation("MetroList Zillow XML Serializer"),
 *   help = @Translation("Serializes views row data using the MetroList Zillow XML Serializer API."),
 *   display_types = {"data"}
 * )
 */
class MetroListZillowXMLSerializer extends Serializer {

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

        $test = $fieldName;

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
      $content_type = !empty($this->options['formats']) ? reset($this->options['formats']) : 'xml';
    }

    return $this->serializer->serialize($rows, $content_type, ['views_style_plugin' => $this]);
  }

}
