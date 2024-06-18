<?php

namespace Drupal\bos_metrolist\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\salesforce\Exception;
use Drupal\salesforce\Rest\RestException;
use Drupal\salesforce\SelectQuery;
use Drupal\webform\Annotation\WebformHandler;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\bos_metrolist\MetroListSalesForceConnection;
use Drupal\file\Entity\File;

/**
 * Post Submission data into Salesforce Objects.
 *
 * @WebformHandler(
 *   id = "post_metrolist_listing_submission",
 *   label = @Translation("Post a MetroList-Listing Submission"),
 *   category = @Translation("MetroList"),
 *   description = @Translation("Posts a MetroList-Listing Submission to SalesForce using SOQL."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_UNLIMITED,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_REQUIRED,
 * )
 */
class PostMetroListingWebformHandler extends WebformHandlerBase {
  /**
   * Flag for Development Updated.
   *
   * @var bool
   */
  public $updatedDevelopmentData = FALSE;

  /**
   * @return \Drupal\salesforce\Rest\RestClient
   */
  public function client() {
    return \Drupal::service('salesforce.client');
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
   * Lookup a SF Contact by Email.
   *
   * @param string $email
   *   Value of Contact email address.
   *
   * @return \Drupal\salesforce\SObject
   *   Object of the requested Salesforce object, Contact that matches the email.
   */
  public function getContactByEmail($email = '') {

    $contactSFObject = $this->client()->objectReadbyExternalId('Contact', 'Email', $email);

    return $contactSFObject;
  }

  /**
   * Add or update a SF Contact.
   *
   * @param array $developmentData
   *   Submission Data.
   *
   * @return mixed
   *   Return SFID of Contact or false
   */
  public function upsertContact(array $developmentData) {

    $contactSFID = $developmentData['contactsfid'] ?? NULL;

    $contactEmail = $developmentData['contact_email'] ?? NULL;
    $contactName = $developmentData['contact_name'] ?? NULL;
    $contactPhone = $developmentData['contact_phone'] ?? NULL;
    $contactAddress = $developmentData['contact_address'] ?? [];

    $fieldData = [
      'AccountId' => $developmentData['accountsfid'],
      'Email' => $contactEmail,
    ];

    if (empty($fieldData['AccountId'])) {
      // Effectively bubble up the addAccount error.
      \Drupal::logger('bos_metrolist')->error("Error encountered saving Contact. SF rejected addition of {$contactName}");
      return FALSE;
    }

    if ($contactPhone) {
      $fieldData['Phone'] = $contactPhone;
    }

    $contactName = explode(' ', $contactName);
    if (isset($contactName[1])) {
      $fieldData['FirstName'] = $contactName[0];
      $fieldData['LastName'] = $contactName[1];
    }
    else {
      $fieldData['FirstName'] = '';
      $fieldData['LastName'] = $contactName[0];
    }

    if (!empty($contactAddress)) {
      $fieldData['MailingStreet'] = $contactAddress['address'];
      $fieldData['MailingCity'] = $contactAddress['city'];
      $fieldData['MailingState'] = $contactAddress['state_province'];
      $fieldData['MailingPostalCode'] = $contactAddress['postal_code'];
    }

    $result = $this->updateSalesforce('Contact', $fieldData, "Id", $contactSFID ?? NULL);
    if (!$result) {
      $contactName = implode(" ", $contactName);
      \Drupal::logger('bos_metrolist')->error("Error encountered updating Contact. SF rejected update of {$contactName}");
    }
    return $result;

  }

  /**
   * Add or update a SF Account.
   *
   * @param array $developmentData
   *   Submission Data.
   *
   * @return mixed
   *   Return SFID or false
   */
  public function upsertAccount(array $developmentData) {

    try {
      $accountQuery = new SelectQuery('Account');
      $company = $developmentData['contact_company'];
      // DU ticket #2494 DIG-79
      // Escape apostrophes b/c of the way the string is built on the next line.
      $company = str_replace("'", "\'", $company);
      $accountQuery->addCondition('Name', "'$company'");
      $accountQuery->addCondition('Type', "'Property Manager'");
      // @TODO: Make Config?, Hardcoded the SFID to `DND Contacts`
      $accountQuery->addCondition('Division__c', "'DND'");
      // @TODO: hardcoded to SFID for Account Record Type: "Vendor"
      $accountQuery->addCondition('RecordTypeId', "'012C0000000I0hCIAS'");
      $accountQuery->fields = ['Id', 'Name', 'Type'];

      $existingAccount = $this->client()->query($accountQuery)->records() ?? NULL;
      $existingAccount = reset($existingAccount) ?? NULL;

    }
    catch (Exception $exception) {
      \Drupal::logger('bos_metrolist')->error($exception->getMessage());
      return FALSE;
    }

    if ($existingAccount) {
      // Just return the SFID of the account, no need to update anything....
      return (string) $existingAccount->id();
    }
    else {
      // Create a new Account in SF.
      $fieldData = [
        'Name' => $developmentData['contact_company'],
        'Business_Legal_Name__c' => $developmentData['contact_company'],
        'Type' => 'Property Manager',
        'Division__c' => 'DND',
        // @TODO: hardcoded to SFID for Account Record Type: "Vendor"
        'RecordTypeId' => '012C0000000I0hCIAS',
      ];
      return $this->updateSalesforce('Account', $fieldData);
    }

  }

  /**
   * Add or update a SF Development.
   *
   * @param string $developmentName
   *   Development Name.
   * @param array $developmentData
   *   Submission Data.
   * @param string $contactId
   *   Contact ID.
   *
   * @return mixed
   *   Return SFID or false
   */
  public function upsertDevelopment(string $developmentName, array $developmentData, string $contactId) {
    $developmentSFID = $developmentData['developmentsfid'] ?? NULL;

    $fieldData = [
      'Name' => $developmentName,
      'Region__c' => !empty($developmentData['region']) ? $developmentData['region'] : 'Boston',
      'Street_Address__c' => $developmentData['street_address'] ?? '',
      'City__c' => !empty($developmentData['city']) ? $developmentData['city'] : 'Boston',
      'ZIP_Code__c' => $developmentData['zip_code'] ?? '',
      'Wheelchair_Access__c' => empty($developmentData['wheelchair_accessible']) ? FALSE : TRUE,
      'Listing_Contact_Company__c' => $developmentData['contact_company'] ?? NULL,
    ];

    if (isset($contactId)) {
      $fieldData['Listing_Contact__c'] = $contactId;

      // @TODO: change to Man_Comp_Contact__c and set the Account on the Contact and not the Development.
      $fieldData['Management_Company_Contact__c'] = $contactId;
    }

    if (isset($developmentData['neighborhood'])) {
      $fieldData['Neighborhood__c'] = !empty($developmentData['neighborhood']) ? $developmentData['neighborhood'] : NULL;
    }

    if (isset($developmentData['utilities_included'])) {
      $fieldData['Utilities_included__c'] = !empty($developmentData['utilities_included']) ? implode(';', $developmentData['utilities_included']) : NULL;
    }

    if (isset($developmentData['upfront_fees'])) {
      $fieldData['Due_at_signing__c'] = !empty($developmentData['upfront_fees']) ? implode(';', $developmentData['upfront_fees']) : NULL;
    }

    if (isset($developmentData['amenities_features'])) {
      $fieldData['Features__c'] = !empty($developmentData['amenities_features']) ? implode(';', $developmentData['amenities_features']) : NULL;
    }

    if (empty($developmentData['same_as_above_contact_info'])) {
      if (!empty($developmentData['public_contact_address'])) {
        $addr = $developmentData['public_contact_address'];
        $fieldData['Public_Contact_Address__c'] = $addr['address'] . "\r\n" . $addr['city'] . ", " . $addr['state_province'] . " " . $addr['postal_code'];
      }
      $fieldData['Public_Contact_Email__c'] = $developmentData['public_contact_email'];
      $fieldData['Public_Contact_Name__c'] = $developmentData['public_contact_name'];
      $fieldData['Public_Contact_Phone__c'] = $developmentData['public_contact_phone'];
    }
    else {
      if (!empty($developmentData['contact_address'])) {
        $addr = $developmentData['contact_address'];
        $fieldData['Public_Contact_Address__c'] = $addr['address'] . "\r\n" . $addr['city'] . ", " . $addr['state_province'] . " " . $addr['postal_code'];
      }
      $fieldData['Public_Contact_Email__c'] = $developmentData['contact_email'];
      $fieldData['Public_Contact_Name__c'] = $developmentData['contact_name'];
      $fieldData['Public_Contact_Phone__c'] = $developmentData['contact_phone'];
    }

    $result = $this->updateSalesforce('Development__c', $fieldData, 'Id', $developmentSFID ?? NULL);
    if (!$result) {
      \Drupal::logger('bos_metrolist')->error("Error encountered saving the Development. Addition/update of {$developmentName} was rejected by SF.");
    }
    return $result;


  }

  /**
   * Add or update a SF Units.
   *
   * @param array $developmentData
   *   Submission Data.
   * @param string $developmentId
   *   Development ID.
   *
   * @return mixed
   *   Return SFID or false
   */
  public function addUnits(array $developmentData, string $developmentId) {

    $units = $developmentData['units'];

    if (!empty($units)) {
      foreach ($units as $unitGroup) {
        if (empty($unitGroup['unit_count'])) {
          continue;
        }

        $waitlist_open =  $developmentData['waitlist_open'] == 'Yes' || (empty($developmentData['waitlist_open']) ? FALSE : TRUE);
        $available_how = $developmentData['available_how'] == "first_come_first_serve" ? "First come, first served" : "Lottery";
        $unitGroup["price"] = !empty($unitGroup['price']) ? (double) filter_var($unitGroup['price'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : 0.0;
        $unitGroup["minimum_income_threshold"] = !empty($unitGroup['minimum_income_threshold']) ? (double) filter_var($unitGroup['minimum_income_threshold'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : 0.0;
        $unitGroup['bedrooms'] = !empty($unitGroup['bedrooms']) ? (double) $unitGroup['bedrooms'] : 0.0;
        $unitGroup['bathrooms'] = !empty($unitGroup['bathrooms']) ? (double) $unitGroup['bathrooms'] : 0.0;;

        for ($unitNumber = 1; $unitNumber <= $unitGroup['unit_count']; $unitNumber++) {

          $unitName = $developmentData['street_address'] . ' Unit #' . $unitNumber;

          // @TODO: Change out the values for some of these by updating the options values in the webform configs to match SF.
          $fieldData = [
            'Name' => $unitName,
            'Development_new__c' => $developmentId,
            'Availability_Status__c' => 'Pending',
            'Income_Restricted_new__c' => $developmentData['units_income_restricted'] ?? 'Yes',
            'Availability_Type__c' => $available_how,
//            'User_Guide_Type__c' => $waitlist_open ? 'Waitlist' : $available_how,
            'Occupancy_Type__c' => $developmentData['type_of_listing'] == 'rental' ? 'Rent' : 'Own',  // "Rent" or "Own"
            'Rent_Type__c' => $unitGroup['rental_type'] == 0 ? 'Fixed $' : 'Variable %',
            'Income_Eligibility_AMI_Threshold__c' => isset($unitGroup['ami']) ? $unitGroup['ami'] : 'N/A',
            'Number_of_Bedrooms__c' => $unitGroup['bedrooms'],
            'Rent_or_Sale_Price__c' => $unitGroup['price'],
            'ADA_V__c' => empty($unitGroup['ada_v']) ? FALSE : TRUE,
            'ADA_H__c' => empty($unitGroup['ada_h']) ? FALSE : TRUE,
            'ADA_M__c' => empty($unitGroup['ada_m']) ? FALSE : TRUE,
            'Waitlist_Open__c' => $waitlist_open,
          ];

          if(isset($developmentData['remove_posting_date'])) {
            $fieldData['Suggested_Removal_Date__c'] = $developmentData['remove_posting_date'];
          }

          if (isset($unitGroup['bathrooms'])) {
            $fieldData['Number_of_Bathrooms__c'] = $unitGroup['bathrooms'];
          }

          if (isset($unitGroup['minimum_income_threshold'])) {
            $fieldData['Minimum_Income_Threshold__c'] = $unitGroup['minimum_income_threshold'];
          }

          if (isset($developmentData['posted_to_metrolist_date'])) {
            $fieldData['Requested_Publish_Date__c'] = $developmentData['posted_to_metrolist_date'];
          }

          if (isset($developmentData['application_deadline_datetime'])) {
            $fieldData['Lottery_Application_Deadline__c'] = $developmentData['application_deadline_datetime'];
          }

          if (isset($developmentData['website_link'])) {
            $fieldData['Lottery_Application_Website__c'] = $developmentData['website_link'] ?? NULL;
          }

          if (isset($developmentData['pdf_upload']) && $developmentData['direct_visitors'] == 'pdf') {
            $fieldData['Lottery_Application_Website__c'] = \Drupal::service('file_url_generator')->generateAbsoluteString(File::load($developmentData['pdf_upload'])->getFileUri()) ?? NULL;
          }

          if ($result = $this->updateSalesforce('Development_Unit__c', $fieldData) === FALSE) {
            \Drupal::logger('bos_metrolist')->error("Error encountered adding Development Units. SF rejected the new record for unit {$unitNumber} in {$unitName}.");
            return FALSE;
          }

        }

      }
    }
    return TRUE;
  }

  /**
   * Update a SF Units.
   *
   * @param array $developmentData
   *   Submission Data.
   * @param string $developmentId
   *   Development ID.
   *
   * @return mixed
   *   Return SFID or false
   */
  public function updateUnits(array $developmentData, string $developmentId) {

    $currentUnits = $developmentData['current_units'];

    if (!empty($currentUnits)) {

      // Fetch the existing development unit information from SF.
      $salesForce = new MetroListSalesForceConnection();
      $sf_currentUnits = $salesForce->getUnitsByDevelopmentSid($developmentId);

      foreach ($currentUnits as $unit) {

        // Find the SF current values for this unit.
        $_sf_unit = NULL;
        $fieldData = [];
        if (empty($unit["sfid"])) {
          // Consider this a non-fatal issue, a change won't be made for this unit.
          \Drupal::logger('bos_metrolist')->warning("Error encountered updating Development Units. Existing unit has no SFID on form." . print_r($unit, TRUE));
          return FALSE;
        }
        foreach($sf_currentUnits as $_sf_unit) {
          if ($_sf_unit->id() == $unit["sfid"]) {
            $sf_unit = $_sf_unit->fields(); break;
          }
        }
        if (empty($sf_unit)) {
          // Consider this a non-fatal issue, a change won't be made for this unit.
          \Drupal::logger('bos_metrolist')->warning("Error encountered updating Development Units. The unit {$unit['sfid']} was not found in SalesForce." . print_r($unit, TRUE));
          continue;
        }

        $unitChanged = (
          intval(str_replace(["$",","], "", $unit["minimum_income_threshold"])) != intval($sf_unit["Minimum_Income_Threshold__c"] ?? 0)
          || intval(str_replace(["$",","], "", $unit["price"])) != intval($sf_unit["Rent_or_Sale_Price__c"] ?? 0)
        );

        if (empty($unit['relist_unit'])) {
          // The active listing checkbox is unselected.

          if (in_array($sf_unit["Availability_Status__c"] ?? "", ["Closed"])) {
            // The Availability status in SF is already Closed.
            // Don't change anything at all.
            continue;
          }
          else {
            // Mark this unit as closed. No need to change anything else on
            // this unit.
            $fieldData = ['Availability_Status__c' => 'Closed'];
          }
        }

        else {
          // The active listing checkbox is selected.

          if (in_array($sf_unit["Availability_Status__c"] ?? "", ["Available", "Pending", "Reviewed"])
            && !$unitChanged) {
            // The availabily status in SF is already Available
            // If the information on the units form is unchanged, do nothing.
            continue;
          }

          $unitRelist = in_array($sf_unit["Availability_Status__c"] ?? "", ["Closed"]);

          // The SF status is currently closed, or the submitted form is
          // changing the status of the unit.
          $waitlist_open =  $developmentData['waitlist_open'] == 'Yes' || (empty($developmentData['waitlist_open']) ? FALSE : TRUE);
          $available_how = $developmentData['available_how'] == 'first_come_first_serve' ? 'First come, first served' : 'Lottery';
          $fieldData = [
            'Availability_Status__c' => 'Pending',
            'Rent_Type__c' => $unit['rental_type'] == 0 ? 'Fixed $' : 'Variable %',
            'Rent_or_Sale_Price__c' => isset($unit['price']) ? (double) filter_var($unit['price'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : 0.0,
            ];
          if (!empty($unit['minimum_income_threshold'])) {
            $fieldData['Minimum_Income_Threshold__c'] = !empty($unit['minimum_income_threshold']) ? (double) filter_var($unit['minimum_income_threshold'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : 0.0;
          }
          if ($unitRelist) {
            $fieldData = array_merge($fieldData, [
              'Availability_Type__c' => $available_how,
//              'User_Guide_Type__c' => $waitlist_open ? 'Waitlist' : $available_how,
              'Waitlist_Open__c' => $developmentData['waitlist_open'] == 'Yes' || empty($developmentData['waitlist_open']) ? FALSE : TRUE,
              'Income_Restricted_new__c' => $developmentData['units_income_restricted'] ?? 'Yes'
            ]);

            if (!empty($developmentData['posted_to_metrolist_date'])) {
              $fieldData['Requested_Publish_Date__c'] = $developmentData['posted_to_metrolist_date'];
            }

            if (!empty($developmentData['application_deadline_datetime'])) {
              $fieldData['Lottery_Application_Deadline__c'] = $developmentData['application_deadline_datetime'];
            }
            if (!empty($developmentData['remove_posting_date'])) {
              $fieldData['Suggested_Removal_Date__c'] = $developmentData['remove_posting_date'];
              }

            if (!empty($developmentData['website_link'])) {
              $fieldData['Lottery_Application_Website__c'] = $developmentData['website_link'];
            }
          }
        }

        // Only make the update if we found some fields to update ...
        if (!empty($fieldData)) {
          if ($this->updateSalesforce('Development_Unit__c', $fieldData, NULL, $unit['sfid']) === FALSE) {
            \Drupal::logger('bos_metrolist')->error("Error encountered updating Development Units. SF rejected the update for {$unit['sfid']}.");
            return FALSE;
          }
        }

      }
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    parent::postSave($webform_submission, $update);
    if ($webform_submission->getElementData('formerrors') != 0) {
      \Drupal::logger('bos_metrolist')->error("Data saving issue.  Confirmation emails will not be sent (to MOH or Submitter).");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postLoad(WebformSubmissionInterface $webform_submission) {
    return;
  }

  /**
   * {@inheritdoc}
   */
  public function postCreate(WebformSubmissionInterface $webform_submission) {
    return;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(WebformSubmissionInterface $webform_submission) {

    if ($webform_submission->getWebform()->id() == "metrolist_listing") {

      switch ($webform_submission->getCurrentPage()) {

        case "your_contact_information":
          // Do nothing
          break;

        case "update_contact_information":
          /*
           * Populate the form with the Contact/Account info from SF.
           * If an existing contact is selected in the contact dropdown, then the
           * contactsfid field is set with the SF ID for that contact. For new
           * contacts the field is blank.
           */
          if ($contactSFID = $webform_submission->getElementData('select_contact')) {
            if ($contactSFID != 'new') {
              /* If the contact selected is not new and: either the contactsfid
               field is empty or the contactsfid does not equal the contact ID
               already selected, then fetch the contact from SF and populate the
               information into the form. */
              if (empty($webform_submission->getElementData('contactsfid'))
                || $contactSFID != $webform_submission->getElementData('contactsfid')) {

                // MAPPING ARRAY for Drupal field => SF field. Used for data taken
                // from the SF Development object (Contact).
                $fields = [
                  'contact_name' => 'Name',
                  'contact_address' => 'MailingAddress',
                  'contact_phone' => 'Phone',
                  'contact_company' => 'Account',
                ];

                $contactData = $this->client()
                  ->objectRead('Contact', $contactSFID);
                $accountData = $contactData->hasField('AccountId') ? $this->client()
                  ->objectRead('Account', $contactData->field('AccountId')) : NULL;

                foreach ($fields as $webform_field => $sf_field) {
                  if ($webform_field == 'contact_address') {
                    $sf_field = [
                      'address' => $contactData->field('MailingStreet'),
                      'city' => $contactData->field('MailingCity'),
                      'state_province' => $contactData->field('MailingState'),
                      'postal_code' => $contactData->field('MailingPostalCode'),
                      'address_2' => "",
                      'country' => "",
                    ];

                    $webform_submission->setElementData($webform_field, $sf_field);
                  }
                  elseif ($webform_field == 'contact_company' && $accountData) {
                    $sf_field = $accountData->field('Name');
                    $webform_submission->setElementData($webform_field, $sf_field);
                  }
                  else {
                    $webform_submission->setElementData($webform_field, $contactData->field($sf_field));
                  }
                }

                if (empty($webform_submission->getElementData("contact_email"))) {
                  $webform_submission->setElementData("contact_email", $contactData->field("Email"));
                }

                $webform_submission->setElementData('contactsfid', $contactSFID);
              }

            }
          }
          break;

        case "property_information":
        case "unit_information":
        case "public_listing_information":
          /*
           * Populate the form fields with the developments data from SF.
           */
          if ($developmentSFID = $webform_submission->getElementData('select_development')) {

            // MAPPING ARRAY for Drupal field => SF field. Used for data taken
            // from the SF Development object (Development__c).
            $fields = [
              'property_name' => 'Name',
              'city' => 'City__c',
              'region' => 'Region__c',
              'neighborhood' => 'Neighborhood__c',
              'street_address' => 'Street_Address__c',
              'zip_code' => 'ZIP_Code__c',
              'upfront_fees' => 'Due_at_signing__c',
              'amenities_features' => 'Features__c',
              'utilities_included' => 'Utilities_included__c',
              'public_contact_email' => 'Public_Contact_Email__c',
              'public_contact_name' => 'Public_Contact_Name__c',
              'property_management_company' => 'Listing_Contact_Company__c',
              'public_contact_phone' => 'Public_Contact_Phone__c',
              'public_contact_address' => 'Public_Contact_Address__c',
              'wheelchair_accessible' => 'Wheelchair_Access__c',
            ];

            /* If the development selected is not new and the developmentsfid
             field value does not equal the developmentID (including if it is
             empty) selected, then fetch the development from SF and populate
             the data into the form fields. */
            if ($developmentSFID != 'new') {
              /* This is an existing development, so grab the field data from
               SF.*/
              if ($developmentSFID != $webform_submission->getElementData('developmentsfid')) {
                /* The developmentsfid has not yet been set, or else the
                  selected item in the development dropdown has been changed. */

                $developmentData = $this->client()
                  ->objectRead('Development__c', $developmentSFID);

                foreach ($fields as $webform_field => $sf_field) {
                  if (NULL != $developmentData->field($sf_field)) {
                    if ($webform_field == 'upfront_fees' || $webform_field == 'amenities_features' || $webform_field == 'utilities_included') {
                      $sf_field = explode(';', $developmentData->field($sf_field));
                      $webform_submission->setElementData($webform_field, $sf_field);
                    }
                    elseif ($webform_field == 'public_contact_address') {
                      // @TODO: Re-factor this!!!
                      $sf_public_address = explode("\r\n", $developmentData->field('Public_Contact_Address__c'));
                      $sf_public_street = $sf_public_address[0];
                      $sf_public_address = explode(', ', $sf_public_address[1]);
                      $sf_public_city = $sf_public_address[0];
                      $sf_public_address = explode(' ', $sf_public_address[1]);
                      $sf_public_state = $sf_public_address[0];
                      $sf_public_zipcode = $sf_public_address[1];

                      $sf_field = [
                        'address' => $sf_public_street,
                        'city' => $sf_public_city,
                        'state_province' => $sf_public_state,
                        'postal_code' => $sf_public_zipcode,
                        'address_2' => "",
                        'country' => "",
                      ];
                      $webform_submission->setElementData($webform_field, $sf_field);
                    }
                    else {
                      $webform_submission->setElementData($webform_field, $developmentData->field($sf_field));
                    }
                  }
                }

                $salesForce = new MetroListSalesForceConnection();
                $currentUnits = $salesForce->getUnitsByDevelopmentSid($developmentSFID);

                $webform_submission->setElementData('current_units', NULL);
                $webformCurrentUnits = [];

                foreach ($currentUnits as $unitSFID => $currentUnit) {
                  if (!empty($currentUnit->field('Id'))
                    && !empty($currentUnit->field('Occupancy_Type__c'))) {
                    $adaUnit = [];

                    if ($currentUnit->field('ADA_H__c')) {
                      $adaUnit[] = '✓ Hearing';
                    }
                    if ($currentUnit->field('ADA_V__c')) {
                      $adaUnit[] = '✓ Visual';
                    }
                    if ($currentUnit->field('ADA_M__c')) {
                      $adaUnit[] = '✓ Mobility';
                    }

                    $adaUnit = !empty($adaUnit) ? implode("\r", $adaUnit) : "✕ None";

                    $summary = [($currentUnit->field('Occupancy_Type__c') == "Rent" ? "RENTAL" : "OWNERSHIP")];

                    if ($currentUnit->field('Number_of_Bedrooms__c')) {
                      $summary[] = "{$currentUnit->field('Number_of_Bedrooms__c')} Bed";
                    }
                    if ($currentUnit->field('Number_of_Bathrooms__c')) {
                      $summary[] = "{$currentUnit->field('Number_of_Bathrooms__c')} Bath";
                    }
                    if ($currentUnit->field('Income_Eligibility_AMI_Threshold__c') && $currentUnit->field('Income_Eligibility_AMI_Threshold__c') != "N/A") {
                      $summary[] = $currentUnit->field('Income_Eligibility_AMI_Threshold__c');
                    }
                    $summary = !empty($summary) ? implode("\r", $summary) : "";

                    $pending = "";
                    if ($currentUnit->field('Availability_Status__c') == "Pending"
                      || $currentUnit->field('Availability_Status__c') == "Reviewed") {
                      // Closed, Available
                      $pending = "PENDING REVIEW";
                    }
                    $notes = [];
                    if ($currentUnit->field('Suggested_Removal_Date__c')) {
                      $rmdate = "Remove " . date("m/d/Y", strtotime($currentUnit->field('Suggested_Removal_Date__c')));
                    }
                    if ($currentUnit->field('Availability_Type__c')) {
                      $a = $currentUnit->field('Availability_Type__c');
                      if ($currentUnit->field('Availability_Type__c') == "Lottery") {
                        $d = date("m/d/Y", strtotime($currentUnit->field('Lottery_Application_Deadline__c')));
                        $a .= " (end {$d})";
                      }
                      $notes[] = $a;
                    }
                    if ($rmdate
                      && in_array($currentUnit->field('Availability_Status__c'), [
                        "Available",
                        "Pending",
                      ])) {
                      $notes[] = $rmdate;
                    }
                    $notes = !empty($notes) ? "Notes: " . implode(": ", $notes) : "";

                    $webformCurrentUnits[] = [
                      'relist_unit' => $currentUnit->field('Availability_Status__c') != "Closed",
                      'ada' => $adaUnit,
                      'summary' => $summary,
                      'note' => $notes,
                      'pending' => $pending,
                      'ami' => $currentUnit->field('Income_Eligibility_AMI_Threshold__c'),
                      'bedrooms' => $currentUnit->field('Number_of_Bedrooms__c'),
                      'price' => $currentUnit->field('Rent_or_Sale_Price__c'),
                      'minimum_income_threshold' => $currentUnit->field('Minimum_Income_Threshold__c'),
                      'rental_type' => ($currentUnit->field('Rent_Type__c') == "Fixed $" ? 0 : 1),
                      'status' => $currentUnit->field('Availability_Status__c'),
                      'sfid' => $currentUnit->field('Id'),
                    ];
                  }
                }
                $webform_submission->setElementData('current_units', $webformCurrentUnits);
              }
            }
            else {
              // This is new development.
              if ($developmentSFID != $webform_submission->getElementData('developmentsfid')) {
                // The development has been changed from an existing to "new".
                // The form probably was previously filled out, so clear it.
                $fields[] = "developmentsfid";
                foreach ($fields as $webform_field => $sf_field) {
                  $newval = NULL;
                  if ($webform_field == "wheelchair_accessible") {
                    $newval = 0;
                  }
                  $webform_submission->setElementData($webform_field, $newval);
                }
                $webform_submission->setElementData('current_units', []);
                $webform_submission->setElementData('additional_units', []);
              }
            }

            /* Set the developmentsfid field on the form so that we don't
             grab this data again (unless the development is changed in
             the dropdown).
             -> Reloading the data will overwrite any data changes made by
                the submitter. */
            $webform_submission->setElementData('developmentsfid', $developmentSFID);

          }
          break;

      }

      parent::preSave($webform_submission);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $a = parent::getSummary();
    unset($a["#theme"]);
    $a["#markup"] = "Custom WebformHandler defined in module bos_metrolist";
    return $a;
  }

  /**
   * Helper function to interract with salesforce client.
   *
   * @param string $name The sf object name to use
   * @param array $params The data to insert/update into the sf object
   * @param string $key The unique key for the sf object for upsert (if missing then create will be used)
   * @param string $value The unique key value for upsert
   *
   * @return false|string The response from Salesforce.
   */
  private function updateSalesforce(string $name, array $params, $key = NULL, $value = NULL) {

    try {
      if ($value == "new") {
        // forces an upsert for new items.
        $value = NULL;
      }

      if (!empty($key) && !empty($value)) {
        $result = (string) $this->client()->objectUpsert($name, $key, $value, $params);
      }
      elseif (empty($key) && !empty($value)) {
        $this->client()->objectUpdate($name, $value, $params);
        $result = TRUE;
      }
      else {
        $result = (string) $this->client()->objectCreate($name, $params);
      }
      return $result;
    }
    catch (RestException | Exception $exception) {
      \Drupal::logger('bos_metrolist')->error($exception->getMessage());
      $this->messenger()->addError("Error saving submission");
      return FALSE;
    }

  }

  /**
   * @inheritdoc
   */
  public function validateForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {

    // Check Dev Unit business logic.
    if ($form["#form_id"] == "webform_submission_metrolist_listing_edit_form") {
      switch ($webform_submission->getCurrentPage()) {
        case "unit_information":

          $isNewBuilding = $form_state->getValue("select_development") == 'new';
          $addUnits = ($isNewBuilding || $form_state->getValue("add_additional_units") == 1);
          $updateUnits = (!$isNewBuilding && $form_state->getValue("update_unit_information") == 1);

          if ($updateUnits) {
            foreach ($form_state->getValue("current_units") as $key => $current_unit) {

              $isIncomeBased = !empty($current_unit["rental_type"]);
              $minIncomeValue = self::getIntVal($current_unit["minimum_income_threshold"]);
              $priceValue = self::getIntVal($current_unit["price"]);

              if ($current_unit["relist_unit"]) {
                if (!$isIncomeBased && !isset($priceValue)) {
                  $name = "current_units][items][{$key}][_item_][price";
                  $form_state->setErrorByName($name, "If eligibility is not income-based, the Price field is required");
                }
                elseif ($isIncomeBased && !isset($minIncomeValue)) {
                  $name = "current_units][items][{$key}][_item_][minimum_income_threshold";
                  $form_state->setErrorByName($name, "If eligibility is income-based, the Minimim Income field is required");
                }
              }
            }
          }

          if ($addUnits) {
            foreach ($form_state->getValue("units") as $key => $unit) {

              $isIncomeBased = !empty($unit["rental_type"]);
              $minIncomeValue = self::getIntVal($unit["minimum_income_threshold"]);
              $priceValue = self::getIntVal($unit["price"]);

              if (!empty($unit["unit_count"])) {
                if (!$isIncomeBased && !isset($priceValue)) {
                  $name = "units][items][{$key}][_item_][price";
                  $form_state->setErrorByName($name, "If eligibility is not income-based, the Price field is required");
                }
                elseif ($isIncomeBased && !isset($minIncomeValue)) {
                  $name = "units][items][{$key}][_item_][minimum_income_threshold";
                  $form_state->setErrorByName($name, "If eligibility is income-based, the Minimim Income field is required");
                }
              }
            }
          }
          break;

      }
    }

    // Complete other validation to see that all conditions and required fields
    // which are defined in the webform are compliant.
    parent::validateForm($form, $form_state, $webform_submission);

    // Update Salesforce.
    // Do this in this hook (rather than onSubmit / pre or post-save because we
    // can stop the form from submitting by adding a validation error.
    if (!$form_state->getErrors() && $form_state->getTriggeringElement()["#value"] == "Submit for Review") {
      /* If the form is being submitted and has no errors, then update
        salesforce and save the sfid's if we don't already have them. */

      $fieldData = $webform_submission->getData();

      // Reset this b/c it gets saved with the form.
      $webform_submission->setElementData('formerrors', 0);

      $header = "There was an error submitting your application and we could not process it.
          Please go back and check the form, and try to submit again.
          If you continue to get this error after you have thoroughly checked the form, please contact us (metrolist@boston.gov) with these details:
          ID: {$webform_submission->get("serial")->first()->getValue()["value"]} & ";

      /* Add or update the Account */
      if ($accountId = $this->upsertAccount($fieldData) ?? FALSE) {
        if (empty($fieldData['accountsfid'])) {
          $fieldData['accountsfid'] = $accountId;
          $webform_submission->setElementData('accountsfid', $accountId);
        }
      }
      else {
        // An error occurred.
        // Set this flag so that confirmation emails are not sent out.
        $webform_submission
          ->setElementData('formerrors', 'Could not save Account')
          ->save();
        $form_state->setErrorByName("submit", "{$header}Code: ML-101");
      }

      /* Add or update the Contact */
      if ($contactId = $this->upsertContact($fieldData) ?? FALSE) {
        if (empty($fieldData['contactsfid'])) {
          $webform_submission->setElementData('contactsfid', $contactId);
        }
      }
      else {
        // An error occurred.
        // Set this flag so that confirmation emails are not sent out.
        $webform_submission
          ->setElementData('formerrors', 'Could not save Contact')
          ->save();
        $form_state->setErrorByName("submit", "{$header}Code: ML-102");
      }

      /* Add or update the Development and Units */
      if ($developmentId = $this->upsertDevelopment($fieldData['property_name'], $fieldData, $contactId) ?? FALSE) {

        if (empty($fieldData['developmentsfid'])) {
          $webform_submission->setElementData("developmentsfid", $developmentId);
        }

        /* Add or update the Units */
        if ($fieldData['update_unit_information']) {
          if ($this->updateUnits($fieldData, $developmentId) === FALSE) {
            // An error occurred.
            // Set this flag so that confirmation emails are not sent out.
            $webform_submission
              ->setElementData('formerrors', 'Could not update Units')
              ->save();
            $form_state->setErrorByName("submit", "{$header}Code: ML-103");
          }
        }
        if ($this->addUnits($fieldData, $developmentId) === FALSE) {
          // An error occurred.
          // Set this flag so that confirmation emails are not sent out.
          $webform_submission
            ->setElementData('formerrors', 'Could not add Units')
            ->save();
          $form_state->setErrorByName("submit", "{$header}Code: ML-104");
        }
      }
      else {
        // An error occurred.
        // Set this flag so that confirmation emails are not sent out.
        $webform_submission
          ->setElementData('formerrors', 'Could not save Development')
          ->save();
        $form_state->setErrorByName("submit", "{$header}Code: ML-105");
      }

    }

  }

  /**
   * Tries to extract a numeric from a string.
   *
   * @param string $value
   * @param bool $intOnly If TRUE an integer is returned, if not a float is.
   *
   * @return float|int|string
   */
  private static function getIntVal(string $value, bool $intOnly = TRUE) {

    // Uses round to coerse a string into an int/float.

    try {
      if (is_numeric($value)) {
        return $intOnly ? round($value, 0) : round($value, 2);
      }
      if (NULL == $value || empty($value)) {
        return 0;
      }

      $value = preg_replace("/[\$,\s]/", "", $value);
      if (is_numeric($value)) {
        $value = $intOnly ? round($value, 0) : round($value, 2);
      }
    }
    catch (\Error $e) {
      // do nothing, the string could not be converted to a number ...
    }
    return $value;

  }
}
