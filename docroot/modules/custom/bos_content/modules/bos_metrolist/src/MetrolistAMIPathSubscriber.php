<?php

namespace Drupal\bos_metrolist;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * MetrolistAMIPathSubscriber - Alter the path and redirect route for MetroList AMI Estimator.
 */
class MetrolistAMIPathSubscriber implements InboundPathProcessorInterface, EventSubscriberInterface {

  /**
   * Set the URL / path to match on.
   */
  public static function amiEstimatorUrl() {
    return '/metrolist/ami-estimator';
  }

  /**
   * Processes the inbound path.
   *
   * @param string $path
   *   The path to process, with a leading slash.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HttpRequest object representing the current request.
   *
   * @return string
   *   The processed path.
   */
  public function processInbound($path, Request $request) {
    if (self::stringStartsWith(self::amiEstimatorUrl(), strtolower($path))) {
      return \Drupal::service('path_alias.manager')->getPathByAlias(self::amiEstimatorUrl());
    }

    return $path;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Register our event right before the redirect module redirects.
    $events[KernelEvents::REQUEST][] = ['stopRedirectIfMetrolistAmi', 31];

    return $events;
  }

  /**
   * From the redirect module (redirect/src/EventSubscriber/RouteNormalizerRequestSubscriber.php):.
   *
   * The normalization can be disabled by setting the "_disable_route_normalizer"
   * request parameter to TRUE. However, this should be done before
   * onKernelRequestRedirect() method is executed.
   *
   * DU changed event typehint from GetResponseEvent to RequestEvent
   * Part of Drupal9 Migration
   *s
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The event to process.
   */
  public static function stopRedirectIfMetrolistAmi(RequestEvent $event) {
    $path = $event->getRequest()->getPathInfo();

    if (self::stringStartsWith(self::amiEstimatorUrl(), strtolower($path))) {
      $event->getRequest()->attributes->set('_disable_route_normalizer', TRUE);
    }
  }

  /**
   * Check if a string starts with a sub-string.
   */
  public static function stringStartsWith($needle, $haystack) {
    return (substr($haystack, 0, strlen($needle)) === $needle);
  }

}
