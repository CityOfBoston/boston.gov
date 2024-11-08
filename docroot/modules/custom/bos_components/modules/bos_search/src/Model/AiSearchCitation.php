<?php

namespace Drupal\bos_search\Model;

/**
 * Class AiSearchCitation.
 *
 * This class defines a citation to be used by any AiSearch plugin.
 *
 * @see \Drupal\bos_search\Model\AiSearchCitationCollection
 * @see \Drupal\bos_search\Plugin\AiSearch\AiSearchPluginManager
 *
 * Example implementation:
 * @see \Drupal\bos_gc_aisearch_plugin\Plugin\AiSearch\GcVertexConversation
 */
class AiSearchCitation extends AiSearchObjectsBase {

  /**
   * Start char for this citation.
   *
   * @var int
   */
  protected int $startIndex = 0;

  /**
   * End char for citation.
   *
   * @var int
   */
  protected int $endIndex = 0;

  /**
   * Collection of references.
   *
   * @var array
   */
  protected array $sources = [];

  /**
   * {@inheritDoc}
   */
  public function __construct(int $startIndex, int $endIndex, array $sources = []) {
    $this->startIndex = $startIndex ?? 0;
    $this->endIndex = $endIndex;
    $this->sources = $sources;
  }

  /**
   * Returns an array with all the properties of this class.
   *
   * @return array
   *   Associative array.
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
   * If $key is provided then will overwrite the object at that index (if any).
   *
   * @param array $source
   *   Reference to add as an array.
   * @param int|null $key
   *   Index position in the collection. If null then appends to the end.
   *
   * @return Drupal\bos_search\Model\AiSearchCitation
   *   Returns the instance of the AIsearchReference for method chaining.
   */
  public function addSource(array $source, int|null $key = NULL): AiSearchCitation {
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
