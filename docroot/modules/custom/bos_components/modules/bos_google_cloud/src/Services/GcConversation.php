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
   *   summarized, and "prompt" A search type prompt.
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

    $parameters["prompt"] = $parameters["prompt"] ?? "default";

    $settings = $this->settings["conversation"] ?? [];

    // check quota.
    if (GcGenerationURL::quota_exceeded(GcGenerationURL::CONVERSATION)) {
      $this->error = "Quota exceeded for this API";
      return $this->error;
    }

    // Manage conversations.
    if ($settings["allow_conversation"] ?? FALSE || $parameters["allow_conversation"] ?? FALSE) {

      // Find any previous conversation and save in the parameters object.
      if (empty($parameters["conversation_id"])) {
        $parameters["conversation"] = [];
      }
      else {
        // try to retrieve the previous conversation.
        $parameters["conversation"] = Drupal::service("keyvalue.expirable")
          ->get(self::id())
          ->get($parameters["conversation_id"]) ?? [];
      }

    }

    // Get token.
    try {
      $headers = [
        "Authorization" => $this->authenticator->getAccessToken($settings['service_account'], "Bearer")
      ];
    }
    catch (Exception $e) {
      $this->error = $e->getMessage() ?? "Error getting access token.";
      return $this->error();
    }

    $url = GcGenerationURL::build(GcGenerationURL::CONVERSATION, $settings);

    if (!$payload = GcGenerationPayload::build(GcGenerationPayload::CONVERSATION, $parameters)) {
      $this->error = "Could not build Payload";
      return $this->error;
    }

    $results = $this->post($url, $payload, $headers);

    if ($this->http_code() == 200 && !$this->error()) {

      if (empty($this->response["body"])) {
        $this->error() || $this->error = "Unexpected response from GcConversation";
        return $this->error();
      }

      $this->response["ai_answer"] = $results["reply"]["reply"];
      $this->loadSafetyRatings($results["reply"]["summary"]["safetyAttributes"]);

      if ($settings["allow_conversation"] ?? TRUE) {
        // Save the conversation as keyvalue with the conversation_id as key.
        $this->response["conversation_id"] = $results["conversation"]["userPseudoId"];
        Drupal::service("keyvalue.expirable")
          ->get(self::id())
          ->setWithExpire($this->response["conversation_id"], $results["conversation"], 300);
      }

      return $this->formattedResponse();

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

  private function formattedResponse(): string {
    $data = $this->response["body"]["reply"]["summary"]["summaryWithMetadata"];

    $refs = [];
    foreach($data["references"] as $key => $reference) {
      $ref_id = $key + 1;
      $refs[$key] = "<a href='{$reference["uri"]}' id='conv-cite-ref-$ref_id-link' data-vertex-doc='{$reference["document"]}'>{$reference["title"]}</a>";
    }

    $cites = [];
    foreach($data["citationMetadata"]["citations"] as $citation) {
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

    $data = $this->response["body"];
    $results = "<div id='conv-wrapper'>\n";
    $results .= "  <div id='conv-reply'>{$this->response["ai_answer"]}</div>\n";
    $results .= "  <div id='conv-cite-wrapper'>\n";
    $results .= "    <div>CITATIONS</div>\n";
    $results .= $citations;
    $results .= "  </div>\n";
    $results .= "  <div id='conv-results-wrapper'>\n";
    $results .= "    <div id='conv-results-title'>RESULTS</div>\n";
    foreach($data["searchResults"] as $key => $result) {
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
   * Update the $this->response["conversation"]["ratings"] array if the safety scores
   * in $ratings are higher (less safe) than those already stored.
   *
   * @param array $ratings The safetyRatings from a gemini ::predict call.
   *
   * @return void
   */
  private function loadSafetyRatings(array $ratings): void {

    if (!isset($this->response["body"]["safetyRatings"])) {
      $this->response["body"]["safetyRatings"] = [];
    }

    foreach($ratings["categories"] as $key => $rating) {
      $this->response["body"]["safetyRatings"][$rating] = $ratings["scores"][$key];
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
      ||$config->get("conversation.location_id") !== $values['location_id']
      ||$config->get("conversation.service_account") !== $values['service_account']
      ||$config->get("conversation.allow_conversation") !== $values['allow_conversation']
      ||$config->get("conversation.endpoint") !== $values['endpoint']) {
      $config->set("conversation.project_id", $values['project_id'])
        ->set("conversation.datastore_id", $values['datastore_id'])
        ->set("conversation.location_id", $values['location_id'])
        ->set("conversation.allow_conversation", $values['allow_conversation'])
        ->set("conversation.endpoint", $values['endpoint'])
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

}
