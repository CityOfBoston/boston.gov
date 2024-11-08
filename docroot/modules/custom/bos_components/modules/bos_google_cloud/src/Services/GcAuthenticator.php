<?php

namespace Drupal\bos_google_cloud\Services;

use DomainException;
use Drupal;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\bos_core\Controllers\Settings\CobSettings;
use Exception;
use Google\Auth\ApplicationDefaultCredentials;

/**
 * Class GcAuthenticator.
 *
 * Creates a service/controller for bos_google_cloud to control logins.
 *
 * david 01 2024
 */
class GcAuthenticator extends ControllerBase implements GcServiceInterface {

  /**
   * CONSTANT ARRAY: List of service accounts (== hardcoded!))
   */
  public const SVS_ACCOUNT_LIST = [
    "service_account_1",
    "service_account_2",
  ];

  /**
   * CONSTANT ARRAY: Maps service accounts to local files for authentication.
   */
  public const SVS_FILE_MAPPING = [
    "service_account_1" => "google_cloud_service_account_1.json",
    "service_account_2" => "google_cloud_service_account_2.json",
  ];

  /**
   * Logger object for class.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $log;

  private const GOOGLE_AUTH_ENVAR = "GOOGLE_APPLICATION_CREDENTIALS";

  /**
   * SCOPE CONSTANTS.
   *
   * Used to define scope for OAUTH2 authentication.
   *
   * @see setScope().
   */
  private const SCOPE_NO_SCOPE = -1;
  public const SCOPE_GOOGLE_CLOUD = 0;
  public const SCOPE_GOOGLE_DRIVE = 1;
  public const SCOPE_BIG_QUERY = 2;

  /**
   * Config object for class.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * Settings for bos_google_cloud.settings.
   *
   * @var array
   */
  protected array $settings;

  /**
   * Flags and reports errors which occur (mainly from CuRL).
   *
   * @var string
   */
  protected string $error;

  /**
   * Array to hold OAuth scopes.
   *
   * @var array
   */
  private array $scope = [];

  /**
   * Cache tag (relevant to specified scope).
   *
   * @var int
   */
  private int $cacheTag;

  /**
   * The Authentication cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected CacheBackendInterface $aiCache;

  /**
   * Properties for the currently active authentication.
   *
   * @var array
   */
  public array $currentAuthentication = [];

  /**
   * {@inheritDoc}
   */
  public function __construct(string $service_account = "", int $scope = self::SCOPE_GOOGLE_CLOUD) {

    if (!empty($service_account)) {
      $this->useSvsAcctCredsFile($service_account);
    }

    $this->settings = CobSettings::getSettings(
      'GC_SETTINGS',
      'bos_google_cloud',
    );

    // This is the main Cache for our Authentication.
    $this->aiCache = $this->cache("gen_ai");

    $this->setScope($scope);

  }

  /**
   * @inheritDoc
   */
  public static function id(): string {
    return "authenticator";
  }

  /**
   * @inheritDoc
   */
  public function buildForm(array &$form): void {

    $form['service_accounts'] = [
      '#type' => 'fieldset',
      '#title' => 'Service Accounts',
      "#markup" => "<h5>Service Accounts allow for non-UI based services to communicate over OAuth 2.0.</h5>",
    ];
    foreach (self::SVS_ACCOUNT_LIST as $key => $svsacct) {
      $form['service_accounts'][$svsacct] = [
        '#type' => 'details',
        '#title' => "Service Account " . $key + 1 . " ($svsacct)",
        'project_id' => [
          '#type' => 'textfield',
          '#title' => t('Project ID'),
          '#description' => t('The Project ID (machine-name) for the project containing the service account.'),
          '#default_value' => $this->settings['auth'][$svsacct]['project_id'] ?? "",
          '#disabled' => FALSE,
          '#required' => FALSE,
          '#attributes' => [
            "placeholder" => 'e.g. vertex-ai-poc-406419',
          ],
        ],
        "private_key_id" => [
          '#type' => 'textfield',
          '#title' => t('Private Key ID'),
          '#description' => t('A long string which is the private key id for the following cert.'),
          '#default_value' => $this->settings['auth'][$svsacct]['private_key_id'] ?? "",
          '#disabled' => FALSE,
          '#required' => FALSE,
          '#attributes' => [
            "placeholder" => 'e.g. abc123def456...',
          ],
        ],
        "private_key" => [
          '#type' => 'textarea',
          '#title' => t('Private Key'),
          '#description' => t('The private key as a text certificate.'),
          '#default_value' => $this->settings['auth'][$svsacct]['private_key'] ?? "",
          '#disabled' => FALSE,
          '#required' => FALSE,
        ],
        "client_email" => [
          '#type' => 'textfield',
          '#title' => t('Client Email'),
          '#description' => t('The google-created email for the service account.'),
          '#default_value' => $this->settings['auth'][$svsacct]['client_email'] ?? "",
          '#disabled' => FALSE,
          '#required' => FALSE,
          '#attributes' => [
            "placeholder" => 'e.g. [svsaccountname]@[project_id].iam.gserviceaccount.com',
          ],
        ],
        "client_id" => [
          '#type' => 'textfield',
          '#title' => t('Client ID'),
          '#description' => t('Numeric Client ID (prob 21 numbers)'),
          '#default_value' => $this->settings['auth'][$svsacct]['client_id'] ?? "",
          '#disabled' => FALSE,
          '#required' => FALSE,
          '#attributes' => [
            "placeholder" => 'e.g. 1234567890',
          ],
        ],
        "client_x509_cert_url" => [
          '#type' => 'textfield',
          '#title' => t('Client Cert URL'),
          '#description' => t('The URL from which the client cert can be obtained, typcially ends with the url-encoded client email.'),
          '#default_value' => $this->settings['auth'][$svsacct]['client_x509_cert_url'] ?? "",
          '#disabled' => FALSE,
          '#required' => FALSE,
          '#attributes' => [
            "placeholder" => 'e.g. https://www.googleapis.com/robot/v1/metadata/x509/[svsaccountemail]',
          ],
        ],
      ];
    }

  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array $form, FormStateInterface $form_state): void {

    $auth = $form_state->getValues()["google_cloud"]['authentication_wrapper']['service_accounts'];
    $config = Drupal::configFactory()->getEditable("bos_google_cloud.settings");

    // Save the authentication values.
    foreach (self::SVS_ACCOUNT_LIST as $service_account) {
      if (
        $config->get("auth.$service_account.project_id") != $auth[$service_account]['project_id']
        || $config->get("auth.$service_account.private_key_id") != $auth[$service_account]['private_key_id']
        || $config->get("auth.$service_account.private_key") != $auth[$service_account]['private_key']
        || $config->get("auth.$service_account.client_email") != $auth[$service_account]['client_email']
        || $config->get("auth.$service_account.client_id") != $auth[$service_account]['client_id']
        || $config->get("auth.$service_account.client_x509_cert_url") != $auth[$service_account]['client_x509_cert_url']
      ) {
        $config->set("auth.$service_account.project_id", $auth[$service_account]['project_id'])
          ->set("auth.$service_account.private_key_id", $auth[$service_account]['private_key_id'])
          ->set("auth.$service_account.private_key", str_replace(["\r\n", "\\"], ["\n", ""], $auth[$service_account]['private_key']))
          ->set("auth.$service_account.client_email", $auth[$service_account]['client_email'])
          ->set("auth.$service_account.client_id", $auth[$service_account]['client_id'])
          ->set("auth.$service_account.client_x509_cert_url", $auth[$service_account]['client_x509_cert_url']);
        $config->save();
        $this->settings = CobSettings::getSettings(
          'GC_SETTINGS',
          'bos_google_cloud',
        );
        $this->makeSvsAcctCredsFile($service_account);
      }
    }

  }

  /**
   * {@inheritDoc}
   */
  public function validateForm(array $form, FormStateInterface &$form_state): void {
    // Not required.
  }

  /**
   * Set the self::GOOGLE_AUTH_ENVAR envar which is used by many
   * google cloud services for authentication.
   * This is pretty secure becaue the envar only exists for the duration of this
   * PHP client session and is available only to this session.
   *
   * The class current_authentication["service_account"] array is updated
   * and can be inspected after calling for additional information.
   *
   * @param string $service_account The service account from SVS_ACCOUNT_LIST
   *
   * @return bool FALSE if the creds file cannot be created and/or saved.
   */
  public function useSvsAcctCredsFile(string $service_account):bool {

    // Check and see if we have already set the envar.
    if (($this->currentAuthentication["service_account"]["envar_set"] ?? FALSE)
      && ($this->currentAuthentication["service_account"]["name"] ?? "") == $service_account) {
      return TRUE;
    }

    // Get the credentials filename for this service account.
    $filename = $this->getAuthFilename($service_account);

    // Check that the credentials file does in fact exist.
    if (!file_exists($filename)) {
      // If not try to create it now.
      $filename = $this->makeSvsAcctCredsFile($service_account);
      if (!file_exists($filename)) {
        // If we can't find and can't make this file, return FALSE as there
        // is no point in continuing.
        $this->error = "Cannot set $service_account to use for authentication (file system error)";
        return FALSE;
      }
    }

    // Set the environment variable.
    // This envar is used by API code to validate.
    // For REST API function, we go ahead and generate an access token from this
    // service account credentials file, and then use that as a bearer token.
    putenv(self::GOOGLE_AUTH_ENVAR . "=" . $filename);

    $this->currentAuthentication["service_account"] = [
      "name" => $service_account,
      "envar" => self::GOOGLE_AUTH_ENVAR,
      "auth_json_file" => self::SVS_FILE_MAPPING[$service_account],
      "envar_set" => TRUE,
    ];

    return TRUE;

  }

  /**
   * This gets an access token from Google using the specified service account.
   *
   * The token has a 60min lifetime, after which it expires.
   * The token should not be saved in any calling function, call this function
   * instead and a new token or an existing token will be returned.
   *
   * The function updates the class current_authentication["auth_token"] array
   * which can be inspected after calling for additional information.
   *
   * The function will always authenticate using
   *         https://www.googleapis.com/auth/cloud-platform
   *       but other scopes can be added: e.g.:
   *         https://www.googleapis.com/auth/drive.readonly
   *         https://www.googleapis.com/auth/bigquery
   *
   * @param string $service_account
   *   The service account to use from self::SVS_ACCOUNT_LIST.
   * @param bool $asHeader
   *   If TRUE an authorization string will be constructed - which can be
   *   injected into a CuRL header.
   * @param array $extra_scope
   *   [optional] additional scopes for OAuth2 authentication.
   *      e.g. https://www.googleapis.com/auth/drive.readonly.
   *
   * @return string
   *   The access token.
   *
   * @throws \Exception
   */
  public function getAccessToken(string $service_account, bool $asHeader = FALSE, int $scope = self::SCOPE_NO_SCOPE): string {

    $cache_id = "$service_account.{$this->cacheTag}.token";

    if ($response = $this->aiCache->get($cache_id)) {
      $this->currentAuthentication["auth_token"] = $response->data;
    }
    else {
      if ($this->useSvsAcctCredsFile($service_account)) {
        if ($scope != self::SCOPE_NO_SCOPE) {
          $this->setScope($scope);
        }
        try {
          $credentials = ApplicationDefaultCredentials::getCredentials($this->scope);
          $this->currentAuthentication["auth_token"] = $credentials->fetchAuthToken();
        }
        catch (DomainException $e) {
          $file = basename(__FILE__);
          Drupal::logger("bos_google_cloud")
            ->error($file . ": " . $e->getMessage());
          throw new Exception($file . ": " . $e->getMessage());
        }

        $this->currentAuthentication["service_account"]["client_name"] = $credentials->getClientName();
        $this->currentAuthentication["project_id"] = $credentials->getProjectId();

        $token = $this->currentAuthentication["auth_token"];
        $expiry = strtotime("now") + $this->currentAuthentication["auth_token"]["expires_in"] - 30;
        $this->aiCache->set($cache_id, $token, $expiry);

      }
      else {
        throw new Exception("Cannot create the credentials file.");
      }
    }

    $response = $this->currentAuthentication["auth_token"]["access_token"];
    if ($asHeader) {
      $response = "{$this->currentAuthentication["auth_token"]["token_type"]} $response";
    }
    return $response;

  }

  /**
   * Invalidates any AuthTokens cached for the specified service account.
   *
   * @param string $service_account service account from self::SVS_ACCOUNT_LIST
   *
   * @return void
   */
  public function invalidateAuthToken(string $service_account, int $scope = self::SCOPE_NO_SCOPE): void {
    if ($scope != self::SCOPE_NO_SCOPE) {
      $this->setScope($scope);
    }
    $this->aiCache->invalidate("$service_account.{$this->cacheTag}.token");
  }

  /**
   * Validates that a supplied service account string exists.
   *
   * @param string $service_account
   *
   * @return bool
   */
  public function validateServiceAccount(string $service_account): bool {
    return in_array($service_account, self::SVS_ACCOUNT_LIST);
  }

  /**
   * Return the filename for a file containing the JSON formatted credentials of
   * the specified service account.
   *
   * The file can be referenced by an ENVAR for OAUTH2 authorization-by-service-account.
   *
  * @param string $service_account The service account to use from self::SVS_ACCOUNT_LIST
   *
   * @return string The filename and path for the Svs Act Creds file (local).
   */
  private function getAuthFilename(string $service_account): string {
    $filename = self::SVS_FILE_MAPPING[$service_account];
    $uri = "private://$filename";
    return Drupal::service('file_system')->realpath($uri);
  }

  /**
   * Set the environment variable to give OAUTH2 authorization for the specified
   * service account.
   *
   * @param string $service_account The service account to use from self::SVS_ACCOUNT_LIST
   *
   * @return string|bool The filename or FALSE if fails.
   */
  private function makeSvsAcctCredsFile(string $service_account): string|bool {

    // Save the file in the private folder on Drupal.
    $config_file = $this->getAuthFilename($service_account);

    try {
      $h = fopen($config_file, "w");
      // Create the settings file content.
      if ($file_content = $this->makeJsonCreds($service_account)) {
        if (fwrite($h, $file_content)) {
          fclose($h);
          return $config_file;
        }
        $this->error = "Could not write to file $config_file";
      }
      return FALSE;
    }
    catch (Exception $e) {
      $this->error = $e->getMessage();
      return FALSE;
    }
  }

  /**
   * Create a JSON string with the service account credentials matching the
   * file which can be downloaded from Vertex/Google Cloud IAM.
   *
   * @param string $service_account The service account to use from self::SVS_ACCOUNT_LIST
   *
   * @return false|string The correctly encoded JSON, or FALSE if issues.
   */
  private function makeJsonCreds(string $service_account): string|bool {
    $auth = $this->settings["auth"][$service_account];
    try {
      return json_encode([
        "type" => "service_account",
        "project_id" => $auth["project_id"] ?? "",
        "private_key_id" => $auth["private_key_id"] ?? "",
        "private_key" => str_replace(["\r\n"], ["\n"], $auth["private_key"] ?? ""),
        "client_email" => $auth["client_email"] ?? "",
        "client_id" => $auth["client_id"] ?? "",
        "auth_uri" => "https://accounts.google.com/o/oauth2/auth",
        "token_uri" => "https://oauth2.googleapis.com/token",
        "auth_provider_x509_cert_url" => "https://www.googleapis.com/oauth2/v1/certs",
        "client_x509_cert_url" => $auth["client_x509_cert_url"] ?? "",
        "universe_domain" => "googleapis.com",
      ],JSON_PRETTY_PRINT+JSON_UNESCAPED_SLASHES);
    }
    catch (Exception $e) {
      $this->error = $e->getMessage();
      return FALSE;
    }
  }

  /**
   * @inheritDoc
   */
  public function execute(array $parameters = []): string {
    // not required for authentication
    return "This class does not support execute method.";
  }

  /**
   * @inheritDoc
   */
  public function error(): string|bool {
    return (empty($this->error) ? FALSE : $this->error);
  }

  /**
   * @inheritDoc
   */
  public function setServiceAccount(string $service_account): GcServiceInterface {
    if ($this->useSvsAcctCredsFile($service_account)) {
      return $this;
    }
    if (!$this->error()) {
      $this->error = "Cannot set the service account $service_account";
    }
    throw new Exception($this->error());
  }

  /**
   * @inheritDoc
   */
  public function hasFollowup(): bool {
    return FALSE;
  }

  /**
   * @inheritDoc
   */
  public function getSettings(): array {
    return [];
  }

  /**
   * @inheritDoc
   */
  public function availablePrompts(): array {
    return [];
  }

  /**
   * @inheritDoc
   */
  public static function ajaxTestService(array &$form, FormStateInterface $form_state): array {
    // not required.
    return [];
  }

  /**
   * Sets the OAUTH2 key scope based on the given integer flags.
   *
   * @param int $scope
   *   Bitwise flags indicating the required scopes.
   *
   * @return array
   *   Array of scope URLs.
   */
  private function setScope(int $scope):array {

    // Use the scope as a cache tag.
    $this->cacheTag = $scope;

    // Always enable cloud platform.
    $this->scope = ["https://www.googleapis.com/auth/cloud-platform"];

    if ($scope & self::SCOPE_GOOGLE_DRIVE) {
      $this->scope[] = "https://www.googleapis.com/auth/drive.readonly";
    }
    if ($scope & self::SCOPE_BIG_QUERY) {
      $this->scope[] = "https://www.googleapis.com/auth/bigquery";
    }
    return $this->scope;
  }

}
