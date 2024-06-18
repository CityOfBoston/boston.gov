<?php

namespace Drupal\bos_link\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;

/**
 * Plugin implementation of the 'link' widget.
 *
 * @FieldWidget(
 *   id = "coblink_default",
 *   label = @Translation("COBLink"),
 *   field_types = {
 *     "link"
 *   }
 * )
 */
class COBLinkWidget extends LinkWidget {

  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['uri']['#field_prefix'] = "";
    $element['uri']['#description_display'] = "before";
    $element['uri']['#description'] = "Search for the page you want to link to through the field below. If you don’t find the page through this search, use an external link instead to link to your content.";
    return $element;
  }
}
