<?php

namespace Drupal\bos_emergency_alerts;

use Drupal\bos_emergency_alerts\Controller\ApiRouter;
use Drupal\bos_emergency_alerts\Event\EmergencyAlertsBuildFormEvent;
use Drupal\bos_emergency_alerts\Event\EmergencyAlertsSubmitFormEvent;
use Drupal\bos_emergency_alerts\Event\EmergencyAlertsValidateFormEvent;
use Symfony\Component\HttpFoundation\Response;

interface EmergencyAlertsAPISettingsInterface {

  /**
   * This function will be used to inject configuration form elements into the
   * "emergency_alerts_admin_settings" form which is defined in
   * EmergencyAlertsSettingsForm.
   *
   * @param EmergencyAlertsBuildFormEvent $event object containing form and form_state variables.
   * @param string $event_id The ID of the event being raised.
   *
   * @return EmergencyAlertsBuildFormEvent
   */
  public function buildForm(EmergencyAlertsBuildFormEvent $event, string $event_id): EmergencyAlertsBuildFormEvent;

  /**
   * This function will be used to save configuration into
   * bos_emergency_alerts.settings.
   *
   * @param EmergencyAlertsSubmitFormEvent $event object containing form and form_state variables
   * @param string $event_id The ID of the event being raised
   *
   * @return EmergencyAlertsSubmitFormEvent
   */
  public function submitForm(EmergencyAlertsSubmitFormEvent $event, string $event_id): EmergencyAlertsSubmitFormEvent;

  /**
   * This function will be used to validate the form before it is saved.
   * The $event object contains the form_state variable which can be used
   * to signal validation failures as with regular form objects.
   *
   * @param EmergencyAlertsValidateFormEvent $event object containing form and form_state variables
   * @param string $event_id The ID of the event being raised
   *
   * @return EmergencyAlertsValidateFormEvent
   */
  public function validateForm(EmergencyAlertsValidateFormEvent $event, string $event_id): EmergencyAlertsValidateFormEvent;

  public function subscribe(array $request_bag, ApiRouter $router): Response;

}
