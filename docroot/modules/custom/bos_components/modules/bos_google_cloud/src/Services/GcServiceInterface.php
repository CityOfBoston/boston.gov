<?php

namespace Drupal\bos_google_cloud\Services;

use Drupal\Core\Form\FormStateInterface;

/**
 * Interface GcServiceInterface.
 *
 * Provides methods to interact with a Google Cloud service.
 */
interface GcServiceInterface {

  /**
   * Provides a standardized name for this service.
   *
   * @return string
   */
  public static function id(): string;

  /**
   * Execute the primary function of the class/service.
   *
   * @params string $parameters An array of parameters for this service.
   *
   * @return string|mixed The output from the service.
   *
   * @description    Typically:
   *       $parameters["text"] - The text string to process
   *       $parameters["prompt"] - The prompt to use during processing
   *
   */
  public function execute(array $parameters = []): object|string|FALSE;

  /**
   * Build the section on the Goggle Cloud Confrm form for this service.
   * Note:
   * You will need to manually inset a call into ConfigForm.php submitForm().
   *
   * @param array $form The node on the form to build.
   *
   * @return void
   */
  public function buildForm(array &$form): void;

  /**
   * Save configuration entered onto the form in the config object.
   *  Note:
   *  You will need to manually inset a call into ConfigForm.php submitForm().
   *
   * @param array $form The entire form
   * @param FormStateInterface $form_state The current form_state
   *
   * @return void
   */
  public function submitForm(array $form, FormStateInterface $form_state): void;

  /**
   * Custom validation for this form.
   * Note:
   * You will need to manually inset a call into ConfigForm.php validateForm().
   *
   * @param array $form The entire form object
   * @param FormStateInterface $form_state The current form_state
   *
   * @return void
   */
  public function validateForm(array $form, FormStateInterface &$form_state): void;

  /**
   * Returns the last error as tring, or else FALSE if no errors.
   *
   * @return string|bool
   */
  public function error(): string|bool;

  /**
   * Set the service_account, overriding the default.
   *
   * @param string $service_account A valid service account.
   *
   * @return $this
   * @throws \Exception
   */
  public function setServiceAccount(string $service_account):GcServiceInterface;

  /**
   * Flag whether the service supports an ongoing conversation.
   *
   * @return bool TRUE is conversation supported.
   */
  public function hasFollowup():bool;

  /**
   * Return the Google Cloud config for this service.
   *
   * @return array
   */
  public function getSettings():array;

  /**
   * Provides a means to test connectivity to this service. Used by the config form.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array Render array for forms API - message back based on test result.
   */
  public static function ajaxTestService(array &$form, FormStateInterface $form_state): array;

  /**
   * Returns a list of prompts which can be used by this service.
   *
   * @return array
   */
  public function availablePrompts(): array ;

}
