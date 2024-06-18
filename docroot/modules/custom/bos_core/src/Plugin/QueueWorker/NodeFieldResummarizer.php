<?php

namespace Drupal\bos_core\Plugin\QueueWorker;

use Drupal;
use Drupal\Core\Annotation\QueueWorker;
use Drupal\Core\Queue\DelayedRequeueException;
use Drupal\Core\Queue\QueueWorkerBase;
use Exception;

/**
 * A Node Publisher that publishes nodes on CRON run.
 *
 * @QueueWorker(
 *   id = "node_field_resummarizer",
 *   title = @Translation("Resummarize entity summary fields"),
 *   cron = {"time" = 15}
 * )
 */

class NodeFieldResummarizer extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    // Grabs a node, invliadtes the GC_cache, deletes the existing summary
    // and saves the node.
    $node = Drupal::entityTypeManager()
      ->getStorage('node')
      ->load($data['nid']);
    if (!$node) {
      // The node does not exist, nothing we can do here allow the item to
      // appear to have been sucessfully processed and remove from queue.
      return;
    }
    try {
      $summarizer = Drupal::service("bos_google_cloud.GcTextSummarizer");
      $settings = Drupal::config("bos_core.settings")->get('summarizer');
      $settings = $settings["content_types"][$node->bundle()]["settings"];
      $original_field = $node->get($data['field'])->value;
      if ($data["nocache"] == 1) {
        $summarizer->invalidateCachedSummary($settings["prompt"], $original_field);
      }
      $result = $summarizer->execute([
        "text" => $original_field,
        "prompt" => $settings["prompt"],
        "cache" => [
          "enabled" => TRUE,
          "expiry" => $settings["cache"],
        ],
      ]);
      if ($result && !$summarizer->error()) {
        $node->{$data["field"]}->summary = $this->sanitize($result);
        $node->save();    // Because we have set the summary, it should not be requeried. It is cached anyway.
      }
      else {
        $data["error"] = $summarizer->error() ?: "Summarizer service returned empty result.";
        throw new Exception($data["error"]);
      }
    }
    catch (Exception $e) {
      // Requeue, but delay retrying for 4 hours.
      $data["error"] = $e->getMessage();
      Drupal::logger('bos_core')->error($e->getMessage());
      throw new DelayedRequeueException((60 * 60 * 4), "Node not found");
    }
  }

  private function sanitize(string $text): string {
    $text = strip_tags($text);
    $text = preg_replace("/\s+/", " ", $text);
    $text = preg_replace("/\#/", "", $text);
    $text = trim($text);
    return $text;
  }

}
