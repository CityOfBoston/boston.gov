<?php

namespace Drupal\bos_core\EventSubscriber;

use Drupal\Core\EventSubscriber\HttpExceptionSubscriberBase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * Last-chance handler for exceptions.
 *
 * This handler will catch any exceptions not caught elsewhere and send a themed
 * error page as a response.
 */
class ExceptionSubscriber extends HttpExceptionSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected static function getPriority() {
    // A very low priority so that custom handlers are almost certain to fire
    // before it, even if someone forgets to set a priority.
    return 1;
  }

  /**
   * {@inheritdoc}
   */
  protected function getHandledFormats() {
    return [
      'html',
    ];
  }

  /**
   * The default 500 content.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The event to process.
   */
  public function on500(ExceptionEvent $event) {
    $content = file_get_contents(DRUPAL_ROOT . '/themes/custom/bos_theme/error/500.html');
    $response = new Response($content, 500);
    $event->setResponse($response);
  }

  /**
   * Handles errors for this subscriber.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The event to process.
   */
  public function onException(ExceptionEvent $event) {
    $exception = $event->getThrowable();
    // Make the exception available for example when rendering a block.
    try {
      $status_code = $exception->getCode();
    }
    catch (\Exception $e){
      $status_code = "0";
    }
    if (preg_match('/5[0-9][0-9]/', $status_code)) {
      $method = 'on' . $status_code;

      if (method_exists($this, $method)) {
        $this->$method($event);
      }

      if (!method_exists($this, $method)) {
        $this->on500($event);
      }
    }
  }

}
