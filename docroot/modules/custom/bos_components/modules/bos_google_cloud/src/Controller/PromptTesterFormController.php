<?php

namespace Drupal\bos_google_cloud\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/*
  class PromptTesterFormController
  Creates a modal testing form for bos_google_cloud

  david 04 2024
  @file docroot/modules/custom/bos_components/modules/bos_google_cloud/src/Form/src/Controller/PromptTesterFormController.php
*/

class PromptTesterFormController extends ControllerBase {

  /**
   * This Controller class is used to launch a modal instance of the
   * PromptTesterForm.
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
   * Callback for opening the modal form.
   */
  public function openModalForm() {
    $response = new AjaxResponse();

    // Get the modal form using the form builder.
    $modal_form = $this->formBuilder->getForm('Drupal\bos_google_cloud\Form\PromptTesterForm');

    // Add an AJAX command to open a modal dialog with the form as the content.
    $response->addCommand(new OpenModalDialogCommand('Prompt Testing/Tuning', $modal_form, ['width' => '80%']));

    return $response;
  }

}
