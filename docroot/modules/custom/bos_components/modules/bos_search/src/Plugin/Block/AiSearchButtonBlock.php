<?php

namespace Drupal\bos_search\Plugin\Block;

use Drupal\bos_search\AiSearch;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides an AI-enabled Search Button block.
 *
 * @Block(
 *   id = "Ai-enabled-search-button",
 *   admin_label = @Translation("AI Enabled Search Button"),
 *   category = @Translation("Boston"),
 * )
 */
class AiSearchButtonBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
    /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'search_button_title' => "Search",
      "search_button_css" => "",
      "aisearch_config_preset" => ""
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $presets = AiSearch::getPresets();
    $form = parent::blockForm($form, $form_state);
    $form['button'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Search Button Display'),
      '#description' => $this->t('Settings for the button used to launch the search form.'),
      '#description_display' => 'before',
      'search_button_title' => [
        '#type' => 'textfield',
        '#title' => $this->t('Button Text'),
        '#description' => $this->t('Enter the text to appear on the search button.'),
        '#default_value' => $this->configuration['search_button_title'] ?? "",
      ],
      'search_button_css' => [
        '#type' => 'textfield',
        '#title' => $this->t('Search Button Custom css'),
        '#description' => $this->t('Add any additional css classes to the button'),
        '#default_value' => $this->configuration['search_button_css'] ?? "",
      ],
      'search_block_text' => [
        '#type' => 'textarea',
        '#title' => $this->t('Search Block Body Text'),
        '#description' => $this->t('Enter the body text to appear alongside the search button. Can be left blank.'),
        '#default_value' => $this->configuration['search_block_text'] ?? "",
      ],
    ];
    $form['display'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Search Form Display'),
      '#description' => $this->t('Settings which control the way the search form is presented to the user.'),
      '#description_display' => 'before',
      'aisearch_config_display' => [
        '#type' => 'radios',
        '#title' => $this->t('Form Type'),
        '#options' => [
          0 => 'Modal (form will show in a popup window)',
          1 => 'Block (form will display in a block on a page)',
        ],
        '#description' => $this->t('Select the display method for the search form.'),
        '#default_value' => $this->configuration['aisearch_config_display'] ?? "",
      ],
      'aisearch_config_preset' => [
        '#type' => 'select',
        '#title' => $this->t('AI-Enabled Search Preset'),
        '#options' => $presets,
        '#description' => $this->t('Select the AI Model (and settings) for the Modal Search Form.'),
        '#default_value' => $this->configuration['aisearch_config_preset'] ?? "",
        '#states' => [
          'visible' => [
            ':input[name="settings[display][aisearch_config_display]"]' => ['value' => '0'],
          ],
        ],
      ],
      'aisearch_config_searchpage' => [
        '#type' => 'textfield',
        '#title' => $this->t('Host Form Page'),
        '#autocomplete_route_name' => 'bos_search.autocomplete_nodes',
        '#description' => $this->t('Please select the page which contains the search block.'),
        '#default_value' => $this->configuration['aisearch_config_searchpage'] ?? "",
        '#states' => [
          'visible' => [
            ':input[name="settings[display][aisearch_config_display]"]' => ['value' => '1'],
          ],
        ],
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    parent::blockSubmit($form, $form_state);
    $this->configuration['aisearch_config_preset'] = $form_state->getValue('display')['aisearch_config_preset'];
    $this->configuration['aisearch_config_display'] = $form_state->getValue('display')['aisearch_config_display'];
    $this->configuration['aisearch_config_searchpage'] = $form_state->getValue('display')['aisearch_config_searchpage'];
    $this->configuration['search_button_title'] = $form_state->getValue('button')['search_button_title'];
    $this->configuration['search_block_text'] = $form_state->getValue('button')['search_block_text'];
    $this->configuration['search_button_css'] = $form_state->getValue('button')['search_button_css'];
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {

    if ($this->configuration["aisearch_config_display"] === "0") {
      $url = Url::fromRoute('bos_search.open_AISearchForm');
    }
    else {
      $url = $this->configuration["aisearch_config_searchpage"];
    }

    $config = AiSearch::getPresetValues($this->configuration["aisearch_config_preset"]);
    $custom_theme_path = "/modules/custom/bos_components/modules/bos_search/templates/presets/{$config['searchform']['theme']}";

    return [
      '#theme' => 'aisearch_button',
      '#attached' => [
        "library" => ["bos_search/dynamic-loader"],
        "drupalSettings" => [
          "bos_search" => [
            'dynamic_script' => "$custom_theme_path/js/preset.js",
            'dynamic_style' => "$custom_theme_path/css/preset.css",
          ]
        ],
      ],
      '#search_form_url' => $url,
      '#button_title' => $this->configuration["search_button_title"],
      '#button_css' => $this->configuration["search_button_css"],
      '#preset' => $this->configuration["aisearch_config_preset"],
      '#body' => $this->configuration["search_block_text"],
      '#display' => $this->configuration["aisearch_config_display"] == "0" ? "modal" : "block",
    ];

  }

}
