<?php

namespace Drupal\bos_swiftype\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class SwiftypeSettingsForm.
 *
 * @package Drupal\bos_swiftype\Form
 */
class SwiftypeSettingsForm extends ConfigFormBase {

  /**
   * Implements getFormId().
   */
  public function getFormId() {
    return 'swiftype_admin_settings';
  }

  /**
   * Implements getEditableConfigNames().
   */
  protected function getEditableConfigNames() {
    return ["bos_swiftype.settings"];
  }

  /**
   * Implements buildForm()
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('bos_swiftype.settings');
    if (!empty($_ENV["bos_swiftype_auth_token"])) {
      $token = substr($_ENV["bos_swiftype_auth_token"], 0, 3);
      $token .= "------" . substr($_ENV["bos_swiftype_auth_token"], 0, 2);
      $class = "";
    }
    else {
      $token = "MISSING - PLEASE SET";
      $class = "error";
    }
    $form = [
      '#tree' => TRUE,
      'swiftype_admin' => [
        '#type' => 'fieldset',
        '#title' => 'Swiftype Search Settings',
        '#description' => 'Configuration for Swiftype Search.',
        'swiftype_key' => [
          '#type' => 'textfield',
          '#title' => t('API KEY / Token'),
          '#disabled' => TRUE,
          '#attributes' => [
            "class" => [$class],
          ],
          '#default_value' => $token,
          '#description' => t('The Swiftype API authentication key (as provided by Swiftype).<br>
            <i><b>Note:</b> This value is stored in the Host Server Environment Variable: "<b>bos_swiftype_auth_token</b>".<br>
             - On local Docker builds this is set in the lando/docker file (<b>DO NOT COMMIT CHANGES TO THE REPO</b>), <br>
             - On Travis this is not needed (unless automated testing is addded) but would be set as a var in the Travis UI,<br>
             - On Acquia this is an Environment Variable loaded on the Acquia Cloud UI.</i>'),
        ],
        'swiftype_engine' => [
          '#type' => 'textfield',
          '#title' => t('Swiftype Engine'),
          '#description' => t('Engine as registered with Swiftype.'),
          '#default_value' => $config->get('swiftype_engine'),
          '#required' => TRUE,
        ],
        'swiftype_endpoint_host' => [
          '#type' => 'textfield',
          '#title' => t('Swiftype Endpoint Host'),
          '#description' => t('The http host (domain) to request from - <i>e.g. https://api.swiftype.com</i>.'),
          '#default_value' => $config->get('swiftype_endpoint_host') ?: "https://api.swiftype.com",
          '#required' => TRUE,
        ],
        'swiftype_endpoint_path' => [
          '#type' => 'textfield',
          '#title' => t('Swiftype Endpoint Path'),
          '#description' => t('The path on the domain to connect to - <i>/api/v1/</i>.'),
          '#default_value' => $config->get('swiftype_endpoint_path') ?: "/api/v1/",
          '#required' => TRUE,
        ],
        'swiftype_email' => [
          '#type' => 'textfield',
          '#title' => t('Email'),
          '#description' => t('[OPTIONAL] Email as registered with Swiftype.'),
          '#default_value' => $config->get('swiftype_email'),
          '#required' => FALSE,
        ],
        'swiftype_password' => [
          '#type' => 'password',
          '#title' => t('Password'),
          '#description' => t('[OPTIONAL] Password as registered with Swiftype.'),
          '#default_value' => $config->get('swiftype_password'),
          '#required' => FALSE,
        ],
        'swiftype_notes' => [
          '#type' => 'textarea',
          '#title' => t('Notes'),
          '#description' => t('Any notes you wish to record with this account.'),
          '#default_value' => $config->get('swiftype_notes'),
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
    $settings = $form_state->getValue('swiftype_admin');

    $this->config('bos_swiftype.settings')
      ->set('swiftype_password', $settings['swiftype_password'])
      ->set('swiftype_email', $settings['swiftype_email'])
      ->set('swiftype_engine', $settings['swiftype_engine'])
      ->set('swiftype_endpoint_host', $settings['swiftype_endpoint_host'])
      ->set('swiftype_endpoint_path', $settings['swiftype_endpoint_path'])
      ->set('swiftype_notes', $settings['swiftype_notes'])
      ->save();
    parent::submitForm($form, $form_state);
  }

}
