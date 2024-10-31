<?php

namespace Drupal\bos_search\Model;

/**
 * class AiSearchResponse.
 * This object defines a standardized search response from any AiSearch plugin.
 *
 * @see \Drupal\bos_search\AiSearchInterface
 * @see \Drupal\bos_search\Plugin\AiSearch\AiSearchPluginManager
 *
 * Example implementation:
 * @see \Drupal\bos_gc_aisearch_plugin\Plugin\AiSearch\GcVertexConversation
 */

class AiSearchResponse extends AiSearchObjectsBase {

  /** @var \Drupal\bos_search\AiSearchRequest The most recent search */
  protected AiSearchRequest $search;

  /** @var string The output answer from the AI Model (summary) */
  protected string $summary = "";

  protected int $no_results = 0;

  protected string $violations = "";

  /** @var string The output answer from the AI Model (full) */
  protected string $body = "";

  /** @var AiSearchCitationCollection Citations related to the body */
  protected AiSearchCitationCollection $citations;

  /** @var string The unique id for this conversation in the Model */
  protected string $session_id;

  /** @var array Any additional metadata */
  protected array $metadata = [];

  /** @var AiSearchReferenceCollection a list of references or links */
  protected AiSearchReferenceCollection $references;

  /** @var AiSearchResultCollection The array of results - AiSearchResult objects */
  protected AiSearchResultCollection $search_results;

  public function __construct(AiSearchRequest $search, string $summary, string $session_id = "") {
    $this->summary = $summary;
    $this->session_id = $session_id;
    $this->search = $search;
    $this->citations = new AiSearchCitationCollection();
    $this->references = new AiSearchReferenceCollection();
    $this->search_results = new AiSearchResultCollection();
    $this->search_results->setMaxResults($search->get("result_count"));
  }

  /**
   *
   *
   * @param \Drupal\bos_search\AiSearchResult $result
   *
   * @return $this
   */
  public function addResult(AiSearchResult $result): AiSearchResponse {
    $this->search_results->addResult($result);
    return $this;
  }

  public function addCitation(AiSearchCitation $citation, int $key = NULL): AiSearchResponse {
    $this->citations->addCitation($citation, $key);
    return $this;
  }

  public function updateCitation(AiSearchCitation $citation, int $key = NULL): AiSearchCitation {
    $this->citations->updateCitation($citation, $key);
    return $this;
  }

  public function addReference(AiSearchReference $reference, $key = NULL): AiSearchResponse {
    $this->references->addReference($reference, $key);
    return $this;
  }

  public function setReferenceId(int $oldReferenceId, int $newReferenceId): void {
    foreach ($this->citations as $citation) {

    }
  }

  public function getAll(): array {
    return [
      "ai_answer" => $this->summary,
      "no_results" => $this->no_results,
      "violations" => $this->violations,
      "session_id" => $this->session_id,
      "results" => $this->search_results->getResults()
    ];
  }

  public function getMetaData():array {
    return $this->metadata;
  }

  public function getResults():array {
    return $this->search_results->getResults();
  }

  public function getResultsCollection():AiSearchResultCollection {
    return $this->search_results;
  }

  public function getCitationsCollection():AiSearchCitationCollection {
    return $this->citations;
  }

  public function getReferences():array {
    return $this->references->getReferences();
  }

  public function getCitations():array {
    return $this->citations->getCitations();
  }

  public function build(): string {

    $preset = $this->search->get("preset") ?? [];

    $render_array = ['#theme' => 'results__' . $preset["searchform"]["theme"]];

    $response = $this->getAll();

    if ($response["no_results"] == 0 && empty($response["violations"]) && $this->search_results) {
      // A summary and optionally citations and results have been returned
      // from the AI Model.
      $render_array += [
        '#id' => $this->search->getId(),
        '#response' => $this->summary,
        '#feedback' => [
          "#theme" => "aisearch_feedback",
          "#thumbsup" => TRUE,
          "#thumbsdown" => TRUE,
        ],
        '#metadata' => $preset["results"]["metadata"] ? ($this->metadata ?? NULL) : NULL,
      ];

      // Add in the search Result items.
      foreach ($this->search_results->getResults() as $result) {
        $render_array["#items"][] = $result->getResult();
      }

      // Add in the Citation References.
      if ($preset["results"]["citations"]) {
        foreach ($this->references->getReferences() as $citation) {
          $render_array['#citations'][] = $citation;
        }
      }

      if (!$preset["results"]["summary"] ?? TRUE) {
        // If we are supressing the summary, then also supress the citations.
        $render_array["#content"] = NULL;
        $render_array["#citations"] = NULL;
      }

      if (!$preset["results"]["feedback"] ?? TRUE) {
        // If we are supressing feedback.
        $render_array["#feedback"] = NULL;
      }
    }
    elseif (!empty($response["violations"])) {
      // There were violations.
      $render_array += [
        '#items' => $this->search_results->getResults() ?? [],
        '#content' => $this->search->get("search_text"),
        '#id' => $this->search->getId(),
        '#response' => $preset["results"]["violations_text"] ?? "Non-conforming Query",
        '#metadata' => $preset["results"]["metadata"] ? ($this->metadata ?? NULL) : NULL,
      ];

      if (!$preset["results"]["summary"] ?? TRUE) {
        // If we are supressing the summary, then also supress the citations.
        $render_array["#content"] = NULL;
        $render_array["#citations"] = NULL;
      }

      if (!$preset["results"]["feedback"] ?? TRUE) {
        // If we are supressing feedback.
        $render_array["#feedback"] = NULL;
      }
    }
    else {
      // No results message was returned from the AI.
      $render_array += [
        '#id' => $this->search->getId(),
        '#feedback' => [
          "#theme" => "aisearch_feedback",
          "#thumbsup" => TRUE,
          "#thumbsdown" => TRUE,
        ],
        '#response' => $preset["results"]["no_result_text"],
        '#no_results' => $this->no_results,
        '#metadata' => $preset["results"]["metadata"] ? ($this->metadata ?? NULL) : NULL,
      ];
      // Add in the search Result items.
      foreach ($this->search_results->getResults() as $result) {
        $render_array["#items"][] = $result->getResult();
      }

    }
    // Allow to override the theme template.
    if (!empty($this->search->get("result_template"))) {
      $render_array['#theme'] = $this->search->get("result_template");
    }
    return \Drupal::service("renderer")->render($render_array);
  }

}
