<?php

namespace Drupal\bos_google_cloud;

use Drupal;
use Drupal\bos_google_cloud\Services\GcSearch;
use Drupal\bos_google_cloud\Services\GcTextRewriter;
use Drupal\bos_google_cloud\Services\GcTextSummarizer;
use Drupal\bos_google_cloud\Services\GcTranslation;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Exception;

/**
  Class GcGenerationPrompt
  Creates a gen-ai prompt management tool

  david 02 2024
  @file docroot/modules/custom/bos_components/modules/bos_google_cloud/src/GcGenerationPrompt.php
 */
class GcGenerationPrompt {

  public const BASE_PROMPTS = [
    "default" => "You are a knowledgeable and polite City of Boston Employee who provides professional answers to questions. ",
    "official" => "You are a knowledgeable and polite City of Boston Employee who provides professional answers to questions. ",
    "friendly" => "You are a young and friendly City of Boston Employee who provides youthful answers to questions. ",
  ];

  public const SUMMARIZE_PROMPTS = [
    "default" => "Summarize the following text.",
    "10w-clean" => "Summarize the following text in 10 words using 8th grade reading level.",
    "20w-clean" => "Summarize the following text in 20 words using 8th grade reading level.",
    "50w-clean" => "Summarize the following text in 50 words using 8th grade reading level.",
    "100w-clean" => "Summarize the following text in 100 words using 8th grade reading level.",
    "50w-bullet-clean" => "Provide a bullet point summary of the following text in 50 words using 8th grade reading level.",
    "100w-bullet-clean" => "Provide a bullet point summary of the following text in 50 words using 8th grade reading level.",
    "10w" => "Summarize the following text in 10 words.",
    "20w" => "Summarize the following text in 20 words.",
    "50w" => "Summarize the following text in 50 words.",
    "100w" => "Summarize the following text in 100 words.",
    "50w-bullet" => "Provide a bullet point summary of the following text in 50 words using 8th grade reading level.",
    "100w-bullet" => "Provide a bullet point summary of the following text in 100 words using 8th grade reading level.",
  ];

  public const REWRITE_PROMPTS = [
    "default" => "Rewrite the following text.",
    "poem" => "Rewrite the following text as a poem.",
    "g8" => "Rewrite the following text using 8th grade reading level.",
    "g10" => "Rewrite the following text using 10th grade reading level.",
    "corrections" => "Rewrite the following text correcting grammar and spelling mistakes.",
    "editor" => "Highlight grammar and spelling mistakes in the following text.",
  ];

  public const SEARCH_CONVERSATION_PROMPTS = [
    "default" => "You are a senior City Hall employee in the City of Boston. You give helpful and polite answers in 300 words or less.",
  ];

  public const TRANSLATION_LANGUAGES = [
    "chinese" => "Translate the following text from English into Simplified Chinese",
    "french" => "Translate the following text from English into French",
    "portugese" => "Translate the following text from English into Portugese",
    "somali" => "Translate the following text from English into Somali",
    "spanish" => "Translate the following text from English into Spanish",
    "vietnamese" => "Translate the following text from English into Vietnamese",
  ];

  /**
   *  Provide a list of prompts for the config type defined in this class as
   *  constants, merged with those defined in the configuration pages.
   *
   * @param string $config_key The prompt type
   *
   * @return array A list of active prompts to populate selectboxes etc.
   */
  public static function getPrompts(string $config_key): array {

    $config = Drupal::config("bos_google_cloud.prompts")->get($config_key) ?? "";

    switch ($config_key) {
      case "base": $defaults = self::BASE_PROMPTS; break;
      case GcTextSummarizer::id(): $defaults = self::SUMMARIZE_PROMPTS; break;
      case GcTextRewriter::id(): $defaults = self::REWRITE_PROMPTS; break;
      case GcSearch::id(): $defaults = self::SEARCH_CONVERSATION_PROMPTS; break;
      case GcTranslation::id(): $defaults = self::TRANSLATION_LANGUAGES; break;
      default: return [];
    }

    $config = json_decode($config)??[];
    $output = [];
    foreach($config as $key => $value) {
      if (!empty($key)) {
        $output[$key] = base64_decode($value);
      }
    }
    return array_merge($defaults, $output);
  }

  /**
   *  Provide the text for the prompt defined in this class as
   *  constants, overridden by text defined in the configuration pages.
   *
   * @param string $config_key The prompt type
   * @param string $prompt The prompt key
   *
   * @return string The prompt text to feed to the GC API.
   * @throws \Exception
   */
  public static function getPromptText(string $config_key, string $prompt): string {
    $prompts = self::getPrompts($config_key);
    if (!array_key_exists($prompt, $prompts)) {
      throw new Exception("Unknown $config_key prompt.");
    }
    return $prompts[$prompt];
  }

  public function buildForm(array &$form): void {
    $description = t('A set of key:value pairs, one per line with the key and value separated by colons.<br>e.g. key1:value1<br>key2:value2');
    $form = $form + [
      'base_wrapper' => [
        '#type' => 'details',
        '#title' => t('Base Prompts'),
        "base" => [
          '#type' => 'textarea',
          '#title' => t('Prompts used by Text Summarizer, Text Rewriter and Text Translation'),
          '#description' => $description,
          '#default_value' => self::stringifyPrompts(self::getPrompts("base")),
          '#rows' => count(self::getPrompts("base"))+2,
          '#required' => TRUE,
        ],
      ],
      'summarizer_wrapper' => [
        '#type' => 'details',
        '#title' => t('Summarize Prompts'),
        GcTextSummarizer::id() => [
          '#type' => 'textarea',
          '#title' => t('Prompts used by Text Summarizer'),
          '#description' => $description,
          '#default_value' => self::stringifyPrompts(self::getPrompts(GcTextSummarizer::id())),
          '#rows' => count(self::getPrompts("summarizer"))+2,
          '#required' => TRUE,
        ],
      ],
      'rewriter_wrapper' => [
        '#type' => 'details',
        '#title' => t('Rewrite Prompts'),
        GcTextRewriter::id() => [
          '#type' => 'textarea',
          '#title' => t('Prompts used by Text Rewriter'),
          '#description' => $description,
          '#default_value' => self::stringifyPrompts(self::getPrompts(GcTextRewriter::id())),
          '#rows' => count(self::getPrompts(GcTextRewriter::id()))+2,
          '#required' => TRUE,
        ],
      ],
      'search_wrapper' => [
        '#type' => 'details',
        '#title' => t('Search Prompts'),
        "search" => [
          '#type' => 'textarea',
          '#title' => t('Prompts used by Search and Conversation'),
          '#description' => $description,
          '#default_value' => self::stringifyPrompts(self::getPrompts("search")),
          '#rows' => count(self::getPrompts("search"))+2,
          '#required' => TRUE,
        ],
      ],
      'translation_wrapper' => [
        '#type' => 'details',
        '#title' => t('Language Prompts'),
        GcTranslation::id() => [
          '#type' => 'textarea',
          '#title' => t('Prompts used by Text Translation'),
          '#description' => $description,
          '#default_value' => self::stringifyPrompts(self::getPrompts(GcTranslation::id())),
          '#rows' => count(self::getPrompts(GcTranslation::id()))+2,
          '#required' => TRUE,
        ],
      ],
    ];
  }

  private static function stringifyPrompts(array $prompts): string {
    $base = [];
    foreach ($prompts as $prompt => $prompt_text) {
      $base[] = "$prompt:$prompt_text";
    }
    return implode("\n", $base);
  }

  public function submitForm(array $form, FormStateInterface $form_state): void {

    $values = $form_state->getValue(['google_cloud'])["prompts_wrapper"];
    $config = Drupal::configFactory()->getEditable("bos_google_cloud.prompts");

    $prompts = self::jsonifyPrompts($values["base_wrapper"]["base"], self::BASE_PROMPTS);
    if ($config->get("base") != $prompts) {
      $config->set("base", $prompts);
    }

    $prompts = self::jsonifyPrompts($values["search_wrapper"][GcSearch::id()], self::SEARCH_CONVERSATION_PROMPTS);
    if ($config->get(GcSearch::id()) != $prompts) {
      $config->set(GcSearch::id(), $prompts);
    }

    $prompts = self::jsonifyPrompts($values["rewriter_wrapper"][GcTextRewriter::id()], self::REWRITE_PROMPTS);
    if ($config->get(GcTextRewriter::id()) != $prompts) {
      $this->invalidatePromptCache(GcTextRewriter::id(), json_decode($config->get(GcTextRewriter::id()), TRUE), json_decode($prompts,TRUE));
      $config->set(GcTextRewriter::id(), $prompts);
    }

    $prompts = self::jsonifyPrompts($values["summarizer_wrapper"][GcTextSummarizer::id()], self::SUMMARIZE_PROMPTS);
    if ($config->get(GcTextSummarizer::id()) != $prompts) {
      $this->invalidatePromptCache(GcTextSummarizer::id(), json_decode($config->get(GcTextSummarizer::id()), TRUE), json_decode($prompts,TRUE));
      $config->set(GcTextSummarizer::id(), $prompts);
    }

    $prompts = self::jsonifyPrompts($values["translation_wrapper"][GcTranslation::id()], self::TRANSLATION_LANGUAGES);
    if ($config->get(GcTranslation::id()) != $prompts) {
      $this->invalidatePromptCache(GcTranslation::id(), json_decode($config->get(GcTranslation::id()), TRUE), json_decode($prompts,TRUE));
      $config->set(GcTranslation::id(), $prompts);
    }

    $config->save();

  }

  /**
   * Returns a json encoded string from a \r\n and colon separated string.
   * (i.e. from a textarea form element.)
   *
   * Only key:value pairs from the string which are different to, or not in
   * (i.e. which override key:value pairs in) $constant_array array will be
   * returned.
   *
   * @param string $prompt_string The \r\n and colon delimited key:value string
   * @param array $constant_array [optional] An array to exclude
   *
   * @return string|bool
   */
  private function jsonifyPrompts(string $prompt_string, array $constant_array = []): string|bool {
    $prompt_array = [];
    foreach(explode("\r\n", $prompt_string) as $prompt) {
      if ($prompt) {
        $parts = explode(":", $prompt);
        if (!empty($parts[0]) && !array_key_exists($parts[0], $constant_array) || $constant_array[$parts[0]] != $parts[1]) {
          // Encode the prompt text to allow punctuation etc.
          $prompt_array[trim($parts[0])] = base64_encode(trim($parts[1]));
        }
      }
    }
    return json_encode($prompt_array);
  }

  /**
   * Helper to invalidate cache values when config form changes values.
   *
   * @param string $service
   * @param array $config_prompts
   * @param array $form_prompts
   *
   * @return void
   */
  private function invalidatePromptCache(string $service, array $config_prompts, array $form_prompts): void {

    $invalidate_list = [];

    switch ($service) {
      case GcTextSummarizer::id():
        $default_prompts = self::SUMMARIZE_PROMPTS;
        break;
      case GcTextRewriter::id():
        $default_prompts = self::REWRITE_PROMPTS;
        break;
      case GcTranslation::id():
        $default_prompts = self::TRANSLATION_LANGUAGES;
        break;
    }

    // process deleted or updated values
    foreach($config_prompts as $prompt => $prompt_text) {
      if (!array_key_exists($prompt, $form_prompts)) {
        // deleted - invalidate previous
        $invalidate_list[] = "$prompt";
      }
      elseif ($prompt_text != $form_prompts[$prompt]) {
        // updated - invalidate the old
        $invalidate_list[] = "$prompt";
      }
    }

    // find new values, where they differ from the default array
    if (!empty($default_prompts)) {
      foreach ($form_prompts as $prompt => $prompt_text) {
        if (array_key_exists($prompt, $default_prompts)
          && $prompt_text != $default_prompts[$prompt]) {
          // New customized prompt based on default
          $invalidate_list[] = "$prompt";
        }
      }
    }

    /**
     * @var \Drupal\bos_google_cloud\Services\GcCacheAI $cache
     */
    if (!empty($invalidate_list)) {
      $cache = Drupal::service("bos_google_cloud.GcCacheAI");
      $invalidate_list = Cache::mergeTags($invalidate_list);
      $cache->invalidateCacheByPrompts($service, $invalidate_list);
    }

  }

}
