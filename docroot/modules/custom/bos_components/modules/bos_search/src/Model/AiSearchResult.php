<?php

namespace Drupal\bos_search\Model;

/**
 * Class AiSearchResult.
 *
 * This class defines a search result to be used by any AiSearch plugin.
 *
 * @see \Drupal\bos_search\AiSearchResponse
 * @see \Drupal\bos_search\Plugin\AiSearch\AiSearchPluginManager
 *
 * Example implementation:
 * @see \Drupal\bos_gc_aisearch_plugin\Plugin\AiSearch\GcVertexConversation
 */
class AiSearchResult extends AiSearchObjectsBase {

  /** @var string Direct extract of copy from the page */
  protected string $content = "";

  /**
   * @var string Body copy from node.
   */
  protected string $description = "";

  /** @var string ID for the result */
  protected string $id = "";

  /** @var string Full URL link to resource which is the result */
  protected string $link = "";

  /** @var string [optional] Link for the title */
  protected string $linkTitle = '';

  /** @var string [optional] AI Model reference for result */
  protected string $ref = '';

  /** @var string A summary of the page content */
  protected string $summary = "";

  /** @var string Title for the result (usually result page title) */
  protected string $title = "";

  /** @var int the drupal nid for the search result */
  protected ?int $nid = NULL;

  public function __construct(string $title, string $link, string $summary) {
    $this->title = $title;
    $this->link = $link;
    $this->summary = $summary;
  }

  /**
   * Returns an array with all the properties of this class.
   *
   * @return array
   */
  public function getResult(): array {
    return [
      "content" => $this->content,
      "description" => $this->description,
      "id" => $this->id,
      "link" => $this->link,
      "link_title" => $this->linkTitle,
      "ref" => $this->ref,
      "summary" => html_entity_decode($this->summary),
      "title" => html_entity_decode($this->title),
      "nid" => $this->nid,
    ];
  }

}
