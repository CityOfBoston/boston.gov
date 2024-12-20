<?php

namespace Drupal\bos_search\Controller;

use Drupal\bos_search\AiSearch;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\OpenDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class AiSearchFormController.
 *
 * This Controller class is used to launch a modal instance of the AiSearchForm.
 * Creates the search form for bos_search.
 *
 * david 06 2024
 */
class AiSearchFormController extends ControllerBase {

  /**
   * Internal formBuilder object.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

  /**
   * The constructor.
   *
   * @param \Drupal\Core\Form\FormBuilder $formBuilder
   *   The form builder.
   */
  public function __construct(FormBuilder $formBuilder) {
    $this->formBuilder = $formBuilder;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder')
    );
  }

  /**
   * Callback: Opens a modal form with the AiSearch form.
   *
   * This method checks the configuration for a disclaimer and shows it if
   * necessary. Then, it creates and returns an AjaxResponse to open a modal
   * dialog containing the AiSearch form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AjaxResponse to open the modal dialog with the form content.
   */
  public function openModalForm(): AjaxResponse {

    $config = AiSearch::getPresetValues();

    if ($config && $config["searchform"]['disclaimer']['enabled']) {
      // Check if disclaimer should be shown.
      if (($config["searchform"]['disclaimer']['show_once'] && !AiSearch::getSessionCookie('shown_search_disclaimer'))
        || !$config["searchform"]['disclaimer']['show_once']) {

        // Show the interstitial (modal) disclaimer.
        $response = $this->openDisclaimerForm(AiSearch::getPreset());
        AiSearch::setSessionCookie('shown_search_disclaimer', TRUE);
        return $response;
      }
    }

    $response = new AjaxResponse();

    // Get the modal form using the form builder.
    $modal_form = $this->formBuilder->getForm('Drupal\bos_search\Form\AiSearchForm');

    // Ensure we have a preset in the search element.
    if (empty($modal_form["AiSearchForm"]["content"]["preset"])) {
      $preset = AiSearch::getPreset();
      $search_preset = [
        "#default_value" => $preset,
        "#value" => $preset,
      ];
      $modal_form["AiSearchForm"]["search"]["preset"] = $modal_form["AiSearchForm"]["content"]["preset"] + $search_preset;
    }

    // Add an AJAX command to open a modal dialog with the form as the content.
    $ui_options = [
      'width' => '85%',
      'maxWidth' => '85%',
      "classes" => [
        "ui-dialog" => "aisearch-modal-form ui-corner-all aienabledsearchform",
      ],
      "closeOnEscape" => TRUE,
      'closeText' => "Close this window",
    ];
    if (empty($modal_form["#modal_title"])) {
      $ui_options["classes"]["ui-dialog-titlebar"] = "ui-titlebar-hidden";
    }
    $response->addCommand(new OpenModalDialogCommand(($modal_form["#modal_title"] ?? ""), $modal_form, $ui_options));
    unset($modal_form["#modal_title"]);

    return $response;
  }

  /**
   * Opens a disclaimer form in a modal dialog via an AJAX response.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response containing the modal dialog command with the disclaimer
   *   form.
   */
  public function openDisclaimerForm(): AjaxResponse {
    $response = new AjaxResponse();
    $modal_form = $this->formBuilder->getForm('Drupal\bos_search\Form\AiDisclaimerForm');
    // Add an AJAX command to open a modal dialog with the form as the content.
    $rendered_form = \Drupal::service('renderer')->render($modal_form);
    $ui_options = [
      'width' => '591px',
      "classes" => [
        "ui-dialog" => "aisearch-disclaimer-form ui-corner-all aienableddisclaimerform",
      ],
      "closeOnEscape" => TRUE,
      'closeText' => "Close this window",
    ];
    if (empty($modal_form["#modal_title"])) {
      $ui_options["classes"]["ui-dialog-titlebar"] = "ui-titlebar-hidden";
    }
    $response->addCommand(new OpenModalDialogCommand(
      ($modal_form["#modal_title"] ?: ""),
      $rendered_form,
      $ui_options
    ));

    AiSearch::setSessionCookie('shown_search_disclaimer', TRUE);
    return $response;
  }

}
