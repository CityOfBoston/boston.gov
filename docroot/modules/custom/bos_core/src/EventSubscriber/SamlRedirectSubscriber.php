<?php

namespace Drupal\bos_core\EventSubscriber;

use Drupal\Core\Url;
use Drupal\redirect\RedirectChecker;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Redirect subscriber for controller requests.
 */
class SamlRedirectSubscriber implements EventSubscriberInterface {

  /**
   * @var \Drupal\redirect\RedirectChecker
   */
  protected $checker;

  /**
   * Constructs a \Drupal\redirect\EventSubscriber\RedirectRequestSubscriber object.
   *
   * @param \Drupal\redirect\RedirectChecker $checker
   *   The redirect checker service.
   */
  public function __construct(RedirectChecker $checker) {
    $this->checker = $checker;
  }

  /**
   * This redirects the /user/logout page to the /saml_auth logout page.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The event to process.
   */
  public function onLoginLogoutPageRequest(RequestEvent $event) {
    // Get a clone of the request. During inbound processing the request
    // can be altered. Allowing this here can lead to unexpected behavior.
    // For example the path_processor.files inbound processor provided by
    // the system module alters both the path and the request; only the
    // changes to the request will be propagated, while the change to the
    // path will be lost.
    $request = clone $event->getRequest();

    if (!$this->checker->canRedirect($request)) {
      return;
    }

    // Get URL info and process it to be used for hash generation.
    if ($request->getPathInfo() == "/user/logout") {
      if (!$this->isSAMLAccount(\Drupal::currentUser())) {
        return;
      }
      $request_query = $request->query->all();
      $url = Url::fromRoute('samlauth.saml_controller_logout', [], ['absolute' => 'true']);
      $url->setOption('query', (array) $url->getOption('query') + $request_query);
      $response = new RedirectResponse($url->toString());
      \Drupal::service('bos_core.redirect')->setRedirectResponse($response);
      $response->send();
    }
    elseif ($request->getPathInfo() == "/user/login") {
      $config = \Drupal::config("samlauth.authentication");
      if ($config->get("allow_local_login") == 1) {
        return;
      }
      $request_query = $request->query->all();
      $url = Url::fromRoute('samlauth.saml_controller_login', [], ['absolute' => 'true']);
      $url->setOption('query', (array) $url->getOption('query') + $request_query);
      $response = new RedirectResponse($url->toString());
      \Drupal::service('bos_core.redirect')->setRedirectResponse($response);
      $response->send();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // This needs to run before RouterListener::onKernelRequest(), which has
    // a priority of 32. Otherwise, that aborts the request if no matching
    // route is found.
    $events[KernelEvents::REQUEST][] = ['onLoginLogoutPageRequest'];
    return $events;
  }

  protected function isSAMLAccount($account) {
    $request = \Drupal::requestStack()->getCurrentRequest();
    if ($request->hasSession() && ($session = $request->getSession())) {
      // Find the actual user account for this session
      $session_uid = $session->get('uid');
      if ($session_uid != $account->id()) {
        // The account in the session is not the account being logged out.
        return FALSE;
      }
    }
    $service = \Drupal::service("externalauth.authmap");
    $authmap_id = $service->getAuthData($session_uid, "samlauth");
    if (!$authmap_id) {
      // The account being logged out does not have an authmap entry, so it's not
      // saml-managed.
      return FALSE;
    }
    return TRUE;
  }

}
