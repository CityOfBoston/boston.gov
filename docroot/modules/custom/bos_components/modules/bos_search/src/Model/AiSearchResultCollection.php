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

  /**
   * Array of AiSearchResult objects.
   *
   * @var array
   */
  protected array $results;

  /**
   * The maximum number of results to return.
   *
   * @var int
   */
  protected int $maxCount;

  public function __construct(int $max_count = -1) {
    if ($max_count != -1) {
      $this->maxCount = $max_count;
    }
    $this->results = [];
  }

  /**
   * Add a search result to the collection.
   *
   * @param \Drupal\bos_search\model\AiSearchResult $result
   *   The populated search results object.
   *
   * @return $this
   */
  public function addResult(AiSearchResult $result): AiSearchResultCollection {
    if ($this->maxCount === 0 || $this->count() < $this->maxCount) {
      // Only add the requested number of results.
      $this->results[] = $result;
    }
    return $this;
  }

  /**
   * Replaces a result element in the array.
   *
   * @param int|string $key
   *   The key for the placement in the array.
   * @param \Drupal\bos_search\Model\AiSearchResult $result
   *   A fully completed result array.
   *
   * @return $this
   */
  public function updateResult(int|string $key, AiSearchResult $result):AiSearchResultCollection {
    $this->results[$key] = $result;
    return $this;
  }

  /**
   * Get all results as an array of AiSearchResult objects.
   *
   * @return array
   *   An array of AiSearchRsult objects that have been added to this class.
   */
  public function getResults(): array {
    if ($this->maxCount == -1) {
      return $this->results;
    }
    else {
      return array_slice($this->results, 0, $this->maxCount);
    }
  }

  /**
   * Returns the number of AiSearchResult objects in the collection.
   *
   * @return int
   *   Number of elements the Results array.
   */
  public function count(): int {
    return count($this->results);
  }

  /**
   * Sets the maximum no. of AiSearchResults objects allowed in the class.
   *
   * @param int $count
   *   The number of results allowed in the class.
   *
   * @return void
   *   Nothing.
   */
  public function setMaxResults(int $count):void {
    $this->maxCount = $count;
  }

}
