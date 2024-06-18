<?php

namespace Drupal\bos_core\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin formatter.
 *
 * @FieldFormatter(
 *   id = "cob_formatter",
 *   module = "bos_core",
 *   label = @Translation("City of Boston Display Formatter"),
 *   field_types = {
 *     "string",
 *     "string_long",
 *     "text",
 *     "text_long",
 *     "text_with_summary",
 *   },
 *   quickedit = {
 *     "editor" = "plain_text"
 *   }
 * )
 */
class CobFormatter extends FormatterBase {
  const FORMAT_JSON = 'json';

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
        'token_replace' => 0,
        'filter' => 'json',
        'autop' => 0,
        'json_table_type' => 'table',
      ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elFilterId     = Html::getUniqueId('cob_formatter_filter');

    $element['filter'] = [
      '#id'      => $elFilterId,
      '#title'   => $this->t('Filter'),
      '#type'    => 'select',
      '#options' => [
        static::FORMAT_JSON   => $this->t('Json to Table'),
      ],
      '#default_value' => $this->getSetting('filter'),
    ];

    $element['json_table_type'] = [
      '#type'           => 'select',
      '#title'          => t('json table type'),
      '#description'    => t('Select the presentation of the json data'),
      '#default_value'  => $this->getSetting('json_table_type'),
      '#options'        => array(
        'ulli'  => t('Unordered List'),
        'table' => t('Table'),
      ),
      '#states'        => [
        'invisible' => [
          '#' . $elFilterId => ['!value' => static::FORMAT_JSON],
        ],
      ],
    ];

    if (\Drupal::moduleHandler()->moduleExists('token')) {
      $token_link = $this->_getTokenUrl($this->fieldDefinition->getTargetEntityTypeId());
      $element['token_replace'] = [
        '#type' => 'checkbox',
        '#description' => $this->t('Replace text pattern. e.g %node-title-token or %node-author-name-token, by token values.', [
            '%node-title-token' => '[node:title]',
            '%node-author-name-token' => '[node:author:name]',
          ]) . '<br/>' . $token_link,
        '#title' => $this->t('Token Replace'),
        '#default_value' => $this->getSetting('token_replace'),
      ];
    }

    $element['autop'] = [
      '#title'         => $this->t('Converts line breaks into HTML (i.e. &lt;br&gt; and &lt;p&gt;) tags.'),
      '#type'          => 'checkbox',
      '#default_value' => $this->getSetting('autop'),
      '#states'        => [
        'invisible' => [
          '#' . $elFilterId => ['!value' => static::FORMAT_JSON],
        ],
      ],
    ];

    $element['br'] = ['#markup' => '<br/>'];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $yes     = $this->t('Yes');
    $no      = $this->t('No');

   switch ($this->getSetting('filter')) {
      case static::FORMAT_JSON:
        $summary[] = $this->t('Filter: @filter', ['@filter' => $this->t('Json to Table')]);
        $summary[] = $this->t('Presentation type: @type', ['@type' => $this->getSetting("json_table_type")]);
        break;

      default:
        $summary[] = $this->t('Filter: @filter', ['@filter' => $this->t('None')]);

        break;
    }

    $autop = $this->getSetting('autop');
    $summary[] = $this->t('Token Replace: @token', ['@token' => $this->getSetting('token_replace') ? $yes : $no]);
    $summary[] = $this->t('Convert line breaks into HTML: @autop.', ['@autop' => !empty($autop) ? $yes : $no]);

    $summary = array_filter($summary);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $token_data = [
      'user' => \Drupal::currentUser(),
      $items->getEntity()->getEntityTypeId() => $items->getEntity(),
    ];

    foreach ($items as $delta => $item) {
      $output = $item->value;

      if ($this->getSetting('token_replace')) {
        $output = \Drupal::token()->replace($output, $token_data);
      }

      switch ($this->getSetting('filter')) {
        case static::FORMAT_JSON:
          $output = $this->_json_to_html($output, $this->getSetting('json_table_type'));
          // allow tables, lists and labels
          $allowed_tags = ["table", "th", "tr", "td", "ul", "li", "label"];
          break;

      }

      $elements[$delta] = [
        '#markup' => $output,
        '#allowed_tags' => $allowed_tags ?: [],
        '#langcode' => $item->getLangcode(),
      ];
    }

    return $elements;
  }

  /**
   * Builds a url to display available tokens.
   *
   * @param $token_types
   *
   * @return \Drupal\Component\Render\MarkupInterface|string
   */
  private function _getTokenUrl($token_types) {

    if (!is_array($token_types)) {
      $token_types = array($token_types);
    }

    $vars['token_types'] = $token_types;

    return \Drupal::theme()->render('token_tree_link', $vars);
  }

  /**
   * Converting a json object string to html depanded on type.
   */
  private function _json_to_html($item = '', $type = 'ulli') {

    // Turn json string to an object.
    try {
      $obj = json_decode($item);
    }
    catch (\Exception $e) {
      $obj = NULL;
    }
    // Check if json is decoded.
    if ($obj === NULL) {
      \Drupal::messenger()->addWarning('JSON to Table Filter: JSON Object string is not a valid json object and could not be decoded.');
      return '';
    }
    // Convert to desired structure.
    if ($type == 'ulli') {
      return $this->_obj_to_list($obj);
    }
    elseif ($type == 'table') {
      return $this->_obj_to_table($obj);
    }
  }

  /**
   * Converting an object to unordered list recursively.
   */
  private function _obj_to_list($obj = NULL) {

    // If empty object.
    if ($obj == NULL) {
      return '';
    }
    $output = '<ul>';
    foreach ($obj as $key => $item) {
      if (in_array(gettype($item), ['array', 'object'])) {
        // Reoursive call for nested items.
        $output .= $this->_obj_to_list($item);
      }
      else {
        $output .= '<li><label style="display:inline">' . Html::escape($key) . ':</label> ' . Html::escape($item) . '</li>';
      }
    }
    $output .= '</ul>';
    return $output;
  }

  /**
   * Converting an object to table.
   */
  private function _obj_to_table($obj = NULL) {

    // If empty object.
    if ($obj == NULL) {
      return '';
    }
    $output = '<table><thead><tr><th>Name</th><th>Value</th></tr></thead><tbody>';
    foreach ($obj as $key => $item) {
      $output .= '<tr><td>' . Html::escape($key) . '</td><td>';
      if (in_array(gettype($item), ['array', 'object'])) {
        // Recoursive call for nested items.
        $output .= $this->_obj_to_table($item);
      }
      else {
        $output .= Html::escape($item);
      }
      $output .= '</td></tr>';
    }
    $output .= '</tbody></table>';
    return $output;
  }

}
