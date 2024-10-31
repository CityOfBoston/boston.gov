<?php

namespace Drupal\bos_search\Model;

/**
 * class AiSearchReference.
 * This class defines a reference to be used by any AiSearch plugin.
 *
 * @see \Drupal\bos_search\AiSearchReferenceCollection
 * @see \Drupal\bos_search\Plugin\AiSearch\AiSearchPluginManager
 *
 * Example implementation:
 * @see \Drupal\bos_gc_aisearch_plugin\Plugin\AiSearch\GcVertexConversation
 */

class AiSearchReference extends AiSearchObjectsBase {

  /** @var string Title for the result (usually result page title) */
  protected string $title = "";

  protected string $ref = "";

  protected string $uri = "";
  protected string $id = "";
  protected int $seq;
  protected int $original_seq;
  protected array $locations = [];
  protected bool $is_result = FALSE;

  protected array $chunkContents = [];

  public function __construct(string $title = '', string $uri = '', string $ref = '') {
    $this->title = $title;
    $this->uri = $uri;
    $this->ref = $ref;
  }

  /**
   * Returns an array with all the properties of this class.
   *
   * @return array
   */
  public function getReference(): array {
    return [
      "title" => $this->title,
      "uri" => $this->uri,
      "ref" => $this->ref,
      "chunkContents" => $this->chunkContents,
      "seq" => $this->seq,
      "original_seq" => $this->original_seq,
      "locations" => $this->locations,
      "is_result" => $this->is_result,
    ];
  }

  /**
   * Adds content to a specific page chunk.
   *
   * @param string $content The content to be added.
   * @param string $pageIdentifier The identifier of the page where the content will be added.
   *
   * @return AIsearchReference      Returns the instance of the AIsearchReference for method chaining.
   */
  public function addChunkContent(string $content, string $pageIdentifier): AiSearchReference {
    $this->chunkContents[] = [
      "content" => $content,
      "pageIdentifier" => $pageIdentifier,
    ];
    return $this;
  }

}
