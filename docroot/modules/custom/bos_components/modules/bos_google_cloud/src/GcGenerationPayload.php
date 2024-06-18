<?php

namespace Drupal\bos_google_cloud;

use Drupal;

class GcGenerationPayload {

  public const CONVERSATION = 0;
  public const PREDICTION = 1;

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
        return self::buildConversation($options["prompt"], $options["text"], $options["conversation"] ?? [], $options["num_results"] ?? 5, $options["include_citations"] ?? TRUE);

      case self::PREDICTION:
        if (empty($options["prediction"]) || empty($options["generation_config"])) {
          Drupal::logger("bos_google_cloud")
            ->error("Require Prediction and Generation Config in payload.",['referer' => __METHOD__]);
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
   * @param string $prompt The prompt to use to guide the AI responses.
   * @param string $text The conversation text to be processed by the AI.
   * @param array $conversation An ongoing conversation to be passed to the AI.
   * @param int $num_results Number of search results desired.
   * @param bool $include_citations If citations should be included in the response.
   *
   * @return array|bool
   *
   * @throws \Exception
   * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1alpha/projects.locations.collections.dataStores.conversations
   * @see https://cloud.google.com/generative-ai-app-builder/docs/reference/rest/v1/projects.locations.collections.dataStores.conversations/converse
   *
   * @see https://cloud.google.com/generative-ai-app-builder/docs/apis
   */
  private static function buildConversation(string $prompt, string $text, array $conversation, int $num_results, bool $include_citations): array|bool {

    $payload = [
      "query" => [
        "input" => $text,
        // "context" => "",
      ],
      // "servingConfig" => "",
      "safeSearch" => FALSE,
      // "conversation" => $conversation,
      "summarySpec" => [
        "summaryResultCount" => $num_results,
        "modelSpec" => ["version" => "stable"],
        "modelPromptSpec" => [
          "preamble" => GcGenerationPrompt::getPromptText("search", $prompt)
        ],
        "ignoreAdversarialQuery" => TRUE,
        "ignoreNonSummarySeekingQuery" => TRUE,
        "includeCitations" => $include_citations,
      ],
    ];

    if (!empty($conversation)) {
      // Pick up the conversation.
      $payload["conversation"] = self::sanitizeConversation($conversation);
    }

    return $payload;

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
