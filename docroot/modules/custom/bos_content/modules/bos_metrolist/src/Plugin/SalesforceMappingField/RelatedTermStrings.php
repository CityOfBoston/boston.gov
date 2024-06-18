<?php

namespace Drupal\bos_metrolist\Plugin\SalesforceMappingField;

use Drupal\Component\Annotation\Plugin;
use Drupal\salesforce_mapping\Plugin\SalesforceMappingField\RelatedTermString;

use Drupal\Core\Entity\EntityInterface;

use Drupal\field\Entity\FieldConfig;
use Drupal\salesforce\SObject;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\salesforce\Exception as SalesforceException;
use Drupal\taxonomy\Entity\Term;

/**
 * Adapter for entity Reference and fields.
 *
 * @Plugin(
 *   id = "RelatedTermStrings",
 *   label = @Translation("Related Term Strings (multilist)")
 * )
 */
class RelatedTermStrings extends RelatedTermString {

  /**
   * {@inheritdoc}
   */
  public function value(EntityInterface $entity, SalesforceMappingInterface $mapping) {
    $field_name = $this->config('drupal_field_value');
    $instances = $this->entityFieldManager->getFieldDefinitions(
      $entity->getEntityTypeId(),
      $entity->bundle()
    );

    if (empty($instances[$field_name])) {
      return;
    }

    $field = $entity->get($field_name);
    if (empty($field->getValue()) || is_null($field->entity)) {
      // This reference field is blank or the referenced entity no longer
      // exists.
      return;
    }

    // Map the term name to the salesforce field.
    return $field->entity->getName();
  }

  /**
   * {@inheritdoc}
   */
  public function pullValue(SObject $sf_object, EntityInterface $entity, SalesforceMappingInterface $mapping) {

    if (!$this->pull() || empty($this->config('salesforce_field'))) {
      throw new SalesforceException('No data to pull. Salesforce field mapping is not defined.');
    }

    $field_name = $this->config('drupal_field_value');
    $instance = FieldConfig::loadByName($this->mapping->getDrupalEntityType(), $this->mapping->getDrupalBundle(), $field_name);
    if (empty($instance)) {
      return;
    }

    $value = $sf_object->field($this->config('salesforce_field'));
    // Empty value means nothing to do here.
    if (empty($value)) {
      return NULL;
    }

    // Get the appropriate vocab from the field settings.
    $vocabs = $instance->getSetting('handler_settings')['target_bundles'];

    // Logic and looping of values goes here.
    $sf_values = explode(';', $value);
    $term_ids = [];

    foreach ($sf_values as $sf_value) {

      // Look for a term that matches the string in the salesforce field.
      $query = \Drupal::entityQuery('taxonomy_term')
        ->accessCheck();
      $query->condition('vid', $vocabs, 'IN');
      $query->condition('name', $sf_value);
      $tids = $query->execute();
      $term_id = NULL;

      if (!empty($tids)) {
        $term_id = reset($tids);
      }

      // If we cant find an existing term, create a new one.
      if (empty($term_id)) {
        $vocab = reset($vocabs);

        $term = Term::create([
          'name' => $sf_value,
          'vid' => $vocab,
        ]);
        $term->save();
        $term_id = $term->id();
      }

      $term_ids[] = $term_id;
    }

    return $term_ids;
  }

}
