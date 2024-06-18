<?php

namespace Drupal\bos_core\Plugin\EntityReferenceSelection;

use Drupal\node\Plugin\EntityReferenceSelection\NodeSelection;

/**
 * Entity reference selection.
 *
 * @EntityReferenceSelection(
 *   id = "autocomplete:node",
 *   label = @Translation("Autocomplete node"),
 *   group = "autocomplete",
 * )
 */
class AutocompleteSelection extends NodeSelection {

  /**
   * {@inheritdoc}
   */
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0) {
    return parent::getReferenceableEntities($match, $match_operator, 25);
  }

}
