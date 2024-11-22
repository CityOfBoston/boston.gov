<?php

namespace Drupal\bos_search\Model;

use Drupal;
use Drupal\bos_search\AiSearch;

/**
 * class AiSearchRequest.
 * This object defines a search which can be interpreted by any AiSearch plugin.
 *
 * @see \Drupal\bos_search\AiSearchInterface
 * @see \Drupal\bos_search\Plugin\AiSearch\AiSearchPluginManager
 *
 * Example implementation:
 * @see \Drupal\bos_gc_aisearch_plugin\Plugin\AiSearch\GcVertexConversation
 */
class AiSearchRequest extends AiSearchObjectsBase {

  /** @var array An array of AiSearchResult objects. */
  protected array $history;

  /** @var string The search question. */
  protected string $search_text;

  /** @var string The unique ID for this conversation. */
  protected string $session_id = "";
  protected string $form_id = "";

  protected int $result_count = 0;
  protected string $result_template = "";

  protected int $include_citations = 0;
  protected int $semantic_chunks = 0;
  protected int $safe_search = 0;
  protected int $metadata = 0;
  protected array $preset = [];

  protected string $prompt = "default";

  public function __construct(string $form_id = "", string $search_text = "", int $result_count = 0, string $result_template = "") {
    $this->form_id = $form_id;
    if (!empty($search_text)) {
      $search_text = AiSearch::sanitize($search_text);
      $this->search_text = trim($search_text);
    }
    $this->result_count = $result_count;
    if (!empty($result_template)) {
      $this->result_template = $result_template;
    }
  }

  public function addHistory(AiSearchResponse $search): AiSearchRequest {
    $this->history[] = $search;
    return $this;
  }
  public function getHistory(): array {
    return $this->history;
  }

  public function set(string $key, mixed $value): AiSearchRequest {
    if ($key == "session_id") {
      $this->load($value);
    }
    else {
      parent::set($key, $value);
    }
    return $this;
  }

  public function getId(): string {
    return $this->session_id;
  }

  /**
   * Save the conversation history so it can be retrieved later.
   *
   * @return void
   */
  public function save(): void {
    Drupal::service("keyvalue.expirable")
      ->get("bos_aisearch")
      ->setWithExpire($this->session_id, $this->getHistory(), 300);
  }

  /**
   * Loads a previous conversation history from key:value store.
   *
   * @param string $id Conversation ID to retrieve
   *
   * @return \Drupal\bos_search\AiSearchRequest
   */
  protected function load(string $id = ""): AiSearchRequest {
    if (!empty($id)) {
      // use the supplied session_id rather than the one set in the class.
      $this->session_id = $id;
    }

    if (!empty($this->session_id)) {
      // If we have a saved conversation, load it up.
      if ($history = Drupal::service("keyvalue.expirable")
        ->get("bos_aisearch")
        ->get($this->session_id) ?? FALSE) {
        $this->history = $history;
      }

    }
    return $this;
  }

}
