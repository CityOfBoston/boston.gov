<?php

namespace Drupal\bos_core\Form;

use Drupal\bos_google_cloud\GcGenerationPrompt;
use Drupal\bos_google_cloud\Services\GcCacheAI;
use Drupal\bos_google_cloud\Services\GcTextSummarizer;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;

/**
 * Admin Settings form for bos_core.
 *
 * @see https://developers.google.com/analytics/devguides/collection/protocol/v1/reference
 */

/**
 * Class BosCoreSettingsForm.
 *
 * @package Drupal\bos_core\Form
 */
class BosCoreSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bos_core_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ["bos_core.settings"];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('bos_core.settings');
    $msettings = $config->get('icon') ?? [];
    $settings = $config->get('ga_settings') ?? [];
    $ssettings = $config->get('summarizer') ?? [];

    $endpoint = isset($settings["ga_endpoint"]) ? $settings["ga_endpoint"] : "https://www.google-analytics.com/collect";

    $content_types = [];
    $def_content_types = [];
    foreach(\Drupal::entityTypeManager()
      ->getStorage('node_type')
      ->loadMultiple() as $ct_name => $ct) {
      if (!empty($ct_name) && !empty($ct)) {
        $content_types[$ct_name] = "<b>$ct_name:</b> <i>{$ct->get("description")}</i>";
        $def_content_types[$ct_name] = ["enabled" => 0];
      }
    }
    $def_content_types = array_merge($def_content_types, $ssettings['content_types']??[]);

    $form = [
      '#tree' => TRUE,
      'bos_core' => [
        '#type' => 'fieldset',
        '#title' => 'Boston Core Settings',
        '#description' => 'Configuration for Core Boston Components.',
        "#description_display" => "before",
        '#collapsible' => FALSE,

        "icon" => [
          '#type' => 'details',
          '#title' => 'Patterns Icon Library',
          '#description' => 'Integration with patterns icon library.',
          '#open' => FALSE,

          "manifest" => [
            '#type' => 'textfield',
            '#title' => t('Manifest location'),
            '#description' => t('The remote http location for the icon manifest.txt file.<br/>example: <i>https://patterns.boston.gov/assets/icons/manifest.txt</i>'),
            '#default_value' => $msettings['manifest'] ?: 'https://patterns.boston.gov/assets/icons/manifest.txt',
            '#attributes' => [
              "placeholder" => 'https://patterns.boston.gov/assets/icons/manifest.txt',
            ],
            '#required' => TRUE,
          ],
          "cron" => [
            '#type' => 'checkbox',
            '#title' => t('Import with Cron'),
            '#description' => t('The manifest file specified above will be imported on cron runs.<br><b>Note:</b> only updated icons will be processed.<br><b>Note:</b> Deslecting this checkbox means icons will only be imported when <span style="color: red">drush biim</span> is executed.'),
            '#default_value' => isset($msettings['cron']) ? $msettings['cron'] : FALSE,
          ],
        ],

        "ga_settings" => [
          '#type' => 'details',
          '#title' => 'Google Analytics',
          '#description' => 'Configuration for REST endpoint tracking in Google Analytics.',
          '#open' => FALSE,

          "ga_enabled" => [
            '#type' => 'checkbox',
            '#title' => t('REST Tracking Enabled'),
            '#default_value' => isset($settings['ga_enabled']) ? $settings['ga_enabled'] : FALSE,
          ],

          'ga_endpoint' => [
            '#type' => 'textfield',
            '#title' => t('Google URL Endpoint'),
            '#description' => t('This is the Google Measurement API endpoint being used.<br/>
                  This value can only be changed using drush (drush bgae) - please contact a developer to make changes.'),
            '#default_value' => $endpoint,
            '#attributes' => [
              "disabled" => TRUE,
            ],
          ],
          'ga_tid' => [
            '#type' => 'textfield',
            '#title' => t('Tracking ID'),
            '#description' => t('Enter the Google Tracking Id provided by Google.'),
            '#default_value' => $settings['ga_tid'] ?? "",
            '#attributes' => [
              "placeholder" => 'e.g. UA-XXXXXXX-XX',
            ],
            '#required' => TRUE,
          ],
          "ga_cid" => [
            '#type' => 'textfield',
            '#title' => t('Client ID'),
            '#description' => t("Enter a Client Id (32char Hex). <br/> See <a href='https://developers.google.com/analytics/devguides/collection/protocol/v1/parameters#cid'>documentation</a>"),
            '#default_value' => $settings['ga_cid'],
            '#attributes' => [
              "placeholder" => 'e.g. 35009a79-1a05-49d7-b876-2b884d0f825b',
            ],
            '#required' => TRUE,
          ],
        ],

        "summarizer" => [
          '#type' => 'details',
          '#title' => 'Gen-AI Body Summarizer',
          '#description' => 'Summarize the body field of the selected Content Types.<br>Note:<i>Only fields of type "Text with Summary" can be summarized.</i>',
          '#open' => FALSE,

          "content_types" => [],

        ],

      ],
    ];

    foreach($content_types as $content_type => $ct) {

      if ($form_state->isProcessingInput()) {
        $show_settings = $form_state->getValue("bos_core")["summarizer"]["content_types"][$content_type]["enabled"];
      }
      else {
        $show_settings = $def_content_types[$content_type]["enabled"] ?? 0;
      }

      if ($show_settings) {
        $defs = \Drupal::getContainer()->get("entity_field.manager")->getFieldDefinitions("node", $content_type);
        $sett = [];
        foreach($defs as $name => $def) {
          if (str_starts_with($name, "field_") || $name == "body") {
            if($def->getType() == "text_with_summary") {
              $sett[$name] = [
                "#type" => "checkbox",
                "#title" => $name,
                "#default_value" => $def_content_types[$content_type]["settings"]["fields"][$name] ?? 1,
              ];
            }
          }
        }

      }

      $form["bos_core"]["summarizer"]["content_types"][$content_type] = [
        "enabled" => [
          "#type" => "checkbox",
          "#title" => $ct,
          "#default_value" => $show_settings,
          '#ajax' => [
            'callback' => [$this, 'ajaxFormHandler'],
            'event' => 'click',
            'wrapper' => 'edit-bos-core-summarizer',
            'disable-refocus' => TRUE,
            'progress' => [
              'type' => 'throbber',
            ]
          ],
        ],
        "settings" => [
          "#type" => "fieldset",
          "#title" => "$content_type settings:",
          "#description" => "The following fields can be summarized:",
          "#description_display" => "before",
          '#attributes' => [
            "style" => ($show_settings ? "" : "display:none;"),
          ],
          "fields" => $sett ?? [],
          'prompt' => [
            "#type" => 'select',
            "#title" => "Summarizer Prompt",
            '#default_value' => $def_content_types[$content_type]["settings"]["prompt"] ?? "default",
            "#options" => GcGenerationPrompt::getPrompts(GcTextSummarizer::id())
          ],
          'cache' => [
            "#type" => 'select',
            "#title" => 'Cache Duration',
            '#default_value' => $def_content_types[$content_type]["settings"]["cache"] ?? GcCacheAI::CACHE_EXPIRY_1DAY,
            "#options" => GcCacheAI::getCacheExpiryOptions(),
          ],
        ]
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $settings = $form_state->getValue('bos_core');

    $newValues1 = [
      'ga_tid' => $settings['ga_settings']['ga_tid'],
      'ga_cid' => $settings['ga_settings']['ga_cid'],
      'ga_enabled' => $settings['ga_settings']['ga_enabled'],
    ];
    $newValues2 = [
      'manifest' => $settings['icon']['manifest'],
      'cron' => $settings['icon']['cron'],
    ];
    $newValues3 = [
      "content_types" => array_filter($settings["summarizer"]['content_types'], function($value) {return $value["enabled"];})
    ];

    $this->config('bos_core.settings')
      ->set('ga_settings', $newValues1)
      ->set('icon', $newValues2)
      ->set('summarizer', $newValues3)
      ->save();

    parent::submitForm($form, $form_state);

  }

  /**
   * Helper to listen for Ajax callbacks, and redirect to correct service.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  public function ajaxFormHandler(array &$form, FormStateInterface $form_state): array {
    $form["bos_core"]["summarizer"]["#open"] = TRUE;
    $form["bos_core"]["summarizer"]["#id"] = "edit-bos-core-summarizer";
    return $form["bos_core"]["summarizer"];
  }

}
