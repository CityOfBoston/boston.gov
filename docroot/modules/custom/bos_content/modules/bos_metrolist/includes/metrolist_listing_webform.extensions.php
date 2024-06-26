<?php

/**
 * @file
 * For ease of coding and debugging, this file includes customizations and hooks
 * specific to the metrolist-listing webform.
 */

use Drupal\bos_metrolist\MetroListSalesForceConnection;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

/**
 *  Extends hook_webform_element_alter() from bos_metrolist.module.
 *
 *  Populates dropdown boxes on MetrolistListing webform with info from
 *  SalesForce.
 *
 * @param array $element
 * @param FormStateInterface $form_state
 * @param array $context
 *
 * @return void
 * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
 * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
 */
function metrolist_listing_webform_element_alter(array &$element, FormStateInterface $form_state, array $context) {

  if (!isset($element['#webform_id'])) {
    return;
  }
  if ($context['form']["#webform_id"] != 'metrolist_listing' || $element["#webform"] != 'metrolist_listing'  ) {
    return;
  }

  $isTest = str_contains(\Drupal::request()->getRequestUri(), "/test");
  $isNewContact = TRUE;
  $isNewBuilding = TRUE;

  // For the dropdown boxes we need to get the webform submission - so we can
  // work out which page we are on, and the query needs to be run.
  if (in_array($element['#webform_id'], [
    'metrolist_listing--select_contact',
    'metrolist_listing--select_development',
    'metrolist_listing--units',
    'metrolist_listing--region',
    'metrolist_listing--city',
    'metrolist_listing--neighborhood',
  ])) {

    $salesForce = new MetroListSalesForceConnection();
    $webformSID = $element['#webform_submission'] ?? NULL;
    $webform_submission = $salesForce->webformSubmission() ?? NULL;

    //    if (!$webformSID && isset($form_state->getBuildInfo()["callback_object"])) {
    //      $salesForce->setWebformSubmission($form_state->getBuildInfo()["callback_object"]->getEntity());
    //    }

    // Load the webform submission from the submissionID supplied.
    if ($webformSID && !isset($webform_submission)) {
      $salesForce->loadWebformSubmission($webformSID);
      $webform_submission = $salesForce->webformSubmission() ?? NULL;
    }

    if (!$webform_submission) {
      // Last ditch attempt to get the submission from the token.
      if (str_contains(\Drupal::request()->getQueryString() ?? "", "token")) {
        $token = explode("=", \Drupal::request()->getQueryString() ?? "=")[1];
        $webform_submission_storage = \Drupal::entityTypeManager()->getStorage('webform_submission');
        if ($entities = $webform_submission_storage->loadByProperties(['token' => $token])) {
          $webformSID = array_key_first($entities);
          $webform_submission = reset($entities);
        }
      }
    }

    if ($webform_submission) {
      $currentPage = $webform_submission->getCurrentPage() ?? "your_contact_information";
      $isNewContact = ($webform_submission->getElementData("select_contact") == "new");
      $isNewBuilding = ($webform_submission->getElementData("select_development") == "new");
    }
    else {
      // This error is typically caused because the metrolist_listing submission
      // and token was generated by the metrolist_listing_entry_form
      // in bos_metrolist_webform_submission_presave(). The confirmation email
      // was sent but the new metrolist_listing submission did not save.
      // This may be because the database is locked during the pre_save of the
      // entry_form submission and therefore if the entry_form submission does
      // not save, then neither does the new metrolist_listing submission.
      if (!$isTest) {
        \Drupal::logger("bos_metrolist")->error("This token does not exist.",);
        throw new BadRequestException("This token does not exist", 404);
      }
    }

  }

  switch ($element['#webform_id']) {
    case 'metrolist_listing--select_contact':
      if ($currentPage == "your_contact_information") {
        $contactOptions = $salesForce->getContactOptionsByEmail($salesForce->getContactEmail());
        if ($contactOptions) {
          $element['#options'] = array_merge($element['#options'], $contactOptions);
        }
      }
      break;

    case 'metrolist_listing--select_development':
      if (!empty($currentPage) && $currentPage == "select_building") {
        $contactSID = $webform_submission->getElementData('select_contact') ?? NULL;
        $developmentOptions = $salesForce->getDevelopmentOptionsByContactSid($contactSID);
        if ($developmentOptions) {
          $element['#options'] = array_merge($element['#options'], $developmentOptions);
        }
      }
      break;

    case 'metrolist_listing--units':
      if (!empty($currentPage) && $currentPage == "unit_information") {
        $excludeOptions = ['Homeless Set Aside'];
        $amiOptions = $salesForce->getPickListValues('Development_unit__C', 'Income_Eligibility_AMI_Threshold__c', $excludeOptions) ?? NULL;
        if ($amiOptions) {
          $element['#element']['ami']['#options'] = $amiOptions;
        }
      }
      break;

    case 'metrolist_listing--region':
      if ($isNewBuilding && !empty($currentPage) && $currentPage == "property_information") {
        $regionOptions = $salesForce->getPickListValues('Development__C', 'Region__c') ?? NULL;
        if ($regionOptions) {
          $element['#options'] = $regionOptions;
        }
      }
      break;

    case 'metrolist_listing--city':
      if ($isNewBuilding && !empty($currentPage) && $currentPage == "property_information") {
        $cityOptions = $salesForce->getPickListValues('Development__C', 'City__c') ?? NULL;
        if ($cityOptions) {
          $element['#options'] = $cityOptions;
        }
      }
      break;

    case 'metrolist_listing--neighborhood':
      if ($isNewBuilding && !empty($currentPage) && $currentPage == "property_information") {
        $neighborhoodOptions = $salesForce->getPickListValues('Development__C', 'Neighborhood__c') ?? NULL;
        if ($neighborhoodOptions) {
          $element['#options'] = $neighborhoodOptions;
        }
      }
      break;
  }

}

/**
 * Implements hook_preprocess_form_alter()
 *
 * @param $variables
 *
 * @return void
 */
function bos_metrolist_preprocess_form_element(&$variables) {
  if (isset($variables["element"]["#webform_id"]) && $variables["element"]["#webform_id"] == "metrolist_listing--select_contact" && $variables["element"]["#type"] == "select") {
    if ($variables['attributes'] instanceof Attribute) {
      $variables["attributes"]->addClass("m-b300");
    }
    else {
      $variables["attributes"]["class"][] = "m-b300";
    }
  }
}
