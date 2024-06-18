<?php

namespace Drupal\bos_core\Plugin\entity_embed\EntityEmbedDisplay;

use Drupal\Core\Form\FormStateInterface;
use Drupal\lightning_media\Plugin\entity_embed\EntityEmbedDisplay\MediaImage;

/**
 * Extends display provided by lightning_media.
 *
 * @EntityEmbedDisplay(
 *   id = "bos_media_image",
 *   label = @Translation("Bos Media Image"),
 *   entity_types = {"media"},
 *   field_type = "image",
 *   provider = "image"
 * )
 */
class BosMediaImage extends MediaImage {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    // Limit image style options to ones appropriate for use in WYSIWYG.
    $form['image_style']['#options'] = array_intersect_key($form['image_style']['#options'], [
      'bos_text_responsive' => '',
    ]);
    $form['image_style']['#default_value'] = "one_column";
    unset($form['image_style']['#empty_option']);

    // Remove svg specific form elements as they aren't allowed in the WYSIWYG.
    unset($form['svg_render_as_image']);
    unset($form['svg_attributes']);
    return $form;
  }

}
