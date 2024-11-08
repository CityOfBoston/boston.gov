<?php

namespace Drupal\bos_google_cloud\Services;

use Drupal;
use Drupal\bos_core\Controllers\Curl\BosCurlControllerBase;
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
  class GcConversation
  Creates a gen-ai conversation service for bos_google_cloud

  david 01 2024
  @file docroot/modules/custom/bos_components/modules/bos_google_cloud/src/Services/GcConversation.php
*/

class GcConversation extends BosCurlControllerBase implements GcServiceInterface {

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

  /**
   * Cache for last engines search.
   *
   * @var array
   */
  public array $engines = [];

  protected array $settings;

  /**
   * @var GcAuthenticator Google Cloud Authenication Service.
   */
  protected GcAuthenticator $authenticator;

  /** @var array Standardized search response. Clone of class AiSearchResponse.*/
  protected array $sc_response = [
    "ai_answer" => '',          // Text only answer from Vertex
    "body" => '',               // Markup response from Vertex - with citations
    "citations" => [],          // Array of citations
    "session_id" => '',    // The unique ID for this conversation
    "metadata" => [],           // Safety and other metadata returned from search
    "references" => [],         // References .. ???
    "search_results" => [],     // List of search result objects
  ];

    public function __construct(LoggerChannelFactory $logger, ConfigFactory $config) {

    // Load the service-supplied variables.
    $this->log = $logger->get('bos_google_cloud');
    $this->config = $config->get("bos_google_cloud.settings");

    $this->settings = CobSettings::getSettings("GCAPI_SETTINGS", "bos_google_cloud");

    // Create an authenticator using service account 1.
    $this->authenticator = new GcAuthenticator($this->settings['conversation']['service_account'] ?? GcAuthenticator::SVS_ACCOUNT_LIST[0]);

    // Do the CuRL initialization in BosCurlControllerBase.
    parent::__construct();

  }

  /**
   * @inheritDoc
   */
  public static function id(): string {
    return "conversation";
  }

  /**
   * Set the service_account, overriding the default.
   *
   * @param string $service_account A valid service account.
   *
   * @return $this
   * @throws \Exception
   */
  public function setServiceAccount(string $service_account):GcConversation {
    if (!$this->authenticator->validateServiceAccount($service_account)) {
      throw new Exception("Service account does not exist.");
    }
    $this->settings['service_account'] = $service_account;
    return $this;
  }

  /**
   *  Adds some text to a conversation, using a pre-defined prompt.
   *
   * @param array $parameters Array containing "text" URLencode text to be
   *   summarized, and "prompt" A search type prompt "session_id" unique id
   *   to continue a previous conversation.
   *
   * @return string
   * /
   * @throws \Exception
   */
  public function execute(array $parameters = []): string {

    if (empty($parameters["text"])) {
      $this->error = "Some text for the conversation is needed.";
      return $this->error();
    }
    elseif (empty($this->settings[$this->id()])) {
      $this->error = "The conversation API settings are empty or missing.";
      return $this->error();
    }

    // check quota.
    if (GcGenerationURL::quota_exceeded(GcGenerationURL::CONVERSATION)) {
      $this->error = "Quota exceeded for Discovery API";
      return $this->error;
    }

    // Specify the prompt to use.
    $parameters["prompt"] = $parameters["prompt"] ?? "default";
    // Specify the LLM to use.
    $parameters["model"] = $this->settings[$this->id()]["model"] ?? "stable";

    // Manage conversations.
    if ($this->settings[$this->id()]["allow_conversation"] ?? FALSE && $parameters["allow_conversation"] ?? FALSE) {

      // Find any previous conversation and save in the parameters object.
      if (empty($parameters["session_id"])) {
        $parameters["conversation"] = [];
      }
      else {
        // try to retrieve the previous conversation.
        $KeyValueService = Drupal::service("keyvalue.expirable");
        $parameters["conversation"] = $KeyValueService
          ->get(self::id())
          ->get($parameters["session_id"]) ?? [];
      }

    }

    // Get token.
    try {
      $headers = [
        "Authorization" => $this->authenticator->getAccessToken($this->settings[$this->id()]['service_account'], "Bearer")
      ];
    }
    catch (Exception $e) {
      $this->error = $e->getMessage() ?? "Error getting access token.";
      return $this->error();
    }

    // If we have overrides for the default projects or datastores, apply the
    // override here.
    if (!empty($parameters["service_account"])) {
      $this->settings[$this->id()]['service_account'] = $parameters["service_account"];
    }
    if (!empty($parameters["project_id"])) {
      $this->settings[$this->id()]['project_id'] = $parameters["project_id"];
    }
    if (!empty($parameters["datastore_id"])) {
      $this->settings[$this->id()]['datastore_id'] = $parameters["datastore_id"];
    }
    if (!empty($parameters["engine_id"])) {
      $this->settings[$this->id()]['engine_id'] = $parameters["engine_id"];
    }

    $url = GcGenerationURL::build(GcGenerationURL::CONVERSATION, $this->settings[$this->id()]);

    if (!$payload = GcGenerationPayload::build(GcGenerationPayload::CONVERSATION, $parameters)) {
      $this->error = "Could not build Payload";
      return $this->error;
    }

    // Query the AI.
    $results = $this->post($url, $payload, $headers);

    if ($this->http_code() == 200 && !$this->error()) {

      if (empty($this->response["body"])) {
        $this->error() || $this->error = "Unexpected response from GcConversation";
        return $this->error();
      }

      // Gather vertex conversation metadata.
      $metadata = $this->loadMetadata($parameters);
      // Process safety information into metadata.
      if (!empty($results["reply"]["summary"]["safetyAttributes"])) {
        $metadata += $this->loadSafetyRatings($results["reply"]["summary"]["safetyAttributes"]);
      }

      // Load up the standardized Search response.
      $this->sc_response = [
        'body' => $results["reply"]["reply"],
        'metadata' => $metadata,
      ];

      // Check for Out-of-scope response.
      if (!empty($this->response["body"]["reply"]["summary"]["summarySkippedReasons"])) {
        $this->sc_response['violations'] = implode(', ', $this->response["body"]["reply"]["summary"]["summarySkippedReasons"]);
        $this->response["body"] = $results["reply"]["summary"]["summaryText"];
      }

      // Include any citations.
      else if ($parameters["include_citations"] ?? FALSE) {
        // Load the citations
        $this->sc_response['citations'] = $this->loadCitations(
          $this->response["body"]["reply"]["summary"]["summaryWithMetadata"]["citationMetadata"]["citations"] ?? [],
          $this->response["body"]["reply"]["summary"]["summaryWithMetadata"]["references"] ?? [],
          $this->sc_response["body"]
        );
      }

      else {
        // Use the summary text with citations.
        if (!empty($results["reply"]["summary"]["summaryWithMetadata"]["summary"])) {
          $this->sc_response['body'] = $results["reply"]["summary"]["summaryWithMetadata"]["summary"];
        }
      }

      // Add in the Search Results
      $this->sc_response['search_results'] = $this->loadSearchResults($this->response["body"]["searchResults"] ?? []);

      // Manage the conversation.
      if ($this->settings[$this->id()]["allow_conversation"] ?? FALSE) {
        // Save the conversation as keyvalue with the session_id as key.
        $this->sc_response['session_id'] = $results["conversation"]["userPseudoId"];
        Drupal::service("keyvalue.expirable")
          ->get(self::id())
          ->setWithExpire($this->sc_response['session_id'], $results["conversation"], 300);
      }

      return $this->sc_response['body'];

    }

    elseif ($this->http_code() == 401) {
      // The token is invalid, because we are caching for the lifetime of the
      // token, this probably means it has been refreshed elsewhere.
      $this->authenticator->invalidateAuthToken($this->settings[$this->id()]["service_account"]);
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
   * Takes the output and turns it into an HTML block.
   * ToDo: Change this into a twig templated object.
   *
   * @return string
   */
  public function render(): string {
    $refs = [];
    foreach($this->sc_response["references"] as $key => $reference) {
      $ref_id = $key + 1;
      $refs[$key] = "<a href='{$reference["uri"]}' id='conv-cite-ref-$ref_id-link' data-vertex-doc='{$reference["document"]}'>{$reference["title"]}</a>";
    }

    $cites = [];
    foreach($this->sc_response["citations"] as $citation) {
      foreach ($citation["sources"] as $key => $source) {
        $ref_id = $source["referenceIndex"] ?? $key;
        $cites[$ref_id] = $refs[$ref_id];
      }
    }

    $citations = "";
    foreach ($cites as $cite_id => $link) {
      $citations .= "    <div id='conv-cite-ref-$cite_id'>\n";
      $citations .= "      <div class='cite'>[" . ($cite_id + 1) . "] </div>\n";
      $citations .= "      <div class='cite-desc'>" . $link . "</div>\n";
      $citations .= "    </div>\n";
    }

    $results = "<div id='conv-wrapper'>\n";
    $results .= "  <div id='conv-reply'>{$this->response["ai_answer"]}</div>\n";
    $results .= "  <div id='conv-cite-wrapper'>\n";
    $results .= "    <div>CITATIONS</div>\n";
    $results .= $citations;
    $results .= "  </div>\n";
    $results .= "  <div id='conv-results-wrapper'>\n";
    $results .= "    <div id='conv-results-title'>RESULTS</div>\n";
    foreach($this->sc_response["search_results"] as $key => $result) {
      $res_id = $key + 1;
      $ans = '';
      $snip = "";
      foreach ($result["document"]["derivedStructData"]["extractive_answers"] as $answer) {
        if (!empty($answer["content"])) {
          $ans = $answer["content"];
          break;
        }
      }
      foreach ($result["document"]["derivedStructData"]["snippets"] as $snippet) {
        if ($snippet["snippet_status"] == "SUCCESS") {
          $snip = $snippet["snippet"];
          break;
        }
      }
      $results .= "    <div id='conv-result-$res_id' data-vertex-document='{$result["id"]}'>\n";
      $doc = $result["document"]["derivedStructData"];
      $results .= "      <div class='result-link'><a href='{$doc['link']}'>{$doc['htmlTitle']}</a></div>\n";
      if (!empty($ans)) {
        $results .= "      <div class='result-content'>$ans</div>\n";
      }
      if (!empty($snip)) {
        $results .= "      <div class='result-snippet'>$snip</div>\n";
      }
      $results .= "    </div>\n";
    }

    $results .= "  </div>\n";
    $results .= "</div>\n";

    return $results;

  }

  /**
   * Return the processed results in a standardized array.
   * @return array
   */
  public function getResults(): array {
    return $this->sc_response;
  }

  /**
   * Establish the safety scores and retuurn.
   * Only save safety scores in $ratings are higher (less safe) than those
   * already stored.
   *
   * @param array $ratings The safetyRatings from vertex.
   *
   * @return array
   */
  private function loadSafetyRatings(array $ratings): array {

    $output = [];

    foreach(($ratings["categories"] ?? []) as $key => $rating) {
      $output[$rating] = $ratings["scores"][$key];
    }

    return $output;

  }

  /**
   * Load Search Results into a simple, standardized search output format.
   * Also de-duplicates the results based on the ultimate node which is
   * referenced in the result link.
   *
   * The array returned is a clone of the array in aiSearchResult (bos_search),
   * but we have copied so as not to create a dependedncy between these modules
   * at this point.
   *
   * @param array $results Output from AI Model
   *
   * @return array Standardized & simplified array of search results.
   */
  private function loadSearchResults(array $results): array {
    $output = [];

    if (empty($results)) {
      return [];
    }

    $alias_manager = \Drupal::service('path_alias.manager');
    $redirect_manager = \Drupal::service('redirect.repository');

    $citations = $this->sc_response["citations"] ?: [];

    foreach($results as $result) {

      // Check if this result is already showing in the citations.h
      $is_citation = FALSE;
      if (!empty($citations)) {
        foreach ($citations as $key => $citation) {
          if ($citation["id"] == $result["id"]) {
            // Mark results as being in the citations set
            $is_citation = TRUE;
            // Mark citation as being in results set.
            $this->sc_response["citations"][$key]["is_result"] = TRUE;
            break;
          }
        }
      }

      /** Standardizes search result - output array is a clone of class aiSearchResult. */

      $path_alias = explode(".gov",$result["document"]["derivedStructData"]["link"],2)[1];
      if (!empty($path_alias)) {

        // Strip out the alias from any other querystings etc
        $path_alias = explode('?', $path_alias, 2);
        $path_alias = explode('#', $path_alias[0], 2)[0];

        // get the nid for this page alias (to prevent duplicates)
        $path = $alias_manager->getPathByAlias($path_alias);
        $path_parts = explode('/', $path);
        $nid = array_pop($path_parts);

        if (!is_numeric($nid)) {
          // If we can't get the node ID then it is possibly a redirect to
          // another page, so try to track that down...

          $redirects = $redirect_manager->findBySourcePath(trim($path_alias, "/"));
          if (!empty($redirects)) {
            $redirect = reset($redirects);
            $original_alias = explode(":", $redirect->getRedirect()['uri'], 2)[1] ?? $redirect->getRedirect()['uri'];
            $path = $alias_manager->getPathByAlias($original_alias);
            $path_parts = explode('/', $path);
            $nid = array_pop($path_parts);
          }
        }

        if (!is_numeric($nid)) {
          // Well ... interesting.
          // Set the nid equal to the original node path so at least we
          // de-duplicate.
          $nid = $path;
        }

      }

      $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
      $description = "";
      if ($node && $node->hasField("field_intro_text")) {
        $description = $node->get("field_intro_text")->value;
      }
      if ($node && $node->hasField("body")) {
        $description .= $node->get("body")->summary ?: $node->get("body")->value;
      }
      if (empty($description) && $node && $node->hasField("field_need_to_know")) {
        $description = $node->get("field_need_to_know")->value;
      }

      $title = explode("|", $result['document']['derivedStructData']['title'], 2)[0];
      $output[$result['id']] = [
        "content" => $result['document']['derivedStructData']['extractive_answers'][0]['content'],
        "description" => trim(strip_tags($description)),
        "id" => $result['id'],
        "is_citation" => $is_citation,
        "link" => $result['document']['derivedStructData']['link'],
        "link_title" => $result['document']['derivedStructData']['displayLink'],
        "ref" => $result['document']['name'],
        "snippet" => $result['document']['derivedStructData']['snippets'][0]['snippet'] ?: "",
        "title" => trim($title),
      ];


    }
    return array_values($output);
  }

  /**
   * Load Vertex available metadata into array and return.
   *
   * @param array $metadata
   *
   * @return array
   */
  private function loadMetadata(array $metadata) {
    $map = [
      "session_id" => "Drupal Internal",
    ];
    $exclude_meta = [
      "conversation",
      "session_id"
    ];
    foreach($metadata as $key => $value) {
      $node = $map[$key] ?? "Request";
      if (!in_array($key, $exclude_meta)) {
        $output[$node][ucwords(str_replace("_", " ", $key))] = [
          "key" => $key,
          "value" => $value,
        ];
      }
    }
    $output[$node]["Full Prompt"] = [
      "key" => "Full Prompt",
      "value" => $this->request["body"]["summarySpec"]["modelPromptSpec"]["preamble"],
    ];
    foreach($this->settings[$this->id()] as $key => $value) {
      $node = $map[$key] ?? "Model Config";
      $output[$node][ucwords(str_replace("_", " ", $key))] = [
        "key" => $key,
        "value" => $value
      ];
    }
    $output["Model State"]["Current Conversation Length"] = ["key" => "conversation_length", "value" => count($this->response["body"]["conversation"]["messages"]) / 2];
    $output["Model Response"]["Endpoint"] = ["key" => "conversation_endpoint", "value" => $this->request["protocol"] . "//" . $this->request["host"] . '/' . $this->request["endpoint"]];
    $output["Model Response"]["Conversation"] = ["key" => "conversation_name", "value" => $this->response["body"]["conversation"]["name"]];
    $output["Model Response"]["State"] = ["key" => "conversation_state", "value" => $this->response["body"]["conversation"]["state"]];
    $output["Model Response"]["PseudoId"] = ["key" => "conversation_ref", "value" => $this->response["body"]["conversation"]["userPseudoId"]];
    $output["Model Response"]["Drupal Internal Id"] = ["key" => "session_id", "value" => $metadata["session_id"] ?? ""];
    $output["Model Response"]["Query Duration"] = ["key" => "conversation_query_duration", "value" => $this->response["elapsedTime"]];
    $output["Model Response"]["Search Results Returned"] = ["key" => "results_length", "value" => count($this->response["body"]["searchResults"] ?? [])];
    $output["Model Response"]["Citations Returned"] = ["key" => "citations_length", "value" => count($this->response["body"]["reply"]["summary"]["summaryWithMetadata"]["citationMetadata"]["citations"] ?? [])];
    return $output;
  }

  /**
   * Creates a unified citation array from a list of citations and references.
   *
   * @param array $citations Citations from Vertex
   * @param array $references References from Vertex
   *
   * @return array a unified array of citations with their references.
   */
  private function loadCitations(array $citations, array $references, string &$body): array {
    $output = [];

    foreach ($references as $key => $reference) {
      $output[$key] = $reference;
      $output[$key]["title"] = trim(explode("|", $output[$key]["title"], 2)[0]);
      $output[$key]["ref"] = $output[$key]["document"];
      $ref = explode("/", $output[$key]["document"]);
      $output[$key]["id"] = array_pop($ref);
      $output[$key]["locations"] = [];
      //      $output[$key]["original_key"] = $key;

      foreach ($citations as $citation) {
        foreach ($citation["sources"] as $source) {
          if (($source["referenceIndex"] ?? 0) == $key) {
            $output[$key]["locations"][] = [
              "startIndex" => $citation["startIndex"] ?? 0,
              "endIndex" => $citation["endIndex"] ?? strlen($body),
            ];
          }
        }
      }

      unset($output[$key]["document"]);
    }

    // reindex the output array, keep the original key to match the citation #'s
    // and replace text on the page
    $out = [];
    $new_key = 1;
    foreach ($output as $key => $value) {
      if (!empty($value["locations"])) {
        $value["original_key"] = $key + 1;
        $body = preg_replace("~\[" . $value["original_key"] . "\]~", "[" . $new_key . "]", $body);
        $body = preg_replace("~, " . $value["original_key"] . "~", "[" . $new_key . "]", $body);
        $body = preg_replace("~" . $value["original_key"] . " ,~", "[" . $new_key . "]", $body);
        $out[$new_key++] = $value;
      }
    }

    // Make the index numbers sequential, starting at 1

    // Todo: add links into the body ?
    return $out;
  }

  /**
   * @inheritDoc
   */
  public function buildForm(array &$form): void {

    $project_id="612042612588";
    $model_id="drupalwebsite_1702919119768";
    $engine_id="oeoi-search-pilot_1726266124376";
    $location_id="global";
    $endpoint="https://discoveryengine.googleapis.com";
    $model="stable";

    $settings = $this->settings['conversation'] ?? [];

    $svs_accounts = [];
    foreach ($this->settings["auth"]??[] as $name => $value) {
      if ($name) {
        $svs_accounts[$name] = $name;
      }
    }

    $form = $form + [
      'conversation' => [
        '#type' => 'details',
        '#title' => 'Gen-AI Conversation',
        "#description" => "Service which starts or continues a conversation with an AI based on a cusomized data store.",
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
          '#default_value' => $settings['allow_conversation'] ?? 1,
          '#required' => FALSE,
        ],
        'test_wrapper' => [
            'test_button' => [
              '#type' => 'button',
              "#value" => t('Test Conversation'),
              '#attributes' => [
                'class' => ['button', 'button--primary'],
                'title' => "Test the provided configuration for this service"
              ],
              '#access' => TRUE,
              '#ajax' => [
                'callback' => '::ajaxHandler',
                'event' => 'click',
                'wrapper' => 'edit-convo-result',
                'disable-refocus' => TRUE,
                'progress' => [
                  'type' => 'throbber',
                ]
              ],
              '#suffix' => '<span id="edit-convo-result"></span>',
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

    if ($config->get("conversation.project_id") !== $values['project_id']
      ||$config->get("conversation.datastore_id") !== $values['datastore_id']
      ||$config->get("conversation.engine_id") !== $values['engine_id']
      ||$config->get("conversation.location_id") !== $values['location_id']
      ||$config->get("conversation.service_account") !== $values['service_account']
      ||$config->get("conversation.allow_conversation") !== $values['allow_conversation']
      ||$config->get("conversation.model") !== $values['model']
      ||$config->get("conversation.endpoint") !== $values['endpoint']) {
      $config->set("conversation.project_id", $values['project_id'])
        ->set("conversation.datastore_id", $values['datastore_id'])
        ->set("conversation.engine_id", $values['engine_id'])
        ->set("conversation.location_id", $values['location_id'])
        ->set("conversation.allow_conversation", $values['allow_conversation'])
        ->set("conversation.endpoint", $values['endpoint'])
        ->set("conversation.model", $values['model'])
        ->set("conversation.service_account", $values['service_account'])
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
   * Ajax callback to test Conversation Service.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  public static function ajaxTestService(array &$form, FormStateInterface $form_state): array {

    $values = $form_state->getValues()["google_cloud"]['services_wrapper']['discovery_engine']['conversation'];
    $conversation = Drupal::service("bos_google_cloud.GcConversation");

    $options = [
      "text" => "How do I pay a parking ticket",
      "prompt" => "default",
    ];

    unset($values["test_wrapper"]);
    $conversation->settings = CobSettings::array_merge_deep($conversation->settings, ["conversation" => $values]);
    $result = $conversation->execute($options);

    if (!empty($result)) {
      return ["#markup" => Markup::create("<span id='edit-convo-result' style='color:green'><b>&#x2714; Success:</b> Authentication and Service Config are OK.</span>")];
    }
    else {
      return ["#markup" => Markup::create("<span id='edit-convo-result' style='color:red'><b>&#x2717; Failed:</b> {$conversation->error()}</span>")];
    }

  }

  /**
   * @inheritDoc
   */
  public function hasFollowup(): bool {
    return $this->config->get("conversation.allow_conversation");
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
  public function availablePrompts(): array {
    return GcGenerationPrompt::getPrompts($this->id());
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
    $this->engines = [];
    try {
      $results = $this->get($url, NULL, $headers);
    }
    catch(\Exception $e) {
      // Do nothing.
    }

    foreach ($results["engines"] ?: [] as $engine) {
      $engineName = explode("/", $engine["name"]);
      $engineId = array_pop($engineName);
      $this->engines[$engineId] = [
        "name" => $engine['displayName'],
        "multi-ds" => (count($engine["dataStoreIds"]) > 1) ? "true" : "false",
        "datastores" => $engine['dataStoreIds'],
      ];
    }

    return $this->engines;

  }

  /**
   * {@inheritDoc}
   */
  public function availableProjects(?string $service_account): array {

    if (!empty($service_account) && $service_account != "default") {
      $settings['service_account'] = $service_account;
    }

    // TODO: For this to work the service account needs resourcemanager.projects.list
    //  permission on the organization. Right now, this has not been granted.
    return [
      "738313172788" => "ai-search-boston-gov-91793",
      "612042612588" => "vertex-ai-poc-406419",
    ];

    // Get token.
    try {
      $headers = [
        "Authorization" => $this->authenticator->getAccessToken($this->settings[$this->id()]['service_account'], "Bearer"),
        "Accept" => "application/json",
      ];
    }
    catch (Exception $e) {
      $this->error = $e->getMessage() ?? "Error getting access token.";
      return [];
    }

    $url = GcGenerationURL::build(GcGenerationURL::PROJECT, $this->settings[$this->id()]);

    // Query the AI.
    $output = [];
    $post_fields = NULL;
    $post_fields = "parent=" . urlencode("organizations/593266943271");
//    $post_fields = [
//      "scope" => urlencode("organizations/593266943271"),
//      "assetTypes" => ["cloudresourcemanager.googleapis.com/Project"]
//    ];
    $results = $this->get($url, $post_fields, $headers);
    foreach($results["dataStores"] ?: [] as $dataStore) {
      $dataStoreName = explode("/", $results["dataStores"][0]["name"]);
      $dataStoreId = array_pop($dataStoreName);
      //      $output[$dataStoreId] = $dataStore['displayName'];
      $output[$dataStoreId] = $dataStoreId;
    }
    return $output;

  }

  /**
   * {@inheritDoc}
   */
  public function fqDataStorename(string $dsname, string $projectid):string {
    return "projects/$projectid/locations/global/collections/default_collection/dataStores/$dsname";
  }

}
