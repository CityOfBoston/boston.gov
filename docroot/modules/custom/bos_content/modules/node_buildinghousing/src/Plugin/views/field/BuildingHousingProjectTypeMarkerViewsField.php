<?php

namespace Drupal\node_buildinghousing\Plugin\views\field;

use Drupal\Core\Render\Markup;
use Drupal\views\ResultRow;

/**
 * A handler to provide a field that is completely custom by the administrator.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("building_housing_project_type_marker_views_field")
 */
class BuildingHousingProjectTypeMarkerViewsField extends BuildingHousingProjectTypeViewsField {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): Markup|array|string {

    $mainType = $this->getMainProjectTypeName($values->_relationship_entities["field_bh_project_ref"]);

//    if ($mainType) {

      switch ($mainType) {
        case "Housing":
        case "Unknown":
          $projectName = 'housing-marker.svg';
          break;

        case "Open Space":
          $projectName = 'open-space-marker.svg';
          break;

        case "Business":
          $projectName = 'business-marker.svg';
          break;

        case "Abutter Sale":
        case "For Sale":
          $projectName = 'sale-marker.svg';
          break;

        default:
          $projectName = 'other-marker.svg';
          break;

      }

      return $projectName;
//    }
//
//    return 'other-marker.svg';

  }

}
