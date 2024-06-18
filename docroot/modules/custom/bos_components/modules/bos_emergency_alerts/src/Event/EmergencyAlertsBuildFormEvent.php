<?php

namespace Drupal\bos_emergency_alerts\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Form\FormStateInterface;

class EmergencyAlertsBuildFormEvent extends Event {

  const BUILD_CONFIG_FORM = 'bos_emergency_alerts_build_form';

  public array $form;
  public FormStateInterface $form_state;

  /**
   * Constructs the object.
   *
   */
  public function __construct(array &$form, FormStateInterface $form_state) {
    $this->form = &$form;
    $this->form_state = $form_state;
  }
}
