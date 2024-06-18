<?php

namespace Drupal\bos_emergency_alerts\Controller;

use Drupal\bos_core\Controllers\Settings\CobSettings;
use Drupal\bos_emergency_alerts\EmergencyAlertsAPISettingsInterface;
use Drupal\bos_emergency_alerts\Event\EmergencyAlertsBuildFormEvent;
use Drupal\bos_emergency_alerts\Event\EmergencyAlertsSubmitFormEvent;
use Drupal\bos_emergency_alerts\Event\EmergencyAlertsValidateFormEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class EverbridgeSubscriber.
 *
 * @package Drupal\bos_emergency_alerts\Controller
 */
class EverbridgeSubscriber extends EmergencyAlertsSubscriberBase implements EmergencyAlertsAPISettingsInterface, EventSubscriberInterface {

  /**
   * API endpoints for message types.
   *
   * @var array
   */
  protected $uri = [
    "login" => "/api/login",
    "contacts" => "/rest/contacts/454102597238915",
  ];

  private array $settings;
  protected ApiRouter $router;

  /**
   * EverbridgeSubscriber constructor.
   *
   * @inheritdoc
   */
  public function __construct() {
  }

  /**
   * Subscribe to the EmergencyAlerts config form build & submit events.
   */
  public static function getSubscribedEvents(): array {
    $events[EmergencyAlertsBuildFormEvent::BUILD_CONFIG_FORM] = ['buildForm'];
    $events[EmergencyAlertsSubmitFormEvent::SUBMIT_CONFIG_FORM] = ['submitForm'];
    return $events;
  }

  /**
   * This is the local /rest/everbridge/subscribe endpoint.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Response from the Everbridge remote API.
   */
  public function subscribe($request_bag, ApiRouter $router):Response {

    $this->router = $router;
    $this->settings = $router->settings;

    $everbridge_env = (object) [];
    if (getenv('EVERBRIDGE_SETTINGS')) {
      $everbridge_env = (object) [];
      $get_vars = explode(",", getenv('EVERBRIDGE_SETTINGS'));
      foreach ($get_vars as $item) {
        $json = explode(":", $item);
        $everbridge_env->{$json[0]} = $json[1];
      }
      $everbridge_env = json_encode($everbridge_env);
    }
    else {
      $everbridge_env = '{
        "org_id": "454102597238915",
        "rec_type_id":"487225385025537",
        "text":"241901148045324",
        "phone":"219910915489799",
        "email":"241901148045317",
        "language_id":"487225385025538",
        "api_user":"xxxx",
        "api_password":"xxxx"
      }';
    }
    $everbridge_env = json_decode($everbridge_env);

    if (!empty($router->settings['api_base']) && !empty($everbridge_env->api_password) && !empty($everbridge_env->api_user)) {

      $uri = $this->uri['contacts'];

      // Make a customKey.
      if (!empty($request_bag['email'])) {
        $customKey = $this->stringtohex($request_bag['email']);
      }
      elseif (!empty($request_bag['phone_number'])) {
        $customKey = $this->stringtohex($request_bag['phone_number']);
      }
      else {
        $customKey = $this->stringtohex($request_bag['first_name'] . $request_bag['last_name']);
      }

      $fields = [
        // Required to post to API.
        "organizationId" => $everbridge_env->org_id,
        "recordTypeId" => $everbridge_env->rec_type_id,
        "externalId" => $customKey,
        "lastName" => $request_bag['last_name'],
        "firstName" => $request_bag['first_name'],
        // End required items.
        "country" => "US",
        "uploadProcessing" => FALSE,
        "timezoneId" => "America/New_York",
      ];
      $fields_paths = [];
      if ($request_bag['text'] && $request_bag['phone_number'] !== "") {
        $paths_text = [
          "waitTime" => 0,
          "countryCode" => "US",
          "pathId" => $everbridge_env->text,
          "value" => $request_bag['phone_number'],
          "skipValidation" => FALSE,
        ];
        array_push($fields_paths, $paths_text);
      }
      if ($request_bag['call'] && $request_bag['phone_number'] !== "") {
        $paths_call = [
          "waitTime" => 0,
          "countryCode" => "US",
          "pathId" => $everbridge_env->phone,
          "value" => $request_bag['phone_number'],
          "skipValidation" => FALSE,
        ];
        array_push($fields_paths, $paths_call);
      }
      if ($request_bag["email"] !== "") {
        $paths_email = [
          "waitTime" => 0,
          "pathId" => $everbridge_env->email,
          "value" => $request_bag['email'],
          "skipValidation" => FALSE,
        ];
        array_push($fields_paths, $paths_email);
      }
      $fields_paths_full["paths"] = $fields_paths;
      $fields = array_merge($fields, $fields_paths_full);

      if ($request_bag["address"] !== "") {
        $fields_address = [
          "address" => [
            [
              "streetAddress" => $request_bag['address'],
              "postalCode" => $request_bag['zip_code'],
              "source" => "MANUAL",
              "state" => $request_bag['state'],
              "locationName" => "Home",
              "country" => "US",
              "city" => $request_bag['city'],
            ],
          ]
        ];
        $fields = array_merge($fields, $fields_address);
      }

      if ($request_bag["language"] !== "") {
        $fields_language = [
          "contactAttributes" => [
            [
              "name" => "Language",
              "orgAttrId" => $everbridge_env->language_id,
              "values" => [$request_bag["language"]]
            ],
          ]
        ];
        $fields = array_merge($fields, $fields_language);
      }

      $result = $this->post($uri, $fields, $everbridge_env);
    }
    else {
      $result = [
        "output" => 'Configuration error',
        "http_code" => "500",
      ];
    }

    return $this->router->responseOutput($result['output'], $result['http_code']);
  }

  /**
   * Makes a standard (authenticating) POST to the Everbridge API.
   *
   * @param string $uri
   *   The endpoint being POSTED to.
   * @param array $fields
   *   Fields to be posted in the message.
   * @param object $everbridge_env
   *   Env variables for endpoint.
   * @param bool $cachebuster
   *   [optional] Appended random string to bust caching (NOT usually needed).
   *
   * @return array
   *   An output array with the Everbridge REST response and http_status_code.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  private function post($uri, array $fields, object $everbridge_env, $cachebuster = FALSE) {

    $url = $this->settings['api_base'] . '/rest/contacts/' . $everbridge_env->org_id;

    // Add a random string at end of post to bust any caches.
    if (isset($cachebuster) && $cachebuster) {
      if (stripos($url, "?") > 0) {
        $url .= "&cobcb=" . rand();
      }
      else {
        $url .= "?cobcb=" . rand();
      }
    }

    // Build headers.
    if (!isset($headers)) {
      $headers = [];
    }

    // Make the post and return the response.
    try {
      $user = $everbridge_env->api_user;
      $pass = $everbridge_env->api_password;
      $user_pass = base64_encode($user . ':' . $pass);
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_POST, TRUE);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($ch, CURLOPT_HTTPHEADER, [
          "Authorization: Basic " . $user_pass,
          "Content-Type: application/json"
      ]);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
      $info = curl_exec($ch);

      if (isset($info)) {
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $json = json_decode($info);
        if (json_last_error() <> 0) {
          throw new \Exception($info);
        }
      }
      else {
        throw new \Exception("Everbridge Endpoint Error");
      }
    }
    catch (\Exception $e) {
      $json = '{"errors":' . $e->getMessage() . '}';
      $http_code = Response::HTTP_INTERNAL_SERVER_ERROR;
    }

    return [
      "output" => $json,
      "http_code" => $http_code,
    ];

  }

  /**
   * Convert each char in a string to a hex "number" and output new string.
   *
   * @param string $string
   *   A string which is to be converted into a hex number.
   * @param int $maxlen
   *   Maximum number of chars to be returned.
   *
   * @return string
   *   The string in hex format.
   */
  private function stringtohex($string, $maxlen = 50) {
    $hex = '';

    for ($i = 0; $i < strlen($string); $i++) {
      $ord = ord($string[$i]);
      $hex .= substr('0' . dechex($ord), -2);
    }

    return substr(strtoupper($hex), 0, $maxlen);
  }

  /**
   * @inheritDoc
   */
  public function buildForm(EmergencyAlertsBuildFormEvent $event, string $event_id): EmergencyAlertsBuildFormEvent {

    if ($event_id == EmergencyAlertsBuildFormEvent::BUILD_CONFIG_FORM) {

      // Add everbridge to the dropdown.
      $classname = explode('\\', get_class($this));
      $class = array_pop($classname);
      $event->form["bos_emergency_alerts"]["emergency_alerts_settings"]["current_api"]["#options"][$class] = "Everbridge";
      $isCurrent =( $event->form["bos_emergency_alerts"]["emergency_alerts_settings"]["current_api"]["#default_value"] == "EverbridgeSubscriber");

      $settings = CobSettings::getSettings("EVERBRIDGE_SETTINGS", "bos_emergency_alerts", "api_config.everbridge");
      $config = $settings["config"] ?? [];

      // Add config for the url/user/pass for everbridge API
      $event->form["bos_emergency_alerts"]["emergency_alerts_settings"]["api_config"]["everbridge"] = [

        '#type' => 'details',
        '#title' => 'Everbridge Endpoint',
        '#description' => 'Configuration for Emergency Alert Subscriptions via Everbridge API.',
        '#markup' => empty($config) ? NULL : "<b>These settings are defined in the envar EVERBRIDGE_SETTINGS and cannot be changed using this form.</b>",
        '#open' => FALSE,

        'api_base' => [
          '#type' => 'textfield',
          '#title' => t('API URL'),
          '#description' => t('Enter the full (remote) URL for the endpoint / API used to register subscriptions.'),
          '#default_value' => $settings['api_base'] ?? "",
          '#attributes' => [
            "placeholder" => 'e.g. https://api.everbridge.com',
          ],
          '#disabled' => array_key_exists("api_base", $config),
          '#required' => $isCurrent && !array_key_exists("api_base", $config),
        ],
        'api_user' => [
          '#type' => 'textfield',
          '#title' => t('API Username'),
          '#description' => t('Username set as Environment variable.'),
          '#default_value' => $settings["api_user"] ?? "",
          '#disabled' => array_key_exists("api_user", $config),
          '#required' => $isCurrent && !array_key_exists("api_user", $config),
        ],
        'api_pass' => [
          '#type' => 'textfield',
          '#title' => t('API Password'),
          '#description' => t('Password set as Environment variable.'),
          '#default_value' => $settings["api_password"] ?? "",
          '#disabled' => array_key_exists("api_password", $config),
          '#required' => $isCurrent && !array_key_exists("api_password", $config),
        ],

      ];

    }
    return $event;
  }

  /**
   * @inheritDoc
   */
  public function submitForm(EmergencyAlertsSubmitFormEvent $event, string $event_id): EmergencyAlertsSubmitFormEvent {

    if ($event_id == EmergencyAlertsSubmitFormEvent::SUBMIT_CONFIG_FORM) {

      $settings = $event->form_state->getValue('bos_emergency_alerts')['emergency_alerts_settings']['api_config']['everbridge'];
      $form = $event->form['bos_emergency_alerts']["emergency_alerts_settings"]["api_config"]["everbridge"];
      $config = $event->config;

      $newValues = [];
      if (!$form['api_base']['#disabled']) {
        $newValues['api_base'] = $settings['api_base'];
      }
      if (!$form['api_user']['#disabled']) {
        $newValues['api_user'] = $settings['api_user'];
      }
      if (!$form['api_pass']['#disabled']) {
        $newValues['api_pass'] = $settings['api_pass'];
      }

      if (!empty($newValues)) {
        $config->set('api_config.everbridge', $newValues)
          ->save();
      }

    }

    return $event;

  }

  /**
   * @inheritDoc
   */
  public function validateForm(EmergencyAlertsValidateFormEvent $event, string $event_id): EmergencyAlertsValidateFormEvent {
    return $event;
  }

}
