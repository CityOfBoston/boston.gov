<?php

namespace Drupal\bos_gc_aisearch_plugin\Plugin\AiSearch;

use Drupal\bos_search\AiSearch;
use Drupal\bos_search\AiSearchBase;
use Drupal\bos_search\Model\AiSearchCitation;
use Drupal\bos_search\AiSearchInterface;
use Drupal\bos_search\Model\AiSearchReference;
use Drupal\bos_search\Model\AiSearchRequest;
use Drupal\bos_search\Model\AiSearchResponse;
use Drupal\bos_search\Model\AiSearchResult;
use Drupal\bos_search\Annotation\AiSearchAnnotation;
use Drupal\bos_search\Twig\CustomFiltersExtension;

/**
 * Provides an 'AiSearch' plugin for bos_google_cloud.
 *
 * @AiSearchAnnotation (
 *   id = "Vertex Search",
 *   service = "bos_google_cloud.GcSearch",
 *   description = "Plugin for Google Cloud Vertex Search Service."
 * )
 */
class GcVertexSearch extends AiSearchBase implements AiSearchInterface {

  private const NO_RESULTS = "No Results Reported";

  /** @injectDoc */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * @inheritDoc
   */
  public function search(AiSearchRequest $request, bool $fake = FALSE): AiSearchResponse {
    try {
      // Ask the search question to Vertex.
      $preset = $request->get("preset") ?? [];

      if ($fake) {
        $response = $this->fakeResponse();
      }
      else {
        $parameters = [
          "text" => $request->get("search_text") ?? "",
          "allow_conversation" => $preset["searchform"]["searchbar"]["allow_conversation"] ?? FALSE,
          "session_id" => $request->get("session_id") ?? "",
          "prompt" => $preset["prompt"] ?? 'default',
          "extra_prompt" => 'If you cannot understand the question or the question cannot be answered, start the response with the text "' . self::NO_RESULTS . '"',
          "metadata" => $preset["results"]["metadata"] ?? 0,
          "num_results" => $preset["results"]["result_count"] ?? 0,
          "include_citations" => $preset["results"]["citations"] ?? 0,
//          "min_citation_relevance" => $preset["results"]["min_citation_relevance"] ?? 0,
          "related_questions" => $preset["results"]["related_questions"] ?? 0,
          "safe_search" => $preset["model_tuning"]['search']["safe_search"] ?? 0,
          "ignoreAdversarialQuery" => $preset["model_tuning"]['summary']["ignoreAdversarialQuery"] ?? 0,
          "ignoreNonSummarySeekingQuery" => $preset["model_tuning"]['summary']["ignoreNonSummarySeekingQuery"] ?? 0,
          "ignoreLowRelevantContent" => $preset["model_tuning"]['summary']["ignoreLowRelevantContent"] ?? 0,
          "ignoreJailBreakingQuery" => $preset["model_tuning"]['summary']["ignoreJailBreakingQuery"] ?? 0,
          "semantic_chunks" => $preset["model_tuning"]['summary']["semantic_chunks"] ?? 0,
        ];

        // Apply any service overrides.
        if (!empty($preset["model_tuning"]["overrides"]["service_account"]) && $preset["model_tuning"]["overrides"]["service_account"] != "default") {
          $parameters["service_account"] = $preset["model_tuning"]["overrides"]["service_account"];
          $this->service->setServiceAccount($parameters["service_account"]);
        }
        if (!empty($preset["model_tuning"]["overrides"]["project_id"]) && $preset["model_tuning"]["overrides"]["project_id"] != "default") {
          $parameters["project_id"] = $preset["model_tuning"]["overrides"]["project_id"];
        }
        if (!empty($preset["model_tuning"]["overrides"]["datastore_id"]) && $preset["model_tuning"]["overrides"]["datastore_id"] != "default") {
          $parameters["datastore_id"] = $preset["model_tuning"]["overrides"]["datastore_id"];
        }
        if (!empty($preset["model_tuning"]["overrides"]["engine_id"]) && $preset["model_tuning"]["overrides"]["engine_id"] != "default") {
          $parameters["engine_id"] = $preset["model_tuning"]["overrides"]["engine_id"];
        }

        // Query the Agent Builder.
        $response = $this->service->execute($parameters);

      }
    }
    catch (\Exception $e) {
      throw new \Exception($e->getMessage());
    }

    // Load the SearchResponse object into the AiSearchResponse object.
    if ($response) {
      $output = $this->loadSearchResponse($this->getService()->response(), $preset, $request);
    }

    return $output;
  }

  /**
   * @inheritDoc
   */
  public function hasFollowUp(): bool {
    return $this->service->hasFollowup();
  }

  /**
   * @inheritDoc
   */
  public function availablePrompts(): array {
    return $this->service->availablePrompts();
  }

  /**
   * Loads a GoogleCloud SearchResponse object.
   *
   * Uses a standardized AiSearchResponse object which can be consumed by
   * bos_search.
   *
   * @param array $fullResponse
   * @param $preset
   * @param \Drupal\bos_search\AiSearchRequest $request
   *
   * @return AiSearchResponse
   *
   * @see bos_google_cloud/src/Apis/v1alpha/SearchResponse.php
   * @see bos_search/src/AiSearchResponse.php
   */
  private function loadSearchResponse(array $fullResponse, $preset, AiSearchRequest $request): AiSearchResponse {

    $searchResponse = $fullResponse["object"]->toArray();

    $aiSearchResponse = new AiSearchResponse($request, $searchResponse["summary"]["summaryText"], $this->service->getSessionInfo()["session_id"] ?? "");

    // Load any citations.
    if ($preset["results"]["citations"] && !empty($searchResponse["summary"]["summaryWithMetadata"]["citationMetadata"]["citations"])) {
      $this->loadCitations($aiSearchResponse, $searchResponse, $preset);
    }

    // Load any results.
    if ($preset["results"]["searchresults"] && !empty($searchResponse["results"])) {
      $this->loadSearchResults($aiSearchResponse, $searchResponse, $preset);
    }

    // [optional] Resolve Search Results into a node and check.
    if ($preset["results"]["searchresults"] && !empty($searchResponse["results"])) {
      $this->postProcessResults($aiSearchResponse);
    }

    if (str_starts_with(trim($searchResponse["summary"]["summaryText"]), self::NO_RESULTS)) {
      $aiSearchResponse->set("no_results", TRUE);
    }
    else {
      $aiSearchResponse->set("body", $searchResponse["summary"]["summaryText"]);
    }

    // Load the metadata from the SearchResponse and extend with preset info.
    if ($preset["results"]["metadata"]) {
      $metadata = array_merge($fullResponse["metadata"], ["Search Presets" => $preset]);
      $metadata = $this->flattenMetadata($metadata);

      // Reformat a bit for display.
      foreach ($metadata as &$metadatum) {
        foreach ($metadatum as $field => $value) {
          $field_parts = explode(".", $field);
          if (count($field_parts) > 1) {
            $new_field = $field_parts[0];
            $counter = 0;
            foreach (array_slice($field_parts, 1) as $part) {
              $new_field .= "<br>" . str_repeat("&nbsp;", $counter += 2) . "-$part";
            }
            $metadatum[$new_field] = $value;
            unset($metadatum[$field]);
          }
        }
      }

      $aiSearchResponse->set("metadata", $metadata);
    }

    return $aiSearchResponse;

  }

  /**
   * Load the GCSearchResults into AiSearchResponse format for Search Results.
   *
   * Also mark where results are duplicated in the list of references.
   *
   * @param AiSearchResponse $aiSearchResponse Search response object.
   * @param array $searchResponse Array of values to load.
   * @param array $preset The preset for this plugin.
   */
  private function loadSearchResults(AiSearchResponse &$aiSearchResponse, array $searchResponse, array $preset):void {

    $references = $aiSearchResponse->getReferences();
    $hasCitations = $preset["results"]["citations"];
    $noDupCitation = $preset["results"]["no_dup_citations"];

    foreach ($searchResponse["results"] as $search_result) {
      $ds = $search_result["document"]["derivedStructData"];
      $title = explode("|", $ds["htmlTitle"], 2)[0];
      $res = new AiSearchResult($title, $ds["link"], $ds["snippets"][0]["snippet"] ?: "");
      $docid = $search_result["id"];

      $filter = new CustomFiltersExtension();
      $content = $ds["extractive_answers"][0]["content"] ?: FALSE;
      if ($filter->hasNonEnglishChars($content) || !$content) {
        // If the selected content string contains non-english content, then
        // try the alternative extractive output.
        $content = $ds["extractive_segments"][0]["content"] ?: FALSE;
        if ($filter->hasNonEnglishChars($content) || !$content) {
          // Still not english content, so set to empty string and the
          // postProcessResults() will inject summary content from the node.
          $content = "";
        }
      }

      $res->set("id", $docid)
        ->set("link_title", explode("|", $ds["title"], 2)[0])
        ->set("ref", $search_result["document"]["name"])
        ->set("content", $content)
        ->set("description", "")
        ->set("is_citation", FALSE);

      // Check if this result is also in the citations (references) list.
      if ($hasCitations) {
        foreach ($references as $reference) {
          $refdoc = explode("/", $reference["ref"]);
          $refdocid = array_pop($refdoc);
          if ($docid == $refdocid) {
            $res->set("is_citation", TRUE);
            break;
          }
        }
      }

      // Actually load this Result.
      if ($noDupCitation) {
        if (!$res->get("is_citation")) {
          // If not loading results which are also citations
          // and this result is not also a citation, then load.
          $aiSearchResponse->addResult($res);
        }
      }
      else {
        // If we are allowing results which are also citations
        // then load.
        $aiSearchResponse->addResult($res);
      }
    }
  }

  /**
   * Load GCSearchResults into AiSearchResponse fmt for Citation & References.
   *
   * Also mark where references are duplicated in the list of search results.
   *
   * @param \Drupal\bos_search\Model\AiSearchResponse $aiSearchResponse
   * @param array $searchResponse
   * @param array $preset
   *
   * @return void
   */
  private function loadCitations(AiSearchResponse &$aiSearchResponse, array $searchResponse, array $preset):void {

    $citations = $searchResponse['summary']['summaryWithMetadata']['citationMetadata']['citations'];
    $references = $searchResponse["summary"]["summaryWithMetadata"]["references"];

    // Cycle through references and deduplicate them.
    // Update Citation source when a duplicate is found so ref is not lost.
    $refs = [];
    foreach ($references as $ref_key => $reference) {
      if (array_key_exists($reference["document"], $refs)) {
        // Need to deduplicate and update Citation.
        $first_instance = $refs[$reference["document"]];
        foreach ($citations as &$citation) {
          foreach ($citation["sources"] as &$source) {
            if ($source["referenceIndex"] == $ref_key) {
              $source["referenceIndex"] = $first_instance;
            }
          }
        }
      }
      else {
        $refs[$reference["document"]] = $ref_key;
      }
    }

    // Cycle through the Citations, and load them into aiSearchResponse.
    foreach ($citations as $citation_key => $citation) {

      $searchCitation = new AiSearchCitation($citation['startIndex'], $citation['endIndex']);

      // Get find the relevance score for each source (Reference) and only
      // save the source if it is the only one, or if it is above the threshold
      // set in the preset.
      foreach ($citation['sources'] as $cit_source_key => $source) {
        $sourceReference = $references[$source["referenceIndex"]];
        if (count($citation['sources']) == 1
          || $sourceReference["extraInfo"]["relevanceScore"] >= $preset["results"]["min_citation_relevance"]) {
          $source["relevanceScore"] = $sourceReference["extraInfo"]["relevanceScore"];
          $searchCitation->addSource($source, $cit_source_key);
        }
      }
      $aiSearchResponse->addCitation($searchCitation, $citation_key);

    }

    // Now reload only  the citations that are loaded into aiSearchResponse,
    // and update the referenceIndex with the new ID's.
    $citations = $aiSearchResponse->getCitations();

    // Cycle through the References and load them into aiSearchResponse.
    $idx = 0;
    foreach ($references as $reference_key => $reference) {

      $title = explode("|", $reference["title"], 2)[0];
      $searchReference = new AiSearchReference($title, $reference["uri"], $reference["document"]);
      $searchReference->addChunkContent($reference["chunkContents"]["content"], $reference["chunkContents"]["pageIdentifier"] ?? "");
      $doc = explode("/", $reference["document"]);
      $searchReference->set("id", array_pop($doc));
      $searchReference->set("relevanceScore", $reference["extraInfo"]["relevanceScore"]);

      // Find Citations which use this Reference and add in the location (char
      // range) for the Summary Annotation.
      foreach ($citations as $citation) {
        $locations = [];
        foreach ($citation["sources"] as $source) {
          if ($source["referenceIndex"] == $reference_key) {
            $locations[] = [
              "startIndex" => $citation["startIndex"] ?? 0,
              "endIndex" => $citation["endIndex"] ?? strlen($aiSearchResponse["body"]),
            ];
            break;
          }
        }
        $searchReference->set("locations", $locations);
      }

      // Set a flag if this Reference is used in any SearchResults.
      $searchReference->set("is_result", FALSE);
      foreach ($searchResponse["results"] as $result) {
        if ($result["id"] == $searchReference->get("id")) {
          $searchReference->set("is_result", TRUE);
          break;
        }
      }

      // Only load this Reference if it has a location in the Citation.
      // Some references are returned which do not have citations, presumably
      // because they were used in drafts, or the citation limit means the
      // Citation did not appear in the final listing returned by the API.
      if (count($searchReference->get("locations"))) {
        $idx++;
        $searchReference->set("original_seq", $reference_key);
        $searchReference->set("seq", $idx);
        $aiSearchResponse->addReference($searchReference, $idx);

        // Update the Citations with the newly set referenceIndex ($idx).
        $citation_collection = $aiSearchResponse->getCitationsCollection();
        foreach ($citation_collection->getCitations() as $cit_key => $citation) {
          foreach ($citation["sources"] as &$source) {
            if ($source["referenceIndex"] == $reference_key) {
              // Use a negative number so we don't end up with this being
              // overwritten on another pass though the loop.
              $source["referenceIndex"] = -$idx;
            }
          }
          $citation_collection->updateCitation($cit_key, $citation);
        }

      }

    }
    // Remove any negative ReferenceIndexes created above.
    foreach ($citation_collection->getCitations() as $cit_key => $citation) {
      foreach ($citation["sources"] as &$source) {
        if ($source["referenceIndex"] < 0) {
          $source["referenceIndex"] = abs($source["referenceIndex"]);
        }
      }
      $citation_collection->updateCitation($cit_key, $citation);
    }

    // Make sure the Citations are indexed correctly.
    $references = $aiSearchResponse->getReferences();
    $citations = $aiSearchResponse->getCitations();

    // Add Annotations to the summary Text, for Citations and References
    // that remain. Copy the original summary to "body" and save the annotated
    // summary.
    $summary = $searchResponse["summary"]["summaryWithMetadata"]["summary"];
    $aiSearchResponse->set("body", $summary);

    foreach ($citations as $citation) {
      $text = substr($summary, $citation["startIndex"], ($citation["endIndex"] - $citation["startIndex"]));
      $citation_collection = [];
      // Check the sources, de-duplicating them using the referenceIndex.
      foreach ($citation["sources"] as $cit_source) {
        $citation_collection[$cit_source["referenceIndex"]] = $cit_source["referenceIndex"];
      }
      $citation_collection = implode(",", array_keys($citation_collection));
      $summaryParts[] = trim($text) . "[$citation_collection] ";
    }
    $summary = implode("", $summaryParts);
    $aiSearchResponse->set("summary", $summary);

  }

  /**
   * Post-processes the search results to enhance content.
   *
   *  - Finding the nid for the node.
   *  - Checking language of page.
   *  - Loading the Drupal summary for content.
   *
   * @param AiSearchResponse $aiSearchResponse
   *   The response object containing the initial search results.
   *
   * @return void
   *   This method does not return a value but modifies the results directly.
   */
  private function postProcessResults(AiSearchResponse $aiSearchResponse):void {

    $results = $aiSearchResponse->getResultsCollection();

    $alias_manager = \Drupal::service('path_alias.manager');
    $redirect_manager = \Drupal::service('redirect.repository');

    foreach ($results->getResults() as $key => $result) {

      // The content field may be empty if either: the AI did not return an
      // extractive_answer or an extractive_segment (unlikely) or if both have
      // non-english chars in them. If content is empty, then find the node and
      // extract a summary from the body of the content.
      if (empty($result->get('content'))) {

        $path_alias = explode(".gov", $result->get("link"), 2)[1];

        if (!empty($path_alias)) {
          // Strip out the alias from any other querystings etc.
          $path_alias = explode('?', $path_alias, 2);
          $path_alias = explode('#', $path_alias[0], 2)[0];

          // Get the nid for this page alias (to prevent duplicates).
          $path = $alias_manager->getPathByAlias($path_alias);
          $path_parts = explode('/', $path);
          $nid = array_pop($path_parts);

          if (!is_numeric($nid)) {
            // If we can't get the node ID then it is possibly a redirect to
            // another page, so try to track that down...
            $nid = NULL;
            $redirects = $redirect_manager->findBySourcePath(trim($path_alias, "/"));
            if (!empty($redirects)) {
              $redirect = reset($redirects);
              $original_alias = explode(":", $redirect->getRedirect()['uri'], 2)[1] ?? $redirect->getRedirect()['uri'];
              $path = $alias_manager->getPathByAlias($original_alias);
              $path_parts = explode('/', $path);
              $nid = array_pop($path_parts);
            }
          }

          if ($nid) {
            $node = \Drupal::entityTypeManager()
              ->getStorage('node')
              ->load($nid);

            $content = "";
            // Build up a summary.
            if ($node && $node->hasField("field_intro_text")) {
              $content .= $node->get("field_intro_text")->value;
            }
            if ($node && $node->hasField("body")) {
              $content .= ($node->get("body")->summary ?: $node->get("body")->value);
            }
            if ($node && $node->hasField("field_need_to_know")) {
              $content .= $node->get("field_need_to_know")->value;
            }

            // Update the result.
            $result->set("nid", $nid);
            $result->set("content", AiSearch::sanitize(strip_tags($content)));
            $results->updateResult($key, $result);

          }
        }
      }
    }

  }

  /**
   * @param array &$elements
   * @param array $map
   * @param array $exclude_elem
   * @param string $prefix *
   *
* @inheritDoc
   */
  protected function flattenMetadata(array &$metadata, array $map = [], array $exclude_elem = []): array {
    $map = [];
    $exclude_elem = [
      "headers.Authorization",
      "answer_response_raw",
      "response_raw",
    ];
    return parent::flattenMetadata($metadata, $map, $exclude_elem);
  }

  /**
   * Generates a fake response for the search functionality.
   *
   * This method is used primarily for testing and development
   * purposes. It simulates a response that would come from the
   * search service, allowing developers to test the flow and
   * interaction without requiring a live service connection.
   *
   * @return AiSearchResponse A simulated search response.
   */
  private function fakeResponse() {
    $a = base64_decode("Tzo1MToiRHJ1cGFsXGJvc19nb29nbGVfY2xvdWRcQXBpc1x2MWFscGhhXFNlYXJjaFJlc3BvbnNlIjoxOntzOjk6IgAqAG9iamVjdCI7YTo3OntzOjc6InJlc3VsdHMiO2E6NTp7aTowO2E6Mjp7czoyOiJpZCI7czozMjoiYmQ3ZmI0ZTlhNTQ0ZDgyNWRkNmJiNWE0ODIyMGFkYWYiO3M6ODoiZG9jdW1lbnQiO2E6Mzp7czo0OiJuYW1lIjtzOjE2OToicHJvamVjdHMvNzM4MzEzMTcyNzg4L2xvY2F0aW9ucy9nbG9iYWwvY29sbGVjdGlvbnMvZGVmYXVsdF9jb2xsZWN0aW9uL2RhdGFTdG9yZXMvb2VvaS1waWxvdC1kYXRhc3RvcmVfMTcyNjI2NTc5NTkxMC9icmFuY2hlcy8wL2RvY3VtZW50cy9iZDdmYjRlOWE1NDRkODI1ZGQ2YmI1YTQ4MjIwYWRhZiI7czoyOiJpZCI7czozMjoiYmQ3ZmI0ZTlhNTQ0ZDgyNWRkNmJiNWE0ODIyMGFkYWYiO3M6MTc6ImRlcml2ZWRTdHJ1Y3REYXRhIjthOjc6e3M6ODoic25pcHBldHMiO2E6MTp7aTowO2E6Mjp7czo3OiJzbmlwcGV0IjtzOjEwMjoiTGVhcm4gbW9yZSBhYm91dCBob3cgdG8gYXBwbHkgZm9yIGNlcnRpZmljYXRpb24gd2l0aCB0aGUgPGI+Q2l0eSYjMzk7cyBTdXBwbGllcjwvYj4gRGl2ZXJzaXR5IFByb2dyYW0uIjtzOjE0OiJzbmlwcGV0X3N0YXR1cyI7czo3OiJTVUNDRVNTIjt9fXM6MTk6ImV4dHJhY3RpdmVfc2VnbWVudHMiO2E6MTp7aTowO2E6MTp7czo3OiJjb250ZW50IjtzOjEwMTE6IllvdXIgdmVuZG9yIGFjY291bnQgd2lsbCBhbGxvdyB5b3UgdG8gc2VlIGFuZCBiaWQgb24gQ2l0eSBjb250cmFjdHMuIEtlZXAgaW4gbWluZCBZb3UgY2FuIHNlYXJjaCBvdXIgZGF0YWJhc2UgdG8gZmluZCBjZXJ0aWZpZWQgZGl2ZXJzZSBhbmQgc21hbGwgYnVzaW5lc3NlcyBpbiBCb3N0b24uIFdlIGFsc28gaGF2ZSBhIGxpc3Qgb2YgYWxsIG9wZW4gYmlkIHByb2plY3RzIGluIHRoZSBDaXR5IG9mIEJvc3Rvbi4gWW91IGNhbiBnZXQgYSBwYWlkIG1haWwgc3Vic2NyaXB0aW9uLCBvciBzZWUgYSBsaXN0IG9mIGN1cnJlbnQgYmlkcyBvbmxpbmUuIFJlbGF0ZWQgUmVzb3VyY2VzIFJlbGF0ZWQgUmVzb3VyY2VzIEhvdyB0byBhcHBseSBmb3IgYSBDaXR5IG9mIEJvc3RvbiBidXNpbmVzcyBjZXJ0aWZpY2F0ZSBTaWduIHVwIGZvciBvdXIgbmV3c2xldHRlciBDb250YWN0OiBTdXBwbGllciBEaXZlcnNpdHkgU2lnbiB1cCBmb3Igb3VyIFN1cHBsaWVyIERpdmVyc2l0eSBuZXdzbGV0dGVyIHRvIGxlYXJuIGFib3V0IHVwY29taW5nIENpdHkgY29udHJhY3Rpbmcgb3Bwb3J0dW5pdGllcywgZXZlbnRzLCBhbmQgd29ya3Nob3BzLiBZb3VyIEVtYWlsIEFkZHJlc3MgWmlwIENvZGUgR290Y2hhIFNpZ24gVXAgSGF2ZSBxdWVzdGlvbnM/IENvbnRhY3Q6IHN1cHBsaWVyIGRpdmVyc2l0eSBwcm9ncmFtIDYxNy02MzUtNDUxMSBidXNpbmVzc2NlcnRpZmljYXRpb25AYm9zdG9uLmdvdiBaT09NIENFUlRJRklDQVRJT04gSE9VUlMgSm9pbiBvdXIgd2Vla2x5IE1XQkUgWm9vbSBDZXJ0aWZpY2F0aW9uIEhvdXJzLCBldmVyeSBXZWRuZXNkYXkgZnJvbSAxMSBhbSAtIDEgcG06IGpvaW4gY2VydGlmaWNhdGlvbiBvZmZpY2UgSG91cnMgUHJvdmlkZSBZb3VyIEZlZWRiYWNrIEJhY2sgdG8gdG9wIEZvb3RlciBtZW51IFByaXZhY3kgUG9saWN5IENvbnRhY3QgdXMgSm9icyBQdWJsaWMgcmVjb3JkcyBMYW5ndWFnZSBhbmQgRGlzYWJpbGl0eSBBY2Nlc3MgQk9TOjMxMSAtIFJlcG9ydCBhbiBpc3N1ZSI7fX1zOjExOiJkaXNwbGF5TGluayI7czoxNDoid3d3LmJvc3Rvbi5nb3YiO3M6NToidGl0bGUiO3M6NDA6IkdldCBZb3VyIEJ1c2luZXNzIENlcnRpZmllZCB8IEJvc3Rvbi5nb3YiO3M6NDoibGluayI7czo5NToiaHR0cHM6Ly93d3cuYm9zdG9uLmdvdi9kZXBhcnRtZW50cy9zdXBwbGllci1hbmQtd29ya2ZvcmNlLWRpdmVyc2l0eS9nZXQteW91ci1idXNpbmVzcy1jZXJ0aWZpZWQiO3M6MTg6ImV4dHJhY3RpdmVfYW5zd2VycyI7YToxOntpOjA7YToxOntzOjc6ImNvbnRlbnQiO3M6MjYxOiJQcm9vZiBvZiB5b3VyIGJ1c2luZXNzJiMzOTsgcmVnaXN0cmF0aW9uIG1pZ2h0IGluY2x1ZGUgYXJ0aWNsZXMgb2YgaW5jb3Jwb3JhdGlvbiAoY29ycG9yYXRpb24pLCBjZXJ0aWZpY2F0ZSBvZiBvcmdhbml6YXRpb24gKExMQyksIG9yIGEgYnVzaW5lc3MgY2VydGlmaWNhdGUsIHdoaWNoIGNhbiBiZSBvYnRhaW5lZCB0aHJvdWdoIHRoZSBCb3N0b24gQ2l0eSBDbGVyayYjMzk7cyBPZmZpY2UgaWYgeW91ciBidXNpbmVzcyBpcyBsb2NhdGVkIGluIEJvc3Rvbi4iO319czo5OiJodG1sVGl0bGUiO3M6NDA6IkdldCBZb3VyIEJ1c2luZXNzIENlcnRpZmllZCB8IEJvc3Rvbi5nb3YiO319fWk6MTthOjI6e3M6MjoiaWQiO3M6MzI6Ijg5MDA3N2EwNjEyZjVkY2U3NDEyZWQ0YmY3OWI0MjcwIjtzOjg6ImRvY3VtZW50IjthOjM6e3M6NDoibmFtZSI7czoxNjk6InByb2plY3RzLzczODMxMzE3Mjc4OC9sb2NhdGlvbnMvZ2xvYmFsL2NvbGxlY3Rpb25zL2RlZmF1bHRfY29sbGVjdGlvbi9kYXRhU3RvcmVzL29lb2ktcGlsb3QtZGF0YXN0b3JlXzE3MjYyNjU3OTU5MTAvYnJhbmNoZXMvMC9kb2N1bWVudHMvODkwMDc3YTA2MTJmNWRjZTc0MTJlZDRiZjc5YjQyNzAiO3M6MjoiaWQiO3M6MzI6Ijg5MDA3N2EwNjEyZjVkY2U3NDEyZWQ0YmY3OWI0MjcwIjtzOjE3OiJkZXJpdmVkU3RydWN0RGF0YSI7YTo3OntzOjk6Imh0bWxUaXRsZSI7czo0NzoiU3RyZWV0IFZlbmRpbmcgR2VuZXJhbCBJbmZvcm1hdGlvbiB8IEJvc3Rvbi5nb3YiO3M6NToidGl0bGUiO3M6NDc6IlN0cmVldCBWZW5kaW5nIEdlbmVyYWwgSW5mb3JtYXRpb24gfCBCb3N0b24uZ292IjtzOjExOiJkaXNwbGF5TGluayI7czoxNDoid3d3LmJvc3Rvbi5nb3YiO3M6MTk6ImV4dHJhY3RpdmVfc2VnbWVudHMiO2E6MTp7aTowO2E6MTp7czo3OiJjb250ZW50IjtzOjE4MzA6IkNpdHkgb2YgQm9zdG9uIE1haW4gbWVudSBIZWxwIC8gMzExIEhvbWUgR3VpZGVzIHRvIEJvc3RvbiBEZXBhcnRtZW50cyBQdWJsaWMgTm90aWNlcyBQYXkgYW5kIGFwcGx5IEpvYnMgYW5kIGNhcmVlcnMgQnVzaW5lc3MgU3VwcG9ydCBFdmVudHMgTmV3cyBQbGFjZXMgQmFjayBDZW1ldGVyaWVzIENvbW11bml0eSBjZW50ZXJzIEhpc3RvcmljIERpc3RyaWN0cyBMaWJyYXJpZXMgTmVpZ2hib3Job29kcyBQYXJrcyBhbmQgcGxheWdyb3VuZHMgU2Nob29scyBHb3Zlcm5tZW50IEJhY2sgVGhlIE1heW9yJ3MgT2ZmaWNlIENpdHkgQ2xlcmsgQ2l0eSBDb3VuY2lsIEVsZWN0aW9ucyBCb2FyZHMgYW5kIGNvbW1pc3Npb25zIENpdHkgZ292ZXJubWVudCBvdmVydmlldyBGZWVkYmFjayBUb2dnbGUgTWVudSBCb3N0b24uZ292IE1heW9yIE1pY2hlbGxlIFd1IENpdHkgb2YgQm9zdG9uIFNlYWwgSW5mb3JtYXRpb24gYW5kIFNlcnZpY2VzIFB1YmxpYyBub3RpY2VzIEZlZWRiYWNrIEVuZ2xpc2ggRXNwYcOxb2wgU29vbWFhbGkgUG9ydHVndcOqcyBmcmFuw6dhaXMg566A5L2T5Lit5paHIFZpZXcgRGlzY2xhaW1lciBFc3Bhw7FvbCBLcmV5w7JsIGF5aXN5ZW4gUG9ydHVndcOqcyBmcmFuw6dhaXMg566A5L2T5Lit5paHIFRp4bq/bmcgVmnhu4d0INCg0YPRgdGB0LrQuNC5IFNvb21hYWxpINin2YTYudix2KjZitipIEFmcmlrYWFucyBzaHFpcCDhiqDhiJvhiK3hipsg2KfZhNi52LHYqNmK2Kkg1bDVodW11aXWgNWl1bYg2KLYsNix2KjYp9uM2KzYp9mGINiv24zZhCBFdXNrYXJhINCR0LXQu9Cw0YDRg9GB0LrQsNGPINC80L7QstCwIOCmrOCmvuCmguCmsuCmviDYqNuJ2LPYp9mG2LPZgtmJINCx0YrQu9Cz0LDRgNGB0LrQuCBjYXRhbMOgIEJpbmlzYXlhIENoaWNoZcW1YSDlub/kuJzor50g5buj5p2x6KmxIENvcnN1IEhydmF0c2tpIMSNZcWhdGluYSBkYW5zayBOZWRlcmxhbmRzIEVzcGVyYW50byBlZXN0aSBrZWVsIFBpbGlwaW5vIHN1b21pIGZyYW7Dp2FpcyDFjHN0ZnLDpGlzayBnYWxlZ28g4YOl4YOQ4YOg4YOX4YOj4YOa4YOYIOGDlOGDnOGDkCBEZXV0c2NoIM6VzrvOu863zr3Ouc66zqwg4KqX4KuB4Kqc4Kqw4Kq+4Kqk4KuAIEtyZXnDsmwgYXlpc3llbiDZh9mO2LHZkti02Y7ZhiDZh9mO2YjZktiz2Y4gyrvFjGxlbG8gSGF3YWnKu2kg16LWtNeR16jWtNeZ16og4KS54KS/4KSC4KSm4KWAIEx1cyBIbW9vYiBNYWd5YXIgw61zbGVuc2thIMOBc+G7pcyAc+G7pcyAIMOMZ2LDsiBiYWhhc2EgSW5kb25lc2lhIEdhZWlsZ2UgSXRhbGlhbm8g5pel5pys6KqeINio2KfYs9inINis2KfZiNinIOCyleCyqOCzjeCyqOCyoSDSmtCw0LfQsNKbINGC0ZbQu9GWIOGel+GetuGen+GetuGegeGfkuGemOGfguGemiDtlZzqta3snbgg2qnZiNix2YXYp9mG2KzbjCDQmtGL0YDQs9GL0Lcg0YLQuNC70Lgg4Lql4Lqy4LqnIExpbmd1YSBMYXRpbmEgbGF0dmllxaF1IHZhbG9kYSBsaWV0dXZpxbMga2FsYmEgTMOrdHplYnVlcmdlc2NoINC80LDQutC10LTQvtC90YHQutC4IG1hbGFnYXN5INio2YfYp9izINmF2YTYp9mK2Ygg4LSu4LSy4LSv4LS+4LSz4LSCIE1hbHRpIE3EgW9yaSDgpK7gpLDgpL7gpKDgpYAg0LzQvtC90LPQvtC7IOGAmeGAvOGAlOGAuuGAmeGArOGAheGAgOGArOGAuCDgpKjgpYfgpKrgpL7gpLLgpYAgbm9yc2sg2b7amtiq2Ygg2YHYp9ix2LPbjCBQb2xza2llIFBvcnR1Z3XDqnMg4Kiq4Kmw4Kic4Ki+4Kis4KmAIGxpbWJhIHJvbcOibsSDINCg0YPRgdGB0LrQuNC5IEdhZ2FuYSBmYSdhIFPEgW1vYSBHw6BpZGhsaWcg0KHRgNC/0YHQutC4IFNvdGhvIGNoaVNob25hINiz2YbajNmKIOC3g+C3kuC2guC3hOC2vSBzbG92ZW7EjWluYSI7fX1zOjg6InNuaXBwZXRzIjthOjE6e2k6MDthOjI6e3M6MTQ6InNuaXBwZXRfc3RhdHVzIjtzOjc6IlNVQ0NFU1MiO3M6Nzoic25pcHBldCI7czoxODI6Ikhhd2tlcnMgYW5kIFBlZGRsZXJzIExpY2Vuc2UgQW55IDxiPnZlbmRvcjwvYj4gc2VsbGluZyBtZXJjaGFuZGlzZSBpbiA8Yj5Cb3N0b248L2I+IGlzIHJlcXVpcmVkIHRvIGhhdmUgYSBIYXdrZXJzIGFuZCBQZWRkbGVycyBMaWNlbnNlLiBZb3UgY2FuIDxiPmdldDwvYj4gdGhpcyBsaWNlbnNlIGZyb206Jm5ic3A7Li4uIjt9fXM6MTg6ImV4dHJhY3RpdmVfYW5zd2VycyI7YToxOntpOjA7YToxOntzOjc6ImNvbnRlbnQiO3M6ODIxOiJIYXdrZXJzIGFuZCBQZWRkbGVycyBMaWNlbnNlIEFueSB2ZW5kb3Igc2VsbGluZyBtZXJjaGFuZGlzZSBpbiBCb3N0b24gaXMgcmVxdWlyZWQgdG8gaGF2ZSBhIEhhd2tlcnMgYW5kIFBlZGRsZXJzIExpY2Vuc2UuIFlvdSBjYW4gZ2V0IHRoaXMgbGljZW5zZSBmcm9tOiBEaXZpc2lvbiBvZiBQcm9mZXNzaW9uYWwgTGljZW5zdXJlIE9uZSBBc2hidXJ0b24gUGxhY2UgQm9zdG9uLCBNQSAwMjEwOCBIYXdrZXIgYW5kIFBlZGRsZXIgTGljZW5zZSBJbmZvcm1hdGlvbiBTdGF0aW9uYXJ5IFZlbmRpbmcgTGljZW5zZSBWZW5kb3JzIGludGVuZGluZyB0byBzZWxsIGdvb2RzIG9uIGEgcHVibGljIHNpZGV3YWxrIG9yIHByb3BlcnR5IG11c3QgZ2V0IGEgcGVybWl0IGZyb206IERlcGFydG1lbnQgb2YgUHVibGljIFdvcmtzIChEUFcpIDEgQ2l0eSBIYWxsIFBsYXphLCBSb29tIDcxNCBCb3N0b24sIE1BIDAyMjAxIFN0YXRpb25hcnkgVmVuZGluZyBBcHBsaWNhdGlvbiBVc2Ugb2YgUHJlbWlzZXMgUGVybWl0IFNlbGxpbmcgZ29vZHMgb24gcHJpdmF0ZSBwcm9wZXJ0eSB3aWxsIHJlcXVpcmUgYSBVc2Ugb2YgUHJlbWlzZXMgUGVybWl0IGZyb206IEluc3BlY3Rpb25hbCBTZXJ2aWNlcyBEZXBhcnRtZW50IDEwMTAgTWFzc2FjaHVzZXR0cyBBdmUuIEJvc3RvbiwgTUEgMDIxMTggSW5zcGVjdGlvbmFsIFNlcnZpY2VzIERlcGFydG1lbnQgUGVybWl0cyBNYXNzYWNodXNldHRzIFN0YXRlIFNhbml0YXJ5IENvZGUgQXBwbGljYW50cyBtdXN0IGdldCBhIGNvcHkgb2YgdGhlIE1hc3NhY2h1c2V0dHMgU3RhdGUgU2FuaXRhcnkgQ29kZSAxMDVDTVI1OTAuMDAwLiI7fX1zOjQ6ImxpbmsiO3M6OTE6Imh0dHBzOi8vd3d3LmJvc3Rvbi5nb3YvZGVwYXJ0bWVudHMvaW5zcGVjdGlvbmFsLXNlcnZpY2VzL3N0cmVldC12ZW5kaW5nLWdlbmVyYWwtaW5mb3JtYXRpb24iO319fWk6MjthOjI6e3M6MjoiaWQiO3M6MzI6ImNhYjNiZDE0ZGZjYzBhYmE4Y2FlMjBmZTFkNzg0MGU3IjtzOjg6ImRvY3VtZW50IjthOjM6e3M6NDoibmFtZSI7czoxNjk6InByb2plY3RzLzczODMxMzE3Mjc4OC9sb2NhdGlvbnMvZ2xvYmFsL2NvbGxlY3Rpb25zL2RlZmF1bHRfY29sbGVjdGlvbi9kYXRhU3RvcmVzL29lb2ktcGlsb3QtZGF0YXN0b3JlXzE3MjYyNjU3OTU5MTAvYnJhbmNoZXMvMC9kb2N1bWVudHMvY2FiM2JkMTRkZmNjMGFiYThjYWUyMGZlMWQ3ODQwZTciO3M6MjoiaWQiO3M6MzI6ImNhYjNiZDE0ZGZjYzBhYmE4Y2FlMjBmZTFkNzg0MGU3IjtzOjE3OiJkZXJpdmVkU3RydWN0RGF0YSI7YTo3OntzOjU6InRpdGxlIjtzOjUyOiJIb3cgVG8gQXBwbHkgRm9yIEEgQnVzaW5lc3MgQ2VydGlmaWNhdGUgfCBCb3N0b24uZ292IjtzOjQ6ImxpbmsiO3M6NzY6Imh0dHBzOi8vd3d3LmJvc3Rvbi5nb3YvZGVwYXJ0bWVudHMvY2l0eS1jbGVyay9ob3ctYXBwbHktYnVzaW5lc3MtY2VydGlmaWNhdGUiO3M6OToiaHRtbFRpdGxlIjtzOjUyOiJIb3cgVG8gQXBwbHkgRm9yIEEgQnVzaW5lc3MgQ2VydGlmaWNhdGUgfCBCb3N0b24uZ292IjtzOjExOiJkaXNwbGF5TGluayI7czoxNDoid3d3LmJvc3Rvbi5nb3YiO3M6MTk6ImV4dHJhY3RpdmVfc2VnbWVudHMiO2E6MTp7aTowO2E6MTp7czo3OiJjb250ZW50IjtzOjkwNToiUGxlYXNlIG5vdGU6IGlmIHlvdXIgZGViaXQgY2FyZCByZXF1aXJlcyB5b3UgdG8gZW50ZXIgeW91ciBwaW4gdG8gcHJvY2VzcyBhIHBheW1lbnQsIHlvdSBDQU5OT1QgdXNlIGl0IHRvIHBheSB5b3VyIGZlZS4gQXBwbHlpbmcgYnkgbWFpbD8gSWYgeW91IHNlbmQgeW91ciBwYXltZW50IGJ5IG1haWwsIHBsZWFzZSBpbmNsdWRlIGEgY2hlY2sgb3IgbW9uZXkgb3JkZXIgbWFkZSBwYXlhYmxlIHRvIHRoZSBDaXR5IG9mIEJvc3Rvbi4gU3RlcCAyIE1ha2Ugc3VyZSB5b3UgaGF2ZSBhbGwgeW91ciBpbmZvcm1hdGlvbiBGb3Igc29tZSBidXNpbmVzc2VzLCB3ZSByZXF1aXJlIG90aGVyIGRvY3VtZW50cy4gVG8gZmlsZSBhIGJ1c2luZXNzIHJlZ2lzdHJhdGlvbiBhcyBhIGZvb2QgdHJ1Y2sgdmVuZG9yLCB5b3UgbXVzdCBoYXZlIGEgdmFsaWQ6IGhlYWx0aCBwZXJtaXQgZmlyZSBwZXJtaXQgSGF3a2VycyBhbmQgUGVkZGxlcnMgTGljZW5zZSBjb21taXNzYXJ5IGtpdGNoZW4gYWdyZWVtZW50IG9yIGxldHRlciwgYW5kIENlcnRpZmljYXRlIG9mIExpYWJpbGl0eSBJbnN1cmFuY2UuIFRvIGZpbGUgYSBidXNpbmVzcyByZWdpc3RyYXRpb24gZm9yIHNob3J0LXRlcm0gcmVudGFsIGhvdXNpbmcsIHlvdSBtdXN0IGhhdmUgYSByZWdpc3RyYXRpb24gbnVtYmVyIGZyb20gSW5zcGVjdGlvbmFsIFNlcnZpY2VzLiBZb3UnbGwgbmVlZCB0byBnaXZlIHVzIGEgY29weSBvZiB0aGUgcmVnaXN0cmF0aW9uIG51bWJlciBmb3JtLiBZb3UgY2FuIGxlYXJuIG1vcmUgYWJvdXQgc2hvcnQtdGVybSByZW50YWxzIG9ubGluZS4gSWYgeW91IHBsYW4gdG8gb3BlbiBhIGRheWNhcmUgYnVzaW5lc3MsIHlvdSBtdXN0IGdpdmUgdXMgYSBjb3B5IG9mIGEgc3RhdGUtaXNzdWVkIGRheWNhcmUgcHJvdmlkZXIgbGljZW5zZS4iO319czo4OiJzbmlwcGV0cyI7YToxOntpOjA7YToyOntzOjE0OiJzbmlwcGV0X3N0YXR1cyI7czo3OiJTVUNDRVNTIjtzOjc6InNuaXBwZXQiO3M6MTkxOiI8Yj5Cb3N0b248L2I+IGJ1c2luZXNzZXMgbmVlZCB0byA8Yj5nZXQ8L2I+IGEgY2VydGlmaWNhdGUgdGhyb3VnaCB0aGUgPGI+Q2l0eTwvYj4gQ2xlcmsmIzM5O3Mgb2ZmaWNlIC4uLiBUbyBmaWxlIGEgYnVzaW5lc3MgcmVnaXN0cmF0aW9uIGFzIGEgZm9vZCB0cnVjayA8Yj52ZW5kb3I8L2I+LCB5b3UgbXVzdCBoYXZlIGEgdmFsaWQ6LiI7fX1zOjE4OiJleHRyYWN0aXZlX2Fuc3dlcnMiO2E6MTp7aTowO2E6MTp7czo3OiJjb250ZW50IjtzOjM1NzoiTWFpbCB5b3VyIGRvY3VtZW50cywgcGF5bWVudCwgYW5kIGNvbXBsZXRlZCBmb3JtIHRvOiBPZmZpY2Ugb2YgdGhlIENpdHkgQ2xlcmsgMSBDaXR5IEhhbGwgU3F1YXJlLCBSb29tIDYwMSBCb3N0b24sIE1BIDAyMjAxIFVuaXRlZCBTdGF0ZXMgc2hvdyBoaWRlIFJlbmV3IHlvdXIgY2VydGlmaWNhdGUgU3RlcCAxIFByZXBhcmUgeW91ciByZW5ld2FsIGFwcGxpY2F0aW9uIFlvdSBuZWVkIHRvIGdpdmUgdXMgdGhlIG5hbWUgYW5kIGFkZHJlc3Mgb2YgeW91ciBidXNpbmVzcywgYWxvbmcgd2l0aCB0aGUgbmFtZXMgYW5kIGFkZHJlc3NlcyBvZiBhbnkgcGVvcGxlIHdobyBoYXZlIGFuIGludGVyZXN0IGluIHlvdXIgYnVzaW5lc3MuIjt9fX19fWk6MzthOjI6e3M6MjoiaWQiO3M6MzI6IjBmYTg1OWVhYmM0MTFlN2Y1M2Y1ZWIzMzBmZWQyNGVlIjtzOjg6ImRvY3VtZW50IjthOjM6e3M6NDoibmFtZSI7czoxNjk6InByb2plY3RzLzczODMxMzE3Mjc4OC9sb2NhdGlvbnMvZ2xvYmFsL2NvbGxlY3Rpb25zL2RlZmF1bHRfY29sbGVjdGlvbi9kYXRhU3RvcmVzL29lb2ktcGlsb3QtZGF0YXN0b3JlXzE3MjYyNjU3OTU5MTAvYnJhbmNoZXMvMC9kb2N1bWVudHMvMGZhODU5ZWFiYzQxMWU3ZjUzZjVlYjMzMGZlZDI0ZWUiO3M6MjoiaWQiO3M6MzI6IjBmYTg1OWVhYmM0MTFlN2Y1M2Y1ZWIzMzBmZWQyNGVlIjtzOjE3OiJkZXJpdmVkU3RydWN0RGF0YSI7YTo3OntzOjE4OiJleHRyYWN0aXZlX2Fuc3dlcnMiO2E6MTp7aTowO2E6MTp7czo3OiJjb250ZW50IjtzOjI3OToiSWYgeW91IHF1ZXN0aW9ucywgeW91IGNhbiBjb250YWN0IHRoZSBkaXZpc2lvbiBhdCA2MTctNjM1LTUzMDAuIFN0ZXAgMiBDb21wbGV0ZSB0aGUgYXBwbGljYXRpb24gTmV3IHZlbmRvcnMgYW5kIHJldHVybmluZyB2ZW5kb3JzIHNob3VsZCBmaWxsIG91dCBvdXIgZmFybWVycyBtYXJrZXQgdmVuZG9yIHByb2ZpbGUgZm9ybS4gWW91IHdpbGwgbmVlZCB0byBpbmNsdWRlIGFueSByZXF1aXJlZCBkb2N1bWVudHMgd2UgYXNrIGZvciBpbiB0aGUgZm9ybSB3aXRoIHlvdXIgYXBwbGljYXRpb24uIjt9fXM6NToidGl0bGUiO3M6NDk6IkhvdyBUbyBUYWtlIFBhcnQgSW4gQSBGYXJtZXJzIE1hcmtldCB8IEJvc3Rvbi5nb3YiO3M6MTE6ImRpc3BsYXlMaW5rIjtzOjE0OiJ3d3cuYm9zdG9uLmdvdiI7czo4OiJzbmlwcGV0cyI7YToxOntpOjA7YToyOntzOjE0OiJzbmlwcGV0X3N0YXR1cyI7czo3OiJTVUNDRVNTIjtzOjc6InNuaXBwZXQiO3M6MjA1OiI8Yj5Cb3N0b248L2I+LmdvdiBBbiBvZmZpY2lhbCB3ZWJzaXRlIG9mIHRoZSA8Yj5DaXR5IG9mIEJvc3RvbjwvYj4uIC4uLiBhcHBseSBmb3IgYSA8Yj52ZW5kb3I8L2I+IHBlcm1pdC4gWW91IGNhbiBhcHBseSBhcyBhIG5ldyAuLi4gTmV3IDxiPnZlbmRvcnM8L2I+IGFuZCByZXR1cm5pbmcgPGI+dmVuZG9yczwvYj4gc2hvdWxkIGZpbGwgb3V0Jm5ic3A7Li4uIjt9fXM6OToiaHRtbFRpdGxlIjtzOjQ5OiJIb3cgVG8gVGFrZSBQYXJ0IEluIEEgRmFybWVycyBNYXJrZXQgfCBCb3N0b24uZ292IjtzOjQ6ImxpbmsiO3M6NzU6Imh0dHBzOi8vd3d3LmJvc3Rvbi5nb3YvZGVwYXJ0bWVudHMvZm9vZC1hY2Nlc3MvaG93LXRha2UtcGFydC1mYXJtZXJzLW1hcmtldCI7czoxOToiZXh0cmFjdGl2ZV9zZWdtZW50cyI7YToxOntpOjA7YToxOntzOjc6ImNvbnRlbnQiO3M6MTAwMjoiQ09OVEFDVCBCb3N0b24gRmlyZSBEZXBhcnRtZW50IDYxNy0zNDMtMzYyOCBSRUFTT04gSWYgeW91IGhhdmUgdGVudCBzdHJ1Y3R1cmVzLCB0aGV5J2xsIG5lZWQgdG8gYmUgYXBwcm92ZWQgYnkgSW5zcGVjdGlvbmFsIFNlcnZpY2VzIGFuZCB0aGUgRmlyZSBEZXBhcnRtZW50LiBDT05UQUNUIEluc3BlY3Rpb25hbCBTZXJ2aWNlcyBEZXBhcnRtZW50IDYxNy02MzUtNTMwMCBSRUFTT04gWW91IG1heSBuZWVkIGEgbGV0dGVyIG9mIHN1cHBvcnQgZnJvbSBOZWlnaGJvcmhvb2QgU2VydmljZXMuIFlvdSBtYXkgYWxzbyBuZWVkIGEgY29udHJhY3QgZm9yIHdhc3RlIHJlbW92YWwuIENvbnRhY3QgTmVpZ2hib3Job29kIFNlcnZpY2VzIHRvIGZpbmQgb3V0LiBDT05UQUNUIE5laWdoYm9yaG9vZCBTZXJ2aWNlcyA2MTctNjM1LTM0ODUgc2hvdyBoaWRlIEFzIGEgdmVuZG9yIFN0ZXAgMSBCZWZvcmUgeW91IGdldCBzdGFydGVkIElmIHlvdSB3YW50IHRvIHNlbGwgcGFja2FnZWQgb3IgcHJvY2Vzc2VkIGZvb2QgYXQgYSBmYXJtZXJzIG1hcmtldCwgeW91J2xsIG5lZWQgdG8gYXBwbHkgZm9yIGEgdmVuZG9yIHBlcm1pdC4gWW91IGNhbiBhcHBseSBhcyBhIG5ldyBvciByZXR1cm5pbmcgdmVuZG9yLiBJZiB5b3UncmUgc2VsbGluZyBmb29kIGJ5IHdlaWdodCwgcGxlYXNlIGxlYXJuIGFib3V0IHRoZSBydWxlcyBmcm9tIHRoZSBXZWlnaHRzIGFuZCBNZWFzdXJlcyBEaXZpc2lvbi4gSWYgeW91IHF1ZXN0aW9ucywgeW91IGNhbiBjb250YWN0IHRoZSBkaXZpc2lvbiBhdCA2MTctNjM1LTUzMDAuIFN0ZXAgMiBDb21wbGV0ZSB0aGUgYXBwbGljYXRpb24gTmV3IHZlbmRvcnMgYW5kIHJldHVybmluZyB2ZW5kb3JzIHNob3VsZCBmaWxsIG91dCBvdXIgZmFybWVycyBtYXJrZXQgdmVuZG9yIHByb2ZpbGUgZm9ybS4gWW91IHdpbGwgbmVlZCB0byBpbmNsdWRlIGFueSByZXF1aXJlZCBkb2N1bWVudHMgd2UgYXNrIGZvciBpbiB0aGUgZm9ybSB3aXRoIHlvdXIgYXBwbGljYXRpb24uIjt9fX19fWk6NDthOjI6e3M6MjoiaWQiO3M6MzI6ImI0NmM3NTA5YmNlN2ExNTA5OGIxMmQ4YjMxZTRlZjE2IjtzOjg6ImRvY3VtZW50IjthOjM6e3M6NDoibmFtZSI7czoxNjk6InByb2plY3RzLzczODMxMzE3Mjc4OC9sb2NhdGlvbnMvZ2xvYmFsL2NvbGxlY3Rpb25zL2RlZmF1bHRfY29sbGVjdGlvbi9kYXRhU3RvcmVzL29lb2ktcGlsb3QtZGF0YXN0b3JlXzE3MjYyNjU3OTU5MTAvYnJhbmNoZXMvMC9kb2N1bWVudHMvYjQ2Yzc1MDliY2U3YTE1MDk4YjEyZDhiMzFlNGVmMTYiO3M6MjoiaWQiO3M6MzI6ImI0NmM3NTA5YmNlN2ExNTA5OGIxMmQ4YjMxZTRlZjE2IjtzOjE3OiJkZXJpdmVkU3RydWN0RGF0YSI7YTo3OntzOjk6Imh0bWxUaXRsZSI7czo1NToiQXBwbHkgZm9yIHRoZSAyMDI0IEZvb2QgQ2FydCBQaWxvdCBQcm9ncmFtIHwgQm9zdG9uLmdvdiI7czo0OiJsaW5rIjtzOjExMjoiaHR0cHM6Ly93d3cuYm9zdG9uLmdvdi9nb3Zlcm5tZW50L2NhYmluZXRzL2Vjb25vbWljLW9wcG9ydHVuaXR5LWFuZC1pbmNsdXNpb24vYXBwbHktMjAyNC1mb29kLWNhcnQtcGlsb3QtcHJvZ3JhbSI7czoxODoiZXh0cmFjdGl2ZV9hbnN3ZXJzIjthOjE6e2k6MDthOjE6e3M6NzoiY29udGVudCI7czoyNjY6IkJ1c2luZXNzIENlcnRpZmljYXRlIFRvIGZpbGUgYSByZWdpc3RlcmVkIGJ1c2luZXNzIGNlcnRpZmljYXRlIGFzIGEgZm9vZCBjYXJ0IHZlbmRvciwgeW91IG11c3QgaGF2ZSBhIHZhbGlkOiBoZWFsdGggcGVybWl0IGZpcmUgcGVybWl0IEhhd2tlcnMgYW5kIFBlZGRsZXJzIExpY2Vuc2UgY29tbWlzc2FyeSBraXRjaGVuIGFncmVlbWVudCBvciBsZXR0ZXIsIGFuZCBDZXJ0aWZpY2F0ZSBvZiBMaWFiaWxpdHkgSW5zdXJhbmNlLiBUaGUgZmlsaW5nIGZlZSBpcyAkNjUuIjt9fXM6NToidGl0bGUiO3M6NTU6IkFwcGx5IGZvciB0aGUgMjAyNCBGb29kIENhcnQgUGlsb3QgUHJvZ3JhbSB8IEJvc3Rvbi5nb3YiO3M6MTk6ImV4dHJhY3RpdmVfc2VnbWVudHMiO2E6MTp7aTowO2E6MTp7czo3OiJjb250ZW50IjtzOjE4MzA6IkNpdHkgb2YgQm9zdG9uIE1haW4gbWVudSBIZWxwIC8gMzExIEhvbWUgR3VpZGVzIHRvIEJvc3RvbiBEZXBhcnRtZW50cyBQdWJsaWMgTm90aWNlcyBQYXkgYW5kIGFwcGx5IEpvYnMgYW5kIGNhcmVlcnMgQnVzaW5lc3MgU3VwcG9ydCBFdmVudHMgTmV3cyBQbGFjZXMgQmFjayBDZW1ldGVyaWVzIENvbW11bml0eSBjZW50ZXJzIEhpc3RvcmljIERpc3RyaWN0cyBMaWJyYXJpZXMgTmVpZ2hib3Job29kcyBQYXJrcyBhbmQgcGxheWdyb3VuZHMgU2Nob29scyBHb3Zlcm5tZW50IEJhY2sgVGhlIE1heW9yJ3MgT2ZmaWNlIENpdHkgQ2xlcmsgQ2l0eSBDb3VuY2lsIEVsZWN0aW9ucyBCb2FyZHMgYW5kIGNvbW1pc3Npb25zIENpdHkgZ292ZXJubWVudCBvdmVydmlldyBGZWVkYmFjayBUb2dnbGUgTWVudSBCb3N0b24uZ292IE1heW9yIE1pY2hlbGxlIFd1IENpdHkgb2YgQm9zdG9uIFNlYWwgSW5mb3JtYXRpb24gYW5kIFNlcnZpY2VzIFB1YmxpYyBub3RpY2VzIEZlZWRiYWNrIEVuZ2xpc2ggRXNwYcOxb2wgU29vbWFhbGkgUG9ydHVndcOqcyBmcmFuw6dhaXMg566A5L2T5Lit5paHIFZpZXcgRGlzY2xhaW1lciBFc3Bhw7FvbCBLcmV5w7JsIGF5aXN5ZW4gUG9ydHVndcOqcyBmcmFuw6dhaXMg566A5L2T5Lit5paHIFRp4bq/bmcgVmnhu4d0INCg0YPRgdGB0LrQuNC5IFNvb21hYWxpINin2YTYudix2KjZitipIEFmcmlrYWFucyBzaHFpcCDhiqDhiJvhiK3hipsg2KfZhNi52LHYqNmK2Kkg1bDVodW11aXWgNWl1bYg2KLYsNix2KjYp9uM2KzYp9mGINiv24zZhCBFdXNrYXJhINCR0LXQu9Cw0YDRg9GB0LrQsNGPINC80L7QstCwIOCmrOCmvuCmguCmsuCmviDYqNuJ2LPYp9mG2LPZgtmJINCx0YrQu9Cz0LDRgNGB0LrQuCBjYXRhbMOgIEJpbmlzYXlhIENoaWNoZcW1YSDlub/kuJzor50g5buj5p2x6KmxIENvcnN1IEhydmF0c2tpIMSNZcWhdGluYSBkYW5zayBOZWRlcmxhbmRzIEVzcGVyYW50byBlZXN0aSBrZWVsIFBpbGlwaW5vIHN1b21pIGZyYW7Dp2FpcyDFjHN0ZnLDpGlzayBnYWxlZ28g4YOl4YOQ4YOg4YOX4YOj4YOa4YOYIOGDlOGDnOGDkCBEZXV0c2NoIM6VzrvOu863zr3Ouc66zqwg4KqX4KuB4Kqc4Kqw4Kq+4Kqk4KuAIEtyZXnDsmwgYXlpc3llbiDZh9mO2LHZkti02Y7ZhiDZh9mO2YjZktiz2Y4gyrvFjGxlbG8gSGF3YWnKu2kg16LWtNeR16jWtNeZ16og4KS54KS/4KSC4KSm4KWAIEx1cyBIbW9vYiBNYWd5YXIgw61zbGVuc2thIMOBc+G7pcyAc+G7pcyAIMOMZ2LDsiBiYWhhc2EgSW5kb25lc2lhIEdhZWlsZ2UgSXRhbGlhbm8g5pel5pys6KqeINio2KfYs9inINis2KfZiNinIOCyleCyqOCzjeCyqOCyoSDSmtCw0LfQsNKbINGC0ZbQu9GWIOGel+GetuGen+GetuGegeGfkuGemOGfguGemiDtlZzqta3snbgg2qnZiNix2YXYp9mG2KzbjCDQmtGL0YDQs9GL0Lcg0YLQuNC70Lgg4Lql4Lqy4LqnIExpbmd1YSBMYXRpbmEgbGF0dmllxaF1IHZhbG9kYSBsaWV0dXZpxbMga2FsYmEgTMOrdHplYnVlcmdlc2NoINC80LDQutC10LTQvtC90YHQutC4IG1hbGFnYXN5INio2YfYp9izINmF2YTYp9mK2Ygg4LSu4LSy4LSv4LS+4LSz4LSCIE1hbHRpIE3EgW9yaSDgpK7gpLDgpL7gpKDgpYAg0LzQvtC90LPQvtC7IOGAmeGAvOGAlOGAuuGAmeGArOGAheGAgOGArOGAuCDgpKjgpYfgpKrgpL7gpLLgpYAgbm9yc2sg2b7amtiq2Ygg2YHYp9ix2LPbjCBQb2xza2llIFBvcnR1Z3XDqnMg4Kiq4Kmw4Kic4Ki+4Kis4KmAIGxpbWJhIHJvbcOibsSDINCg0YPRgdGB0LrQuNC5IEdhZ2FuYSBmYSdhIFPEgW1vYSBHw6BpZGhsaWcg0KHRgNC/0YHQutC4IFNvdGhvIGNoaVNob25hINiz2YbajNmKIOC3g+C3kuC2guC3hOC2vSBzbG92ZW7EjWluYSI7fX1zOjExOiJkaXNwbGF5TGluayI7czoxNDoid3d3LmJvc3Rvbi5nb3YiO3M6ODoic25pcHBldHMiO2E6MTp7aTowO2E6Mjp7czo3OiJzbmlwcGV0IjtzOjE5OToiVGhpcyBzdW1tZXIsIHRoZSA8Yj5DaXR5IG9mIEJvc3RvbjwvYj4gd2lsbCBvcGVuIHVwIG5ldyBvcHBvcnR1bml0aWVzIGZvciBmb29kIGNhcnRzIGFuZCBtb2JpbGUgPGI+dmVuZG9yczwvYj4gdG8gc2VsbCBvbiA8Yj5Cb3N0b248L2I+IHN0cmVldHMgYW5kIGluIG5laWdoYm9yaG9vZHMgLi4uIDxiPkJlPC9iPiBzdXJlIHRvIHVzZSZuYnNwOy4uLiI7czoxNDoic25pcHBldF9zdGF0dXMiO3M6NzoiU1VDQ0VTUyI7fX19fX19czo5OiJ0b3RhbFNpemUiO2k6MjUzO3M6MTY6ImF0dHJpYnV0aW9uVG9rZW4iO3M6MzE4OiI2Z0h3NlFvTUNJV0pnYmdHRUotcGxxd0RFaVEyTnpBME9ETmxNaTB3TURBd0xUSTJZMkl0WWpNMk1DMDNORGMwTkRZell6QmhPVFVpQjBkRlRrVlNTVU1xcUFHcS1MTXR6cHEwTUtpdXR6RDU5ck10bjlhM0xiZVNyakNWa3NVd3hjdnpGLXVDc1MyZ2liTXRyZml6TFlDeW1pS3J4SW90eE1heE1OdWF0RERlbXJRdzFMS2RGWTJrdEREQzhKNFZvNENYSXM3bXRTX243WWd0MjQtYUlwYmVxQy1ROTdJd3BhNjNNTjZQbWlMb2dyRXRuTmEzTGN1YXRERDg5ck10anI2ZEZkSG10Uy0wa3E0d2c3S2FJdVR0aUMyamliTXRtZDZvTDVDa3REQ3V4SW90eDhheE1MVzNqQzB3QVEiO3M6MTM6Im5leHRQYWdlVG9rZW4iO3M6NzI6IjFrVFl3TTJNMlFETjNRek50QWpOeklXTGlObU55MENNd0FETXRFVFp6Z0ROd2NqTmtvUlR5cXN3UVlBdVFLX2hJc2dFMUVnQyI7czoxODoiZ3VpZGVkU2VhcmNoUmVzdWx0IjthOjI6e3M6MjA6InJlZmluZW1lbnRBdHRyaWJ1dGVzIjtOO3M6MTc6ImZvbGxvd1VwUXVlc3Rpb25zIjthOjU6e2k6MDtzOjQ5OiJIb3cgZG8gSSBnZXQgYSBwZXJtaXQgZm9yIGEgZm9vZCB0cnVjayBpbiBCb3N0b24/IjtpOjE7czo2MjoiSG93IGRvIEkgZ2V0IGNlcnRpZmllZCBhcyBhIG1pbm9yaXR5LW93bmVkIGJ1c2luZXNzIGluIEJvc3Rvbj8iO2k6MjtzOjQ0OiJIb3cgZG8gSSBnZXQgbXkgYnVzaW5lc3MgbGljZW5zZWQgaW4gQm9zdG9uPyI7aTozO3M6MjY6IldoYXQgaXMgdGhlIHZlbmRvciBwb3J0YWw/IjtpOjQ7czo1NDoiSG93IGRvIEkgcmVnaXN0ZXIgbXkgYnVzaW5lc3Mgd2l0aCB0aGUgQ2l0eSBvZiBCb3N0b24/Ijt9fXM6Nzoic3VtbWFyeSI7YTo0OntzOjExOiJzdW1tYXJ5VGV4dCI7czozODc6IlRvIGJlY29tZSBhIHZlbmRvciB3aXRoIHRoZSBDaXR5IG9mIEJvc3RvbiwgeW91IGNhbiBjcmVhdGUgYW4gYWNjb3VudCBvbiB0aGUgU3VwcGxpZXIgUG9ydGFsLiBUaGlzIHdpbGwgYWxsb3cgeW91IHRvIHNlZSBhbmQgYmlkIG9uIENpdHkgY29udHJhY3RzLiBZb3UgY2FuIGFsc28gc2VhcmNoIHRoZSBkYXRhYmFzZSB0byBmaW5kIGNlcnRpZmllZCBkaXZlcnNlIGFuZCBzbWFsbCBidXNpbmVzc2VzIGluIEJvc3Rvbi4gSWYgeW91IGFyZSBpbnRlcmVzdGVkIGluIHNlbGxpbmcgZm9vZCBhdCBhIGZhcm1lcnMgbWFya2V0LCB5b3Ugd2lsbCBuZWVkIHRvIGFwcGx5IGZvciBhIHZlbmRvciBwZXJtaXQuIFlvdSBjYW4gYXBwbHkgYXMgYSBuZXcgb3IgcmV0dXJuaW5nIHZlbmRvci4gCiI7czoxNjoic2FmZXR5QXR0cmlidXRlcyI7YToxOntpOjA7czowOiIiO31zOjE5OiJzdW1tYXJ5V2l0aE1ldGFkYXRhIjthOjM6e3M6Nzoic3VtbWFyeSI7czozODc6IlRvIGJlY29tZSBhIHZlbmRvciB3aXRoIHRoZSBDaXR5IG9mIEJvc3RvbiwgeW91IGNhbiBjcmVhdGUgYW4gYWNjb3VudCBvbiB0aGUgU3VwcGxpZXIgUG9ydGFsLiBUaGlzIHdpbGwgYWxsb3cgeW91IHRvIHNlZSBhbmQgYmlkIG9uIENpdHkgY29udHJhY3RzLiBZb3UgY2FuIGFsc28gc2VhcmNoIHRoZSBkYXRhYmFzZSB0byBmaW5kIGNlcnRpZmllZCBkaXZlcnNlIGFuZCBzbWFsbCBidXNpbmVzc2VzIGluIEJvc3Rvbi4gSWYgeW91IGFyZSBpbnRlcmVzdGVkIGluIHNlbGxpbmcgZm9vZCBhdCBhIGZhcm1lcnMgbWFya2V0LCB5b3Ugd2lsbCBuZWVkIHRvIGFwcGx5IGZvciBhIHZlbmRvciBwZXJtaXQuIFlvdSBjYW4gYXBwbHkgYXMgYSBuZXcgb3IgcmV0dXJuaW5nIHZlbmRvci4gCiI7czoxNjoiY2l0YXRpb25NZXRhZGF0YSI7YToxOntzOjk6ImNpdGF0aW9ucyI7YTo0OntpOjA7YTozOntzOjEwOiJzdGFydEluZGV4IjtpOjA7czo4OiJlbmRJbmRleCI7czozOiIxNDciO3M6Nzoic291cmNlcyI7YToxOntzOjE0OiJyZWZlcmVuY2VJbmRleCI7aTowO319aToxO2E6Mzp7czoxMDoic3RhcnRJbmRleCI7czozOiIxNDgiO3M6ODoiZW5kSW5kZXgiO3M6MzoiMjM4IjtzOjc6InNvdXJjZXMiO2E6MTp7czoxNDoicmVmZXJlbmNlSW5kZXgiO2k6MDt9fWk6MjthOjM6e3M6MTA6InN0YXJ0SW5kZXgiO3M6MzoiMjM5IjtzOjg6ImVuZEluZGV4IjtzOjM6IjM0MSI7czo3OiJzb3VyY2VzIjthOjE6e3M6MTQ6InJlZmVyZW5jZUluZGV4IjtpOjA7fX1pOjM7YTozOntzOjEwOiJzdGFydEluZGV4IjtzOjM6IjM0MiI7czo4OiJlbmRJbmRleCI7czozOiIzODUiO3M6Nzoic291cmNlcyI7YToxOntzOjE0OiJyZWZlcmVuY2VJbmRleCI7aTowO319fX1zOjEwOiJyZWZlcmVuY2VzIjthOjEwOntpOjA7YTo1OntzOjU6InRpdGxlIjtzOjQwOiJHZXQgWW91ciBCdXNpbmVzcyBDZXJ0aWZpZWQgfCBCb3N0b24uZ292IjtzOjg6ImRvY3VtZW50IjtzOjE2OToicHJvamVjdHMvNzM4MzEzMTcyNzg4L2xvY2F0aW9ucy9nbG9iYWwvY29sbGVjdGlvbnMvZGVmYXVsdF9jb2xsZWN0aW9uL2RhdGFTdG9yZXMvb2VvaS1waWxvdC1kYXRhc3RvcmVfMTcyNjI2NTc5NTkxMC9icmFuY2hlcy8wL2RvY3VtZW50cy9iZDdmYjRlOWE1NDRkODI1ZGQ2YmI1YTQ4MjIwYWRhZiI7czozOiJ1cmkiO3M6OTU6Imh0dHBzOi8vd3d3LmJvc3Rvbi5nb3YvZGVwYXJ0bWVudHMvc3VwcGxpZXItYW5kLXdvcmtmb3JjZS1kaXZlcnNpdHkvZ2V0LXlvdXItYnVzaW5lc3MtY2VydGlmaWVkIjtzOjEzOiJjaHVua0NvbnRlbnRzIjthOjI6e3M6NzoiY29udGVudCI7czoxMjQ0OiJUaGlzIGFsbG93cyB5b3UgdG8gZWFzaWx5IHNhdmUgYW5kIHJldHVybiB0byB5b3VyIGFwcGxpY2F0aW9uIHdoaWxlIGl0J3MgaW4gcHJvZ3Jlc3MuIEJFR0lOIE9OTElORSBBUFBMSUNBVElPTiBQbGVhc2Ugbm90ZTogV2UgYWxzbyBzdWdnZXN0IHRoYXQgYmVjb21lIGEgdmVuZG9yIHdpdGggdGhlIENpdHkgb2YgQm9zdG9uIGJ5IGNyZWF0aW5nIGFuIGFjY291bnQgb24gdGhlIFN1cHBsaWVyIFBvcnRhbC4gWW91ciB2ZW5kb3IgYWNjb3VudCB3aWxsIGFsbG93IHlvdSB0byBzZWUgYW5kIGJpZCBvbiBDaXR5IGNvbnRyYWN0cy4gS2VlcCBpbiBtaW5kIFlvdSBjYW4gc2VhcmNoIG91ciBkYXRhYmFzZSB0byBmaW5kIGNlcnRpZmllZCBkaXZlcnNlIGFuZCBzbWFsbCBidXNpbmVzc2VzIGluIEJvc3Rvbi4gV2UgYWxzbyBoYXZlIGEgbGlzdCBvZiBhbGwgb3BlbiBiaWQgcHJvamVjdHMgaW4gdGhlIENpdHkgb2YgQm9zdG9uLiBZb3UgY2FuIGdldCBhIHBhaWQgbWFpbCBzdWJzY3JpcHRpb24sIG9yIHNlZSBhIGxpc3Qgb2YgY3VycmVudCBiaWRzIG9ubGluZS4gUmVsYXRlZCBSZXNvdXJjZXMgUmVsYXRlZCBSZXNvdXJjZXMgSG93IHRvIGFwcGx5IGZvciBhIENpdHkgb2YgQm9zdG9uIGJ1c2luZXNzIGNlcnRpZmljYXRlIFNpZ24gdXAgZm9yIG91ciBuZXdzbGV0dGVyIENvbnRhY3Q6IFN1cHBsaWVyIERpdmVyc2l0eSBTaWduIHVwIGZvciBvdXIgU3VwcGxpZXIgRGl2ZXJzaXR5IG5ld3NsZXR0ZXIgdG8gbGVhcm4gYWJvdXQgdXBjb21pbmcgQ2l0eSBjb250cmFjdGluZyBvcHBvcnR1bml0aWVzLCBldmVudHMsIGFuZCB3b3Jrc2hvcHMuIFlvdXIgRW1haWwgQWRkcmVzcyBaaXAgQ29kZSBHb3RjaGEgU2lnbiBVcCBIYXZlIHF1ZXN0aW9ucz8gQ29udGFjdDogc3VwcGxpZXIgZGl2ZXJzaXR5IHByb2dyYW0gNjE3LTYzNS00NTExIGJ1c2luZXNzY2VydGlmaWNhdGlvbkBib3N0b24uZ292IFpPT00gQ0VSVElGSUNBVElPTiBIT1VSUyBKb2luIG91ciB3ZWVrbHkgTVdCRSBab29tIENlcnRpZmljYXRpb24gSG91cnMsIGV2ZXJ5IFdlZG5lc2RheSBmcm9tIDExIGFtIC0gMSBwbTogam9pbiBjZXJ0aWZpY2F0aW9uIG9mZmljZSBIb3VycyBQcm92aWRlIFlvdXIgRmVlZGJhY2sgQmFjayB0byB0b3AgRm9vdGVyIG1lbnUgUHJpdmFjeSBQb2xpY3kgQ29udGFjdCB1cyBKb2JzIFB1YmxpYyByZWNvcmRzIExhbmd1YWdlIGFuZCBEaXNhYmlsaXR5IEFjY2VzcyBCT1M6MzExIC0gUmVwb3J0IGFuIGlzc3VlICI7czoxNDoicGFnZUlkZW50aWZpZXIiO047fXM6OToiZXh0cmFJbmZvIjthOjE6e3M6MTQ6InJlbGV2YW5jZVNjb3JlIjtkOjAuODt9fWk6MTthOjU6e3M6NToidGl0bGUiO3M6NDk6IkhvdyBUbyBUYWtlIFBhcnQgSW4gQSBGYXJtZXJzIE1hcmtldCB8IEJvc3Rvbi5nb3YiO3M6ODoiZG9jdW1lbnQiO3M6MTY5OiJwcm9qZWN0cy83MzgzMTMxNzI3ODgvbG9jYXRpb25zL2dsb2JhbC9jb2xsZWN0aW9ucy9kZWZhdWx0X2NvbGxlY3Rpb24vZGF0YVN0b3Jlcy9vZW9pLXBpbG90LWRhdGFzdG9yZV8xNzI2MjY1Nzk1OTEwL2JyYW5jaGVzLzAvZG9jdW1lbnRzLzBmYTg1OWVhYmM0MTFlN2Y1M2Y1ZWIzMzBmZWQyNGVlIjtzOjM6InVyaSI7czo3NToiaHR0cHM6Ly93d3cuYm9zdG9uLmdvdi9kZXBhcnRtZW50cy9mb29kLWFjY2Vzcy9ob3ctdGFrZS1wYXJ0LWZhcm1lcnMtbWFya2V0IjtzOjEzOiJjaHVua0NvbnRlbnRzIjthOjI6e3M6NzoiY29udGVudCI7czoxMTk1OiJDT05UQUNUIENvbnN1bWVyIEFmZmFpcnMgYW5kIExpY2Vuc2luZyA2MTctNjM1LTQxNjUgUkVBU09OIFlvdSdsbCBuZWVkIGEgcGVybWl0IGlmIHlvdSBwbGFuIHRvIHVzZSBhIHBvcnRhYmxlIGdlbmVyYXRvci4gWW91IG1heSBhbHNvIG5lZWQgYSBwZXJtaXQgaWYgeW91IHBsYW4gdG8gaG9sZCBjb29raW5nIGRlbW9uc3RyYXRpb25zLiBDT05UQUNUIEJvc3RvbiBGaXJlIERlcGFydG1lbnQgNjE3LTM0My0zNjI4IFJFQVNPTiBJZiB5b3UgaGF2ZSB0ZW50IHN0cnVjdHVyZXMsIHRoZXknbGwgbmVlZCB0byBiZSBhcHByb3ZlZCBieSBJbnNwZWN0aW9uYWwgU2VydmljZXMgYW5kIHRoZSBGaXJlIERlcGFydG1lbnQuIENPTlRBQ1QgSW5zcGVjdGlvbmFsIFNlcnZpY2VzIERlcGFydG1lbnQgNjE3LTYzNS01MzAwIFJFQVNPTiBZb3UgbWF5IG5lZWQgYSBsZXR0ZXIgb2Ygc3VwcG9ydCBmcm9tIE5laWdoYm9yaG9vZCBTZXJ2aWNlcy4gWW91IG1heSBhbHNvIG5lZWQgYSBjb250cmFjdCBmb3Igd2FzdGUgcmVtb3ZhbC4gQ29udGFjdCBOZWlnaGJvcmhvb2QgU2VydmljZXMgdG8gZmluZCBvdXQuIENPTlRBQ1QgTmVpZ2hib3Job29kIFNlcnZpY2VzIDYxNy02MzUtMzQ4NSBzaG93IGhpZGUgQXMgYSB2ZW5kb3IgU3RlcCAxIEJlZm9yZSB5b3UgZ2V0IHN0YXJ0ZWQgSWYgeW91IHdhbnQgdG8gc2VsbCBwYWNrYWdlZCBvciBwcm9jZXNzZWQgZm9vZCBhdCBhIGZhcm1lcnMgbWFya2V0LCB5b3UnbGwgbmVlZCB0byBhcHBseSBmb3IgYSB2ZW5kb3IgcGVybWl0LiBZb3UgY2FuIGFwcGx5IGFzIGEgbmV3IG9yIHJldHVybmluZyB2ZW5kb3IuIElmIHlvdSdyZSBzZWxsaW5nIGZvb2QgYnkgd2VpZ2h0LCBwbGVhc2UgbGVhcm4gYWJvdXQgdGhlIHJ1bGVzIGZyb20gdGhlIFdlaWdodHMgYW5kIE1lYXN1cmVzIERpdmlzaW9uLiBJZiB5b3UgcXVlc3Rpb25zLCB5b3UgY2FuIGNvbnRhY3QgdGhlIGRpdmlzaW9uIGF0IDYxNy02MzUtNTMwMC4gU3RlcCAyIENvbXBsZXRlIHRoZSBhcHBsaWNhdGlvbiBOZXcgdmVuZG9ycyBhbmQgcmV0dXJuaW5nIHZlbmRvcnMgc2hvdWxkIGZpbGwgb3V0IG91ciBmYXJtZXJzIG1hcmtldCB2ZW5kb3IgcHJvZmlsZSBmb3JtLiBZb3Ugd2lsbCBuZWVkIHRvIGluY2x1ZGUgYW55IHJlcXVpcmVkIGRvY3VtZW50cyB3ZSBhc2sgZm9yIGluIHRoZSBmb3JtIHdpdGggeW91ciBhcHBsaWNhdGlvbi4gIjtzOjE0OiJwYWdlSWRlbnRpZmllciI7Tjt9czo5OiJleHRyYUluZm8iO2E6MTp7czoxNDoicmVsZXZhbmNlU2NvcmUiO2Q6MC41O319aToyO2E6NTp7czo1OiJ0aXRsZSI7czo1MjoiSG93IFRvIEFwcGx5IEZvciBBIEJ1c2luZXNzIENlcnRpZmljYXRlIHwgQm9zdG9uLmdvdiI7czo4OiJkb2N1bWVudCI7czoxNjk6InByb2plY3RzLzczODMxMzE3Mjc4OC9sb2NhdGlvbnMvZ2xvYmFsL2NvbGxlY3Rpb25zL2RlZmF1bHRfY29sbGVjdGlvbi9kYXRhU3RvcmVzL29lb2ktcGlsb3QtZGF0YXN0b3JlXzE3MjYyNjU3OTU5MTAvYnJhbmNoZXMvMC9kb2N1bWVudHMvY2FiM2JkMTRkZmNjMGFiYThjYWUyMGZlMWQ3ODQwZTciO3M6MzoidXJpIjtzOjc2OiJodHRwczovL3d3dy5ib3N0b24uZ292L2RlcGFydG1lbnRzL2NpdHktY2xlcmsvaG93LWFwcGx5LWJ1c2luZXNzLWNlcnRpZmljYXRlIjtzOjEzOiJjaHVua0NvbnRlbnRzIjthOjI6e3M6NzoiY29udGVudCI7czoxMjIwOiJXZSBhY2NlcHQgY2FzaCwgY3JlZGl0IGNhcmRzLCBwaW5sZXNzIGRlYml0IGNhcmRzLCBhbmQgY2hlY2tzIG9yIG1vbmV5IG9yZGVycyBtYWRlIHBheWFibGUgdG8gdGhlIENpdHkgb2YgQm9zdG9uLiBJZiB5b3UgdXNlIGEgY3JlZGl0IGNhcmQgb3IgcGlubGVzcyBkZWJpdCBjYXJkLCB0aGVyZSBpcyBhIG5vbi1yZWZ1bmRhYmxlIHNlcnZpY2UgZmVlIG9mIDIuNSUgb2YgdGhlIHRvdGFsIHBheW1lbnQsIHdpdGggYSAkMSBtaW5pbXVtLiBUaGlzIGZlZSBpcyBwYWlkIHRvIHRoZSBjYXJkIHByb2Nlc3NvciBhbmQgbm90IGtlcHQgYnkgdGhlIENpdHkuIFBsZWFzZSBub3RlOiBpZiB5b3VyIGRlYml0IGNhcmQgcmVxdWlyZXMgeW91IHRvIGVudGVyIHlvdXIgcGluIHRvIHByb2Nlc3MgYSBwYXltZW50LCB5b3UgQ0FOTk9UIHVzZSBpdCB0byBwYXkgeW91ciBmZWUuIEFwcGx5aW5nIGJ5IG1haWw/IElmIHlvdSBzZW5kIHlvdXIgcGF5bWVudCBieSBtYWlsLCBwbGVhc2UgaW5jbHVkZSBhIGNoZWNrIG9yIG1vbmV5IG9yZGVyIG1hZGUgcGF5YWJsZSB0byB0aGUgQ2l0eSBvZiBCb3N0b24uIFN0ZXAgMiBNYWtlIHN1cmUgeW91IGhhdmUgYWxsIHlvdXIgaW5mb3JtYXRpb24gRm9yIHNvbWUgYnVzaW5lc3Nlcywgd2UgcmVxdWlyZSBvdGhlciBkb2N1bWVudHMuIFRvIGZpbGUgYSBidXNpbmVzcyByZWdpc3RyYXRpb24gYXMgYSBmb29kIHRydWNrIHZlbmRvciwgeW91IG11c3QgaGF2ZSBhIHZhbGlkOiBoZWFsdGggcGVybWl0IGZpcmUgcGVybWl0IEhhd2tlcnMgYW5kIFBlZGRsZXJzIExpY2Vuc2UgY29tbWlzc2FyeSBraXRjaGVuIGFncmVlbWVudCBvciBsZXR0ZXIsIGFuZCBDZXJ0aWZpY2F0ZSBvZiBMaWFiaWxpdHkgSW5zdXJhbmNlLiBUbyBmaWxlIGEgYnVzaW5lc3MgcmVnaXN0cmF0aW9uIGZvciBzaG9ydC10ZXJtIHJlbnRhbCBob3VzaW5nLCB5b3UgbXVzdCBoYXZlIGEgcmVnaXN0cmF0aW9uIG51bWJlciBmcm9tIEluc3BlY3Rpb25hbCBTZXJ2aWNlcy4gWW91J2xsIG5lZWQgdG8gZ2l2ZSB1cyBhIGNvcHkgb2YgdGhlIHJlZ2lzdHJhdGlvbiBudW1iZXIgZm9ybS4gWW91IGNhbiBsZWFybiBtb3JlIGFib3V0IHNob3J0LXRlcm0gcmVudGFscyBvbmxpbmUuIElmIHlvdSBwbGFuIHRvIG9wZW4gYSBkYXljYXJlIGJ1c2luZXNzLCB5b3UgbXVzdCBnaXZlIHVzIGEgY29weSBvZiBhIHN0YXRlLWlzc3VlZCBkYXljYXJlIHByb3ZpZGVyIGxpY2Vuc2UuICI7czoxNDoicGFnZUlkZW50aWZpZXIiO047fXM6OToiZXh0cmFJbmZvIjthOjE6e3M6MTQ6InJlbGV2YW5jZVNjb3JlIjtkOjAuNTt9fWk6MzthOjU6e3M6NToidGl0bGUiO3M6NTI6IkhvdyBUbyBBcHBseSBGb3IgQSBCdXNpbmVzcyBDZXJ0aWZpY2F0ZSB8IEJvc3Rvbi5nb3YiO3M6ODoiZG9jdW1lbnQiO3M6MTY5OiJwcm9qZWN0cy83MzgzMTMxNzI3ODgvbG9jYXRpb25zL2dsb2JhbC9jb2xsZWN0aW9ucy9kZWZhdWx0X2NvbGxlY3Rpb24vZGF0YVN0b3Jlcy9vZW9pLXBpbG90LWRhdGFzdG9yZV8xNzI2MjY1Nzk1OTEwL2JyYW5jaGVzLzAvZG9jdW1lbnRzL2NhYjNiZDE0ZGZjYzBhYmE4Y2FlMjBmZTFkNzg0MGU3IjtzOjM6InVyaSI7czo3NjoiaHR0cHM6Ly93d3cuYm9zdG9uLmdvdi9kZXBhcnRtZW50cy9jaXR5LWNsZXJrL2hvdy1hcHBseS1idXNpbmVzcy1jZXJ0aWZpY2F0ZSI7czoxMzoiY2h1bmtDb250ZW50cyI7YToyOntzOjc6ImNvbnRlbnQiO3M6MTEwNjoiSWYgeW91IHVzZSBhIGNyZWRpdCBjYXJkIG9yIHBpbmxlc3MgZGViaXQgY2FyZCwgdGhlcmUgaXMgYSBub24tcmVmdW5kYWJsZSBzZXJ2aWNlIGZlZSBvZiAyLjUlIG9mIHRoZSB0b3RhbCBwYXltZW50LCB3aXRoIGEgJDEgbWluaW11bS4gVGhpcyBmZWUgaXMgcGFpZCB0byB0aGUgY2FyZCBwcm9jZXNzb3IgYW5kIG5vdCBrZXB0IGJ5IHRoZSBDaXR5LiBQbGVhc2Ugbm90ZTogaWYgeW91ciBkZWJpdCBjYXJkIHJlcXVpcmVzIHlvdSB0byBlbnRlciB5b3VyIHBpbiB0byBwcm9jZXNzIGEgcGF5bWVudCwgeW91IENBTk5PVCB1c2UgaXQgdG8gcGF5IHlvdXIgZmVlLiBBcHBseWluZyBieSBtYWlsPyBJZiB5b3Ugc2VuZCB5b3VyIHBheW1lbnQgYnkgbWFpbCwgcGxlYXNlIGluY2x1ZGUgYSBjaGVjayBvciBtb25leSBvcmRlciBtYWRlIHBheWFibGUgdG8gdGhlIENpdHkgb2YgQm9zdG9uLiBTdGVwIDIgTWFrZSBzdXJlIHlvdSBoYXZlIGFsbCB5b3VyIGluZm9ybWF0aW9uIEZvciBzb21lIGJ1c2luZXNzZXMsIHdlIHJlcXVpcmUgb3RoZXIgZG9jdW1lbnRzLiBUbyBmaWxlIGEgYnVzaW5lc3MgcmVnaXN0cmF0aW9uIGFzIGEgZm9vZCB0cnVjayB2ZW5kb3IsIHlvdSBtdXN0IGhhdmUgYSB2YWxpZDogaGVhbHRoIHBlcm1pdCBmaXJlIHBlcm1pdCBIYXdrZXJzIGFuZCBQZWRkbGVycyBMaWNlbnNlIGNvbW1pc3Nhcnkga2l0Y2hlbiBhZ3JlZW1lbnQgb3IgbGV0dGVyLCBhbmQgQ2VydGlmaWNhdGUgb2YgTGlhYmlsaXR5IEluc3VyYW5jZS4gVG8gZmlsZSBhIGJ1c2luZXNzIHJlZ2lzdHJhdGlvbiBmb3Igc2hvcnQtdGVybSByZW50YWwgaG91c2luZywgeW91IG11c3QgaGF2ZSBhIHJlZ2lzdHJhdGlvbiBudW1iZXIgZnJvbSBJbnNwZWN0aW9uYWwgU2VydmljZXMuIFlvdSdsbCBuZWVkIHRvIGdpdmUgdXMgYSBjb3B5IG9mIHRoZSByZWdpc3RyYXRpb24gbnVtYmVyIGZvcm0uIFlvdSBjYW4gbGVhcm4gbW9yZSBhYm91dCBzaG9ydC10ZXJtIHJlbnRhbHMgb25saW5lLiBJZiB5b3UgcGxhbiB0byBvcGVuIGEgZGF5Y2FyZSBidXNpbmVzcywgeW91IG11c3QgZ2l2ZSB1cyBhIGNvcHkgb2YgYSBzdGF0ZS1pc3N1ZWQgZGF5Y2FyZSBwcm92aWRlciBsaWNlbnNlLiAiO3M6MTQ6InBhZ2VJZGVudGlmaWVyIjtOO31zOjk6ImV4dHJhSW5mbyI7YToxOntzOjE0OiJyZWxldmFuY2VTY29yZSI7ZDowLjQ7fX1pOjQ7YTo1OntzOjU6InRpdGxlIjtzOjQ5OiJIb3cgVG8gVGFrZSBQYXJ0IEluIEEgRmFybWVycyBNYXJrZXQgfCBCb3N0b24uZ292IjtzOjg6ImRvY3VtZW50IjtzOjE2OToicHJvamVjdHMvNzM4MzEzMTcyNzg4L2xvY2F0aW9ucy9nbG9iYWwvY29sbGVjdGlvbnMvZGVmYXVsdF9jb2xsZWN0aW9uL2RhdGFTdG9yZXMvb2VvaS1waWxvdC1kYXRhc3RvcmVfMTcyNjI2NTc5NTkxMC9icmFuY2hlcy8wL2RvY3VtZW50cy8wZmE4NTllYWJjNDExZTdmNTNmNWViMzMwZmVkMjRlZSI7czozOiJ1cmkiO3M6NzU6Imh0dHBzOi8vd3d3LmJvc3Rvbi5nb3YvZGVwYXJ0bWVudHMvZm9vZC1hY2Nlc3MvaG93LXRha2UtcGFydC1mYXJtZXJzLW1hcmtldCI7czoxMzoiY2h1bmtDb250ZW50cyI7YToyOntzOjc6ImNvbnRlbnQiO3M6MTMwNDoiVmVuZG9yIFByb2ZpbGUgRm9ybSBJZiB5b3UgYWxyZWFkeSBmaWxsZWQgb3V0IGEgdmVuZG9yIHByb2ZpbGUgYnV0IHlvdSB3YW50IHRvIHNlbGwgeW91ciBwcm9kdWN0cyBhdCBvdGhlciBmYXJtZXJzIG1hcmtldHMsIHBsZWFzZSBlbWFpbCBUcmFjeSBTZW5lc2NoYWwgYXQgSW5zcGVjdGlvbmFsIFNlcnZpY2VzOiB0cmFjeS5zZW5lc2NoYWxAYm9zdG9uLmdvdiBTdGVwIDMgRmluZCBvdXQgd2hhdCB5b3Ugd2lsbCBoYXZlIHRvIHBheSBCb3N0b24gSW5zcGVjdGlvbmFsIFNlcnZpY2VzIERlcGFydG1lbnQgKElTRCkgY2hhcmdlcyBhICQxMDAgaGVhbHRoIGZlZSBwZXIgRmFybWVycyBNYXJrZXQgdmVuZG9yLiBTdGVwIDQgU3VibWl0IHlvdXIgYXBwbGljYXRpb24gUmV0dXJuIHRoZSBmb3JtIHRvIHRoZSBmYXJtZXJzIG1hcmtldCBtYW5hZ2VyIG9mIHRoZSBsb2NhdGlvbiB3aGVyZSB5b3UgYXJlIGFwcGx5aW5nLiBZb3VyIGFwcGxpY2F0aW9uIHdpbGwgYmUgcHJvY2Vzc2VkIHdpdGggSW5zcGVjdGlvbmFsIFNlcnZpY2VzOiBJbnNwZWN0aW9uYWwgU2VydmljZXMgRGVwYXJ0bWVudCAxMDEwIE1hc3NhY2h1c2V0dHMgQXZlLiwgQm9zdG9uLCBNQSAwMjExOCBPZmZpY2UgaG91cnM6IE1vbmRheSB0aHJvdWdoIEZyaWRheSwgOCBhbSAtIDQgcG0gS2VlcCBpbiBtaW5kIFNlbGxpbmcgd2luZSBZb3UgY2FuIG9ubHkgc2VsbCBib3R0bGVkIHdpbmUgYXQgYSBmYXJtZXJzIG1hcmtldCBpZiB0aGUgbWFya2V0IGlzIG9uIHByaXZhdGUgcHJvcGVydHkuIENhbGwgdGhlIE1hc3NhY2h1c2V0dHMgRGVwYXJ0bWVudCBvZiBBZ3JpY3VsdHVyYWwgUmVzb3VyY2VzIGF0IDYxNy02MjYtMTc1NCBmb3IgbW9yZSBpbmZvcm1hdGlvbi4gU3RhdGUgRm9vZCBQcm90ZWN0aW9uIFByb2dyYW0gVGhlIFN0YXRlIEZvb2QgUHJvdGVjdGlvbiBQcm9ncmFtIGVuc3VyZXMgYSBzYWZlIGFuZCB3aG9sZXNvbWUgZm9vZCBzdXBwbHkgaW4gTWFzc2FjaHVzZXR0cy4gQ29udGFjdCB0aGUgcHJvZ3JhbSBhdCA2MTctOTgzLTY3MTIgb3IgRlBQLkRQSEBzdGF0ZS5tYS51cy4gQ29udGFjdDogRm9vZCBKdXN0aWNlIDYxNy02MzUtMzcxNyBzZW5kIGFuIGVtYWlsIDEgQ2l0eSBIYWxsIFNxdWFyZSBSb29tIDgwNCBCb3N0b24sIE1BIDAyMjAxIFVuaXRlZCBTdGF0ZXMgUHJvdmlkZSBZb3VyIEZlZWRiYWNrIEJhY2sgdG8gdG9wIEZvb3RlciBtZW51IFByaXZhY3kgUG9saWN5IENvbnRhY3QgdXMgSm9icyBQdWJsaWMgcmVjb3JkcyBMYW5ndWFnZSBhbmQgRGlzYWJpbGl0eSBBY2Nlc3MgQk9TOjMxMSAtIFJlcG9ydCBhbiBpc3N1ZSAiO3M6MTQ6InBhZ2VJZGVudGlmaWVyIjtOO31zOjk6ImV4dHJhSW5mbyI7YToxOntzOjE0OiJyZWxldmFuY2VTY29yZSI7ZDowLjM7fX1pOjU7YTo1OntzOjU6InRpdGxlIjtzOjQ5OiJIb3cgVG8gVGFrZSBQYXJ0IEluIEEgRmFybWVycyBNYXJrZXQgfCBCb3N0b24uZ292IjtzOjg6ImRvY3VtZW50IjtzOjE2OToicHJvamVjdHMvNzM4MzEzMTcyNzg4L2xvY2F0aW9ucy9nbG9iYWwvY29sbGVjdGlvbnMvZGVmYXVsdF9jb2xsZWN0aW9uL2RhdGFTdG9yZXMvb2VvaS1waWxvdC1kYXRhc3RvcmVfMTcyNjI2NTc5NTkxMC9icmFuY2hlcy8wL2RvY3VtZW50cy8wZmE4NTllYWJjNDExZTdmNTNmNWViMzMwZmVkMjRlZSI7czozOiJ1cmkiO3M6NzU6Imh0dHBzOi8vd3d3LmJvc3Rvbi5nb3YvZGVwYXJ0bWVudHMvZm9vZC1hY2Nlc3MvaG93LXRha2UtcGFydC1mYXJtZXJzLW1hcmtldCI7czoxMzoiY2h1bmtDb250ZW50cyI7YToyOntzOjc6ImNvbnRlbnQiO3M6MTEyNDoiWW91IG5lZWQgdG8gZmluZCBhIGxvY2F0aW9uIGFuZCBhIG1hbmFnZXIgYmVmb3JlIHlvdSBjYW4gc3RhcnQgYSBmYXJtZXJzIG1hcmtldC4gSWYgeW91IGFyZSBsb29raW5nIHRvIHN0YXJ0IGEgbmV3IGZhcm1lcnMgbWFya2V0IOKAlCBvciByZW5ldyBhbiBleGlzdGluZyBtYXJrZXQg4oCUIHlvdSBuZWVkIHRvIGNvbXBsZXRlIG91ciBtYW5hZ2VyIGZvcm06IEZhcm1lcnMgTWFya2V0IE1hbmFnZXIgRm9ybSBTdGVwIDIgR2l2ZSB1cyB5b3VyIGFwcGxpY2F0aW9uIFlvdSBuZWVkIHRvIHRlbGwgdXMgd2hhdCB0eXBlIG9mIHZlbmRvcnMgeW91IHBsYW4gdG8gaGF2ZSBhdCB5b3VyIG1hcmtldCBhbmQgZ2l2ZSB1cyB0aGVpciB2ZW5kb3IgcHJvZmlsZXMuIFRoZSBmb3JtIGxpc3RzIHdoYXQgb3RoZXIgZG9jdW1lbnRzIHlvdSBtYXkgbmVlZCBnaXZlIHVzIHdpdGggeW91ciBhcHBsaWNhdGlvbi4gWW91IGNhbiBtYWlsIG9yIGJyaW5nIGV2ZXJ5dGhpbmcgdG86IE9mZmljZSBvZiBGb29kIEFjY2VzcyAxIENpdHkgSGFsbCBTcXVhcmUsIFJvb20gODA2LCBCb3N0b24sIE1BIDAyMjAxIE9mZmljZSBob3VyczogTW9uZGF5IHRocm91Z2ggRnJpZGF5LCA5IGFtIC0gNSBwbSBTdGVwIDMgR2V0IGFueSBzcGVjaWFsIHBlcm1pdHMgeW91IG1heSBuZWVkIEFmdGVyIHlvdSBzdWJtaXQgeW91ciBhcHBsaWNhdGlvbiwgd2UnbGwgdGVsbCB5b3UgaWYgeW91IG5lZWQgdG8gZ2V0IGFueSBtb3JlIHBlcm1pdHMuIFlvdSBtYXkgbmVlZCB0byBnZXQgcGVybWl0cyBmb3IgdGhlIHNwZWNpYWwgc2l0dWF0aW9ucyBsaXN0ZWQgYmVsb3c6IFJFQVNPTiBDT05UQUNUIFJFQVNPTiBZb3UgbmVlZCBhIFB1YmxpYyBXYXlzIHBlcm1pdCBpZiB5b3VyIGZhcm1lcnMgbWFya2V0IGlzIG9uIGEgc2lkZXdhbGsuIENPTlRBQ1QgUHVibGljIFdvcmtzIDYxNy02MzUtNDkwMCBSRUFTT04gWW91IG5lZWQgYSBQYXJrcyBQZXJtaXQgaWYgdGhlIG1hcmtldCBpcyBpbiBhIENpdHkgcGFyay4gQ09OVEFDVCBQYXJrcyBEZXBhcnRtZW50IDYxNy02MzUtNDUwNSBSRUFTT04gSWYgeW91IHBsYW4gdG8gaGF2ZSBhbXBsaWZpZWQgbXVzaWMgcGxheWluZywgeW91IG5lZWQgYW4gZW50ZXJ0YWlubWVudCBsaWNlbnNlLiAiO3M6MTQ6InBhZ2VJZGVudGlmaWVyIjtOO31zOjk6ImV4dHJhSW5mbyI7YToxOntzOjE0OiJyZWxldmFuY2VTY29yZSI7ZDowLjM7fX1pOjY7YTo1OntzOjU6InRpdGxlIjtzOjQwOiJHZXQgWW91ciBCdXNpbmVzcyBDZXJ0aWZpZWQgfCBCb3N0b24uZ292IjtzOjg6ImRvY3VtZW50IjtzOjE2OToicHJvamVjdHMvNzM4MzEzMTcyNzg4L2xvY2F0aW9ucy9nbG9iYWwvY29sbGVjdGlvbnMvZGVmYXVsdF9jb2xsZWN0aW9uL2RhdGFTdG9yZXMvb2VvaS1waWxvdC1kYXRhc3RvcmVfMTcyNjI2NTc5NTkxMC9icmFuY2hlcy8wL2RvY3VtZW50cy9iZDdmYjRlOWE1NDRkODI1ZGQ2YmI1YTQ4MjIwYWRhZiI7czozOiJ1cmkiO3M6OTU6Imh0dHBzOi8vd3d3LmJvc3Rvbi5nb3YvZGVwYXJ0bWVudHMvc3VwcGxpZXItYW5kLXdvcmtmb3JjZS1kaXZlcnNpdHkvZ2V0LXlvdXItYnVzaW5lc3MtY2VydGlmaWVkIjtzOjEzOiJjaHVua0NvbnRlbnRzIjthOjI6e3M6NzoiY29udGVudCI7czoxOTY3OiLZgtivINmK2KTYr9mKINmH2LDYpyDYpdmE2Ykg2YbYtSDZhdiq2LHYrNmFINi62YrYsSDYr9mC2YrZgiDYjCDYo9mIINij2K7Yt9in2KEg2KPYrtix2Ykg2YHZiiDYp9mE2LXZiNixINmI2KfZhNmF2LjZh9ixINin2YTYudin2YUg2YTZhNi12YHYrdin2Kog2KfZhNmF2KrYsdis2YXYqS4g2YjZhdi5INiw2YTZgyDYjCDZitmF2YPZhtmDINin2YTYpdio2YTYp9i6INi52YYg2KrYsdis2YXYp9iqINi62YrYsSDYtdit2YrYrdipINij2Ygg2K/ZiNmGINin2YTZhdiz2KrZiNmJINin2YTZhdi32YTZiNioINmI2KfZhNmF2LPYp9mH2YXYqSDZgdmKINiq2LHYrNmF2KfYqiDYo9mB2LbZhCDYqNin2LPYqtiu2K/Yp9mFINin2YTYqtix2KzZhdipINmF2YYgR29vZ2xlLiDYo9mI2YTYp9mLINiMINmF2LHYsSDYp9mE2YXYp9mI2LMg2YHZiNmCINij2Yog2YbYtSDZitit2KrZiNmKINi52YTZiSDYrti32KMg2YjYp9mG2YLYsSDYudmE2YrZhy4g2YrYrNioINij2YYg2YrYuNmH2LEg2YXYsdio2Lkg2YXZhtio2KvZgi4g2KjYudivINiw2YTZgyDYjCDYp9mG2YLYsSDZgdmI2YIg4oCc2KfZhNmF2LPYp9mH2YXYqSDYqNiq2LHYrNmF2Kkg2KPZgdi22YTigJwuINin2YbZgtixINmG2YLYsdmL2Kcg2YXYstiv2YjYrNmL2Kcg2YHZiNmCINmF2YbYt9mC2Kkg2KfZhNmG2KfZgdiw2Kkg2KfZhNmF2YbYqNir2YLYqSDYp9mE2KrZiiDYqtmC2YjZhCDigJzYp9mG2YLYsSDZgdmI2YIg2YPZhNmF2Kkg2YTZhNit2LXZiNmEINi52YTZiSDYqtix2KzZhdin2Kog2KjYr9mK2YTYqSDYjCDYo9mIINin2YbZgtixINmG2YLYsdmL2Kcg2YXYstiv2YjYrNmL2Kcg2YTZhNiq2LnYr9mK2YQg2YXYqNin2LTYsdip4oCcLiDZgtmFINio2KXYrNix2KfYoSDYqti52K/ZitmE2KfYqtmDINmF2KjYp9i02LHYqSDYudmE2Ykg2KfZhNmG2LUg2KfZhNmF2YjYrNmI2K8g2YHZiiDZhdix2KjYuSDYp9mE2YbYtS4g2KPYrtmK2LHZi9inINiMINin2LbYuti3INi52YTZiSDZhdiz2KfZh9mF2Kkg2YTZhNmF2LPYp9mH2YXYqSDYqNiq2LnYr9mK2YTYp9iq2YMg2KfZhNmF2YLYqtix2K3YqS4g2YrZhdmD2YYg2KfZhNi52KvZiNixINi52YTZiSDZhdiy2YrYryDZhdmGINin2YTZhdi52YTZiNmF2KfYqiDYrdmI2YQg2KfZhNmF2LPYp9mH2YXYqSDZgdmKINiq2LHYrNmF2KkgR29vZ2xlINmH2YbYpy4g2YrYsdis2Ykg2YXZhNin2K3YuNipINij2YYgRG9JVCDZhNinINiq2KrYrdmD2YUg2YHZiiDYp9mE2LnZhdmE2YrYqSDYp9mE2KrZiiDZitiq2YUg2YXZhiDYrtmE2KfZhNmH2Kcg2K/ZhdisINin2YTYqtix2KzZhdin2Kog2KfZhNmF2LPYp9mH2YXYqSDZgdmKINmF2KrYsdis2YUg2KfZhNmI2YrYqCDZhdmGIEdvb2dsZS4g2LLZhSDZhdiv2YrZhtipINio2YjYs9i32YYg2KjYqtit2LPZitmGINis2YjYr9ipINmI2KfYqtiz2KfYuSDYp9mE2YXYrdiq2YjZiSDZhdiq2LnYr9ivINin2YTZhNi62KfYqiDYudmE2Ykg2YXZiNmC2LnZhtinLiBTZWFyY2ggU2VhcmNoIEdldCBZb3VyIEJ1c2luZXNzIENlcnRpZmllZCBZb3UgYXJlIGhlcmUgSG9tZSDigLogZGVwYXJ0bWVudHMg4oC6IEdldCBZb3VyIEJ1c2luZXNzIENlcnRpZmllZCBMYXN0IHVwZGF0ZWQ6IDUvMzEvMjQgTGVhcm4gbW9yZSBhYm91dCBob3cgdG8gYXBwbHkgZm9yIGNlcnRpZmljYXRpb24gd2l0aCB0aGUgQ2l0eSdzIFN1cHBsaWVyIERpdmVyc2l0eSBQcm9ncmFtLiBzaG93IGhpZGUgT25saW5lIFN0ZXAgMSBCZWZvcmUgeW91IGFwcGx5IG9ubGluZSwgY2hvb3NlIHlvdXIgY2VydGlmaWNhdGlvbiBPdXIgbWlzc2lvbiBpcyB0byBjcmVhdGUgZXF1YWwgb3Bwb3J0dW5pdGllcyBmb3IgYnVzaW5lc3NlcyBvZiBhbGwga2luZHMgaW4gQm9zdG9uLiBBZnRlciB5b3VyIGJ1c2luZXNzIGlzIGNlcnRpZmllZCB3aXRoIG91ciBvZmZpY2UsIHdlJ2xsIGluY2x1ZGUgeW91IGluIGFueSBvdXRyZWFjaCBlZmZvcnRzIHdlIG1ha2UgZm9yIENpdHkgcHJvamVjdHMuICI7czoxNDoicGFnZUlkZW50aWZpZXIiO047fXM6OToiZXh0cmFJbmZvIjthOjE6e3M6MTQ6InJlbGV2YW5jZVNjb3JlIjtkOjAuMzt9fWk6NzthOjU6e3M6NToidGl0bGUiO3M6NDk6IkhvdyBUbyBUYWtlIFBhcnQgSW4gQSBGYXJtZXJzIE1hcmtldCB8IEJvc3Rvbi5nb3YiO3M6ODoiZG9jdW1lbnQiO3M6MTY5OiJwcm9qZWN0cy83MzgzMTMxNzI3ODgvbG9jYXRpb25zL2dsb2JhbC9jb2xsZWN0aW9ucy9kZWZhdWx0X2NvbGxlY3Rpb24vZGF0YVN0b3Jlcy9vZW9pLXBpbG90LWRhdGFzdG9yZV8xNzI2MjY1Nzk1OTEwL2JyYW5jaGVzLzAvZG9jdW1lbnRzLzBmYTg1OWVhYmM0MTFlN2Y1M2Y1ZWIzMzBmZWQyNGVlIjtzOjM6InVyaSI7czo3NToiaHR0cHM6Ly93d3cuYm9zdG9uLmdvdi9kZXBhcnRtZW50cy9mb29kLWFjY2Vzcy9ob3ctdGFrZS1wYXJ0LWZhcm1lcnMtbWFya2V0IjtzOjEzOiJjaHVua0NvbnRlbnRzIjthOjI6e3M6NzoiY29udGVudCI7czoxODU5OiLZgtivINmK2KTYr9mKINmH2LDYpyDYpdmE2Ykg2YbYtSDZhdiq2LHYrNmFINi62YrYsSDYr9mC2YrZgiDYjCDYo9mIINij2K7Yt9in2KEg2KPYrtix2Ykg2YHZiiDYp9mE2LXZiNixINmI2KfZhNmF2LjZh9ixINin2YTYudin2YUg2YTZhNi12YHYrdin2Kog2KfZhNmF2KrYsdis2YXYqS4g2YjZhdi5INiw2YTZgyDYjCDZitmF2YPZhtmDINin2YTYpdio2YTYp9i6INi52YYg2KrYsdis2YXYp9iqINi62YrYsSDYtdit2YrYrdipINij2Ygg2K/ZiNmGINin2YTZhdiz2KrZiNmJINin2YTZhdi32YTZiNioINmI2KfZhNmF2LPYp9mH2YXYqSDZgdmKINiq2LHYrNmF2KfYqiDYo9mB2LbZhCDYqNin2LPYqtiu2K/Yp9mFINin2YTYqtix2KzZhdipINmF2YYgR29vZ2xlLiDYo9mI2YTYp9mLINiMINmF2LHYsSDYp9mE2YXYp9mI2LMg2YHZiNmCINij2Yog2YbYtSDZitit2KrZiNmKINi52YTZiSDYrti32KMg2YjYp9mG2YLYsSDYudmE2YrZhy4g2YrYrNioINij2YYg2YrYuNmH2LEg2YXYsdio2Lkg2YXZhtio2KvZgi4g2KjYudivINiw2YTZgyDYjCDYp9mG2YLYsSDZgdmI2YIg4oCc2KfZhNmF2LPYp9mH2YXYqSDYqNiq2LHYrNmF2Kkg2KPZgdi22YTigJwuINin2YbZgtixINmG2YLYsdmL2Kcg2YXYstiv2YjYrNmL2Kcg2YHZiNmCINmF2YbYt9mC2Kkg2KfZhNmG2KfZgdiw2Kkg2KfZhNmF2YbYqNir2YLYqSDYp9mE2KrZiiDYqtmC2YjZhCDigJzYp9mG2YLYsSDZgdmI2YIg2YPZhNmF2Kkg2YTZhNit2LXZiNmEINi52YTZiSDYqtix2KzZhdin2Kog2KjYr9mK2YTYqSDYjCDYo9mIINin2YbZgtixINmG2YLYsdmL2Kcg2YXYstiv2YjYrNmL2Kcg2YTZhNiq2LnYr9mK2YQg2YXYqNin2LTYsdip4oCcLiDZgtmFINio2KXYrNix2KfYoSDYqti52K/ZitmE2KfYqtmDINmF2KjYp9i02LHYqSDYudmE2Ykg2KfZhNmG2LUg2KfZhNmF2YjYrNmI2K8g2YHZiiDZhdix2KjYuSDYp9mE2YbYtS4g2KPYrtmK2LHZi9inINiMINin2LbYuti3INi52YTZiSDZhdiz2KfZh9mF2Kkg2YTZhNmF2LPYp9mH2YXYqSDYqNiq2LnYr9mK2YTYp9iq2YMg2KfZhNmF2YLYqtix2K3YqS4g2YrZhdmD2YYg2KfZhNi52KvZiNixINi52YTZiSDZhdiy2YrYryDZhdmGINin2YTZhdi52YTZiNmF2KfYqiDYrdmI2YQg2KfZhNmF2LPYp9mH2YXYqSDZgdmKINiq2LHYrNmF2KkgR29vZ2xlINmH2YbYpy4g2YrYsdis2Ykg2YXZhNin2K3YuNipINij2YYgRG9JVCDZhNinINiq2KrYrdmD2YUg2YHZiiDYp9mE2LnZhdmE2YrYqSDYp9mE2KrZiiDZitiq2YUg2YXZhiDYrtmE2KfZhNmH2Kcg2K/ZhdisINin2YTYqtix2KzZhdin2Kog2KfZhNmF2LPYp9mH2YXYqSDZgdmKINmF2KrYsdis2YUg2KfZhNmI2YrYqCDZhdmGIEdvb2dsZS4g2LLZhSDZhdiv2YrZhtipINio2YjYs9i32YYg2KjYqtit2LPZitmGINis2YjYr9ipINmI2KfYqtiz2KfYuSDYp9mE2YXYrdiq2YjZiSDZhdiq2LnYr9ivINin2YTZhNi62KfYqiDYudmE2Ykg2YXZiNmC2LnZhtinLiBTZWFyY2ggU2VhcmNoIEhvdyBUbyBUYWtlIFBhcnQgSW4gQSBGYXJtZXJzIE1hcmtldCBZb3UgYXJlIGhlcmUgSG9tZSDigLogZGVwYXJ0bWVudHMg4oC6IEZvb2QgSnVzdGljZSDigLogSG93IFRvIFRha2UgUGFydCBJbiBBIEZhcm1lcnMgTWFya2V0IExhc3QgdXBkYXRlZDogNC8yNS8yNCBMZWFybiBob3cgdG8gc3RhcnQgYSBmYXJtZXJzIG1hcmtldCwgb3Igam9pbiBhbiBleGlzdGluZyBvbmUgYXMgYSB2ZW5kb3IuIHNob3cgaGlkZSBBcyBhIG1hbmFnZXIgU3RlcCAxIEdldCB5b3VyIGluZm9ybWF0aW9uIHRvZ2V0aGVyIEl0J3MgYSBnb29kIGlkZWEgdG8gbGVhcm4gbW9yZSBhYm91dCB0aGUgcnVsZXMgYW5kIHJlZ3VsYXRpb25zIGZvciBydW5uaW5nIGEgZmFybWVycyBtYXJrZXQuICI7czoxNDoicGFnZUlkZW50aWZpZXIiO047fXM6OToiZXh0cmFJbmZvIjthOjE6e3M6MTQ6InJlbGV2YW5jZVNjb3JlIjtkOjAuMzt9fWk6ODthOjU6e3M6NToidGl0bGUiO3M6NDA6IkdldCBZb3VyIEJ1c2luZXNzIENlcnRpZmllZCB8IEJvc3Rvbi5nb3YiO3M6ODoiZG9jdW1lbnQiO3M6MTY5OiJwcm9qZWN0cy83MzgzMTMxNzI3ODgvbG9jYXRpb25zL2dsb2JhbC9jb2xsZWN0aW9ucy9kZWZhdWx0X2NvbGxlY3Rpb24vZGF0YVN0b3Jlcy9vZW9pLXBpbG90LWRhdGFzdG9yZV8xNzI2MjY1Nzk1OTEwL2JyYW5jaGVzLzAvZG9jdW1lbnRzL2JkN2ZiNGU5YTU0NGQ4MjVkZDZiYjVhNDgyMjBhZGFmIjtzOjM6InVyaSI7czo5NToiaHR0cHM6Ly93d3cuYm9zdG9uLmdvdi9kZXBhcnRtZW50cy9zdXBwbGllci1hbmQtd29ya2ZvcmNlLWRpdmVyc2l0eS9nZXQteW91ci1idXNpbmVzcy1jZXJ0aWZpZWQiO3M6MTM6ImNodW5rQ29udGVudHMiO2E6Mjp7czo3OiJjb250ZW50IjtzOjI2Mjk6ItCeINC/0LXRgNC10LLQvtC00LDRhSDQvdCwIEJvc3Rvbi5nb3Yg0JTQtdC/0LDRgNGC0LDQvNC10L3RgiDQuNC90L3QvtCy0LDRhtC40Lkg0Lgg0YLQtdGF0L3QvtC70L7Qs9C40Lkg0LPQvtGA0L7QtNCwINCR0L7RgdGC0L7QvdCwICjigJxEb0lU4oCcKSDQv9GA0LXQtNC70LDQs9Cw0LXRgiDQv9C10YDQtdCy0L7QtNGLINC60L7QvdGC0LXQvdGC0LAg0L3QsCBCb3N0b24uZ292INGH0LXRgNC10Lcg0LLQtdCxLdC/0LXRgNC10LLQvtC00YfQuNC6IEdvb2dsZSBUcmFuc2xhdGUgKHRyYW5zbGF0ZS5nb29nbGUuY29tKS4g0J/QvtGB0LrQvtC70YzQutGDIEdvb2dsZSBUcmFuc2xhdGUg0Y/QstC70Y/QtdGC0YHRjyDQstC90LXRiNC90LjQvCDQstC10LEt0YHQsNC50YLQvtC8ICwgRG9JVCDQvdC1INC60L7QvdGC0YDQvtC70LjRgNGD0LXRgiDQutCw0YfQtdGB0YLQstC+INC40LvQuCDRgtC+0YfQvdC+0YHRgtGMINC/0LXRgNC10LLQtdC00LXQvdC90L7Qs9C+INC60L7QvdGC0LXQvdGC0LAuINCt0YLQviDQvNC+0LbQtdGCINC/0YDQuNCy0LXRgdGC0Lgg0Log0L3QtdGC0L7Rh9C90L7QvNGDINC/0LXRgNC10LLQtdC00LXQvdC90L7QvNGDINGC0LXQutGB0YLRgyDQuNC70Lgg0LTRgNGD0LPQuNC8INC+0YjQuNCx0LrQsNC8INCyINC40LfQvtCx0YDQsNC20LXQvdC40Y/RhSDQuCDQvtCx0YnQtdC80YMg0LLQuNC00YMg0L/QtdGA0LXQstC10LTQtdC90L3Ri9GFINGB0YLRgNCw0L3QuNGGLiDQntC00L3QsNC60L4g0LLRiyDQvNC+0LbQtdGC0LUg0YHQvtC+0LHRidCw0YLRjCDQviDQvdC10L/RgNCw0LLQuNC70YzQvdGL0YUg0LjQu9C4INC90LXQutCw0YfQtdGB0YLQstC10L3QvdGL0YUg0L/QtdGA0LXQstC+0LTQsNGFINC4INCy0L3QvtGB0LjRgtGMINCx0L7Qu9C10LUg0LrQsNGH0LXRgdGC0LLQtdC90L3Ri9C1INC/0LXRgNC10LLQvtC00Ysg0YEg0L/QvtC80L7RidGM0Y4gR29vZ2xlIFRyYW5zbGF0ZS4g0KHQvdCw0YfQsNC70LAg0L3QsNCy0LXQtNC40YLQtSDQutGD0YDRgdC+0YAg0LzRi9GI0Lgg0Lgg0YnQtdC70LrQvdC40YLQtSDQu9GO0LHQvtC5INGC0LXQutGB0YIsINGB0L7QtNC10YDQttCw0YnQuNC5INC+0YjQuNCx0LrRgy4g0JTQvtC70LbQvdC+INC/0L7Rj9Cy0LjRgtGM0YHRjyDQstGB0L/Qu9GL0LLQsNGO0YnQtdC1INC+0LrQvdC+LiDQlNCw0LvQtdC1INC90LDQttC80LjRgtC1IOKAnNCS0L3QtdGB0YLQuCDQu9GD0YfRiNC40Lkg0L/QtdGA0LXQstC+0LTigJwuINCU0LLQsNC20LTRiyDRidC10LvQutC90LjRgtC1INC+0LHQu9Cw0YHRgtGMINCy0YHQv9C70YvQstCw0Y7RidC10LPQviDQvtC60L3QsCDRgSDQvdCw0LTQv9C40YHRjNGOIOKAnNCp0LXQu9C60L3QuNGC0LUg0YHQu9C+0LLQviDQtNC70Y8g0LDQu9GM0YLQtdGA0L3QsNGC0LjQstC90YvRhSDQv9C10YDQtdCy0L7QtNC+0LIg0LjQu9C4INC00LLQsNC20LTRiyDRidC10LvQutC90LjRgtC1LCDRh9GC0L7QsdGLINC+0YLRgNC10LTQsNC60YLQuNGA0L7QstCw0YLRjCDQvdCw0L/RgNGP0LzRg9GOLuKAnCDQktC90LXRgdC40YLQtSDQuNC30LzQtdC90LXQvdC40Y8g0L3QtdC/0L7RgdGA0LXQtNGB0YLQstC10L3QvdC+INCyINGC0LXQutGB0YIg0LIg0YLQtdC60YHRgtC+0LLQvtC8INC/0L7Qu9C1LiDQndCw0LrQvtC90LXRhiwg0L3QsNC20LzQuNGC0LUgQ29udHJpYnV0ZSDQtNC70Y8g0LLQvdC10YHQtdC90LjRjyDQv9GA0LXQtNC70L7QttC10L3QvdGL0YUg0LLQsNC80Lgg0LjQt9C80LXQvdC10L3QuNC5LiDQlNC+0L/QvtC70L3QuNGC0LXQu9GM0L3Rg9GOINC40L3RhNC+0YDQvNCw0YbQuNGOINC+INGB0L7QtNC10LnRgdGC0LLQuNC4INC/0LXRgNC10LLQvtC00YfQuNC60YMgR29vZ2xlINC80L7QttC90L4g0L3QsNC50YLQuCDQt9C00LXRgdGMLiDQntCx0YDQsNGC0LjRgtC1INCy0L3QuNC80LDQvdC40LUsINGH0YLQviBEb0lUINC90LUg0LrQvtC90YLRgNC+0LvQuNGA0YPQtdGCINC/0YDQvtGG0LXRgdGBLCDRgSDQv9C+0LzQvtGJ0YzRjiDQutC+0YLQvtGA0L7Qs9C+INC/0LXRgNC10LLQvtC00YssINCy0LrQu9GO0YfQtdC90L3Ri9C1INCyINC/0LXRgNC10LLQvtC0LCDQstC60LvRjtGH0LDRjtGC0YHRjyDQsiDQstC10LEt0L/QtdGA0LXQstC+0LTRh9C40LogR29vZ2xlLiDQk9C+0YDQvtC0INCR0L7RgdGC0L7QvSDRgdGC0YDQtdC80LjRgtGB0Y8g0YPQu9GD0YfRiNC40YLRjCDQutCw0YfQtdGB0YLQstC+INC4INGI0LjRgNC+0YLRgyDQvNC90L7Qs9C+0Y/Qt9GL0YfQvdC+0LPQviDQutC+0L3RgtC10L3RgtCwINC90LAg0L3QsNGI0LXQvCDQstC10LEt0YHQsNC50YLQtS4gS3UgU2FhYnNhbiBUYXJqdW1pZGEgYm9nZ2EgQm9zdG9uLmdvdiBNYWdhYWxhZGEgQm9zdG9uIFdhYXhkYSBDdXNib29uZXlzaWludGEgaXlvIFRpa25vbG9qaXlhZGRhICjigJxEb0lU4oCcKSB3YXhheSBiaXhpc2FhIHRhcmp1bWFhZGRhIHdheGEga3VqaXJhIEJvc3Rvbi5nb3YgaXlhZGEgb28gbG9vIG1hcmluYXlvIHR1cmp1YmFhbmthIHdlYnNheWRoa2EgZWUgR29vZ2xlIFRyYW5zbGF0ZSAodHJhbnNsYXRlLmdvb2dsZS5jb20pLiAsIERvSVQgbWEgeHVrdW1hYW4gdGF5YWRhIGFtYSBzYXggYWhhYW50YSB3YXh5YWFiYWhhIGxhIHRhcmp1bWF5LiAiO3M6MTQ6InBhZ2VJZGVudGlmaWVyIjtOO31zOjk6ImV4dHJhSW5mbyI7YToxOntzOjE0OiJyZWxldmFuY2VTY29yZSI7ZDowLjM7fX1pOjk7YTo1OntzOjU6InRpdGxlIjtzOjU1OiJBcHBseSBmb3IgdGhlIDIwMjQgRm9vZCBDYXJ0IFBpbG90IFByb2dyYW0gfCBCb3N0b24uZ292IjtzOjg6ImRvY3VtZW50IjtzOjE2OToicHJvamVjdHMvNzM4MzEzMTcyNzg4L2xvY2F0aW9ucy9nbG9iYWwvY29sbGVjdGlvbnMvZGVmYXVsdF9jb2xsZWN0aW9uL2RhdGFTdG9yZXMvb2VvaS1waWxvdC1kYXRhc3RvcmVfMTcyNjI2NTc5NTkxMC9icmFuY2hlcy8wL2RvY3VtZW50cy9iNDZjNzUwOWJjZTdhMTUwOThiMTJkOGIzMWU0ZWYxNiI7czozOiJ1cmkiO3M6MTEyOiJodHRwczovL3d3dy5ib3N0b24uZ292L2dvdmVybm1lbnQvY2FiaW5ldHMvZWNvbm9taWMtb3Bwb3J0dW5pdHktYW5kLWluY2x1c2lvbi9hcHBseS0yMDI0LWZvb2QtY2FydC1waWxvdC1wcm9ncmFtIjtzOjEzOiJjaHVua0NvbnRlbnRzIjthOjI6e3M6NzoiY29udGVudCI7czoxMTU1OiJTdGVwIDMgUGljayB5b3VyIHZlbmRpbmcgbG9jYXRpb24gUGljayB5b3VyIHZlbmRpbmcgbG9jYXRpb24gYmFzZWQgb24gdGhlIGZvbGxvd2luZyBhdmFpbGFibGUgbG9jYXRpb25zOiBDaXR5IEhhbGwgUGxhemEgKERvd250b3duKSAxIENpdHkgSGFsbCBTcXVhcmUsIEJvc3RvbiwgTUEgMDIyMDMgTHVuY2gsIDExIGFtIHRvIDMgcG0gRGlubmVyLCA0IHBtIHRvIDggcG0gTWNraW0gQnJhbmNoLCBDZW50cmFsIExpYnJhcnkgKEJhY2sgQmF5KSA3MDAgQm95bHN0b24gU3QuIEJvc3RvbiwgTUEgMDIxMTYgTHVuY2gsIDExIGFtIHRvIDMgcG0gRGlubmVyLCA0IHBtIHRvIDggcG0gUGhpbGxpcHMgU3F1YXJlIChDaGluYXRvd24pIDEgSGFycmlzb24gQXZlLCBCb3N0b24sIE1BIDAyMTExIEx1bmNoLCAxMSBhbSB0byAzIHBtIERpbm5lciwgNCBwbSB0byA4IHBtIEFkYW1zIFN0cmVldCBCcmFuY2ggb2YgdGhlIEJvc3RvbiBQdWJsaWMgTGlicmFyeSAoRG9yY2hlc3RlcikgNjkwIEFkYW1zIFN0LCBEb3JjaGVzdGVyLCBNQSAwMjEyMiBMdW5jaCwgMTEgYW0gdG8gMyBwbSBEaW5uZXIsIDQgcG0gdG8gOCBwbSBNYXZlcmljayBTcXVhcmUgKEVhc3QgQm9zdG9uKSA2MyBNYXZlcmljayBTcXVhcmUsIEJvc3RvbiwgTUEgMDIxMjggTHVuY2gsIDExIGFtIHRvIDMgcG0gRGlubmVyLCA0IHBtIHRvIDggcG0gU3RlcCA0IFN0YXJ0IHZlbmRpbmcgT25jZSB5b3UgYXJlIGFwcHJvdmVkLCB5b3UgY2FuIHN0YXJ0IHZlbmRpbmchIEZvciBhbGwgcXVlc3Rpb25zIGFuZCBpbnF1aXJpZXMsIHBsZWFzZSBjb250YWN0OiBXZWxkb24gQm9kcmljayBNb2JpbGUgRW50ZXJwcmlzZSBNYW5hZ2VyIHdlbGRvbi5ib2RyaWNrQGJvc3Rvbi5nb3YgRm9vZCBDYXJ0IFBpbG90IFByb2dyYW0gTG9jYXRpb25zIEZvb2QgQ2FydCBCYWNrZ3JvdW5kIEluZm9ybWF0aW9uIEEgc3RyZWV0IGZvb2QgY2FydCBvcGVyYXRlcyBsaWtlIGEgbW9iaWxlIGtpdGNoZW4uIEl0IGlzIG5vdCBtb3Rvcml6ZWQuIEZvb2QgY2FydHMgYXJlIGxpbWl0ZWQgaW4gdGhlIHR5cGVzIG9mIGZvb2QgdGhleSBzZWxsLiBUaGV5IGNhbiBzZWxsIGNoaWNrZW4ga2Fib2JzLCBzYWxhZHMsIGZhbGFmZWwsIGJ1cnJpdG9zLCBldGMuIGlmIHRoZXkgaGF2ZSBoYW5kd2FzaGluZyBmYWNpbGl0aWVzLiAiO3M6MTQ6InBhZ2VJZGVudGlmaWVyIjtOO31zOjk6ImV4dHJhSW5mbyI7YToxOntzOjE0OiJyZWxldmFuY2VTY29yZSI7ZDowLjM7fX19fXM6OToiZXh0cmFJbmZvIjthOjc6e3M6MjI6InF1ZXJ5VW5kZXJzdGFuZGluZ0luZm8iO2E6MTp7czoyMzoicXVlcnlDbGFzc2lmaWNhdGlvbkluZm8iO2E6MTp7aTowO2E6MTp7czo0OiJ0eXBlIjtzOjE5OiJKQUlMX0JSRUFLSU5HX1FVRVJZIjt9fX1zOjEwOiJhbnN3ZXJOYW1lIjtzOjE2NjoicHJvamVjdHMvNzM4MzEzMTcyNzg4L2xvY2F0aW9ucy9nbG9iYWwvY29sbGVjdGlvbnMvZGVmYXVsdF9jb2xsZWN0aW9uL2VuZ2luZXMvb2VvaS1zZWFyY2gtcGlsb3RfMTcyNjI2NjEyNDM3Ni9zZXNzaW9ucy8xNzU3NjM5MDM3MDk3NzEzMDI2OS9hbnN3ZXJzLzM4MDE2NTA1OTEzMzcwNjgwMiI7czo1OiJzdGVwcyI7YToxOntpOjA7YToyOntzOjU6InN0YXRlIjtzOjk6IlNVQ0NFRURFRCI7czoxMToiZGVzY3JpcHRpb24iO3M6MzA6IlJlcGhyYXNlIHRoZSBxdWVyeSBhbmQgc2VhcmNoLiI7fX1zOjU6InN0YXRlIjtzOjk6IlNVQ0NFRURFRCI7czoxMDoiY3JlYXRlVGltZSI7czowOiIiO3M6MTI6ImNvbXBsZXRlVGltZSI7czowOiIiO3M6MjA6ImFuc3dlclNraXBwZWRSZWFzb25zIjtzOjA6IiI7fX1zOjExOiJzZXNzaW9uSW5mbyI7YTo2OntzOjQ6Im5hbWUiO3M6MTM5OiJwcm9qZWN0cy83MzgzMTMxNzI3ODgvbG9jYXRpb25zL2dsb2JhbC9jb2xsZWN0aW9ucy9kZWZhdWx0X2NvbGxlY3Rpb24vZW5naW5lcy9vZW9pLXNlYXJjaC1waWxvdF8xNzI2MjY2MTI0Mzc2L3Nlc3Npb25zLzE3NTc2MzkwMzcwOTc3MTMwMjY5IjtzOjc6InF1ZXJ5SWQiO3M6Njk6InByb2plY3RzLzczODMxMzE3Mjc4OC9sb2NhdGlvbnMvZ2xvYmFsL3F1ZXN0aW9ucy8xNzU3NjM5MDM3MDk3NzEzMTY5OCI7czo1OiJzdGF0ZSI7czoxMToiSU5fUFJPR1JFU1MiO3M6NToidHVybnMiO2E6MTp7aTowO2E6Mjp7czo1OiJxdWVyeSI7YToyOntzOjc6InF1ZXJ5SWQiO3M6Njk6InByb2plY3RzLzczODMxMzE3Mjc4OC9sb2NhdGlvbnMvZ2xvYmFsL3F1ZXN0aW9ucy8xNzU3NjM5MDM3MDk3NzEzMTY5OCI7czo0OiJ0ZXh0IjtzOjQ5OiJIb3cgZG8gSSBiZWNvbWUgYSB2ZW5kb3Igd2l0aCB0aGUgQ2l0eSBvZiBCb3N0b24/Ijt9czo2OiJhbnN3ZXIiO3M6MTY2OiJwcm9qZWN0cy83MzgzMTMxNzI3ODgvbG9jYXRpb25zL2dsb2JhbC9jb2xsZWN0aW9ucy9kZWZhdWx0X2NvbGxlY3Rpb24vZW5naW5lcy9vZW9pLXNlYXJjaC1waWxvdF8xNzI2MjY2MTI0Mzc2L3Nlc3Npb25zLzE3NTc2MzkwMzcwOTc3MTMwMjY5L2Fuc3dlcnMvMzgwMTY1MDU5MTMzNzA2ODAyIjt9fXM6OToic3RhcnRUaW1lIjtzOjI3OiIyMDI0LTEwLTA0VDE5OjM5OjUxLjQyNjg0N1oiO3M6NzoiZW5kVGltZSI7czoyNzoiMjAyNC0xMC0wNFQxOTozOTo1MS40MjY4NDdaIjt9fX0=");
    return unserialize($a);
  }

}
