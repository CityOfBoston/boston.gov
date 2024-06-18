<?php

namespace Drupal\bos_emergency_alerts\Controller;

use Drupal\bos_core\Services\BosCoreGAPost;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Mail\MailManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ApiRouter extends ControllerBase {

  protected Request $request;
  protected LoggerChannel $log;
  protected MailManager $mail;
  protected BosCoreGAPost $gapost;
  public array $settings;
  protected array $submitted_contact;

  /**
   * Emergency Alerts API Router create.
   *
   * @inheritdoc
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('logger.factory'),
      $container->get('plugin.manager.mail'),
      $container->get('bos_core.gapost')
    );
  }

  /**
   * @inheritDoc
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger
   * @param \Drupal\Core\Mail\MailManager $mail
   * @param \Drupal\bos_core\Services\BosCoreGAPost $gapost
   */
  public function __construct(RequestStack $requestStack, LoggerChannelFactory $logger, MailManager $mail, BosCoreGAPost $gapost) {
    $this->request = $requestStack->getCurrentRequest();
    $this->log = $logger->get('EmergencyAlerts');
    $this->mail = $mail;
    $this->gapost = $gapost;
    $this->settings = $this->config("bos_emergency_alerts.settings")->getRawData();
  }

  /**
   * Magic function.  Catches calls to endpoints that don't exist and returns
   * a 404 message.
   *
   * @param string $name
   *   Name.
   * @param mixed $arguments
   *   Arguments.
   */
  public function __call($name, $arguments) {
    throw new NotFoundHttpException();
  }

  /**
   * Route the call to the currently active Vendor.
   *
   * @param string $action the action from the endpoint call.
   *
   * @return Response
   */
  public function route(string $action): Response {

    // Post to Google Analytics
    // $this->gapost->pageview($this->request->getRequestUri(), "CoB REST | Emergency Alerts Subscription");

    $this->submitted_contact = (array) $this->request->getPayload()->all();

    // Check the honeypot on the subscription form.
    if (!$this->checkHoneypot($this->submitted_contact)) {
      return $this->responseOutput("ok", 200);
    }

    // Check for flooding/DDOS attacks.
    if ($this->isFlooding($this->request)) {
      return $this->responseOutput("ok", 200);
    }

    // Link to the active API.
    $mod = '\\Drupal\\bos_emergency_alerts\\Controller\\' . $this->settings["emergency_alerts_settings"]["current_api"];
    $vendor = new $mod($this);

    // Pass through the action and the form.
    return $vendor->$action($this->submitted_contact, $this);

  }

  /**
   * Check if the honeypot on the emergency alerts form is empty or not.
   *
   * @param array $payload The submitted form
   *
   * @return bool
   */
  private function checkHoneypot(array &$payload): bool {
    if (isset($payload["email2"]["suffix"])) {
      if (empty($payload["email2"]["suffix"])) {
        unset($payload["email2"]);
        return TRUE;
      }
    }
    $this->log->warning("Honeypot detected from {$this->request->getClientIp()}");
    return FALSE;
  }

  /**
   * This will use the drupal flood system.
   *
   * We allow a certain number of subscription events in a time window, and when
   * that limit is reached this function return false to identify likely misuse.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return bool
   */
  private function isFlooding(Request $request) {

    $flood_window = 60;  // seconds
    $max_requests = 3;   // # requests allowed in the window.
    $name = "bos_emergency_alerts.subcribe";

    /**
     * @var \Drupal\Core\Flood\DatabaseBackend $flood
     */
    $flood = \Drupal::service("flood");

    $user = \Drupal::currentUser();
    if ($user->isAuthenticated()) {
      // Authenticated users have a unique userId.
      $id = $user->id();
    }
    else {
      // Using NULL defaults to the current users IPAddress - Unauthenticated
      // users will def. have an IP - it may not be unique though ...
      $id = NULL;
    }

    // NOTE: expired flood records in table flood in the database are cleared
    // automatically by cron.
    if ($flood->isAllowed($name, $max_requests, $flood_window, $id)) {
      return FALSE;
    }
    $this->log->warning("Flood detected from {$this->request->getClientIp()}");
    return TRUE;

  }

  /**
   * Helper function to email alerts.
   *
   * Actual email formatted in bos_emergency_alerts_mail().
   */
  public function mailAlert($message): void {

    $request = $this->request->request->all();

    if (empty($this->settings["emergency_alerts_settings"]["email_alerts"])) {
      $this->log->warning("Emergency_alerts email recipient is not set.  An error has been encountered, but no email has been sent.");
      return;
    }

    $params['message'] = ["error" => $message, "form" => $request];
    $result = $this->mail->mail("bos_emergency_alerts", "subscribe_error", $this->settings["emergency_alerts_settings"]["email_alerts"], "en", $params, NULL, TRUE);
    if ($result['result'] !== TRUE) {
      $this->log->warning("There was a problem sending your message and it was not sent.");
    }

  }

  /**
   * Helper: Formats a standardised Response object.
   *
   * @param string $message
   *   Message to JSON'ify.
   * @param int $type
   *   Response constant for the HTTP Status code returned.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Full Response object to be returned to caller.
   */
  public function responseOutput(string $message, int $type): Response {

    $json = [
      'status' => 'error',
      'contact' => $message,
    ];
    $response = new Response(
      json_encode($json),
      $type,
      [
        'Content-Type' => 'application/json',
        'Cache-Control' => 'must-revalidate, no-cache, private',
        'X-Generator-Origin' => 'City of Boston (https://www.boston.gov)',
        'Content-Language' => 'en',
      ]
    );

    switch ($type) {
      case "200":
        $json['status'] = 'success';
        $response->setContent(json_encode($json));
        break;

      case "400":
      case "401":
        $json['status'] = 'error';
        $json['errors'] = ["message" => $message];
        unset($json['contact']);
        $response->setContent(json_encode($json));
        $this->mailAlert($message);
        break;

      default:
        $json['status'] = 'error';
        $json['errors'] = ["message" => $message];
        $response->setContent(json_encode($json));
        $this->mailAlert($message);
        break;
    }
    return $response;
  }

}
