<?php

namespace Drupal\bos_google_cloud\Form;

use Drupal;
use Drupal\bos_google_cloud\GcGenerationPrompt;
use Drupal\bos_google_cloud\Services\GcAuthenticator;
use Drupal\bos_google_cloud\Services\GcConversation;
use Drupal\bos_google_cloud\Services\GcGeocoder;
use Drupal\bos_google_cloud\Services\GcSearch;
use Drupal\bos_google_cloud\Services\GcTextRewriter;
use Drupal\bos_google_cloud\Services\GcTextSummarizer;
use Drupal\bos_google_cloud\Services\GcTranslation;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Creates a config/admin form for bos_google_cloud module.
 *
 * david 01 2024
 * @file docroot/modules/custom/bos_components/modules/bos_google_cloud/src/Form/configForm.php
 */
class ConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'bos_google_cloud_configForm';
  }

  /**
   * @inheritDoc
   */
  protected function getEditableConfigNames(): array {
    return ["bos_google_cloud.settings"];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $settings = Drupal::configFactory()->getEditable("bos_google_cloud.settings");

    // Base form structure.
    $form = [
      'google_cloud' => [
        '#type' => 'fieldset',
        '#title' => 'Google Cloud Configurations',
        "#markup" => "<h5>This page allows you to set the various Google Cloud configurations and locations for Services exposed in this module.</h5>",
        '#tree' => TRUE,
        'authentication_wrapper' => [
          '#type' => "fieldset",
          '#title' => "Authentication",
        ],

        'services_wrapper' => [
          '#type' => "fieldset",
          '#title' => "Services",

          'discovery_engine' => [
            '#type' => "fieldset",
            '#title' => "Discovery Engine API",
            'quota' => [
              '#type' => 'textfield',
              '#title' => t('Subnmission Rate Limit:'),
              '#description' => t('The maximum number of Discovery Engine API requests per minute'),
              '#default_value' => $settings->get('discovery_engine.quota') ?? 300,
              '#required' => TRUE,
              "#weight" => 10,
            ],
          ],

          'vertex_ai' => [
            '#type' => "fieldset",
            '#title' => "Vertex AI API",
            'quota' => [
              '#type' => 'textfield',
              '#title' => t('Subnmission Rate Limit:'),
              '#description' => t('The maximum number of Vertex AI requests per minute'),
              '#default_value' => $settings->get('vertex_ai.quota') ?? 10,
              '#required' => TRUE,
              "#weight" => 10,
            ],
          ],

          'google_cloud' => [
            '#type' => "fieldset",
            '#title' => "Google Cloud Services",
          ],
        ],

        'prompts_wrapper' => [
          '#type' => "fieldset",
          '#title' => "Prompt Settings",
          "#description" => "The following are prompts which can be defined for the various services.",
          '#description_display' => 'before',
          'tester' => [
            '#type' => "link",
            '#title' => "Prompt Tester",
            '#weight' => 100,
            '#url' => Url::fromRoute('bos_google_cloud.open_PromptTesterForm'),
            '#attributes' => [
              'class' => ['use-ajax', 'button'],
            ],
          ],
        ],
      ],
    ];

    // Authentication section.
    $authenticator = new GcAuthenticator();
    $authenticator->buildForm($form['google_cloud']['authentication_wrapper']);

    // Search section.
    /**
     * @var \Drupal\bos_google_cloud\Services\GcSearch $search
     */
    $search = Drupal::service("bos_google_cloud.GcSearch");
    $search->buildForm($form["google_cloud"]["services_wrapper"]["discovery_engine"]);

    // Conversation section.
    /**
     * @var \Drupal\bos_google_cloud\Services\GcConversation $conversation
     */
    $conversation = Drupal::service("bos_google_cloud.GcConversation");
    $conversation->buildForm($form["google_cloud"]["services_wrapper"]["discovery_engine"]);

    // Rewiter section.
    /**
     * @var \Drupal\bos_google_cloud\Services\GcTextRewriter $rewriter
     */
    $rewriter = Drupal::service("bos_google_cloud.GcTextRewriter");
    $rewriter->buildForm($form["google_cloud"]["services_wrapper"]["vertex_ai"]);

    // Summaraizer section.
    /**
     * @var \Drupal\bos_google_cloud\Services\GcTextSummarizer $summarizer
     */
    $summarizer = Drupal::service("bos_google_cloud.GcTextSummarizer");
    $summarizer->buildForm($form["google_cloud"]["services_wrapper"]["vertex_ai"]);

    // Translation section.
    /**
     * @var \Drupal\bos_google_cloud\Services\GcTranslation $translation
     */
    $translation = Drupal::service("bos_google_cloud.GcTranslate");
    $translation->buildForm($form["google_cloud"]["services_wrapper"]["vertex_ai"]);

    // Geocoder section (copy settings across from bos_geocoder module)
    $geocoder = new GcGeocoder();
    $geocoder->buildForm($form['google_cloud']["services_wrapper"]['google_cloud']);

    // Prompt section.
    $prompt = new GcGenerationPrompt();
    $prompt->buildForm($form['google_cloud']["prompts_wrapper"]);

    return parent::buildForm($form, $form_state);

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {

    $values = $form_state->getValues()["google_cloud"]['services_wrapper'];
    Drupal::configFactory()->getEditable("bos_google_cloud.settings")
      ->set("vertex_ai.quota", $values["vertex_ai"]["quota"])
      ->set("discovery_engine.quota", $values["discovery_engine"]["quota"])
      ->save();

    // Authentication section.
    $authenticator = new GcAuthenticator();
    $authenticator->submitForm($form, $form_state);

    // Search section
    /**
     * @var $search \Drupal\bos_google_cloud\Services\GcSearch
     */
    $search = Drupal::service("bos_google_cloud.GcSearch");
    $search->submitForm($form, $form_state);

    // Conversation section
    /**
     * @var $conversation \Drupal\bos_google_cloud\Services\GcConversation
     */
    $conversation = Drupal::service("bos_google_cloud.GcConversation");
    $conversation->submitForm($form, $form_state);

    // Rewriter section
    /**
     * @var $rewriter \Drupal\bos_google_cloud\Services\GcTextRewriter
     */
    $rewriter = Drupal::service("bos_google_cloud.GcTextRewriter");
    $rewriter->submitForm($form, $form_state);

    // Summaraizer section
    /**
     * @var $summarizer \Drupal\bos_google_cloud\Services\GcTextSummarizer
     */
    $summarizer = Drupal::service("bos_google_cloud.GcTextSummarizer");
    $summarizer->submitForm($form, $form_state);

    // Translation section
    /**
     * @var $translation \Drupal\bos_google_cloud\Services\GcTranslation
     */
    $translation = Drupal::service("bos_google_cloud.GcTranslate");
    $translation->submitForm($form, $form_state);

    // Prompt section
    $prompt = new GcGenerationPrompt();
    $prompt->submitForm($form, $form_state);

    parent::submitForm($form, $form_state); // optional
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
  }

  /**
   * Helper to listen for Ajax callbacks, and redirect to correct service.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  public function ajaxHandler(array &$form, FormStateInterface $form_state): array {
    return match ($form_state->getUserInput()["_triggering_element_value"]) {
      "Test Search" => GcSearch::ajaxTestService($form, $form_state),
      "Test Conversation" => GcConversation::ajaxTestService($form, $form_state),
      "Test Rewriter" => GcTextRewriter::ajaxTestService($form, $form_state),
      "Test Summarizer" => GcTextSummarizer::ajaxTestService($form, $form_state),
      "Test Translator" => GcTranslation::ajaxTestService($form, $form_state),
      "Test Geocoder" => GcGeocoder::ajaxTestService($form, $form_state),
      default => [],
    };
  }

}
