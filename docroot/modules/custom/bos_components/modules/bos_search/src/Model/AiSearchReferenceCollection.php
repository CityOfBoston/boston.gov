<?php

namespace Drupal\bos_search\Model;

/**
 * class AiReferenceCollection.
 * This object defines a collection of references from any AiSearch plugin.
 *
 * @see \Drupal\bos_search\AiSearchReference
 * @see \Drupal\bos_search\Plugin\AiSearch\AiSearchPluginManager
 *
 * Example implementation:
 * @see \Drupal\bos_gc_aisearch_plugin\Plugin\AiSearch\GcVertexConversation
 */

class AiSearchReferenceCollection extends AiSearchObjectsBase {

  /** @var array Array of AiSearchReference objects */
  protected array $references = [];

  /**
   * Add a reference to the collection.
   *
   * @param \Drupal\bos_search\AiSearchReference $reference
   *
   * @return $this
   */
  public function addReference(AiSearchReference $reference, int $key = NULL): AiSearchReferenceCollection {
    if (empty($key)) {
      $key = count($this->references ?? []);
    }
    $this->references[$key] = $reference->getReference();
    return $this;
  }

  /**
   * Get all results as an array of AISearchReference objects.
   *
   * @return array
   */
  public function getReferences(): array {
    return $this->references;
  }

  /**
   * Returns the number of AISearchReference objects in the collection.
   * @return int
   */
  public function count(): int {
    return count($this->references);
  }

}
