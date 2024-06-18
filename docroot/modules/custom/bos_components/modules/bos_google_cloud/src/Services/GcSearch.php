<?php

namespace Drupal\bos_google_cloud\Services;

use Drupal;
use Drupal\bos_core\Controllers\Curl\BosCurlControllerBase;
use Drupal\bos_google_cloud\GcGenerationURL;
use Drupal\bos_google_cloud\GcGenerationPayload;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\bos_core\Controllers\Settings\CobSettings;
use Drupal\Core\Render\Markup;
use Exception;

/**
  class GcSearch
  Creates a gen-ai search service for bos_google_cloud

  david 01 2024
  @file docroot/modules/custom/bos_components/modules/bos_google_cloud/src/Services/GcSearch.php
*/

class GcSearch extends BosCurlControllerBase implements GcServiceInterface {

    /**
   * Logger object for class.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $log;

  /**
   * Config object for class.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  protected array $settings;

  /**
   * @var GcAuthenticator Google Cloud Authenication Service.
   */
  protected GcAuthenticator $authenticator;

  public function __construct(LoggerChannelFactory $logger, ConfigFactory $config) {

    // Load the service-supplied variables.
    $this->log = $logger->get('bos_google_cloud');
    $this->config = $config->get("bos_google_cloud.settings");

    $this->settings = CobSettings::getSettings("GCAPI_SETTINGS", "bos_google_cloud");

    // Create an authenticator using service account 1.
    $this->authenticator = new GcAuthenticator($this->settings["search"]["service_account"] ?? GcAuthenticator::SVS_ACCOUNT_LIST[0]);

    // Do the CuRL initialization in BosCurlControllerBase.
    parent::__construct();

  }

  /**
   * @inheritDoc
   */
  public static function id(): string {
    return "search";
  }

  /**
   * Set the service_account, overriding the default.
   *
   * @param string $service_account A valid service account.
   *
   * @return $this
   * @throws \Exception
   */
  public function setServiceAccount(string $service_account):GcSearch {
    if (!$this->authenticator->validateServiceAccount($service_account)) {
      throw new Exception("Service account does not exist.");
    }
    $this->settings["service_account"] = $service_account;
    return $this;
  }

  /**
   * Searches boston.gov based on a search text, using a pre-defined prompt.
   *
   * @param array $parameters Array containing "search" text to be searched
   *   for, and "prompt" a search type prompt.
   *
   * @return string
   * @throws \Exception
   */
  public function execute(array $parameters = []): string {

    $settings = $this->settings["search"] ?? [];

    try {
      $headers = [
        "Authorization" => $this->authenticator->getAccessToken($settings["service_account"], "Bearer")
      ];
    }
    catch (Exception $e) {
      $this->error = $e->getMessage() ?? "Error getting access token.";
      return $this->error();
    }

    if (empty($parameters["search"])) {
      $this->error = "A search request is required.";
      return $this->error();
    }

    $parameters["prompt"] = $parameters["prompt"] ?? "default";
    $parameters["text"] = $parameters["search"];

    if (GcGenerationURL::quota_exceeded(GcGenerationURL::CONVERSATION)) {
      $this->error = "Quota exceeded for this API";
      return $this->error;
    }

    $url = GcGenerationURL::build(GcGenerationURL::CONVERSATION, $settings);

    try {
      if (!$payload = GcGenerationPayload::build(GcGenerationPayload::CONVERSATION, $parameters)) {
        $this->error = "Could not build Payload";
        return $this->error;
      }
    }
    catch (Exception $e) {
      $this->error = $e->getMessage();
      return $this->error;
    }

    $results = $this->post($url, $payload, $headers);

    if ($this->http_code() == 200 && !$this->error()) {

      $this->response["search"] = [];

      $this->response["search"]["results"] = $results["reply"]["summary"]["summaryWithMetadata"];
      $this->response["search"]["conversation"] = $results["conversation"];
      $this->response["search"]["results"]["webpages"] = $results["searchResults"];
      $this->loadSafetyRatings($results["reply"]["summary"]["safetyAttributes"]);
      unset($this->response["body"]);

      if (empty($this->response["search"]["results"]) || $this->error()) {
        $this->error() || $this->error = "Unexpected response from GcSearch";
        return $this->error();
      }

      return $this->response["search"]["results"]["summary"];

    }

    elseif ($this->http_code() == 401) {
      // The token is invalid, because we are caching for the lifetime of the
      // token, this probably means it has been refreshed elsewhere.
      $this->authenticator->invalidateAuthToken($settings["service_account"]);
      if (empty($parameters["invalid-retry"])) {
        $parameters["invalid-retry"] = 1;
        return $this->execute($parameters);
      }
      return "";
    }

    elseif ($this->error()) {
      return "";
    }

    else {
      $this->error = "Unknown Error: " . $this->response["http_code"];
      return "";
    }

  }

  /**
   * Update the $this->response["search"]["ratings"] array if the safety scores
   * in $ratings are higher (less safe) than those already stored.
   *
   * @param array $ratings The safetyRatings from a gemini ::predict call.
   *
   * @return void
   */
  private function loadSafetyRatings(array $ratings): void {

    if (!isset($this->response["search"]["safetyRatings"])) {
      $this->response["search"]["safetyRatings"] = [];
    }

    foreach($ratings["categories"] as $key => $rating) {
      $this->response["search"]["safetyRatings"][$rating] = $ratings["scores"][$key];
    }

  }

  /**
   * @inheritDoc
   */
  public function buildForm(array &$form): void {

    $project_id="612042612588";
    $model_id="drupalwebsite_1702919119768";
    $location_id="global";
    $endpoint="https://discoveryengine.googleapis.com";

    $svs_accounts = [];
    foreach ($this->settings["auth"]??[] as $name => $value) {
      if ($name) {
        $svs_accounts[$name] = $name;
      }
    }

    $settings = $this->settings['search'] ?? [];

    $form = $form + [
      'search' => [
        '#type' => 'details',
        '#title' => 'Gen-AI Search',
        "#description" => "Service which searches a website-based datastore and returns summary text, page results, annotations and references.",
        '#open' => FALSE,
        'project_id' => [
          '#type' => 'textfield',
          '#title' => t('Google Cloud Project'),
          '#description' => t(''),
          '#default_value' => $settings['project_id'] ?? $project_id,
          '#required' => TRUE,
          '#attributes' => [
            "placeholder" => 'e.g. ' . $project_id,
          ],
        ],
        'datastore_id' => [
          '#type' => 'textfield',
          '#title' => t('Data Store'),
          '#description' => t(''),
          '#default_value' => $settings['datastore_id'] ?? $model_id,
          '#required' => TRUE,
          '#attributes' => [
            "placeholder" => 'e.g. ' . $model_id,
          ],
        ],
        'location_id' => [
          '#type' => 'textfield',
          '#title' => t('Location (always global for now)'),
          '#description' => t(''),
          '#default_value' => $settings['location_id'] ?? $location_id,
          '#required' => TRUE,
          '#disabled' => TRUE,
          '#attributes' => [
            "placeholder" => 'e.g. ' . $location_id,
          ],
        ],
        'endpoint' => [
          '#type' => 'textfield',
          '#title' => t('Endpoint URL'),
          '#description' => t('Ensure the API version is appended to the URL, e.g. /v1 or /v1alpha'),
          '#default_value' => $settings['endpoint'] ?? $endpoint,
          '#required' => TRUE,
          '#attributes' => [
            "placeholder" => 'e.g. ' . $endpoint,
          ],
        ],
        'service_account' => [
          '#type' => 'select',
          '#title' => t('The default service account to use'),
          '#description' => t('This default can be overridden using the API.'),
          '#default_value' => $settings['service_account'] ?? ($svs_accounts[0] ?? ""),
          '#options' => $svs_accounts,
          '#required' => TRUE,
          '#attributes' => [
            "placeholder" => 'e.g. ' . ($svs_accounts[0] ?? "No Service Accounts!"),
          ],
        ],
        'test_wrapper' => [
          'test_button' => [
            '#type' => 'button',
            "#value" => t('Test Search'),
            '#attributes' => [
              'class' => ['button', 'button--primary'],
              'title' => "Test the provided configuration for this service"
            ],
            '#access' => TRUE,
            '#ajax' => [
              'callback' => '::ajaxHandler',
              'event' => 'click',
              'wrapper' => 'edit-search-result',
              'disable-refocus' => TRUE,
              'progress' => [
                'type' => 'throbber',
              ]
            ],
            '#suffix' => '<span id="edit-search-result"></span>',
          ],
        ],
      ],
    ];
  }

  /**
   * @inheritDoc
   */
  public function submitForm(array $form, FormStateInterface $form_state): void {

    $values = $form_state->getValues()["google_cloud"]['services_wrapper']['discovery_engine'][self::id()];
    $config = Drupal::configFactory()->getEditable("bos_google_cloud.settings");

    if ($config->get("search.project_id") !== $values['project_id']
      ||$config->get("search.datastore_id") !== $values['datastore_id']
      ||$config->get("search.location_id") !== $values['location_id']
      ||$config->get("search.service_account") !== $values['service_account']
      ||$config->get("search.endpoint") !== $values['endpoint']) {
      $config->set("search.project_id", $values['project_id'])
        ->set("search.datastore_id", $values['datastore_id'])
        ->set("search.location_id", $values['location_id'])
        ->set("search.endpoint", $values['endpoint'])
        ->set("search.service_account", $values['service_account'])
        ->save();
    }

  }

  /**
   * @inheritDoc
   */
  public function validateForm(array $form, FormStateInterface &$form_state): void {
    // not required
  }

  /**
   * Ajax callback to test Search
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  public static function ajaxTestService(array &$form, FormStateInterface $form_state): array {

    $values = $form_state->getValues()["google_cloud"]['services_wrapper']['discovery_engine']['search'];
    $search = Drupal::service("bos_google_cloud.GcSearch");

    $options = [
      "search" => "How do I pay a parking ticket",
      "prompt" => "default",
    ];

    unset($values["test_wrapper"]);
    $search->settings = CobSettings::array_merge_deep($search->settings, ["search" => $values]);
    $result = $search->execute($options);

    if (!empty($result) && !$search->error()) {
      return ["#markup" => Markup::create("<span id='edit-search-result' style='color:green'><b>&#x2714; Success:</b> Authentication and Service Config are OK.</span>")];
    }
    else {
      return ["#markup" => Markup::create("<span id='edit-search-result' style='color:red'><b>&#x2717; Failed:</b> {$search->error()}</span>")];
    }

  }

}
