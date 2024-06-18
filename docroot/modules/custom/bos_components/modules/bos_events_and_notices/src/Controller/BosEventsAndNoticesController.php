<?php

namespace Drupal\bos_events_and_notices\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;


class BosEventsAndNoticesController extends ControllerBase {

  /**
   * Get Events by Zipcode for MNL
   *
   * @param string|null $zipcode
   *  5 digit Zipcode used to filter Events
   *
   * @return Response
   *  Clean HTML of filtered Views Events
   */
  public function getMnlByZipcode (string $zipcode = null) {

    $markup = \Drupal::service('renderer')
      ->render(views_embed_view('events_and_notices', 'upcoming', $zipcode));

    //Make a new, clean Response object so we only have the views html and no headers, footers or other assets.
    return new Response($markup);
  }
}
