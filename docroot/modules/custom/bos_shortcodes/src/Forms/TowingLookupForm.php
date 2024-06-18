<?php

namespace Drupal\bos_shortcodes\Forms;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;

/**
 * Class bos_towing_lookup_form.
 *
 * @package Drupal\bos_shortcodes\Froms\TowingLookupForm
 */
class TowingLookupForm extends FormBase {

  /**
   * Identifies a plugin "parent" for this class.
   *
   * @var string
   *   Identifies a plugin "parent" for this class.
   */
  public static $shortcodePluginId = "Form";

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return "bos_towing_lookup_form";
  }

  /**
   * Returns the plugin "parent" for this class.
   *
   * @return string
   *   The Drupal\bos_shortcodes\Plugin\Shortcode class we expect to be calling
   *   this class.
   */
  public function shortcodePlugin() {
    return $this->shortcodePluginId;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Attach the correct template (must be in templates/ folder and also
    // defined in hook_theme in bos_shortcodes.module).
    $form["#theme"] = 'shortcode_form';
    // Add fields to form.
    $form['wrapper'] = [
      '#type' => 'fieldset',
      '#title' => t('Your License Plate'),
      '#attributes' => [
        'class' => [
          'form__fieldset form__fieldset--inline',
        ],
      ],
      'plate' => [
        '#type' => 'textfield',
        '#attributes' => [
          'class' => ['form__input form__input--inline'],
          'placeholder' => t('Your Plate Number'),
        ],
        '#size' => 10,
        '#maxlength' => 8,
        '#required' => TRUE,
        '#theme_wrappers' => [],
      ],
      'submit_button' => [
        '#type' => 'submit',
        '#value' => t('Submit'),
        '#attributes' => [
          'class' => [
            'form__button',
            'form__button--inline',
            'form__button--chevron',
          ],
        ],
      ],
    ];
    $form['errors'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'license-plate-error',
          'form-error',
          'inline-form-error',
        ],
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    // Check that the license is supplied and valid.
    $regx = "/^[A-Za-z0-9]+$/";
    $plate = $form_state->getValue('plate');
    if (!preg_match($regx, $plate) || strlen($plate) < 3 || strlen($plate) > 8) {
      $form_state->setErrorByName('plate', $this->t("This does not appear to be a valid license plate."));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $options = [
      "query" => [
        "plate" => $form_state->getValue('plate'),
      ],
    ];
    $redirect = Url::fromUri('https://www.cityofboston.gov/towing/search/', $options);
    $form_state->setResponse(new TrustedRedirectResponse($redirect->toString()));
  }

}
