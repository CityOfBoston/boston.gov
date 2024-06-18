<?php

namespace Drupal\node_buildinghousing\Plugin\views\field;

use Drupal\Core\Render\Markup;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\ResultRow;

/**
 * A handler to provide a field that is completely custom by the administrator.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("building_housing_project_type_views_field_with_text")
 */
class BuildingHousingProjectTypeViewsFieldWithText extends BuildingHousingProjectTypeViewsField {

  /**
   * {@inheritdoc}
   */
  public function usesGroupBy() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Do nothing -- to override the parent query.
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['hide_alter_empty'] = ['default' => FALSE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
  }

    /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): array|Markup|string {

    $publicStage = NULL;
    if ($publicStageId = $values->_entity->field_bh_public_stage->target_id ?? NULL) {
      if ($publicStage = Term::load($publicStageId)) {
        $publicStage = $publicStage->getName() ?? NULL;
      }
    }

    $banner_status = "Archived";
    if (!empty($values->taxonomy_term_field_data_node__field_bh_banner_status_tid)) {
      $term = Term::load($values->taxonomy_term_field_data_node__field_bh_banner_status_tid);
      $banner_status = $term->getName();
      $map_visibility = $term->field_map_visibility->value;
    }

    $mainType = $this->getMainProjectTypeName($values->_relationship_entities["field_bh_project_ref"]);

    if ($map_visibility == 'inactive') {
      $iconType = NULL;
      $pillColor = 'medium-gray';
      $pillText = t('MOH Owned Land');
      $pillTextColor = 'black';
    }
    else {
        switch ($mainType) {
          case "Housing":
          case "Unknown":
            $iconType = 'maplist-housing';
            $pillColor = 'charles-blue';
            $pillText = t('Housing');
            break;

          case "Open Space":
            $iconType = 'maplist-open-space';
            $pillColor = 'green';
            $pillText = t('Open Space');
            break;

          case "Business":
            $iconType = 'maplist-business';
            $pillColor = 'gray-blue';
            $pillText = t('Business');
            break;

          case "Abutter Sale":
          case "For Sale":
            $iconType = 'maplist-sale';
            // $pillColor = 'medium-gray';
            $pillColor = 'dark-gray';
            $pillText = t('For Sale');
            break;

          case "Other":
          case "Unknown":
          default:
            // $iconType = 'maplist-other';
            $iconType = NULL;
            $pillColor = 'dark-gray';
            $pillText = t('To Be Decided');
            break;
        }
      }

    $elements = [
      'projectType' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['bh-project-type-pill', $pillColor]
        ],
        'icon' => [],
        'text' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $pillText,
        ]
      ]
    ];

    if (!empty($iconType)) {
      $elements['projectType']['icon']['#markup'] = \Drupal::theme()->render("bh_icons", ['type' => $iconType]);
    }

    return $elements;

  }

}
