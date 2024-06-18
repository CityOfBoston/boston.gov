<?php

namespace Drupal\bos_emergency_alerts\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Config\Config;
use Drupal\Core\Form\FormStateInterface;

class EmergencyAlertsSubmitFormEvent extends Event {

  const SUBMIT_CONFIG_FORM = 'bos_emergency_alerts_submit_form';

  public array $form;
  public FormStateInterface $form_state;
  public Config $config;

  /**
   * Constructs the object.
   *
   */
  public function __construct(array &$form, FormStateInterface $form_state, Config $config) {
    $this->form = &$form;
    $this->form_state = $form_state;
    $this->config = $config;
  }

}
