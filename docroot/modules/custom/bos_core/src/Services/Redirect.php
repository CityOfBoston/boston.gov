<?php

namespace Drupal\bos_core\Services;

use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\HttpKernel\HttpKernelInterface;

use Symfony\Component\HttpFoundation\RedirectResponse;

/**

 * Class Redirect.

 *

 *    Redirects traffic without blocking hooks etc.

 *

 * @package Drupal\bos_core\Services

 */

class Redirect implements HttpKernelInterface {

  protected $httpKernel;

  protected $redirectResponse;

  public function __construct(HttpKernelInterface $http_kernel) {

    $this->httpKernel = $http_kernel;

  }

  public function handle(Request $request, int $type = 1, bool $catch = TRUE): Response {

    $response = $this->httpKernel->handle($request, $type, $catch);

    return $this->redirectResponse ?: $response;

  }

  public function setRedirectResponse(?RedirectResponse $redirectResponse) {

    $this->redirectResponse = $redirectResponse;

  }

}

