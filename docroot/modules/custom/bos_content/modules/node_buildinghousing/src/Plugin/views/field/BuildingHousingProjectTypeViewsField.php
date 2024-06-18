<?php

namespace Drupal\node_buildinghousing\Plugin\views\field;

use Drupal\Core\Entity\EntityInterface as EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * A handler to provide a field that is completely custom by the administrator.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("building_housing_project_type_views_field")
 */
class BuildingHousingProjectTypeViewsField extends FieldPluginBase {

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
   * Get Main Project Type Name.
   *
   * @param \Drupal\Core\Entity\EntityInterface $projectEntity
   *   Project Entity.
   *
   * @return string|null
   *   Main Project Type name string
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getMainProjectTypeName(EntityInterface $projectEntity): string|NULL {

//    $mainType = "Housing";

    $termStorage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

    if ($dispositionTypeId = $projectEntity->get('field_bh_disposition_type')->target_id) {
      $dispositionTypeParents = $termStorage->loadAllParents($dispositionTypeId);
      $dispositionType = !empty($dispositionTypeParents) ? array_pop($dispositionTypeParents) : NULL;
    }

    if (!empty($dispositionType)) {
      return $dispositionType->getName();
    }

//    if ($projectTypeId = $projectEntity->get('field_bh_project_type')->target_id) {
//      if (empty($dispositionType) || $dispositionType->getName() == 'Housing') {
//        $mainType = 'Housing';
//      }
//    }
//
//    if ($mainType) {
//      return is_string($mainType) ? $mainType : $mainType->getName();
//    }

    return "Unknown";
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): array|Markup|string {

    $mainType = $this->getMainProjectTypeName($values->_relationship_entities["field_bh_project_ref"]);

    if ($mainType) {

      switch ($mainType) {
        case "Housing":
        case "Unknown":
          $iconType = 'maplist-housing';
          break;

        case "Open Space":
          $iconType = 'maplist-open-space';
          break;

        case "Business":
          $iconType = 'maplist-business';
          break;

        case "Abutter Sale":
          $iconType = 'maplist-sale';
          break;

        default:
          $iconType = 'maplist-other';
          break;
      }

      return \Drupal::theme()->render("bh_icons", ['type' => $iconType]);

    }

    return Markup::create("");

  }

}
