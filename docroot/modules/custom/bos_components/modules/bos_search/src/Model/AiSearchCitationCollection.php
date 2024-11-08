<?php

namespace Drupal\bos_search\Model;

/**
 * class AiSearchCitationCollection.
 * This object defines a collection of citations from any AiSearch plugin.
 *
 * @see \Drupal\bos_search\Model\AiSearchCitation
 * @see \Drupal\bos_search\Plugin\AiSearch\AiSearchPluginManager
 *
 * Example implementation:
 * @see \Drupal\bos_gc_aisearch_plugin\Plugin\AiSearch\GcVertexConversation
 */

class AiSearchCitationCollection extends AiSearchObjectsBase {

  /** @var array Array of AiSearchCitation objects */
  protected array $citations;

  /**
   * Add a citation to the collection.
   *
   * Checks for duplicates and will not add again if this citation is already
   * in the collection.
   *
   * @param \Drupal\bos_search\Model\AiSearchCitation $citation
   *   The citation object to add to the collection.
   * @param int|null $key
   *   Index position in the collection. If null then appends to the end.
   *
   * @return $this
   *   Updated collection.
   */
  public function addCitation(AiSearchCitation $citation, int|null $key = NULL): AiSearchCitationCollection {
    if (empty($key)) {
      $key = count($this->citations ?? []);
    }
    // Check for duplicates.
    $cit = $citation->getCitation();
    if (isset($this->citations)) {
      foreach ($this->citations as &$existing_citation) {
        if ($existing_citation['startIndex'] == $cit['startIndex']) {
          $existing_citation["sources"] = array_merge($existing_citation['sources'], $cit['sources']);
          return $this;
        }
      }
    }

    $this->citations[$key] = $cit;
    return $this;
  }

  /**
   * Updates citation at the specified key with the provided citation array.
   *
   * **Note**: does not check for duplicates.
   *
   * @param array $citation
   *   The citation details to update.
   * @param int $key
   *   The key identifying the citation to be updated.
   *
   * @return AiSearchCitationCollection
   *   Returns the updated citation collection.
   */
  public function updateCitation(array $citation, int $key):AiSearchCitationCollection {
    $this->citations[$key] = $citation;
    return $this;
  }

  /**
   * Retrieves the collection of citations.
   *
   * Each array member is a **Drupal/bos_search/Model/AiSearchCitation** object.
   *
   * @return array
   *   Returns an array of objects.
   */
  public function getCitations(): array {
    return $this->citations;
  }

  /**
   * Counts the total number of citations in the collection.
   *
   * @return int
   *   The total count of citations.
   */
  public function count(): int {
    return count($this->citations);
  }

}
