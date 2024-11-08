<?php

namespace Drupal\bos_search\Model;

use Drupal;

/**
 * Class AiSearchResponse.
 *
 * This object defines a standardized search response from any AiSearch plugin.
 *
 * @see \Drupal\bos_search\AiSearchInterface
 * @see \Drupal\bos_search\Plugin\AiSearch\AiSearchPluginManager
 *
 * Example implementation:
 * @see \Drupal\bos_gc_aisearch_plugin\Plugin\AiSearch\GcVertexConversation
 */
class AiSearchResponse extends AiSearchObjectsBase {

  /**
   * The most recent search based on user-input query.
   *
   * @var \Drupal\bos_search\Model\AiSearchRequest
   */
  protected AiSearchRequest $search;

  /**
   * The AI Generated summary for this query.
   *
   * @var string
   */
  protected string $summary = "";

  /**
   * Flag to denote if results were returned.
   *
   * @var int
   */
  protected int $noResults = 0;

  /**
   * List of violations reported by the Model.
   *
   * @var string
   */
  protected string $violations = "";

  /**
   * The Answer to be used from the AI Model (full).
   *
   * @var string
   */
  protected string $body = "";

  /**
   * Citations returned by the AI Model.
   *
   * @var AiSearchCitationCollection
   */
  protected AiSearchCitationCollection $citations;

  /**
   * The unique id for this conversation in the Model.
   *
   * @var string
   */
  protected string $sessionId;

  /**
   * Metadata for reporting/analysis.
   *
   * @var array
   */
  protected array $metadata = [];

  /**
   * The References (url/uri) associated with citations.
   *
   * @var AiSearchReferenceCollection
   */
  protected AiSearchReferenceCollection $references;

  /**
   * Search results returned from AI Search.
   *
   * An array AiSearchResult objects.
   *
   * @var AiSearchResultCollection
   */
  protected AiSearchResultCollection $searchResults;

  /**
   * Constructor for initializing the search request with summary & session ID.
   *
   * @param AiSearchRequest $search
   *   An instance of the AiSearchRequest class.
   * @param string $summary
   *   A string representing the summary.
   * @param string $session_id
   *   An optional string for session ID. Default is an empty string.
   *
   * @return void
   *   Nothing returned by this function.
   */
  public function __construct(AiSearchRequest $search, string $summary, string $session_id = "") {
    $this->summary = $summary;
    $this->sessionId = $session_id;
    $this->search = $search;
    $this->citations = new AiSearchCitationCollection();
    $this->references = new AiSearchReferenceCollection();
    $this->searchResults = new AiSearchResultCollection();
    $this->searchResults->setMaxResults($search->get("result_count"));
  }

  /**
   * Adds a search result to collection and returns the updated search response.
   *
   * @param AiSearchResult $result
   *   The search result to be added.
   *
   * @return AiSearchResponse
   *   The updated search response.
   */
  public function addResult(AiSearchResult $result): AiSearchResponse {
    $this->searchResults->addResult($result);
    return $this;
  }

  /**
   * Adds a citation to the citations collection.
   *
   * An optional key can be provided to specify the position.
   *
   * @param AiSearchCitation $citation
   *   The citation to be added.
   * @param int|null $key
   *   The optional key to specify the position for the citation.
   *
   * @return AiSearchResponse
   *   Returns the current instance of AiSearchResponse.
   */
  public function addCitation(AiSearchCitation $citation, ?int $key = NULL): AiSearchResponse {
    $this->citations->addCitation($citation, $key);
    return $this;
  }

  /**
   * Updates a citation in the collection.
   *
   * @param AiSearchCitation $citation
   *   The citation object to be updated.
   * @param int $key
   *   An optional key to specify the citation's position.
   *
   * @return AiSearchCitation
   *   The updated citation object.
   */
  public function updateCitation(AiSearchCitation $citation, int $key = 0): AiSearchCitation {
    $this->citations->updateCitation($citation->toArray(), $key);
    return $this->citations->getCitations()[$key];
  }

  /**
   * Adds a reference to the collection with an optional key.
   *
   * @param AiSearchReference $reference
   *   The reference to be added.
   * @param int|null $key
   *   Optional. The key to associate with the reference.
   *
   * @return AiSearchResponse
   *   The current instance of the response.
   */
  public function addReference(AiSearchReference $reference, int|null $key = NULL): AiSearchResponse {
    $this->references->addReference($reference, $key);
    return $this;
  }

  /**
   * Updates the reference ID for all citations in the collection.
   *
   * @param int $oldReferenceId
   *   The current reference ID that needs to be updated.
   * @param int $newReferenceId
   *   The new reference ID to replace the old one.
   *
   * @return void
   *   Nothing returned.
   */
  public function setReferenceId(int $oldReferenceId, int $newReferenceId): void {
    foreach ($this->citations as $citation) {

    }
  }

  /**
   * Retrieves this object as an array.
   *
   * @return array
   *   An associative array with keys 'ai_answer', 'no_results', 'violations',
   *   'session_id', and 'results'.
   */
  public function getAll(): array {
    return [
      "ai_answer" => $this->summary,
      "no_results" => $this->noResults,
      "violations" => $this->violations,
      "session_id" => $this->sessionId,
      "results" => $this->searchResults->getResults(),
    ];
  }

  /**
   * Retrieve metadata stored in the object.
   *
   * @return array
   *   Returns an array containing the metadata.
   */
  public function getMetaData():array {
    return $this->metadata;
  }

  /**
   * Retrieves the search results from the searchResults property.
   *
   * @return array
   *   The associative array of search results.
   */
  public function getResults():array {
    return $this->searchResults->getResults();
  }

  /**
   * Retrieve the collection of search results.
   *
   * @return AiSearchResultCollection
   *   The results collection.
   */
  public function getResultsCollection():AiSearchResultCollection {
    return $this->searchResults;
  }

  /**
   * Retrieves the collection of AI search citations.
   *
   * @return AiSearchCitationCollection
   *   The collection of citations.
   */
  public function getCitationsCollection():AiSearchCitationCollection {
    return $this->citations;
  }

  /**
   * Retrieves an array of references.
   *
   * @return array
   *   An array containing the references.
   */
  public function getReferences():array {
    return $this->references->getReferences();
  }

  /**
   * Retrieves an array of citations from the citations collection.
   *
   * @return array
   *   An array containing citation entries.
   */
  public function getCitations():array {
    return $this->citations->getCitations();
  }

  /**
   * Builds and returns the search results form section.
   *
   * Based on the AI model's responses and the preset configurations, form
   * elements are rendered and returned as a processed HTML string which can be
   * returned by AJAX callback and inserted into a form on the calling page.
   *
   * @return string
   *   The rendered HTML string of search results.
   */
  public function build(): string {

    $preset = $this->search->get("preset") ?? [];

    $render_array = ['#theme' => 'results__' . $preset["searchform"]["theme"]];

    $response = $this->getAll();

    if ($response["no_results"] == 0 && empty($response["violations"]) && $this->searchResults) {
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
      foreach ($this->searchResults->getResults() as $result) {
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
        '#items' => $this->searchResults->getResults() ?? [],
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
        '#no_results' => $this->noResults,
        '#metadata' => $preset["results"]["metadata"] ? ($this->metadata ?? NULL) : NULL,
      ];
      // Add in the search Result items.
      foreach ($this->searchResults->getResults() as $result) {
        $render_array["#items"][] = $result->getResult();
      }

    }
    // Allow to override the theme template.
    if (!empty($this->search->get("result_template"))) {
      $render_array['#theme'] = $this->search->get("result_template");
    }
    return Drupal::service("renderer")->render($render_array);
  }

}
