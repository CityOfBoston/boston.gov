<?php

namespace Drupal\bos_city_score\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class CityscoreSettingsForm.
 *
 * @package Drupal\city_score\Form
 */
class CityscoreSettingsForm extends ConfigFormBase {

  /**
   * Implements getFormId().
   */
  public function getFormId() {
    return 'city_score_admin_settings';
  }

  /**
   * Implements getEditableConfigNames().
   */
  protected function getEditableConfigNames() {
    return ["bos_city_score.settings"];
  }

  /**
   * Implements buildForm()
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('bos_city_score.settings');
    $form = [
      '#tree' => TRUE,
      'cityscore_admin' => [
        '#type' => 'fieldset',
        '#title' => 'Cityscore API Endpoint',
        '#description' => 'Configuration for Cityscore custom API endpoints.',
        'auth_token' => [
          '#type' => 'textfield',
          '#title' => t('API KEY / Token'),
          '#description' => t('Enter a random string to authenticate API calls.'),
          '#default_value' => $config->get('auth_token'),
          '#required' => FALSE,
        ],
        'ip_whitelist' => [
          '#type' => 'textarea',
          '#title' => t('IP Address Whitelist Filter'),
          '#description' => t('List of valid IP Addresses, one address per line. Leave empty for no IPAddress filtering.'),
          '#default_value' => $config->get('ip_whitelist'),
          '#rows' => 5,
          '#required' => FALSE,
        ],
      ],
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * Implements submitForm().
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $settings = $form_state->getValue('cityscore_admin');

    $this->config('bos_city_score.settings')
      ->set('auth_token', $settings['auth_token'])
      ->set('ip_whitelist', $settings['ip_whitelist'])
      ->save();
    parent::submitForm($form, $form_state);
  }

}
