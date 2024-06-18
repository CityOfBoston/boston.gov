<?php

namespace Drupal\bos_core\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Config\Config;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class to contain an entity event.
 */
class BosCoreFormEvent extends Event {

  const CONFIG_FORM_BUILD = "buildform";
  const CONFIG_FORM_SUBMIT = "submitform";
  const CONFIG_FORM_VALIDATE = "validateform";

  /**
   * The Entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  private $form;

  /**
   * The Entity.
   *
   * @var FormStateInterface
   */
  private $form_state;

  /**
   * The event type.
   *
   * @var \Drupal\bos_core\BosCoreEntityEventType
   */
  private $eventType;

  /**
   * @var \Drupal\Core\Config\Config $config
   */
  private $config;

  /**
   * Construct a new entity event.
   *
   * @param string $event_type
   *   The event type.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity which caused the event.
   */
  public function __construct($event_type, array $form, FormStateInterface $form_state, Config $config) {
    $this->form = $form;
    $this->form_state = $form_state;
    $this->eventType = $event_type;
    $this->config = $config;
  }

  /**
   * Method to get the form array from the event.
   */
  public function getForm(): array {
    return $this->form;
  }

  public function setForm(array $form): void {
    $this->form = $form;
  }

  /**
   * Method to get the form_state from the event.
   */
  public function getFormState(): FormStateInterface {
    return $this->form_state;
  }
  /**
   * Method to get the event type.
   */
  public function getEventType() {
    return $this->eventType;
  }

  public function getConfig(?string $key = NULL): string {
    if (empty($key)) {
      return $this->config->get();
    }
    return $this->config->get($key) ?? FALSE;
  }

  public function setConfig(string $key, string $value): void {
    $this->config->set($key, $value)->save();
  }

}
