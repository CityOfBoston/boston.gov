<?php

namespace Drupal\node_rollcall\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Cache\CacheableJsonResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Downloader class for endpoint.
 *
 */
class Downloader extends ControllerBase {

  public function experiment(string $type): CacheableResponseInterface {

    $query = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->condition('type', "roll_call_dockets");
    $nids = $query->execute();

    // Chunk the array into groups of X elements and process
    $chunks = array_chunk($nids, 100);

    $output = [];
    if ($type == "csv") {
      $output[] = 'COB ID", "Docket Number", "Original Docket Text", "AI Generated Title"';
    }

    // Loop through the chunks and process each.
    foreach ($chunks as $chunk) {

      // Load a batch of nodes.
      $nodes = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->loadMultiple($chunk);

      // Loop through the nodes and process each.
      foreach ($nodes as $node) {
        $body = $node->get('body')->getValue();
        if ($type == "json") {
          $output[] = [
            'COB ID' => $node->id(),
            'Docket Number' => $node->getTitle(),
            'Original Docket Text' => $this->sanitize($body[0]["value"], $type),
            'AI Generated Title' => $this->sanitize($body[0]["summary"], $type),
          ];
        }
        elseif ($type == "csv") {
          $output[] = "{$node->id()}, {$node->getTitle()}, \"{$this->sanitize($body[0]["value"], $type)}\", \"{$this->sanitize($body[0]["summary"], $type)}\"";
        }
      }

      $nodes = NULL;

    }

    // Load the correct response for the type requested.
    if ($type == "json") {
      $response = new CacheableJsonResponse($output, 200);
      $response->headers->set('Content-Type', 'application/json');
    }
    elseif ($type == "csv") {
      $response = new CacheableResponse(implode("\n", $output), 200);
      $response->headers->set('Content-Type', 'text/csv');
    }

    // Make the output an attachment/file
    $response->headers->set('Content-Disposition',
      $response->headers->makeDisposition(
        ResponseHeaderBag::DISPOSITION_ATTACHMENT,
        basename("rollcall_ai_experiment.{$type}")
      )
    );

    // Cache response for this custom URL route for 1 year.
    // This cache gets invalidated when a new record is saved (in
    // node_rollcall_entity_update() and/or node_rollcall_entity_create()
    // in node_rollcall.module.
    $cache_metadata = new CacheableMetadata();
    $cache_metadata->setCacheContexts(['url']);
    $cache_metadata->setCacheMaxAge(60 * 60 * 24 * 365); // 1 year
    $cache_metadata->setCacheTags(["rollcall_ai_experiment.{$type}"]);
    $response->addCacheableDependency($cache_metadata);

    return $response;

  }

  private function sanitize(string $text, string $type): string {
    $text = strip_tags($text);
    $text = str_replace(["\r\n", "\n", "\r"], " ", $text);
    $text = preg_replace('/\s+/', ' ', $text);
    $text = str_replace(["#", "*"], "", $text);
    $text = htmlentities($text, ENT_QUOTES);
    $type == "csv" && $text = str_replace([","], ["&#44;"], $text);
    return trim($text);
  }


}
