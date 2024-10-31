<?php

namespace Drupal\bos_search\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/* Note, the ControllerBase class instantiates this class with many core services
 *        pre-loaded as class variables, or available in the container object.
 *        @see ControllerBase.php
 *
 *  class autocomplete_searchPage
 *
 *  david 08 2024
 *  @file docroot/modules/custom/bos_components/modules/bos_search/src/Controller/autocomplete_searchPage.php
 */

class AutocompleteController extends ControllerBase {

  // Searches and returns nodes matching text provided.
  public static function searchNodes(Request $request) {
    $titles = [];
    $input = $request->query->get('q');

    if ($input && strlen($input) > 3) {

      $nids = \Drupal::entityQuery('node')
        ->condition('title', $input, 'CONTAINS')
        ->sort('created', 'DESC')
        ->accessCheck(0)
        ->execute();
      $nids = array_slice($nids, 0, 10);
      $nodes = Node::loadMultiple($nids);

      foreach ($nodes as $node) {
        $titles[] = [
          'value' => "{$node->toUrl()->toString()}",
          'label' => "{$node->getTitle()}",
          'entity_id' => $node->id(),
          'description' => "{$node->getTitle()} ({$node->toUrl()->toString()})",
        ];
      }
    }

    return new JsonResponse($titles);
  }

}
