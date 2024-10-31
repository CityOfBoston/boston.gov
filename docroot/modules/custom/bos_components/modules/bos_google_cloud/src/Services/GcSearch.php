<?php

namespace Drupal\bos_google_cloud\Services;

use Drupal;
use Drupal\bos_core\Controllers\Curl\BosCurlControllerBase;
use Drupal\bos_google_cloud\Apis\v1alpha\SearchResponse;
use Drupal\bos_google_cloud\GcGenerationPrompt;
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
 * Class GcSearch.
 *
 * Creates a gen-ai search service for bos_google_cloud - uses Discovery Engine
 * API to access Vertex Agent Builder apps, engines and datastores.
 *
 * david 01 2024
 *
 * @extends BosCurlControllerBase
 * @implements GcServiceInterface, GcAgentBuilderInterface
 *
 * @file docroot/modules/custom/bos_components/modules/bos_google_cloud/src/Services/GcSearch.php
 * @see https://cloud.google.com/generative-ai-app-builder/docs/introduction
 */
class GcSearch extends BosCurlControllerBase implements GcServiceInterface, GcAgentBuilderInterface {

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
  public function execute(array $parameters = []): FALSE|SearchResponse {

    // Verify the minimum information is available.
    $this->validateQueryParameters($parameters);
    if ($this->error()) {
      return $this->error();
    }

    // Check quota
    if (GcGenerationURL::quota_exceeded(GcGenerationURL::SEARCH)) {
      $this->error = "Quota exceeded for Discovery API";
      return $this->error;
    }

    // Manage conversations.
    $allow_conversation = ($this->settings[$this->id()]["allow_conversation"] ?? FALSE && $parameters["allow_conversation"] ?? FALSE);

    // If we have overrides for the default projects or datastores, apply the
    // override here.
    $this->overrideModelSettings($parameters);

    // Get new or cached OAuth2 authorization from GC.
    try {
      $headers = [
        "Authorization" => $this->authenticator->getAccessToken($this->settings[$this->id()]["service_account"], "Bearer")
      ];
    }
    catch (Exception $e) {
      $this->error = $e->getMessage() ?? "Error getting access token.";
      return $this->error();
    }

    // Build the endpoint.
    $url = GcGenerationURL::build(GcGenerationURL::SEARCH, $this->settings[$this->id()]);

    // Build the payload (:search).
    try {
      if (!$payload = GcGenerationPayload::build(GcGenerationPayload::SEARCH, $parameters)) {
        $this->error = "Could not build Payload";
        return $this->error;
      }
    }
    catch (Exception $e) {
      $this->error = $e->getMessage();
      return $this->error;
    }

    // Run the Query.
    $results = $this->post($url, $payload, $headers);

    if ($this->http_code() == 401) {
      // The token is invalid, because we are caching for the lifetime of the
      // token, this probably means it has been refreshed elsewhere.
      $this->authenticator->invalidateAuthToken($this->settings[$this->id()]["service_account"]);
      if (empty($parameters["invalid-retry"])) {
        $parameters["invalid-retry"] = 1;
        return $this->execute($parameters);
      }
      throw new Exception($this->error);
    }

    elseif (empty($results) || $this->error() || $this->http_code() != 200) {
      if (empty($this->error)) {$this->error = " Unknown Error: ";}
      $this->error .= ", HTTP-CODE: " . $this->response["http_code"];
      throw new Exception($this->error);
    }

    // We got some sort of response, so load it into the SearchResponse obejct,
    // verify it and then remove the "body" element because it is no longer
    // needed.
    $this->response["object"] = new SearchResponse($results);
    if (!$this->response["object"]->validate()) {
      $this->error() || $this->error = "Unexpected response from GcSearch";
      return $this->error();
    }
    unset($this->response["body"]);

    if ($allow_conversation) {

      /* When we built the initial Payload, the $allow_conversation = TRUE
      caused the query to be set up for follow-up questions (by creating a
      session).
      The SearchResponse will have returned search results and session info.
      Now we need to use the sessioninfo get a generated answer with a call
      to projects.locations.collections.engines.servingconfigs.answer */

      // Fetch the sessionid (and queryid) from the response.
      $session_id = explode("/", $results["sessionInfo"]["name"]);
      $session_id = array_pop($session_id);
      $parameters["session_id"] = $session_id;
      $query_id = explode("/", $results["sessionInfo"]["queryId"]);
      $query_id = array_pop($query_id);
      $parameters["query_id"] = $query_id;

      // Save the search request and response objects for later.
      // (Calling the post method creates new request & response objects,
      // overwriting what we currently have.)
      $this->response["session_id"] = $session_id;
      $this->response["query_id"] = $query_id;
      $searchResponse = $this->response;
      $this->searchRequest = $this->request;

      // Build the endpoint.
      $url = GcGenerationURL::build(GcGenerationURL::SEARCH_ANSWER, $this->settings[$this->id()]);

      // Build the payload (:answer).
      try {
        if (!$payload = GcGenerationPayload::build(GcGenerationPayload::SEARCH_ANSWER, $parameters)) {
          $this->error = "Could not build Payload";
          return $this->error;
        }
      }
      catch (Exception $e) {
        $this->error = $e->getMessage();
        return $this->error;
      }

      // Run the second query.
      $results = $this->post($url, $payload, $headers);

      if (!$results) {
        throw new \Exception($this->error);
      }

      // Merge the Answer Results into the Search Results
      $this->mergeResults($searchResponse, $results);
      $this->response = $searchResponse;

    }

    // Gather Vertex search metadata.
    $this->loadMetadata($parameters);

    return $this->response["object"];

  }

  /**
   * @inheritDoc
   */
  public function buildForm(array &$form): void {

    $project_id="612042612588";
    $datastore_id="drupalwebsite_1702919119768";
    $engine_id="oeoi-search-pilot_1726266124376";
    $location_id="global";
    $endpoint="https://discoveryengine.googleapis.com";
    $model="stable";

    $svs_accounts = [];
    foreach ($this->settings["auth"]??[] as $name => $value) {
      if ($name) {
        $svs_accounts[$name] = $name;
      }
    }

    $settings = $this->settings[$this->id()] ?? [];

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
          '#default_value' => $settings['datastore_id'] ?? $datastore_id,
          '#required' => TRUE,
          '#attributes' => [
            "placeholder" => 'e.g. ' . $datastore_id,
          ],
        ],
        'engine_id' => [
          '#type' => 'textfield',
          '#title' => t('Engine'),
          '#description' => t(''),
          '#default_value' => $settings['engine_id'] ?? $engine_id,
          '#required' => TRUE,
          '#attributes' => [
            "placeholder" => 'e.g. ' . $engine_id,
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
        'model' => [
          '#type' => 'select',
          '#title' => t('The LLM model to use'),
          '#description' => t('This is the model that will be used.<br>Best to set to "stable" for latest stable release (which typically is frozen and only updated periodically) or "preview" for the latest model (which is more experimental and can be updated more frequently).<br>See https://cloud.google.com/generative-ai-app-builder/docs/answer-generation-models#models'),
          '#default_value' => $settings['model'] ?? $model,
          '#options' => [
            'stable' => 'Stable',
            'preview' => 'Preview',
          ],
          '#required' => TRUE,
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
        'allow_conversation' => [
          '#type' => 'checkbox',
          '#title' => t('Allow conversations to continue.'),
          '#description' => t('If this option is de-selected, previous questions and answers are not considered for context.'),
          '#default_value' => $settings['allow_conversation'] ?? 0,
          '#required' => FALSE,
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
      ||$config->get("search.engine_id") !== $values['engine_id']
      ||$config->get("search.location_id") !== $values['location_id']
      ||$config->get("search.service_account") !== $values['service_account']
      ||$config->get("search.allow_conversation") !== $values['allow_conversation']
      ||$config->get("search.endpoint") !== $values['endpoint']
      ||$config->get("search.model") !== $values['model']) {
      $config->set("search.project_id", $values['project_id'])
        ->set("search.datastore_id", $values['datastore_id'])
        ->set("search.engine_id", $values['engine_id'])
        ->set("search.location_id", $values['location_id'])
        ->set("search.allow_conversation", $values['allow_conversation'])
        ->set("search.endpoint", $values['endpoint'])
        ->set("search.model", $values['model'])
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

  /**
   * @inheritDoc
   */
  public function hasFollowup(): bool {
    return TRUE;
  }

  /**
   * @inheritDoc
   */
  public function getSettings(): array {
    return $this->settings[$this->id()];
  }

  /**
   * @inheritDoc
   */
  public function loadMetadata(array $parameters): void {

    if (!$parameters["metadata"]) {
      return;
    }

    $service_account = $this->settings[$this->id()]["service_account"];

    // Populate the metadata with everything.
    $this->response["metadata"] = [
      "Model" => array_merge($this->settings[$this->id()], [
        $service_account => [
            "client_id" => $this->settings["auth"][$service_account]["client_id"],
            "client_email" => $this->settings["auth"][$service_account]["client_email"],
            "project_id" => $this->settings["auth"][$service_account]["project_id"],
          ]
        ]),
      "Search Presets" => [],
      "Search Query Request" => NULL,
      "Summary Query Request" => $this->request(),
      "Response" => $this->response(),
    ];
    if (property_exists($this, "searchRequest")){
      $this->response["metadata"]["Search Query Request"] = $this->searchRequest;
    }
    else {
      unset($this->response["metadata"]["Search Query Request"]);
    }

    // Flatten the SearchResponse object
    $this->response["metadata"]["Response"]["SearchResponse"] = $this->response["metadata"]["Response"]["object"]->toArray();

    // Remove elements we don't need.
    unset($this->response["metadata"]["Response"]["object"]);
    unset($this->response["metadata"]["Response"]["metadata"]);

  }

  /**
   * @param string|null $service_account *
   *
   * @inheritDoc
   */
  public function availableProjects(?string $service_account): array {
    // Todo: adjust permissions in GC so we can scan for projects, then
    //      only return projects which have agent builder enabled.
    if (!empty($service_account) && $service_account != "default") {
      $settings['service_account'] = $service_account;
    }
    return [
      "738313172788" => "ai-search-boston-gov-91793",
      "612042612588" => "vertex-ai-poc-406419",
    ];
  }

  /**
   * @inheritDoc
   */
  public function availableDataStores(?string $service_account, ?string $project_id): array {

    $settings =  $this->settings[$this->id()];

    if (!empty($service_account) && $service_account != "default") {
      $settings['service_account'] = $service_account;
    }
    if (!empty($project_id) && $project_id != "default") {
      $settings['project_id'] = $project_id;
    }

    // Get token.
    try {
      $headers = [
        "Authorization" => $this->authenticator->getAccessToken($settings['service_account'], "Bearer")
      ];
    }
    catch (Exception $e) {
      $this->error = $e->getMessage() ?? "Error getting access token.";
      return [];
    }

    $url = GcGenerationURL::build(GcGenerationURL::DATASTORE, $settings);

    // Query the AI.
    try {
      $results = $this->get($url, NULL, $headers);
    }
    catch(\Exception $e) {
      return [];
    }

    $output = [];
    foreach($results["dataStores"] ?? [] as $dataStore) {
      $dataStoreName = explode("/", $dataStore["name"]);
      $dataStoreId = array_pop($dataStoreName);
      $output[$dataStoreId] = $dataStore['displayName'];
    }
    return $output;
  }

  /**
   * @inheritDoc
   */
  public function availableEngines(?string $service_account, ?string $project_id): array {
    // Get token.
    $settings =  $this->settings[$this->id()];

    if (!empty($service_account) && $service_account != "default") {
      $settings['service_account'] = $service_account;
    }
    if (!empty($project_id) && $project_id != "default") {
      $settings['project_id'] = $project_id;
    }

    try {
      $headers = [
        "Authorization" => $this->authenticator->getAccessToken($settings['service_account'], "Bearer")
      ];
    }
    catch (Exception $e) {
      $this->error = $e->getMessage() ?? "Error getting access token.";
      return [];
    }

    $url = GcGenerationURL::build(GcGenerationURL::ENGINE, $settings);

    // Query the AI.
    $output = [];
    try {
      $results = $this->get($url, NULL, $headers);
    }
    catch(\Exception $e) {}

    foreach($results["engines"] ?: [] as $engine) {
      $engineName = explode("/", $engine["name"]);
      $engineId = array_pop($engineName);
      $output[$engineId] = $engine['displayName'];
    }

    return $output;

  }

  /**
   * @inheritDoc
   */
  public function availableApps(?string $service_account, ?string $project_id): array {
    return $this->availableDataStores($service_account, $project_id);
  }

  /**
   * @inheritDoc
   */
  public function availablePrompts(): array {
    return GcGenerationPrompt::getPrompts($this->id());
  }

  /**
   * Returns the current session info (if any).
   * @return array
   */
  public function getSessionInfo(): array {
    return [
      "query_id" => $this->response["query_id"] ?: NULL,
      "session_id" => $this->response["session_id"] ?: NULL,
    ];
  }

  /********************************************
   * Helper Functions
   ********************************************/

  /**
   * Make an initial check on the parameters array contents.
   *
   * @param array $parameters
   *
   * @return bool|string|void|null
   * @throws \Exception
   */

  private function validateQueryParameters(array &$parameters) {

    if (empty($parameters["text"])) {
      $this->error = "A search request is required.";
    }
    elseif (empty($this->settings[$this->id()])) {
      $this->error = "The conversation API settings are empty or missing.";
    }

    // ensure these parameters have a default setting.
    $parameters["prompt"] = $parameters["prompt"] ?? "default";
    $parameters["model"] = $this->settings[$this->id()]["model"] ?? "stable";

  }

  /**
   * Override the model settings with values from parameters["overrides"].
   *
   * Copy the svs_settings into parameters array.
   *
   * @param array $parameters
   *
   * @return void After this method, the svs_settings and parameters should be
   *              synchronized.
   */
  private function overrideModelSettings(array &$parameters): void {

    if (!empty($parameters["service_account"])) {
      $this->settings[$this->id()]['service_account'] = $parameters["service_account"];
    }
    else {
      $parameters["service_account"] = $this->settings[$this->id()]['service_account'];
    }

    if (!empty($parameters["project_id"])) {
      $this->settings[$this->id()]['project_id'] = $parameters["project_id"];
    }
    else {
      $parameters["project_id"] = $this->settings[$this->id()]['project_id'];
    }

    if (!empty($parameters["datastore_id"])) {
      $this->settings[$this->id()]['datastore_id'] = $parameters["datastore_id"];
    }
    else {
      $parameters["datastore_id"] = $this->settings[$this->id()]['datastore_id'];
    }

    if (!empty($parameters["engine_id"])) {
      $this->settings[$this->id()]['engine_id'] = $parameters["engine_id"];
    }
    else {
      $parameters["engine_id"] = $this->settings[$this->id()]['engine_id'];
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

    foreach($ratings["categories"] ?? [] as $key => $rating) {
      $this->response["search"]["safetyRatings"][$rating] = $ratings["scores"][$key];
    }

  }

  /**
   * Merge an AnswerResponseObject into a ResponseObject
   *
   * @param $results
   *
   * @return void
   */
  private function mergeResults(array &$searchResponse, array $results): void {
    // Merge these results/response into the original response.
    $searchResponse["object"]->set("summary", [
      "summaryText" => $results["answer"]["answerText"],  // Summary with citations
      "safetyAttributes" => [""],
      "summaryWithMetadata" => [
        "summary" => $results["answer"]["answerText"],      // Summary with no citations
        "citationMetadata" => [
          "citations" => $this->reformatCitations($results["answer"]["citations"] ?? []),
        ],
        "references" => $this->reformatReferences($results["answer"]["references"] ?? []),
      ],
      "extraInfo" => [
        "queryUnderstandingInfo" => $results["answer"]["queryUnderstandingInfo"] ?? NULL,
        "answerName" => $results["answer"]["name"],
        "steps" => $results["answer"]["steps"],
        "state" => $results["answer"]["state"],
        "createTime" => $results["answer"]["createTime"] ?? NULL,
        "completeTime" => $results["answer"]["completeTime"] ?? NULL,
        "answerSkippedReasons" => $results["answer"]["answerSkippedReasons"] ?? NULL,
      ]
    ]);
    $searchResponse["object"]->set("guidedSearchResult", [
      "refinementAttributes" => NULL,
      "followUpQuestions" => $results["answer"]["relatedQuestions"] ?? NULL,
    ]);
    $searchResponse["object"]->set("sessionInfo", array_merge($searchResponse["object"]->get("sessionInfo"), $results["session"]));

    // Manage the response object.
    $searchResponse["elapsedTime"] += $this->response["elapsedTime"];
    $searchResponse["http_code"] = $this->response["http_code"];
    $searchResponse["answer_response_raw"] = $this->response["response_raw"];
    $searchResponse["metadata"] = NULL;

  }

  /**
   * Reformats the citations in AnswerQueryResponse to the SearchResponse format.
   *
   * @param $answerCitations
   *
   * @return array
   */
  private function reformatCitations($answerCitations): array {
    $output = [];
    foreach($answerCitations as $k => $citation) {
      foreach( $citation["sources"] as $key => $source) {
        $sources[$key] = ["referenceIndex" => $source["referenceId"]];
      }
      $output[$k] = [
        "startIndex" => $citation["startIndex"] ?? 0,
        "endIndex" => $citation["endIndex"],
        "sources" => $sources,
      ];
    }
    return $output;
  }

  /**
   * Reformats the citations in AnswerQueryResponse to the SearchResponse format.
   *
   * @param $answerCitations
   *
   * @return array
   */
  private function reformatReferences($answerReferences): array {
    $output = [];
    foreach($answerReferences as $reference) {
      $output[] = [
        "title" => $reference["chunkInfo"]["documentMetadata"]["title"],
        "document" => $reference["chunkInfo"]["documentMetadata"]["document"],
        "uri" => $reference["chunkInfo"]["documentMetadata"]["uri"],
        "chunkContents" => [
          "content" => $reference["chunkInfo"]["content"],
          "pageIdentifier" => NULL,
        ],
        "extraInfo" => [
          "relevanceScore" => $reference["chunkInfo"]["relevanceScore"] ?: NULL,
        ],
      ];
    }
    return $output;
  }

}
