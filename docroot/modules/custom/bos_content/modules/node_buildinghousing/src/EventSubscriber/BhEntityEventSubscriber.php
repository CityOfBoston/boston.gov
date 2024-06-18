<?php

namespace Drupal\node_buildinghousing\EventSubscriber;

use Drupal\bos_core\BosCoreEntityEventType;
use Drupal\bos_core\Event\BosCoreEntityEvent;
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
class BhEntityEventSubscriber implements EventSubscriberInterface {

  /**
   * @inheritDoc
   */
  public static function getSubscribedEvents(): array {
    return [
      BosCoreEntityEventType::LOAD => 'ProjectLoad',
    ];
  }

  /**
   * @param BosCoreEntityEvent $event
   *
   * @return BosCoreEntityEvent
   */
  public function ProjectLoad(BosCoreEntityEvent $event): BosCoreEntityEvent {
    /**
     * @var \Drupal\node\Entity\Node $entity
     */
    $entity = $event->getEntity();

    if ($entity->bundle() == "bh_project") {
      // DIG-4405: Adds a default value for the body when its empty. This
      // forces the body to have a value and therefore exist in the fields
      // when preprocessing.  We can then either ignore it (when default) or
      // overwrite with the value from the bh_update (if any).
      $urlparts = explode("/", \Drupal::request()->getRequestUri());
      $final = array_pop($urlparts);
      if ($final != "edit" && empty($entity->get("body")->value)) {
        // Do not set the default value if we are editing the node.
        $entity->set("body", "default");
      }
    }

    return $event;
  }

}
