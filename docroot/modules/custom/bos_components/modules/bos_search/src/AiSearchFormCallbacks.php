<?php

namespace Drupal\bos_search;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Url;

/**
 * Lazy build callbacks.
 */
class AiSearchFormCallbacks implements TrustedCallbackInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * FormBuilderInterface type object.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected FormBuilderInterface $formBuilder;

  /**
   * Callbacks constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   FormBuilderInterface type object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, FormBuilderInterface $form_builder) {
    $this->entityTypeManager = $entity_type_manager;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['renderSearchForm'];
  }

  /**
   * Lazy builder callback for switch-back link.
   *
   * @return array|string
   *   Render array or an emty string.
   */
  public function renderSearchForm(?string $preset = NULL) {

    $form = $this->formBuilder->getForm('Drupal\bos_search\Form\AiSearchForm', $preset);

    // Enable the disclaimer if required by preset.
    $config = AiSearch::getPresetValues($preset);

    if ($config && $config["searchform"]['disclaimer']['enabled']) {

      // Check if disclaimer should be shown.
      if (($config["searchform"]['disclaimer']['show_once'] && !AiSearch::getSessionCookie('shown_search_disclaimer'))
        || !$config["searchform"]['disclaimer']['show_once']) {

        // Add in the js to show the modal, plus drupalSettings it needs.
        $form['#attached']['library'][] = 'bos_search/disclaimer';
        $form['#attached']['drupalSettings']['disclaimerForm'] = [
          'openModal' => Url::fromRoute('bos_search.open_DisclaimerForm')
            ->toString(),
          'triggerDisclaimerModal' => TRUE,
        ];

        // Mark the disclaimer session flag.
        AiSearch::setSessionCookie('shown_search_disclaimer', TRUE);
      }
    }
    return $form;

  }

  /**
   * AJAX callback to open the modal disclaimer form - not implemented.
   */
  public function ajaxOpenDisclaimerModalForm(array &$form, FormStateInterface $form_state) {
    return new AjaxResponse();
  }

}
