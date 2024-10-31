<?php

namespace Drupal\bos_search\Model;

/**
 * class AiSearchResultCollection.
 * This object defines a collection of search results from any AiSearch plugin.
 *
 * @see \Drupal\bos_search\AiSearchResult
 * @see \Drupal\bos_search\AiSearchResponse
 * @see \Drupal\bos_search\Plugin\AiSearch\AiSearchPluginManager
 *
 * Example implementation:
 * @see \Drupal\bos_gc_aisearch_plugin\Plugin\AiSearch\GcVertexConversation
 */
class AiSearchResultCollection extends AiSearchObjectsBase {

  /** @var array Array of AiSearchResult objects */
  protected array $results;

  protected int $max_count;

  public function __construct(int $max_count = -1) {
    if ($max_count != -1) {
      $this->max_count = $max_count;
    }
    $this->results = [];
  }

  /**
   * Add a search result to the collection.
   *
   * @param \Drupal\bos_search\AiSearchResult $result
   *
   * @return $this
   */
  public function addResult(AiSearchResult $result): AiSearchResultCollection {
    if ($this->max_count === 0 || $this->count() < $this->max_count) {
      // Only add the requested number of results.
      $this->results[] = $result;
    }
    return $this;
  }

  public function updateResult($key, AiSearchResult $result):AiSearchResultCollection {
    $this->results[$key] = $result;
    return $this;
  }

  /**
   * Get all results as an array of AiSearchResult objects.
   *
   * @return array
   */
  public function getResults(): array {
    return $this->results;
  }

  /**
   * Returns the number of AiSearchResult objects in the collection.
   * @return int
   */
  public function count(): int {
    return count($this->results);
  }

  /**
   * Sets the maximum number of AiSearchResults objects allowed in the collection.
   * @param $count
   *
   * @return void
   */
  public function setMaxResults(int $count):void {
    $this->max_count = $count;
  }

}
