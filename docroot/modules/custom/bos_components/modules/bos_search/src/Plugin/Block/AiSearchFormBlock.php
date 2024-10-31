<?php

namespace Drupal\bos_search\Plugin\Block;

use Drupal\bos_search\AiSearch;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides an AI-enabled Search Form block.
 *
 * @Block(
 *   id = "Ai-enabled-search-form",
 *   admin_label = @Translation("AI Enabled Search Form"),
 *   category = @Translation("Boston"),
 * )
 */
class AiSearchFormBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
    /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      "search_form_title" => "AI Search of Site",
      "aisearch_config_preset" => ""
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $presets = AiSearch::getPresets();
    $form = parent::blockForm($form, $form_state);
    $form['preset'] = [
      '#type' => 'fieldset',
      '#title' => 'Search Block Preset',
      '#description' => $this->t('Please provide a preset to be used by this form block.'),
      '#description_display' => 'before',
      'aisearch_config_preset' => [
        '#type' => 'select',
        '#options' => $presets,
        '#description' => $this->t('This defines the AI Model (and settings) that the Search Form will utilise.<br><br>Presets are defined at <a href="/admin/config/system/boston/aisearch">admin/config/system/boston/aisearch</a>'),
        '#default_value' => $this->configuration['aisearch_config_preset'] ?? "",
        ],
      ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $this->configuration['aisearch_config_preset'] = $form_state->getValue('preset')['aisearch_config_preset'];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $preset = \Drupal::request()->query->get('preset') ?: ($this->configuration["aisearch_config_preset"] ?: AiSearch::getPreset());
    $params = [
      "preset" => $preset,
    ];
    return [
      [
        '#lazy_builder' => ['bos_search.callbacks:renderSearchForm', $params ],
        '#create_placeholder' => TRUE,
      ],
    ];
  }

}
