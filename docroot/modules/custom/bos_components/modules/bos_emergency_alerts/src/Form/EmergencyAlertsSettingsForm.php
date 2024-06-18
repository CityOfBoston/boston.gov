<?php

namespace Drupal\bos_emergency_alerts\Form;

use Drupal\bos_emergency_alerts\Event\EmergencyAlertsBuildFormEvent;
use Drupal\bos_emergency_alerts\Event\EmergencyAlertsSubmitFormEvent;
use Drupal\bos_emergency_alerts\Event\EmergencyAlertsValidateFormEvent;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;

/**
 * Class EmergencyAlertsSettingsForm.
 *
 * @package Drupal\bos_emergency_alerts\Form
 */
class EmergencyAlertsSettingsForm extends ConfigFormBase implements EventDispatcherInterface {

  /**
   * Implements getFormId().
   */
  public function getFormId() {
    return 'emergency_alerts_admin_settings';
  }

  /**
   * Implements getEditableConfigNames().
   */
  protected function getEditableConfigNames() {
    return ["bos_emergency_alerts.settings"];
  }

  /**
   * Implements buildForm()
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('bos_emergency_alerts.settings');
    $config_settings = $config->get('emergency_alerts_settings');

    $form = [
      '#tree' => TRUE,
      'bos_emergency_alerts' => [
        '#type' => 'fieldset',
        '#title' => 'Emergency Alert Subscription Service',
        '#description' => 'Configuration for Emergency Alerts.',
        '#collapsible' => FALSE,

        'emergency_alerts_settings' => [
          '#type' => 'details',
          '#title' => 'API Endpoints',
          '#description' => 'Configuration for connected vendor API\'s.',
          '#open' => TRUE,

          'current_api' => [
            '#type' => "radios",
            '#title' => "Active Vendor",
            '#description' => t('<i>This is the endpoint which is currently in use.</i>'),
            '#description_display' => 'before',
            '#default_value' => $config_settings['current_api'] ?? "",
//            '#ajax' => [
//              'callback' => [$this, 'ajaxChangeAPI'],
//              'wrapper' => "edit-bos-emergency-alerts",
//              'event' => 'click',
//              'progress' => [
//                'type' => 'throbber',
//                'message' => $this->t('Switching API.'),
//              ],
//            ],
            "#options" => [],
          ],
          'api_config' => [],
          'email_alerts' => [
            '#type' => 'textfield',
            '#title' => t('Sign-up Error Alerts'),
            '#description' => t('Enter an email to which module errors will be advised.<br><i>In production, should use a distribution group (e.g. digital-de@boston.gov ) to ensure mails are always delivered</i>'),
            '#default_value' => $config_settings['email_alerts'] ?? "",
            '#required' => FALSE,
            '#attributes' => [
              "placeholder" => 'e.g. digital-dev@boston.gov',
            ],
          ],
        ],
      ],
    ];

    // Send an event which allows all classes which subscribe to the event to
    // update this form before it is finally built.
    $event = new EmergencyAlertsBuildFormEvent($form, $form_state);
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $event_dispatcher->dispatch($event, EmergencyAlertsBuildFormEvent::BUILD_CONFIG_FORM);

    // Now the current_api #options are set - select the current vendor API.
//    $form['bos_emergency_alerts']['emergency_alerts_settings']['current_api']['#default_value'] = $config_settings['current_api'];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Implements submitForm().
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $settings = $form_state->getValue('bos_emergency_alerts');

    $newValues = [
      'current_api' => $settings['emergency_alerts_settings']['current_api'],
      'email_alerts' => $settings['emergency_alerts_settings']['email_alerts'],
    ];
    $config = $this->config('bos_emergency_alerts.settings');
    $config->set('emergency_alerts_settings', $newValues)
      ->save();

    // Send an event which allows all classes which subscribe to the event to
    // save settings on this form.
    $event = new EmergencyAlertsSubmitFormEvent($form, $form_state, $config);
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $event_dispatcher->dispatch($event, EmergencyAlertsSubmitFormEvent::SUBMIT_CONFIG_FORM);

    parent::submitForm($form, $form_state);
  }

  /**
   * @implements validateForm()
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    // Send an event which allows all classes which subscribe to the event to
    // validate their section of this form.
    $event = new EmergencyAlertsValidateFormEvent($form, $form_state);
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $event_dispatcher->dispatch($event, EmergencyAlertsValidateFormEvent::VALIDATE_CONFIG_FORM);

    parent::validateForm($form, $form_state);

  }

  public function dispatch(object $event, string $eventName = NULL): object {
    // TODO: Implement dispatch() method.
  }

  public function ajaxChangeAPI(array &$form, FormStateInterface $form_state): array {
    return $form["bos_emergency_alerts"]["emergency_alerts_settings"];
  }

}
