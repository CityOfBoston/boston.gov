<?php

namespace Drupal\bos_core\EventSubscriber;

use Drupal;
use Drupal\bos_core\BosCoreEntityEventType;
use Drupal\bos_core\Event\BosCoreEntityEvent;
use Drupal\bos_core\Controllers\Settings\CobSettings;
use Drupal\bos_google_cloud\Services\GcTextSummarizer;
use Drupal\Core\Cache\Cache;
use Exception;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * class GcNodeEventSubscriber (Event Subscriber)
 *
 * Listens to Node operations and updates summary as needed.
 *
 * david 02 2024
 *
 * @file docroot/modules/custom/bos_components/modules/bos_core/src/EventSubscriber/NodeSummarizerSubscriber.php
 *
 */
class NodeSummarizerSubscriber implements EventSubscriberInterface {

  // TODO: Move the cache duration and the prompt into a configuration form
  const prompt = "10w";

  const cache_duration = Cache::PERMANENT;

  private bool $summarizer_presave = FALSE;

  private string $content_type;

  /**
   * @inheritDoc
   */
  public static function getSubscribedEvents(): array {
    return [
      BosCoreEntityEventType::LOAD => 'AiLoad',
      BosCoreEntityEventType::PRESAVE => 'AiPresave',
    ];
  }

  /**
   * @param BosCoreEntityEvent $event
   *
   * @return BosCoreEntityEvent
   */
  public function AiPresave(BosCoreEntityEvent $event): BosCoreEntityEvent {
    /**
     * @var \Drupal\node\Entity\Node $entity
     */
    $entity = $event->getEntity();
    $this->content_type = $entity->bundle();

    // Find the cache duration and prompt settings for this content_type.
    $config = CobSettings::getSettings("", "bos_core", "summarizer")["content_types"] ?? [];
    $prompt = $config[$this->content_type]["settings"]["prompt"] ?? self::prompt;

    if (!$this->summarizer_presave
      && $this->isSummarizeEligible($entity->bundle())) {
      // Only respond to entity|nodes of selected content types.

      // NOTE: The summary can be manually set/changed, and the summary will
      // be retained until the body is changed, at which time the summary will
      // be recalculated.

      $this->summarizer_presave = TRUE;         // To prevent re-running.

      foreach ($config[$this->content_type]["settings"]["fields"] as $field_name => $enabled) {
        if ($enabled) {
          $new_field = $entity->get($field_name)->value;
          $new_field_summary = $entity->get($field_name)->summary;

          if (empty($new_field_summary)) {
            // No existing summary, or the summary has been erased by the user.
            // Generate a new summary.
            $summarizer = Drupal::service("bos_google_cloud.GcTextSummarizer");
          }

          elseif (isset($entity->original)
            && $new_field != $entity->original->get($field_name)->value) {
            // The entity is being changed. Specifically, the body has been changed.
            // Invalidate any cached summary, and then generate a new summary.
            $summarizer = Drupal::service("bos_google_cloud.GcTextSummarizer");
            $original_field = $entity->original->get($field_name)->value;
            $original_field_summary = $entity->original->get($field_name)->summary ?? "";
            $ai_summary = $summarizer->getCachedSummary($prompt, $original_field);
            if ($ai_summary) {
              $summarizer->invalidateCachedSummary($prompt, $original_field);
            }
            // Check the summary is not being manually set by the user.
            if ($original_field_summary != $new_field_summary) {
              // Summary is being manually set or changed, don't replace.
              return $event;
            }
            elseif ($ai_summary && ($ai_summary != $new_field_summary)) {
              // The summary has been generated before, but the provided summary is
              // now different.
              // It must be being manually overwritten, do not replace it.
              return $event;
            }
          }

          if (isset($summarizer)) {
            // If $summarizer is set, need to generate a new summary.
            $result = $this->getSummary($new_field, $summarizer);

            // Check if there were problems, and if not set the summary on the entity
            // in the $event object - it will be saved when the entity is saved.
            if (!$summarizer->error()) {
              $event->getEntity()->{$field_name}->summary = $result;
            }
          }
        }
      }
    }

    return $event;
  }

  /**
   * @param BosCoreEntityEvent $event
   *
   * @return BosCoreEntityEvent
   */
  public function AiLoad(BosCoreEntityEvent $event): BosCoreEntityEvent {
    /**
     * @var \Drupal\node\Entity\Node $entity
     */
    $entity = $event->getEntity();
    $this->content_type = $entity->bundle();

    if ($this->isSummarizeEligible($entity->bundle())) {
      // Only respond to entity|nodes of selected content types.

      $config = CobSettings::getSettings("", "bos_core", "summarizer")["content_types"] ?? [];

      foreach ($config[$this->content_type]["settings"]["fields"] as $field_name => $enabled) {
        if ($enabled) {
          if (empty($entity->{$field_name}->summary)) {
            // Only generate a summary if no summary is present.

            $summarizer = Drupal::service("bos_google_cloud.GcTextSummarizer");
            $field = $entity->{$field_name}->value;
            $summary = $this->getSummary($field, $summarizer);

            // Save the summary for future load events.
            $this->saveSummary($event, $summary);

            // Return the summary for this load event.
            if (!$summarizer->error()) {
              $event->getEntity()->{$field_name}->summary = $summary;
            }
          }
        }
      }
    }

    return $event;
  }

  /**
   * Helper to request summary from Google Cloud AI.
   *
   * @param string $text
   * @param GcTextSummarizer $summarizer
   *
   * @return string
   */
  private function getSummary(string $text, GcTextSummarizer $summarizer): string {
    // Find the cache duration and prompt settings for this content_type.
    $config = CobSettings::getSettings("", "bos_core", "summarizer")["content_types"] ?? [];
    $prompt = $config[$this->content_type]["settings"]["prompt"] ?? self::prompt;
    $cache_duration = $config[$this->content_type]["settings"]["cache"] ?? self::cache_duration;

    // Get the summary
    return $summarizer->execute([
      "text" => $text,
      "prompt" => $prompt,
      "cache" => [
        "enabled" => TRUE,
        "expiry" => $cache_duration,
      ],
    ]);
  }

  /**
   * Save the summary to the Database.
   *
   * @param BosCoreEntityEvent $event
   * @param string $summary
   *
   * @return void
   */
  private function saveSummary(BosCoreEntityEvent $event, string $summary): void {
    $entity = $event->getEntity()->toArray();

    try {
      // Have to do this directly against the DB or else will call iterative
      // load/save events....
      Drupal::database()
        ->update("node__body")
        ->fields(["body_summary" => $summary])
        ->condition("entity_id", $entity["nid"][0]["value"])
        ->execute();
      Drupal::database()
        ->update("node_revision__body")
        ->fields(["body_summary" => $summary])
        ->condition("entity_id", $entity["nid"][0]["value"])
        ->condition("revision_id", $entity["vid"][0]["value"])
        ->execute();
    }
    catch (Exception) {
      // do nothing.
    }
  }

  /**
   * Return True/False if provided content_type is required to be summarized.
   * Eligibility set at /admin/config/system/boston
   *
   * @param string $content_type
   *
   * @return bool
   */
  private function isSummarizeEligible(string $content_type): bool {
    $content_types = CobSettings::getSettings("", "bos_core", "summarizer")["content_types"] ?? [];
    // Only keep enabled content types.
    $content_types = array_filter($content_types, function($value) { return $value["enabled"]; });
    // See if requested content type is in the array.
    return array_key_exists($content_type, $content_types);
  }

  public function nothing($event) {}

}
