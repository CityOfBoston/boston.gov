<?php

namespace Drupal\bos_geocoder\Form;

use Drupal\bos_core\Controllers\Settings\CobSettings;
use Drupal\bos_geocoder\Controller\BosGeoCoderBase;
use Drupal\bos_geocoder\Services\ArcGisGeocoder;
use Drupal\bos_geocoder\Utility\BosGeoAddress;
use Drupal\bos_google_cloud\Services\GcGeocoder;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;

/*
  class GeocoderConfigForm
  Creates the Administration/Configuration form for bos_geocoder

  david 02 2024
  @file docroot/modules/custom/bos_components/modules/bos_geocoder/src/Form/GeocoderConfigForm.php
*/

class GeocoderConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'bos_geocoder_GeocoderConfigForm';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ["bos_geocoder.settings"];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $config = CobSettings::getSettings("GEOCODER_SETTINGS", "bos_geocoder");

    $envar_list = array_flip($config["config"] ?? []);
    if (empty($envar_list)) {
      $rec_settings = array_intersect_key($config, array_flip($envar_list));
      $note = "<p style='color:red'>Genasys Settings are stored in Drupal config, this is not best practice and not recommended for production sites.</p>
          <p>Please set the <b>GEOCODER_SETTINGS</b> envar to this value:<br><b>" . CobSettings::envar_encode($rec_settings) . "</b></p>";
    }
    else {
      $note = "<b>Some settings are defined in the envar GEOCODER_SETTINGS and cannot be changed using this form.</b> This is best practice - Please change them in the environment.";
    }

    $form = [
      "bos_geocoder" => [
        '#type' => 'fieldset',
        '#title' => 'COB Geocoder Service',
        '#description' => 'Runs geocoding against the City\'s ArcGIS geocoder, and the public Google Geocoder API.',
        '#markup' => $note,
        '#collapsible' => FALSE,
        '#tree' => TRUE,
        'arcgis' => [
          '#type' => 'details',
          '#title' => 'City of Boston ArcGIS Geocoder',
          '#open' => TRUE,
          'base_url' => [
            '#type' => 'textfield',
            '#title' => t('Base URL for geocoder'),
            '#description' => t(''),
            '#default_value' => $config['arcgis']['base_url'] ?? "",
            '#disabled' => array_key_exists("base_url", $envar_list["arcgis"]??[]),
            '#required' => !array_key_exists("base_url", $envar_list["arcgis"]??[]),
            '#attributes' => [
              "placeholder" => 'e.g. https://geocoder.boston.gov/arcgis/rest/services/SAMLocator/GeocodeServer',
            ],
          ],
          'find_location' => [
            '#type' => 'textfield',
            '#title' => t('Endpoint for finding Lat/Long from an address (forward geocode)'),
            '#description' => t('see https://geocoder.boston.gov/arcgis/sdk/rest/index.html#/Find_Address_Candidates/02ss00000015000000/'),
            '#default_value' => $config['arcgis']['find_location'] ?? "",
            '#disabled' => array_key_exists("find_location", $envar_list["arcgis"]??[]),
            '#required' => !array_key_exists("find_location", $envar_list["arcgis"]??[]),
            '#attributes' => [
              "placeholder" => 'e.g. findAddressCandidates',
            ],
          ],
          'find_address' => [
            '#type' => 'textfield',
            '#title' => t('Endpoint for finding address from Lat/Long (reverse geocode)'),
            '#description' => t('see https://geocoder.boston.gov/arcgis/sdk/rest/index.html#/Reverse_Geocode/02ss00000030000000/'),
            '#default_value' => $config['arcgis']['find_address'] ?? "",
            '#disabled' => array_key_exists("find_address", $envar_list["arcgis"]??[]),
            '#required' => !array_key_exists("find_address", $envar_list["arcgis"]??[]),
            '#attributes' => [
              "placeholder" => 'e.g. reverseGeocode',
            ],
          ],
          'test_wrapper' => [
            'test_button' => [
              '#type' => 'button',
              "#value" => t('Test ArcGIS'),
              '#attributes' => [
                'class' => ['button', 'button--primary'],
                'title' => "Test the provided configuration for this service"
              ],
              '#access' => TRUE,
              '#ajax' => [
                'callback' => [$this, 'ajaxTestArcgis'],
                'event' => 'click',
                'wrapper' => 'edit-arcgis-result',
                'disable-refocus' => TRUE,
                'progress' => [
                  'type' => 'throbber',
                ]
              ],
              '#suffix' => '<span id="edit-arcgis-result"></span>',
            ],
          ],

        ],
        'google' => [
          '#type' => 'details',
          '#title' => 'Google API\'s Geocoder',
          '#open' => TRUE,
          'base_url' => [
            '#type' => 'textfield',
            '#title' => t('Base URL for geocoder'),
            '#description' => t(''),
            '#default_value' => $config['google']['base_url'] ?? "",
            '#disabled' => array_key_exists("base_url", $envar_list["google"]??[]),
            '#required' => !array_key_exists("base_url", $envar_list["google"]??[]),
            '#attributes' => [
              "placeholder" => 'e.g. https://maps.googleapis.com',
            ],
          ],
          'find_location' => [
            '#type' => 'textfield',
            '#title' => t('Endpoint for finding Lat/Long from an address (forward geocode)'),
            '#description' => t('see https://developers.google.com/maps/documentation/geocoding'),
            '#default_value' => $config['google']['find_location'] ?? "",
            '#disabled' => array_key_exists("find_location", $envar_list["google"]??[]),
            '#required' => !array_key_exists("find_location", $envar_list["google"]??[]),
            '#attributes' => [
              "placeholder" => 'e.g. maps/api/geocode/json',
            ],
          ],
          'find_address' => [
            '#type' => 'textfield',
            '#title' => t('Endpoint for finding address from Lat/Long (reverse geocode)'),
            '#description' => t('see https://developers.google.com/maps/documentation/geocoding/requests-reverse-geocoding'),
            '#default_value' => $config['google']['find_address'] ?? "",
            '#disabled' => array_key_exists("find_address", $envar_list["google"]??[]),
            '#required' => !array_key_exists("find_address", $envar_list["google"]??[]),
            '#attributes' => [
              "placeholder" => 'e.g. maps/api/geocode/json',
            ],
          ],
          'token' => [
            '#type' => 'textfield',
            '#title' => t('The Google API token'),
            '#description' => t('see https://developers.google.com/maps/documentation/geocoding/get-api-key'),
            '#default_value' => CobSettings::obfuscateToken($config['google']['token'] ?? "", "*", 8,6),
            '#disabled' => array_key_exists("token", $envar_list["google"]??[]),
            '#required' => !array_key_exists("token", $envar_list["google"]??[]),
            '#attributes' => [
              "placeholder" => '',
            ],
          ],
          'test_wrapper' => [
            'test_button' => [
              '#type' => 'button',
              "#value" => t('Test Google Geocoder'),
              '#attributes' => [
                'class' => ['button', 'button--primary'],
                'title' => "Test the provided configuration for this service"
              ],
              '#access' => TRUE,
              '#ajax' => [
                'callback' => [$this, 'ajaxTestGoogle'],
                'event' => 'click',
                'wrapper' => 'edit-google-result',
                'disable-refocus' => TRUE,
                'progress' => [
                  'type' => 'throbber',
                ]
              ],
              '#suffix' => '<span id="edit-google-result"></span>',
            ],
          ],
        ],
      ],
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValues()["bos_geocoder"];
    $config = $this->config('bos_geocoder.settings');
    $config->set("arcgis.base_url", trim($values["arcgis"]["base_url"], "/\\"));
    $config->set("arcgis.find_location", trim($values["arcgis"]["find_location"], "/\\"));
    $config->set("arcgis.find_address", trim($values["arcgis"]["find_address"], "/\\"));
    $config->set("google.base_url", trim($values["google"]["base_url"], "/\\"));
    $config->set("google.find_location", trim($values["google"]["find_location"], "/\\"));
    $config->set("google.find_address", trim($values["google"]["find_address"], "/\\"));
    if (!str_contains($values["google"]["token"], "************")) {
      $config->set("google.token", $values["google"]["token"]);
    }
    $config->save();
    parent::submitForm($form, $form_state); // optional
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    // TODO: Create a form validator here.
    //        Typically alter the $form_state object.
    $values = $form_state->getValues();
    $trigger = $form_state->getTriggeringElement();
  }

  /**
   * Ajax callback to test Search
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  public static function ajaxTestGoogle(array &$form, FormStateInterface $form_state): array {
    return GcGeocoder::ajaxTestService($form, $form_state);
  }

  /**
   * Ajax callback to test Search
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  public static function ajaxTestArcgis(array &$form, FormStateInterface $form_state): array {

    $values = $form_state->getValues();
    $settings = ["arcgis" => $values["bos_geocoder"]["arcgis"]];
    unset($settings["arcgis"]["test_wrapper"]);

    $address = new BosGeoAddress(singlelineaddress: "1 Cityhall plaza, Boston, MA");
    $geocoder = new BosGeoCoderBase($address, $settings);

    $result = $geocoder->geocode($geocoder::AREA_ARCGIS_ONLY);

    if ($result) {

      $address = new BosGeoAddress();
      $address->setLocation(42.360300000003122,-71.058271500000757);
      $geocoder->setAddress($address);
      $result = $geocoder->reverseGeocode($geocoder::AREA_ARCGIS_ONLY);

      if ($result) {
        return ["#markup" => Markup::create("<span id='edit-arcgis-result' style='color:green'><b>&#x2714; Success:</b> Service Config is OK.</span>")];
      }
      else {
        return ["#markup" => Markup::create("<span id='edit-arcgis-result' style='color:red'><b>&#x2717; Failed:</b> Check Reverse Geocoder Endpoint.</span>")];
      }

    }

    else {
      return ["#markup" => Markup::create("<span id='edit-arcgis-result' style='color:red'><b>&#x2717; Failed:</b> Check Base URL and/or Forward Geocoder Endpoint.</span>")];
    }

  }

}
