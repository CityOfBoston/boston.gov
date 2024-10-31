<?php

namespace Drupal\bos_search\Model;

/**
 * class AiSearchCitation.
 * This class defines a citation to be used by any AiSearch plugin.
 *
 * @see \Drupal\bos_search\Model\AiSearchCitationCollection
 * @see \Drupal\bos_search\Plugin\AiSearch\AiSearchPluginManager
 *
 * Example implementation:
 * @see \Drupal\bos_gc_aisearch_plugin\Plugin\AiSearch\GcVertexConversation
 */

class AiSearchCitation extends AiSearchObjectsBase {

  /** @var int start char for this citation */
  protected int $startIndex = 0;

  /**
   * @var int end char for citation.
   */
  protected int $endIndex = 0;

  /** @var array Collection of references */
  protected array $sources = [];

  public function __construct(int $startIndex, int $endIndex, array $sources = []) {
    $this->startIndex = $startIndex;
    $this->endIndex = $endIndex;
    $this->sources = $sources;
  }

  /**
   * Returns an array with all the properties of this class.
   *
   * @return array
   */
  public function getCitation(): array {
    return [
      "startIndex" => $this->startIndex,
      "endIndex" => $this->endIndex,
      "sources" => $this->sources,
    ];
  }

  /**
   * Adds a new source (reference) to this citation.
   *
   * @param int $referenceIndex
   *
   * @return \use Drupal\bos_search\Model\AiSearchCitation Returns the instance of the AIsearchReference for method chaining.
   */
  public function addSource(array $source, int $key): AiSearchCitation {
    if (empty($key)) {
      $key = count($this->sources ?? []);
    }
    $this->sources[$key] = [
      "referenceIndex" => $source["referenceIndex"],
      "relevanceScore" => $source["relevanceScore"],
    ];
    return $this;
  }

}
