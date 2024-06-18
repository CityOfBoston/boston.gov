<?php

namespace Drupal\bos_core;

/**
 * Enumeration of entity event types.
 */
class BosCoreEntityEventType {
  const INSERT = 'event.insert';
  const UPDATE = 'event.update';
  const PRESAVE = 'event.presave';
  const DELETE = 'event.delete';
  const LOAD = "event.load";
}
