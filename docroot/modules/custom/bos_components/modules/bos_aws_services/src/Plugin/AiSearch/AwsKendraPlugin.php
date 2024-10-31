<?php

namespace Drupal\bos_aws_services\Plugin\AiSearch;

use Drupal\bos_aws_services\Services\AwsKendraService;
use Drupal\bos_search\AiSearchBase;
use Drupal\bos_search\AiSearchInterface;
use Drupal\bos_search\Model\AiSearchRequest;
use Drupal\bos_search\Model\AiSearchResponse;
use Drupal\bos_search\Model\AiSearchResult;
use Drupal\bos_search\Annotation\AiSearchAnnotation;

/**
 * Provides an 'AiSearch' plugin for bos_aws_services.
 *
 * @AiSearchAnnotation (
 *   id = "Kendra Search",
 *   service = "bos_aws_services.kendra",
 *   description = "Plugin for AWS Kendra GenAI Service"
 * )
 */
class AwsKendraPlugin extends AiSearchBase implements AiSearchInterface {

  /** @var \Drupal\bos_aws_services\Services\AwsKendraService Holds the injected Vertex service. */
  protected AwsKendraService $kendra;

  /** @injectDoc */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    // Inject the kendra service.
    $this->kendra = \Drupal::getContainer()->get("bos_aws_services.kendra");
  }

  /**
   * @param \Drupal\bos_search\AiSearchRequest $request
   * @param bool $fake *
   *
* @inheritDoc
   */
  public function search(AiSearchRequest $request, bool $fake = FALSE): AiSearchResponse {
    try {

      $this->kendra->execute([
        "text" => $request->get("search_text"),
        "conversation_id" => $request->get("conversation_id") ?? "",
      ]);
      $result = $this->kendra->getResults();
    }
    catch (\Exception $e) {
      $result = FALSE;
    }

    // Load the GcSearchConversationResponse into the AiSearchResponse fmt.
    if ($result) {
      $response = new AiSearchResponse($request, $result['ai_answer'], $result['conversation_id']);
      $response->set("body", $result['body'])
        ->set("citations", $result['citations'])
        ->set("metadata", $result['metadata'])
        ->set("references", $result['references']);
      foreach($result['search_results'] as $search_result) {
        // Load each search result into the AiSearchResult format.
        $res = new AiSearchResult($search_result["title"], $search_result["link"], $search_result["summary"]);
        $res->set("id", $search_result["id"])
          ->set("link_title", $search_result["link_title"])
          ->set("ref", $search_result["ref"]);
        $response->addResult($res);
      }
      $request->addHistory($response);
      $response->set("search", $request);
    }

    return $response;
  }

  /**
   * @inheritDoc
   */
  public function hasFollowUp(): bool {
    return $this->kendra->hasFollowup();
  }

  /**
   * @inheritDoc
   */
  public function availablePrompts(): array {
    // TODO: Implement availablePrompts() method.
  }

}
