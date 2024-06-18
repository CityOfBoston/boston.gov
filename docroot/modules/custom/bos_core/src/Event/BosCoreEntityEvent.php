<?php

namespace Drupal\bos_core\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Entity\EntityInterface;

/**
 * Class to contain an entity event.
 */
class BosCoreEntityEvent extends Event {

  /**
   * The Entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  private $entity;

  /**
   * The event type.
   *
   * @var \Drupal\bos_core\BosCoreEntityEventType
   */
  private $eventType;

  /**
   * Construct a new entity event.
   *
   * @param string $event_type
   *   The event type.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity which caused the event.
   */
  public function __construct($event_type, EntityInterface $entity) {
    $this->entity = $entity;
    $this->eventType = $event_type;
  }

  /**
   * Method to get the entity from the event.
   */
  public function getEntity() {
    return $this->entity;
  }

  /**
   * Method to get the event type.
   */
  public function getEventType() {
    return $this->eventType;
  }

}
