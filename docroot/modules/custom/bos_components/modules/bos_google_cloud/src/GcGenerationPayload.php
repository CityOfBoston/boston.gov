<?php

namespace Drupal\bos_google_cloud;

use Drupal;

use Drupal\bos_google_cloud\Apis\v1alpha\answerGenerationSpec\AnswerGenerationSpec;
use Drupal\bos_google_cloud\Apis\v1alpha\answerGenerationSpec\PromptSpec;
use Drupal\bos_google_cloud\Apis\v1alpha\contentSearchSpec\ContentSearchSpec;
use Drupal\bos_google_cloud\Apis\v1alpha\contentSearchSpec\ExtractiveContentSpec;
use Drupal\bos_google_cloud\Apis\v1alpha\contentSearchSpec\SnippetSpec;
use Drupal\bos_google_cloud\Apis\v1alpha\projects\locations\collections\dataStores\conversations\Converse;
use Drupal\bos_google_cloud\Apis\v1alpha\projects\locations\collections\dataStores\conversations\TextInput;
use Drupal\bos_google_cloud\Apis\v1alpha\projects\locations\collections\engines\servingConfigs\Answer;
use Drupal\bos_google_cloud\Apis\v1alpha\projects\locations\collections\engines\servingConfigs\Search;
use Drupal\bos_google_cloud\Apis\v1alpha\contentSearchSpec\SummarySpec;
use Drupal\bos_google_cloud\Apis\v1alpha\contentSearchSpec\ModelPromptSpec;
use Drupal\bos_google_cloud\Apis\v1alpha\contentSearchSpec\ModelSpec;
use Drupal\bos_google_cloud\Apis\v1alpha\contentSearchSpec\ChunkSpec;
use Drupal\bos_google_cloud\Apis\v1alpha\projects\locations\evaluations\DataStoreSpec;
use Drupal\bos_google_cloud\Apis\v1alpha\projects\locations\evaluations\QueryExpansionSpec;
use Drupal\bos_google_cloud\Apis\v1alpha\relatedQuestionsSpec\RelatedQuestionsSpec;
use Drupal\bos_google_cloud\Apis\v1alpha\safetySpec\SafetySpec;

class GcGenerationPayload {

  public const CONVERSATION = 0;
  public const SEARCH = 1;
  public const PREDICTION = 2;
  public const SEARCH_ANSWER = 16;

  /**
   * HARDCODED safety settings for bos_google_cloud gen-ai prediction services.
   */
  public const SAFETY_SETTINGS = [
    [
      "category" => "HARM_CATEGORY_HATE_SPEECH",
      "threshold" => "BLOCK_LOW_AND_ABOVE",
    ],
    [
      "category" => "HARM_CATEGORY_HARASSMENT",
      "threshold" => "BLOCK_LOW_AND_ABOVE",
    ],
    [
      "category" => "HARM_CATEGORY_DANGEROUS_CONTENT",
      "threshold" => "BLOCK_LOW_AND_ABOVE",
    ],
    [
      "category" => "HARM_CATEGORY_SEXUALLY_EXPLICIT",
      "threshold" => "BLOCK_LOW_AND_ABOVE",
    ],
  ];

  /**
   * Creates standardized payloads for the type defined.
   *
   * @param int $type a constant from this class
   * @param array $options
   *
   * @return array|bool
   * @throws \Exception
   */
  public static function build(int $type, array $options):array|bool {

    switch ($type) {

      case self::CONVERSATION:
        if (empty($options["text"]) || empty($options["prompt"])) {
          Drupal::logger("bos_google_cloud")
            ->error("Require Text and Prompt in payload (prompt:{$options["prompt"]},text:{$options["text"]}");
          return FALSE;
        }
        return self::buildConversation($options);

      case self::SEARCH_ANSWER:
      case self::SEARCH:
        if (empty($options["text"]) || empty($options["prompt"])) {
          Drupal::logger("bos_google_cloud")
            ->error("Require Text and Prompt in payload (prompt:{$options["prompt"]},text:{$options["text"]}");
          return FALSE;
        }
        $options["type"] = $type;
        return self::buildSearch($options);

      case self::PREDICTION:
        if (empty($options["prediction"]) || empty($options["generation_config"])) {
          Drupal::logger("bos_google_cloud")
            ->error("Require Prediction and Generation Config in payload.", ['referer' => __METHOD__]);
          return FALSE;
        }
        return self::buildPrediction($options["prediction"], $options["generation_config"]);

      default:
        return FALSE;
    }

  }

  /**
   * Produces the standardized payload for the conversations:converse endpoint.
   *
   * @param array $options An array of options for the conversation API
   *    string prompt - The prompt to use to guide the AI responses.
   *    string text - The conversation text to be processed by the AI.
   *    array conversation - An ongoing conversation to be passed to the AI.
   *    int num_results - Number of search results desired.
   *    bool include_citations - If citations should be included in the response.
   *    bool safe_search - If the API should conduct a safe search
   *    bool semantic_chunks - Improve results using semantic chunking.
   *    bool ignoreAdversarialQuery -
   *    bool ignoreNonSummarySeekingQuery -
   *    bool ignoreLowRelevantContent -
   *    bool ignoreJailBreakingQuery -
   *
   * @return array|bool
   *
   * @throws \Exception
   * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/projects.locations.collections.dataStores.conversations
   * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1/projects.locations.collections.dataStores.conversations/converse
   *
   * @see https://cloud.google.com/generative-ai-app-builder/docs/apis
   */
  private static function buildConversation(array $options): array|bool {

    // v1alpha version
    $payload = new Converse();
    $payload->set("query", new TextInput([
      "input" => $options["text"]
    ]));
    $payload->set("safeSearch", $options["safe_search"] ?? TRUE);
    $payload->set("summarySpec", new SummarySpec([
      "summaryResultCount" => $options["num_results"] ?? 5,
      "includeCitations" => $options["include_citations"] ?? FALSE,
      "ignoreAdversarialQuery" => $options["ignoreAdversarialQuery"] ?? TRUE,
      "ignoreNonSummarySeekingQuery" => $options["ignoreNonSummarySeekingQuery"] ?? TRUE,
      "ignoreLowRelevantContent" => $options["ignoreLowRelevantContent"] ?? TRUE,
      "ignoreJailBreakingQuery" => $options["ignoreJailBreakingQuery"] ?? TRUE,
      "languageCode" => NULL,
      "modelPromptSpec" => new ModelPromptSpec([
        "preamble" => GcGenerationPrompt::getPromptText("search", $options["prompt"]) . " " . $options["extra_prompt"]
      ]),
      "modelSpec" => new ModelSpec([
        "version" => $options["model"] ?? "stable",
      ]),
      "useSemanticChunks" => $options["semantic_chunks"] ?? FALSE,
    ]));

    if (!empty($options["conversation"])) {
      // Pick up the conversation.
      $payload->set("conversation", self::sanitizeConversation($options["conversation"]));
    }

    return $payload->toArray();

  }

  /**
   * Produces the standardized payload for the servingConfigs/search endpoint.
   *
   * @param array $options An array of options for the conversation API
   *    string prompt The prompt to use to guide the AI responses.
   *    string text The query to be processed by the AI.
   *    array conversation An ongoing conversation to be passed to the AI.
   *    int num_results Number of search results desired.
   *    bool include_citations If citations should be included in the response.
   *    bool safe_search If the API should conduct a safe search
   *    bool semantic_chunks Improve results using semantic chunking.
   *    bool ignoreAdversarialQuery -
   *    bool ignoreNonSummarySeekingQuery -
   *    bool ignoreLowRelevantContent -
   *    bool ignoreJailBreakingQuery -
   *
   * @return array|bool
   *
   * @throws \Exception
   * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/projects.locations.collections.engines.servingConfigs
   * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/projects.locations.collections.engines.servingConfigs/search
   *
   * @see https://cloud.google.com/generative-ai-app-builder/docs/apis
   */
  private static function buildSearch(array $options): array|bool {
    // This uses DiscoveryEngine API v1alpha format.
    switch ($options["type"]) {
      case self::SEARCH:
        $payload = new Search();
        $payload->set("query", $options["text"]);
        $payload->set("pageSize", ($options["num_results"] * 2) ?? 5);
        if (!$options["engine_mds"]) {
          // QueryExpansionSpec not supported in multi-datastore engines/apps.
          $queryExpansionSpec = new QueryExpansionSpec([
            "condition" => "AUTO",
            "pinUnexpandedResults" => TRUE,
          ]);
          $payload->set("queryExpansionSpec", $queryExpansionSpec);
        }
        $content_spec = [
          "snippetSpec" => new SnippetSpec([
            "returnSnippet" => TRUE,
          ]),
          "extractiveContentSpec" => new ExtractiveContentSpec([
            "maxExtractiveAnswerCount" => $options["num_results"],
            "maxExtractiveSegmentCount" => 1,
            "returnExtractiveSegmentScore" => FALSE,
            "numPreviousSegments" => 0,
            "numNextSegments" => 0,
          ]),
          "searchResultMode" => "DOCUMENTS",
          "chunkSpec" => new ChunkSpec([
            "numPreviousChunks" => 0,
            "numNextChunks" => 0,
          ]),
        ];
        if ($options["allow_conversation"] && !empty($options["session_id"])) {
          $payload->setSession($options["project_id"], $options["engine_id"], $options["session_id"]);
        }
        else {
          $payload->setSession($options["project_id"], $options["engine_id"], "-");
        }
        $payload->set("contentSearchSpec", new ContentSearchSpec($content_spec));
        if (!empty($options["datastore_id"]) && $options["datastore_id"] != "default") {
          $datastores = [];
          if (is_array($options["datastore_id"])) {
            foreach ($options["datastore_id"] as $ds) {
              $datastores[] = new DataStoreSpec(['dataStore' => $ds]);
            }
          }
          else {
            $datastores[] = new DataStoreSpec(['dataStore' => $options["datastore_id"]]);
          }
          $payload->set("dataStoreSpecs", $datastores);
        }
        $payload->set("safeSearch", $options["safe_search"] ?? TRUE);
        $payload->set("relevanceThreshold", "RELEVANCE_THRESHOLD_UNSPECIFIED");
        return $payload->toArray();

      case self::SEARCH_ANSWER:
        $payload = new Answer();
        $payload->setQuery($options["text"], $options["query_id"], $options["project_id"]);
        $payload->setSession($options["project_id"], $options["engine_id"], $options["session_id"]);
        $payload->set("relatedQuestionsSpec", new RelatedQuestionsSpec(["enable" => $options["related_questions"] ?? FALSE]));
        $payload->set("answerGenerationSpec", new AnswerGenerationSpec([
          "modelSpec" => new ModelSpec(["modelVersion" => $options["model"] ?? "stable"]),
          "promptSpec" => new PromptSpec(["preamble" => GcGenerationPrompt::getPromptText("search", $options["prompt"]) . " " . $options["extra_prompt"]]),
          "answerLanguageCode" => NULL,
          "includeCitations" => $options["include_citations"] ?? FALSE,
          "ignoreAdversarialQuery" => $options["ignoreAdversarialQuery"] ?? TRUE,
          "ignoreNonAnswerSeekingQuery" => $options["ignoreNonSummarySeekingQuery"] ?? TRUE,
          "ignoreLowRelevantContent" => $options["ignoreLowRelevantContent"] ?? TRUE,
          "ignoreJailBreakingQuery" => $options["ignoreJailBreakingQuery"] ?? TRUE,
        ]));
        $payload->set("safeSearch", new SafetySpec([
          "enable" => $options["safe_search"] ?? TRUE,
        ]));
        return $payload->toArray();

      default:
        return FALSE;
    }

  }

  /**
   * Checks that the conversation array is propely formatted.
   *
   * @param array $conversation
   *
   * @return array
   */
  private static function sanitizeConversation(array $conversation): array {
    foreach($conversation["messages"] as &$message) {
      if (array_key_exists("reply", $message)) {
        if (empty($message["reply"]["summary"]["summaryWithMetadata"]["citationMetadata"])) {
          $message["reply"]["summary"]["summaryWithMetadata"]["citationMetadata"] = NULL;
        }
      }
    }
    return $conversation;
  }

  /**
   * Produces the standardized payload for the conversations:converse endpoint
   *
   * @param array $prompts Array of prompts/questions/text to make prediction on.
   * @param array $generation_config Configuration for prediction engine (LLM).
   *
   * @return array|bool The properly formatted payload.
   *
   * @see https://cloud.google.com/vertex-ai/docs/generative-ai/model-reference/gemini
   */
  private static function buildPrediction(array $prompts, array $generation_config): array|bool {

    $payload = [
      "contents" => [
        [
          "role" => "USER",
          "parts" => []
        ]
      ],
      "safetySettings" => self::SAFETY_SETTINGS,
      "generationConfig" => $generation_config,
    ];
    foreach($prompts as $prompt) {
      $payload["contents"][0]["parts"][] = ["text" => $prompt];
    }
    return $payload;

  }

}
