<?php

namespace Drupal\bos_metrolist\Plugin\views\argument_validator;

use Drupal\Core\Annotation\Translation;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\TermStorageInterface;
use Drupal\views\Annotation\ViewsArgumentValidator;
use Drupal\views\Plugin\views\argument_validator\Entity;

/**
 * Validates whether a term name is a valid term argument.
 *
 * @ViewsArgumentValidator(
 *   id = "bos_metrolist_term_ami_threshold",
 *   title = @Translation("MetroList: Taxonomy term - AMI Threshold"),
 *   entity_type = "taxonomy_term"
 * )
 */
class TermAMIThreshold extends Entity {

  /**
   * The taxonomy term storage.
   *
   * @var \Drupal\taxonomy\TermStorageInterface
   */
  protected $termStorage;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $entity_type_bundle_info);
    // Not handling exploding term names.
    $this->multipleCapable = TRUE;
    $this->termStorage = $entity_type_manager->getStorage('taxonomy_term');
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['transform'] = ['default' => FALSE];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['transform'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Transform dashes in URL to spaces in term name filter values'),
      '#default_value' => $this->options['transform'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateArgument($argument) {
    if ($this->options['transform']) {
      $argument = str_replace('-', ' ', $argument);
    }
    // $terms = $this->termStorage->loadByProperties(['name' => $argument]);
    // $min_ami = reset($terms) ?? null;

    $terms = $this->termStorage->loadTree('ml_income_eligibility_ami_thold', 0, NULL, TRUE);

    if (!$terms) {
      // Returned empty array no terms with the name.
      return FALSE;
    }
    // Elseif ($min_ami = reset($terms)){

    // }

    // Not knowing which term will be used if more than one is returned check
    // each one.
    foreach ($terms as $term) {
      if (!$this->validateEntity($term)) {
        return FALSE;
      }
    }

    return TRUE;
  }

}
