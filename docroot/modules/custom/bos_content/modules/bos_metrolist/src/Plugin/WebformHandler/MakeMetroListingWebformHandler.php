<?php

namespace Drupal\bos_metrolist\Plugin\WebformHandler;

use Drupal\bos_metrolist\MetroListSalesForceConnection;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Annotation\WebformHandler;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\Entity\Webform;
/**
 * Create a new node entity from a webform submission.
 *
 * @WebformHandler(
 *   id = "make_metrolist_listing_submission",
 *   label = @Translation("Create a MetroList-Listing Submission"),
 *   category = @Translation("MetroList"),
 *   description = @Translation("Create a MetroList-Listing Submission in Drupal."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_UNLIMITED,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_IGNORED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 *   )
 */
class MakeMetroListingWebformHandler extends WebformHandlerBase {

  protected $pluginId;

  /**
   * @inheritdoc
   */
  public function getSummary() {
    $a = parent::getSummary();
    unset($a["#theme"]);
    $a["#markup"] = "Custom WebformHandler defined in module bos_metrolist.<br>Creates a new, blank Metrolist-Listing submission using the provided email address.";
    return $a;
  }

  /**
   *  On submission of metrolist_listing_entry_form.
   *
   *  Note: FYI, the way we have configured the listing_entry_form, it does not
   *        actually save the submission, but this event still fires.
   *
   *  Create a new metrolist_listing submission and inject the token into the
   *  body of email sent back to the submitter.
   *
   * @inheritdoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {

    parent::submitForm($form, $form_state, $webform_submission);

    $email = $webform_submission->getElementData('email') ?? ($webform_submission->getElementData('contact_email') ?? $_POST["email"]);
    $token = $this->_createMetrolistListingSubmission($email);

    $message = $webform_submission->getElementData('hidden_message') ?? $_POST["hidden_message"];
    $message = str_replace('#metrolist:new-listing:token#', $token, $message);
    $webform_submission->setElementData('hidden_message', $message);

  }

  /**
   * @inheritdoc
   */
  public function confirmForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    parent::confirmForm($form, $form_state, $webform_submission); // TODO: Change the autogenerated stub
  }

  /**
   * @inheritdoc
   */
  public function validateForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    parent::validateForm($form, $form_state, $webform_submission); // TODO: Change the autogenerated stub
  }

  /**
   *  Creates a new metrolist_listing submission, popultaes with the email address
   *  of the submitter and then saves the submission.
   *  Returns a token for the submission or FALSE.
   *
   * @param $contact_email
   *
   * @return array|bool
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function _createMetrolistListingSubmission($contact_email) {

    // Find the contact ID for this contact if possible.
    $salesForce = new MetroListSalesForceConnection();
    $contacts = $salesForce->getContactsByEmail($contact_email);
    $contactId = 'new';
    if (!empty($contacts)) {
      $contactId = (string) $contacts[array_key_first($contacts)]->id();
    }

    // Create new metrolist_listing webform submission.
    $webform = Webform::load('metrolist_listing');
    $values = [
      'webform_id' => $webform->id(),
      'in_draft' => TRUE,
      'data' => [
        'contact_email' => $contact_email,
        'select_contact' => $contactId,
      ],
    ];

    $new_submission = WebformSubmission::create($values);

    // Save the new submission.
    $saved = $new_submission->save();
    if ($saved != SAVED_NEW) {
      \Drupal::logger('bos_metrolist')->error("Error creating a new metrolist listing submission");
    }

    // Get a token for the new submission and add to the email response body.
    $token = $new_submission->getToken();


    return $token ?? FALSE;

  }

}