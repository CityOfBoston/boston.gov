<?php

namespace Drupal\bos_metrolist;

use Drupal\salesforce\Exception;
use Drupal\salesforce\SelectQuery;
use Drupal\webform\Entity\WebformSubmission;

/**
 * Connection for Drupal CoB MetroList connection to SF.
 */
class MetroListSalesForceConnection {

  /**
   * Webform Submission.
   *
   * @var \Drupal\Core\Entity\EntityInterface|null
   */
  private $webformSubmission = NULL;

  /**
   * Contact Email.
   *
   * @var string|null
   */
  private $contactEmail = NULL;

  /**
   * {@inheritdoc}
   */
  public function client() {
    $client = \Drupal::service('salesforce.client');
    return $client;
  }

  /**
   * {@inheritdoc}
   */
  public function authMan() {
    return \Drupal::service('plugin.manager.salesforce.auth_providers');
  }

  /**
   * {@inheritdoc}
   */
  public function getSalesforceUrl() {
    return $this->authMan()->getProvider()->getInstanceUrl() . '/' . $this->salesforce_id->value;
  }

  /**
   * Load a Webform Submission.
   *
   * @param string $sid
   *   Submission ID.
   *
   * @return \Drupal\Core\Entity\EntityInterface|MetroListSalesForceConnection|null
   *   Webform Submission.
   */
  public function loadWebformSubmission(string $sid = NULL) {
    if ($this->webformSubmission && is_null($sid)) {
      return $this->webformSubmission;
    }

    $sid = $sid ?? reset($_SESSION['webform_submissions']) ?? NULL;
    if ($sid) {
      $this->webformSubmission = WebformSubmission::load($sid) ?? NULL;
    }
    else {
      // Most likely a user has attempted to access a form which has expired,
      // or is trying access the metrolist form without first getting an email.
      // TODO: redirect somewhere useful
    }

    return $this;
  }

  /**
   * The current Webform Submission.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Webform Submission.
   */
  public function webformSubmission() {
    return $this->webformSubmission;
  }

  public function setWebformSubmission($webformSubmission) {
    $this->webformSubmission = $webformSubmission;
    $this->getContactEmail();
  }

  /**
   * Get Contact email from webform submission.
   *
   * @return string
   *   Contact email.
   */
  public function getContactEmail() {
    if ($this->webformSubmission()) {
      $this->contactEmail = $this->webformSubmission()->getElementData('contact_email') ?? NULL;
    }
    return $this->contactEmail;
  }

  /**
   * Lookup a SF Contact by Email.
   *
   * @param string $email
   *   Value of Contact email address.
   *
   * @return \Drupal\salesforce\SObject
   *   Object of the requested Salesforce object, Contact that matches the email.
   */
  public function getContactByEmail($email = '') {

    try{
      $contactSFObject = $this->client()->objectReadbyExternalId('Contact', 'Email', $email);
    }
    catch (\Exception $e) {
      $contacts = $this->getContactsByEmail($email);
      if (!empty($contacts)) {
        $contactSFObject = (string) $contacts[array_key_first($contacts)];
      }
      else {
        return NULL;
      }
    }

    return $contactSFObject;
  }

  /**
   * Get Contacts by Email.
   *
   * @param string $email
   *   Contact Email.
   *
   * @return array|bool|null
   *   Contacts.
   */
  public function getContactsByEmail(string $email) {

    try {
      $contactQuery = new SelectQuery('Contact');

      $contactQuery->addCondition('Email', "'" . urlencode($email) . "'");
      $contactQuery->fields = ['Id', 'Name', 'Email'];

      return $this->client()->query($contactQuery)->records() ?? NULL;
    }
    catch (Exception $exception) {
      \Drupal::logger('bos_metrolist')->error($exception->getMessage());
      return FALSE;
    }

  }

  /**
   * Get Contact options by Email lookup.
   *
   * @param string|null $email
   *   Contact Email.
   *
   * @return array|void
   *   Array of Contact options from SF.
   */
  public function getContactOptionsByEmail(string $email = NULL) {
    if (empty($email)) {
      return NULL;
    }

    $options = [];

    foreach ($this->getContactsByEmail($email) as $contact) {
      if ($contact->hasField('Id') && $contact->hasField('Name')) {
        $options[$contact->field('Id')] = $contact->field('Name');
      }
    }

    return $options;
  }

  /**
   * Get Developments by Contact SF ID.
   *
   * @param string $contactSID
   *   Contact SF ID.
   *
   * @return bool|null
   *   Developments.
   */
  public function getDevelopmentsByContactSid(string $contactSID) {

    try {
      $developmentQuery = new SelectQuery('Development__c');
      $developmentQuery->addCondition('Listing_Contact__c', "'$contactSID'");
      $developmentQuery->fields = ['Id', 'Name', 'Street_Address__c', 'City__c'];

      return $this->client()->query($developmentQuery)->records() ?? NULL;
    }
    catch (Exception $exception) {
      \Drupal::logger('bos_metrolist')->error($exception->getMessage());
      return FALSE;
    }

  }

  /**
   * Get Development options by Contact SF ID.
   *
   * @param string|null $contactSID
   *   Contact SF ID.
   *
   * @return array|void
   *   Development options.
   */
  public function getDevelopmentOptionsByContactSid(string $contactSID = NULL) {
    if (empty($contactSID) || $contactSID == 'new') {
      return NULL;
    }

    $options = [];

    foreach ($this->getDevelopmentsByContactSid($contactSID) as $development) {
      if ($development->hasField('Id') && $development->hasField('Name')) {
        $options[$development->field('Id')] = $development->field('Name') . ' (' . $development->field('Street_Address__c') . ', ' . $development->field('City__c') . ')';
      }
    }

    return $options;
  }

  /**
   * Get SF Units of a Development.
   *
   * @param string $developmentSFID
   *   Development SF ID.
   *
   * @return bool|null
   *   Return Units or null/false.
   */
  public function getUnitsByDevelopmentSid(string $developmentSFID) {

    try {
      $developmentQuery = new SelectQuery('Development_Unit__c');
      $developmentQuery->addCondition('Development_new__c', "'$developmentSFID'");
      $developmentQuery->fields = [
        'Id',
        'Name',
        'Availability_Status__c',
        'Income_Eligibility_AMI_Threshold__c',
        'Number_of_Bedrooms__c',
        'Number_of_Bathrooms__c',
        'Rent_or_Sale_Price__c',
        'Minimum_Income_Threshold__c',
        'Occupancy_Type__c',
        'Availability_Type__c',
        'Lottery_Application_Deadline__c',
        'Rent_Type__c',
        'ADA_H__c',
        'ADA_V__c',
        'ADA_M__c',
        'Published_Date__c',
        'Suggested_Removal_Date__c',
      ];

      return $this->client()->query($developmentQuery)->records() ?? NULL;
    }
    catch (Exception $exception) {
      \Drupal::logger('bos_metrolist')->error($exception->getMessage());
      return FALSE;
    }

  }

  /**
   * Get picklist values from SF.
   *
   * @param string $sf_object
   *   SF object.
   * @param string $sf_field
   *   SF Field name.
   * @param array $exclude
   *   Values to exclude.
   *
   * @return array
   *   Array of options from SF.
   */
  public function getPickListValues(string $sf_object, string $sf_field, array $exclude = []) {
    $values = [];
    $picklistData = $this->client()->objectDescribe($sf_object)->getField($sf_field)['picklistValues'] ?? NULL;

    if ($picklistData) {
      foreach ($picklistData as $option) {
        if ($option['active'] && !in_array($option['value'], $exclude)) {
          $values[$option['value']] = $option['label'];
        }
      }
    }

    return $values;
  }

  /**
   * Get Attachments by SF ID.
   *
   * @param string $sfId
   *   Object SF ID.
   *
   * @return bool|null
   *   Attachments.
   */
  public function getAttachmentsBySid(string $sfId) {

    try {
      $attachmentQuery = new SelectQuery('Attachment');
      $attachmentQuery->addCondition('ParentId', "'$sfId'");
      $attachmentQuery->fields = ['Id', 'Body'];

      return $this->client()->query($attachmentQuery)->records() ?? NULL;
    }
    catch (Exception $exception) {
      \Drupal::logger('bos_metrolist')->error($exception->getMessage());
      return FALSE;
    }

  }

  /**
   * Get Attachment Body by SF ID.
   *
   * @param string $sfId
   *   Object SF ID.
   *
   * @return bool|null
   *   Attachment Body.
   */
  public function getAttachmentBody(string $sfId) {

    try {
      $attachmentQuery = new SelectQuery('Attachment');
      $attachmentQuery->addCondition('ParentId', "'$sfId'");
      $attachmentQuery->fields = ['Id', 'Body'];

      return $this->client()->query($attachmentQuery)->records() ?? NULL;
    }
    catch (Exception $exception) {
      \Drupal::logger('bos_metrolist')->error($exception->getMessage());
      return FALSE;
    }

  }

}
