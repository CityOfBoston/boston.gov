<?php

/**
 * @file
 * This module holds functions for entity events.
 */

use Drupal\bos_core\BosCoreEntityEventType;
use Drupal\bos_core\Event\BosCoreEntityEvent;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Component\EventDispatcher\Event;


/**
 * Implements hook_ENTITY_TYPE_load().
 */
function bos_core_node_load($entities):void {

  // Create a custom event for entity_presave, and dispatch.
  // NOTE: this is an extension of the contributed entity_events module.
  foreach ($entities as $entity) {
    $event_type = BosCoreEntityEventType::LOAD;
    $event = new BosCoreEntityEvent($event_type, $entity);
    Drupal::service('event_dispatcher')
      ->dispatch($event, $event_type);
  }

}

/**
 * Implements hook_entity_insert().
 *
 * {@inheritdoc}
 */
function bos_core_entity_insert(EntityInterface $entity) {
  $event_type = BosCoreEntityEventType::INSERT;
  $event = new BosCoreEntityEvent($event_type, $entity);
  _bos_core_dispatch($event_type, $event);
}

/**
 * Implements hook_entity_update().
 *
 * { @inheritdoc }
 */
function bos_core_entity_update(EntityInterface $entity) {
  $event_type = BosCoreEntityEventType::UPDATE;
  $event = new BosCoreEntityEvent($event_type, $entity);
  _bos_core_dispatch($event_type, $event);
}

/**
 * Implements hook_entity_presave().
 *
 * { @inheritdoc }
 */
function _bos_core_entity_presave(EntityInterface $entity) {
  $event_type = BosCoreEntityEventType::PRESAVE;
  $event = new BosCoreEntityEvent($event_type, $entity);
  _bos_core_dispatch($event_type, $event);
}

/**
 * Implements hook_entity_delete().
 *
 * {@inheritdoc}
 */
function bos_core_entity_delete(EntityInterface $entity) {
  $event_type = BosCoreEntityEventType::DELETE;
  $event = new BosCoreEntityEvent($event_type, $entity);
  _bos_core_dispatch($event_type, $event);
}

/**
 * Helper method to dispatch an event.
 *
 * @param string $event_type
 *   The event type.
 * @param \Drupal\Component\EventDispatcher\Event $event
 *   The event to dispatch.
 */
function _bos_core_dispatch($event_type, Event $event) {
  /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher */
  $dispatcher = \Drupal::service('event_dispatcher');
  $dispatcher->dispatch($event, $event_type);
}
