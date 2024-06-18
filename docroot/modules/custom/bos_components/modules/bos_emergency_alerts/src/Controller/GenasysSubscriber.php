<?php

namespace Drupal\bos_emergency_alerts\Controller;

use CurlHandle;
use Drupal\bos_core\Controllers\Settings\CobSettings;
use Drupal\bos_emergency_alerts\EmergencyAlertsAPISettingsInterface;
use Drupal\bos_emergency_alerts\Event\EmergencyAlertsBuildFormEvent;
use Drupal\bos_emergency_alerts\Event\EmergencyAlertsSubmitFormEvent;
use Drupal\bos_emergency_alerts\Event\EmergencyAlertsValidateFormEvent;
use Drupal\bos_geocoder\Controller\BosGeocoderController;
use Drupal\bos_geocoder\Utility\BosGeoAddress;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class Genasys Subscriber.
 *
 * @package Drupal\bos_emergency_alerts\Controller
 */
class GenasysSubscriber extends EmergencyAlertsSubscriberBase implements EmergencyAlertsAPISettingsInterface, EventSubscriberInterface  {

    private array $settings;

  /**
   * Some settings we don't ever want to come from the envar (i.e. they must
   * be set using the config form).
   *
   * @var array $envar_list This list specifies the only settings which can be set from the envar.
   */
  private array $envar_list = [
    "api_base",
    "api_user",
    "api_pass",
    "api_clientid",
    "api_clientsecret",
  ];

  public function __construct() {
    $this->settings = CobSettings::getSettings("GENASYS_SETTINGS", "bos_emergency_alerts", "api_config.genasys", $this->envar_list);
    $this->debug_headers = FALSE;
  }

  /**
   * Subscribe to the EmergencyAlerts config form build & submit events.
   */
  public static function getSubscribedEvents(): array {
    $events[EmergencyAlertsBuildFormEvent::BUILD_CONFIG_FORM] = ['buildForm'];
    $events[EmergencyAlertsSubmitFormEvent::SUBMIT_CONFIG_FORM] = ['submitForm'];
    $events[EmergencyAlertsValidateFormEvent::VALIDATE_CONFIG_FORM] = ['validateForm'];
    return $events;
  }

  /**
   * @inheritDoc
   */
  public function buildForm(EmergencyAlertsBuildFormEvent $event, string $event_id): EmergencyAlertsBuildFormEvent {

    if ($event_id == EmergencyAlertsBuildFormEvent::BUILD_CONFIG_FORM) {

      $envar_list = array_flip($this->settings["config"] ?? []);
      $required = ($event->form["bos_emergency_alerts"]["emergency_alerts_settings"]["current_api"]["#default_value"] == "GenasysSubscriber");

      $genToken = "Fetch/Generate Token";
      if (!empty($this->settings["refresh_token"])) {
        $genToken = "Refresh Token";
      }

      if (empty($envar_list)) {
        $rec_settings = array_intersect_key($this->settings, array_flip($this->envar_list));
        $note = "<p style='color:red'>Genasys Settings are stored in Drupal config, this is not best practice and not recommended for production sites.</p>
          <p>Please set the <b>GENASYS_SETTINGS</b> envar to this value:<br><b>" . CobSettings::envar_encode($rec_settings) . "</b></p>";
      }
      else {
        $note = "<b>Some settings are defined in the envar GENASYS_SETTINGS and cannot be changed using this form.</b> This is best practice - Please change them in the environment.";
      }

      // Add config for the url/user/pass for everbridge API
      $event->form["bos_emergency_alerts"]["emergency_alerts_settings"]["api_config"]["genasys"] = [

        '#type' => 'details',
        '#title' => 'Genasys Endpoint',
        '#description' => 'Configuration for Emergency Alert Subscriptions via Genasys API.',
        '#markup' => $note,
        '#open' => FALSE,

        'api_base' => [
          '#type' => 'textfield',
          '#title' => t('Genasys API URL'),
          '#description' => t('Enter the full (remote) URL for the endpoint / API used to register subscriptions.'),
          '#disabled' => array_key_exists("api_base", $envar_list),
          '#required' => !array_key_exists("api_base", $envar_list) && $required,
          '#default_value' => $this->settings["api_base"] ?? "",
          '#attributes' => [
            "placeholder" => 'e.g. https://alertboston.genasys.com',
          ],
        ],
        'api_clientid' => [
          '#type' => 'textfield',
          '#title' => t('Genasys Client ID'),
          '#description' => t('The City of Boston Client ID for authentication.'),
          '#default_value' => $this->settings["api_clientid"] ?? "",
          '#disabled' => array_key_exists("api_clientid", $envar_list),
          '#required' => !array_key_exists("api_clientid", $envar_list) && $required,
        ],
        'api_clientsecret' => [
          '#type' => 'textfield',
          '#title' => t('Genasys Client Secret'),
          '#description' => t('Genasys City of Boston Client Secret for authentication.'),
          '#default_value' => $this->settings["api_clientsecret"] ?? "",
          '#disabled' => array_key_exists("api_clientsecret", $envar_list),
          '#required' => !array_key_exists("api_clientsecret", $envar_list) && $required,
        ],
        'api_user' => [
          '#type' => 'textfield',
          '#title' => t('API Username'),
          '#description' => t('API Username for authentication.'),
          '#default_value' => $this->settings["api_user"] ?? "",
          '#disabled' => array_key_exists("api_user", $envar_list),
          '#required' => !array_key_exists("api_user", $envar_list) && $required,
        ],
        'api_pass' => [
          '#type' => 'textfield',
          '#title' => t('API Password'),
          '#description' => t('API Password for authentication.'),
          '#default_value' => $this->settings["api_pass"] ?? "",
          '#disabled' => array_key_exists("api_pass", $envar_list),
          '#required' => !array_key_exists("api_pass", $envar_list) && $required,
        ],
        'token_wrapper' => [
          '#type' => 'fieldset',
          '#title' => 'Oauth2.0 Token',
          '#description' => 'Token used for authentication with Genasys endpoint.',
          '#description_display' => 'before',
          'api_token' => [
            '#type' => 'textfield',
            '#title' => t('Current Token'),
            '#prefix' => "<div id='edit-api-token'>",
            '#suffix' => "</div>",
            '#default_value' => CobSettings::obfuscateToken($this->settings["api_token"] ?? "", "*", 8,6),
            '#disabled' => TRUE,
            '#required' => FALSE,
          ],
          'generate_token' => [
            '#type' => 'button',
            '#value' => $genToken,
            '#attributes' => ["cob-tag" => "genToken"],
            '#ajax' => [
              'callback' => [$this, 'ajaxAuthenticate'],
              'wrapper' => "edit-api-token",
              'event' => 'click',
              'progress' => [
                'type' => 'throbber',
                'message' => $this->t('Retrieving oauth2.0 token.'),
              ],
            ],
          ],
        ],
        'contact_wrapper' => [
          '#type' => 'fieldset',
          '#title' => 'New Contact Settings',
          '#description' => 'Default values used for contact registration with Genasys.',
          '#description_display' => 'before',
          'address' => [
            'geocode_wrapper' => [
              '#type' => 'fieldset',
              '#title' => 'New Contact Settings',
              '#description' => 'Default values used for contact registration with Genasys.',
              '#description_display' => 'before',
              'geo_lat' => [
                '#type' => 'textfield',
                '#title' => t('Default Latitude'),
                '#description' => t(''),
                '#default_value' => $this->settings["geo_lat"] ?? "",
                '#required' => $required,
                '#attributes' => [
                  "placeholder" => 'e.g. 42.3560729',
                ],
              ],
              'geo_long' => [
                '#type' => 'textfield',
                '#title' => t('Default Longitude'),
                '#description' => t(''),
                '#default_value' => $this->settings["geo_long"] ?? "",
                '#required' => $required,
                '#attributes' => [
                  "placeholder" => 'e.g. -71.3452085',
                ],
              ],
            ],
          ],
          'groups' => [
            '#type' => 'textfield',
            '#title' => t('Subscription Groups'),
            '#description' => t('Enter a list of Genasys-defined groups that users will be subscribed to.<br><i>Separate each group with a comma (e.g. group1,group2).</i>'),
            '#default_value' => $this->settings["groups"] ?? "",
            '#required' => $required,
            '#attributes' => [
              "placeholder" => 'e.g. group1,group2',
            ],
          ],
          'types' => [
            '#type' => 'textfield',
            '#title' => t('Notification Types'),
            '#description' => t('Enter a list of Genasys-defined notification types that users will be subscribed to.<br><i>Separate each type with a comma (e.g. type1,types2).</i>'),
            '#default_value' => $this->settings["types"] ?? "",
            '#required' => $required,
            '#attributes' => [
              "placeholder" => 'e.g. type1,types2',
            ],
          ],
          'messages' => [
            'msg_success' => [
              '#type' => 'textarea',
              '#title' => t('Sucessful subscription'),
              '#description' => t('This message will display after the user sucessfully subscribes.'),
              '#default_value' => $this->settings["msg_success"] ?? "",
              '#required' => $required,
            ],
            'msg_duplicate_number' => [
              '#type' => 'textarea',
              '#title' => t('Subscription- Phone already registered'),
              '#description' => t('This message will display if the user\'s phone number is already subscribed.'),
              '#default_value' => $this->settings["msg_duplicate_number"] ?? "",
              '#required' => $required,
            ],
            'msg_duplicate_email' => [
              '#type' => 'textarea',
              '#title' => t('Subscription-  Email already registered'),
              '#description' => t('This message will display if the user\'s email address is already subscribed.'),
              '#default_value' => $this->settings["msg_duplicate_email"] ?? "",
              '#required' => $required,
            ],
            'msg_error' => [
              '#type' => 'textarea',
              '#title' => t('Subscription Error'),
              '#description' => t('This message will display when there is a subscription error.'),
              '#default_value' => $this->settings["msg_error"] ?? "",
              '#required' => $required,
            ],
          ],
        ],
      ];

      // Add vendor to the dropdown.
      $classname = explode('\\', get_class($this));
      $class = array_pop($classname);
      $event->form["bos_emergency_alerts"]["emergency_alerts_settings"]["current_api"]["#options"][$class] = "Genasys";

    }
    return $event;  }

  /**
   * @inheritDoc
   */
  public function submitForm(EmergencyAlertsSubmitFormEvent $event, string $event_id): EmergencyAlertsSubmitFormEvent {

    if ($event_id == EmergencyAlertsSubmitFormEvent::SUBMIT_CONFIG_FORM) {

      $form_values = $event->form_state->getValue('bos_emergency_alerts')['emergency_alerts_settings']['api_config']['genasys'];
      $form = $event->form['bos_emergency_alerts']["emergency_alerts_settings"]["api_config"]["genasys"];

      $config = $event->config;

      if (!$form['api_base']['#disabled']) {
        $config->set('api_config.genasys.api_base', $form_values['api_base']);
      }
      if (!$form['api_user']['#disabled']) {
        $config->set('api_config.genasys.api_user', $form_values['api_user']);
      }
      if (!$form['api_pass']['#disabled']) {
        $config->set('api_config.genasys.api_pass', $form_values['api_pass']);
      }
      if (!$form['api_clientid']['#disabled']) {
        $config->set('api_config.genasys.api_clientid', $form_values['api_clientid']);
      }
      if (!$form['api_clientsecret']['#disabled']) {
        $config->set('api_config.genasys.api_clientsecret', $form_values['api_clientsecret']);
      }
      $config->set('api_config.genasys.groups', $form_values['contact_wrapper']['groups']);
      $config->set('api_config.genasys.types', $form_values['contact_wrapper']['types']);
      $config->set('api_config.genasys.geo_lat', $form_values['contact_wrapper']['address']['geocode_wrapper']['geo_lat']);
      $config->set('api_config.genasys.geo_long', $form_values['contact_wrapper']['address']['geocode_wrapper']['geo_long']);
      $config->set('api_config.genasys.msg_success', $form_values['contact_wrapper']['messages']['msg_success']);
      $config->set('api_config.genasys.msg_duplicate_number', $form_values['contact_wrapper']['messages']['msg_duplicate_number']);
      $config->set('api_config.genasys.msg_duplicate_email', $form_values['contact_wrapper']['messages']['msg_duplicate_email']);
      $config->set('api_config.genasys.msg_error', $form_values['contact_wrapper']['messages']['msg_error']);
      $config->save();
    }

    return $event;

  }

  /**
   * @inheritDoc
   */
  public function validateForm(EmergencyAlertsValidateFormEvent $event, string $event_id): EmergencyAlertsValidateFormEvent {

    if ($event->form_state->getTriggeringElement()["#attributes"]["cob-tag"] == "genToken"){
      // We don't want the ajax callback to validate anything.
      $event->form_state->disableCache();
      $event->form_state->clearErrors();
    }
    return $event;
  }

  /**
   * @inheritDoc
   */
  protected function makeCurl(string $post_url, array|string $post_fields, array $headers = [], string $type = "POST", bool $insecure = FALSE): CurlHandle {

    $post_url = "{$this->settings["api_base"]}/{$post_url}";

    return parent::makeCurl($post_url, $post_fields, $headers, $type, $insecure);

  }

  /**
   * This is the callback from the form when the Generate Token button
   * is clicked.
   *
   * A new Oauth2.0 token is generated with Genasys.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  public function ajaxAuthenticate(array &$form, FormStateInterface $form_state): array {

    // Use authentication showing on the form, not what is saved as the user may
    // have changed but not saved some fields.
    $values = $form_state->getValues();
    $creds = $values["bos_emergency_alerts"]["emergency_alerts_settings"]["api_config"]["genasys"];

    // Generate or refresh token.
    $refresh = !empty($this->settings["refresh_token"]);
    $token = $this->authenticate($refresh, $creds);

    // Check the token
    if ($token) {
      \Drupal::messenger()->addStatus("New Token generated and saved.");
    }
    else {
      \Drupal::messenger()->addError($this->error);
      $token = "Authentication Failed";
    }

    // Update and return the form element.
    $form["bos_emergency_alerts"]["emergency_alerts_settings"]["api_config"]["genasys"]['token_wrapper']["api_token"]["#default_value"] = $token;
    $form["bos_emergency_alerts"]["emergency_alerts_settings"]["api_config"]["genasys"]['token_wrapper']["api_token"]["#value"] = $token;
    return $form["bos_emergency_alerts"]["emergency_alerts_settings"]["api_config"]["genasys"]['token_wrapper']["api_token"];

  }

  /**
   * Generate a new Oauth2.0 token from Genesys endpoint.
   *
   * @param bool $refresh TRUE = Refresh an existing token, FALSE = get new token
   * @param array $creds an array with wuth creds for genarting token.
   *
   * @return string|bool
   */
  private function authenticate(bool $refresh = FALSE, array $creds = []): string|bool {

    if (!empty($creds)) {
      $settings = $creds;
      $settings["refresh_token"] = $this->settings["refresh_token"];
    }
    else {
      $settings = $this->settings;
    }

    $auth = "{$settings["api_clientid"]}:{$settings["api_clientsecret"]}";
    $auth = base64_encode($auth);

    $headers = [
      "Authorization" =>  "Basic {$auth}",
      "Content-Type" => "application/x-www-form-urlencoded"
    ];

    // The authorization request must use post, and be application/x-www-form-urlencoded
    // This means the post_fields must be a urlencoded string (not an array).
    if ($refresh) {
      $post_fields = "grant_type=" . urlencode("refresh_token");
      $post_fields .= "&refresh_token=" . urlencode($settings['refresh_token']);
    }
    else {
      $post_fields = "grant_type=" . urlencode("password");
      $post_fields .= "&username=" . urlencode($settings['api_user']);
      $post_fields .= "&password=" . urlencode($settings['api_pass']);
    }

    try {
      if ($this->makeCurl("oauth/token", $post_fields, $headers, "POST")) {
        $result = $this->executeCurl( FALSE);
      }
    }
    catch (\Exception $e) {
      if ( $refresh
        && ($this->curl->response()["http_code"] == 400 || $this->curl->response()["http_code"] == 401)
        && str_contains($this->curl->response()["body"]["error_description"] ?? "", "Invalid refresh token")) {
        // The refresh token is expired or invalid.
        return $this->authenticate(FALSE, $settings);
      }
      return FALSE;
    }

    if ($result) {
      $config = \Drupal::configFactory()
        ->getEditable('bos_emergency_alerts.settings');

      $config->set("api_config.genasys.api_token", $result["access_token"])
       ->set("api_config.genasys.refresh_token", $result["refresh_token"])
       ->set("api_config.genasys.token_expiry", intval($result["date"]/1000) + ($result["expires_in"] - 1)) // round ms down
       ->save();

      // Reload the settings into the class.
      $this->settings = CobSettings::getSettings("GENASYS_SETTINGS", "bos_emergency_alerts", "api_config.genasys");

      return $result["access_token"];
    }

    return FALSE;

  }

  /**
   * Ensure that we have a token, and that it has not expired.
   * Returns FALSE if we have no token or a stale token and cannot authenticate
   * or refresh it as needed.
   *
   * @return bool
   */
  private function validateToken():bool {

    if (empty($this->settings["api_token"])) {
      // No token, so need to go get the token (or create it)
      if (!$this->authenticate(FALSE)) {
        return FALSE;
      }
    }

    if (($this->settings["token_expiry"] - 60) <= strtotime("now")) {
      // The save or retrieved token is within 1 min of expiry so must refresh.
      if (!$this->authenticate(TRUE)) {
        return FALSE;
      }
    }

    return TRUE;

  }

  /**
   * Removes all nonp-numeric characters from a phone, number, along with any
   * leading or trailing spaces.
   *
   * @param string $phone_number a formatted telephone number
   *
   * @return string The phone number strippe of all non-digit chars.
   */
  private function fixPhoneNumber(string $phone_number): string {
    $phone_number = preg_replace("/\D/", "", trim($phone_number));
    return $phone_number;
  }

  /**
   * The subscribes a new user to the Genasys system.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Response from the Genasys remote API.
   */
  public function subscribe(array $request_bag, ApiRouter $router): Response {

    $this->router = $router;

    $types = [];

    $groups = explode(",", $this->settings["groups"]);

    foreach (explode(",", $this->settings["types"]) as $type) {
      $types[] = ["name" => $type, "active"=>TRUE];
    }

    // This will request lat/long for the provided address.
    $address = new BosGeoAddress(
      $request_bag['address']??"",
      "",
      $request_bag['city']??"",
      "",
      $request_bag['state']??"",
      $request_bag['zip_code']??"",
    "US");
    $geocoder = new BosGeocoderController($address);
    if (!$geocoder->geocode($geocoder::AREA_ARCGIS_ONLY)
      || $address->location() === FALSE) {
      $address->setLocation($this->settings["geo_lat"],  $this->settings["geo_long"]);
    }

    $contact = [
      "contact" => [
        "number" => $this->fixPhoneNumber($request_bag['phone_number'] ?? ""),
        "prefix_number" => '+1',
        "name" => $request_bag['first_name'],
        "surname" => $request_bag['last_name'],
        "email" => $request_bag['email'] ?? "",
        "lang" => $request_bag['language'] ?? "English",
        "groupNames" => $groups,
        "locations" => [
          [
            "name" => 'Primary',
            "line1" => $request_bag['address'] ?? "",
            "city" => $request_bag['city'] ?? "",
            "zipCode" => $request_bag['zip_code'] ?? "",
            "state" => $request_bag['state'] ?? "",
            "country" => 'United States',
            "latitude" => $address->location()->lat() ,
            "longitude" => $address->location()->long(),
          ],
        ],
        "notification_types" => $types,
        "notification_channels" => [
          ["name" => "EMAIL", "active" => !empty($request_bag["email"])],
          ["name" => "SMS", "active" => !empty($request_bag["phone_number"]) && ($request_bag["text"]??0 == "1")],
          ["name" => "VOICE", "active" => !empty($request_bag["phone_number"]) && ($request_bag["call"]??0 == '1')],
        ],
        "groups" => [],
        "customFields" => [],
        "emails" => [],
        "phones" => [],
        "alternative_contacts" => [],
      ]
    ];

    if (empty($request_bag['phone_number'])) {
      unset($contact["contact"]["number"]);
      unset($contact["contact"]["prefix_number"]);
    }

    $contact = json_encode($contact);

    // Check the token is still valid.
    if (!$this->validateToken()) {
      $this->router->responseOutput(json_encode(["status" => "failed", "reason" => $this->error]), 400);
    }

    $headers["Content-Type"] = "application/json";
    $headers["Authorization"] = "Bearer {$this->settings["api_token"]}";

    try {
      if ($this->makeCurl("api/contact", $contact, $headers, "POST")) {
        if ($result = $this->executeCurl(FALSE)) {
          if (empty($result["id"])) {
            throw new \Exception($this->error, $this->curl::BAD_RESPONSE);
          }
          return $this->router->responseOutput($this->settings["msg_success"] ?? "", 200);
        }
      }
      throw new \Exception("Empty Response", $this->curl::BAD_RESPONSE);
    }
    catch (\Exception $e) {
      if ($this->curl->response()["http_code"] == 400) {
        if ($this->curl->response()["body"]["internalCode"] == 1222) {
          // Number or email already subscribed.
          if (str_contains($this->curl->response()["body"]["message"], "Email already exists")) {
            return $this->router->responseOutput($this->settings["msg_duplicate_email"]??"---", 401);
          }
          else if (str_contains($this->curl->response()["body"]["message"], "Number already exists")) {
            return $this->router->responseOutput($this->settings["msg_duplicate_number"]??"--", 401);
          }
          else {
            return $this->router->responseOutput($this->settings["msg_error"]??"--", 400);
          }
        }
      }

      // Something else went wrong.  It's all logged just return a friendly
      // message.
      return $this->router->responseOutput($this->settings["msg_error"], 400);
    }

  }
  
}
