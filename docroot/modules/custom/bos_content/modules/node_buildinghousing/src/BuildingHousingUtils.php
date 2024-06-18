<?php

namespace Drupal\node_buildinghousing;

use CommerceGuys\Addressing\Address;
use Drupal\Core\Entity\EntityInterface as EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\ProxyClass\Lock\DatabaseLockBackend;
use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;
use Drupal\file_entity\Entity\FileEntity;
use Drupal\node_buildinghousing\Commands\BHCommands;
use Drupal\node_buildinghousing\Form\SalesforceSyncSettings;
use Drupal\salesforce\Exception;
use Drupal\taxonomy\Entity\Term;

/**
 * BuildingHousingUtils - Utilities and helper functions for Building Housing.
 */
class BuildingHousingUtils {

  /**
   * Project Public Stage.
   *
   * @var string|null
   */
  public $publicStage = NULL;

  /**
   * Project Entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface|null
   */
  public $project = NULL;

  public $debug_cleanupLog = FALSE;

  private const bh_email = [
    "name" => "MOH",
    "email" => "DND@boston.gov"
  ];

  private const this_module = "node_buildinghousing";

  /**
   * Project Web Update Entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface|null
   */
  public $webUpdate = NULL;

  /**
   * Get any meetings from a WebUpdateId.
   *
   * @param string $webUpdateId
   *   Project Web Update ID.
   *
   * @return bool|EntityInterface[]|null
   *   False, Null, array or Entities
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function getMeetingsFromWebUpdateId(string $webUpdateId) {
    $meetings = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'field_bh_update_ref' => $webUpdateId,
        'type' => 'bh_meeting'
      ])
      ?? NULL;

    if ($meetings && count($meetings) >= 1) {
      return $meetings;
    }

    return FALSE;
  }

  /**
   * Get a meetings from an EventId.
   *
   * @param string $eventId
   *   Event ID.
   *
   * @return bool|EntityInterface[]|null
   *   False, Null, array or Entities
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function getMeetingFromEventId(string $eventId) {
    $meetings = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'field_bh_event_ref' => $eventId,
        'type' => 'bh_meeting'
      ])
      ?? NULL;

    if ($meetings && count($meetings) >= 1) {
      return array_shift($meetings);
    }

    return FALSE;
  }

  /**
   * Get a Project's WebUpdate.
   *
   * @param \Drupal\Core\Entity\EntityInterface $projectEntity
   *   Building Housing Project Entity.
   *
   * @return bool|mixed
   *   False, Web Update Entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function getWebUpdate(EntityInterface $projectEntity) {
    $webUpdate = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'field_bh_project_ref' => $projectEntity->id(),
        'type' => 'bh_update',
        'field_sf_web_update' => TRUE
      ])
      ?? NULL;

    if ($webUpdate) {
      if (count($webUpdate) > 1) {
        return $webUpdate[array_key_last($webUpdate)];
      }
      elseif (count($webUpdate) == 1) {
        return reset($webUpdate);
      }
    }

    return FALSE;
  }

  /**
   * Set the Project Weblink onto a Web Update to write back to SF.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Building Housing Project Entity.
   *
   * @return \Drupal\Core\GeneratedUrl|string|null
   *   URL Object, URL String, or null
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function setProjectWebLink(EntityInterface &$entity) {

    $project = $entity->get('field_bh_project_ref')->target_id ? \Drupal::entityTypeManager()
      ->getStorage('node')
      ->load($entity->get('field_bh_project_ref')->target_id) : NULL;

    if ($project) {
      $projectWebLink = $project->toLink()
        ->getUrl()
        ->setAbsolute(FALSE)
        ->toString() ?? NULL;
      $entity->set('field_bh_project_web_link', 'https://www.boston.gov' . $projectWebLink);

      return $projectWebLink;
    }
    return "";
  }

  /**
   * Set the Public Stage of a Project.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Building Housing Project Entity.
   *
   * @return string|null
   *   False or Public Stage that was set.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function setPublicStage(EntityInterface &$entity) {

    // @TODO: RM after no issues
    // $projectRecordType = \Drupal\taxonomy\Entity\Term::load($entity->get('field_bh_record_type')->target_id)->name->value ?? null;
    // $projectRecordType = $projectRecordType == '0120y0000007rw7AAA' ? 'Disposition' : $projectRecordType;
    // $projectRecordType = $projectRecordType == '012C0000000Hqw0IAC' ? 'NHD Development' : $projectRecordType;

    $projectRecordType = self::getProjectRecordType($entity);

    $projectStatus = $entity->get('field_bh_project_status')->target_id ? Term::load($entity->get('field_bh_project_status')->target_id)->name->value : NULL;

    $projectStage = $entity->get('field_bh_project_stage')->target_id ? Term::load($entity->get('field_bh_project_stage')->target_id)->name->value : NULL;

    $publicStages = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadTree('bh_public_stage') ?? NULL;

    foreach ($publicStages as $key => $publicStage) {
      $publicStages[$publicStage->name] = $publicStage->tid;
      unset($publicStages[$key]);
    }

    $projectCompeteDate = $entity->get('field_bh_project_complete_date')->value ?? NULL;

    $publicStage = NULL;

    // Rule B.
    if (in_array($projectRecordType, ['Disposition'])
      && in_array($projectStatus, ['Not Started', 'Hold', 'Suspended'])
    ) {
      $publicStage = 'Not Active';
    }

    // Rule C.
    if (in_array($projectRecordType, ['Disposition'])
      && in_array($projectStatus, ['Active'])
      && in_array($projectStage, [
        'Under Consideration',
        'Research for Disposition Underway',
        'Future Consideration',
        'Remnant - No Interest',
        'Urban Wild / Conservation',
        'Municipal Use',
        'Tax Taking / Subject to Redemption'
      ])
    ) {
      $publicStage = 'Not Active';
    }

    // Rule D.
    if (in_array($projectRecordType, ['Disposition'])
      && in_array($projectStatus, ['Active'])
      && in_array($projectStage, [
        'Community Meeting In Process',
        'RFP In Process'
      ])
    ) {
      $publicStage = 'Project Launch';
    }

    // Rule E.
    if (in_array($projectRecordType, ['Disposition'])
      && in_array($projectStatus, ['Active'])
      && in_array($projectStage, [
        'RFP Issued',
        'Proposal Review'
      ])
    ) {
      $publicStage = 'Selecting Developer';
    }

    // Rule F.
    if (in_array($projectRecordType, ['Disposition'])
      && in_array($projectStatus, ['Active'])
      && in_array($projectStage, [
        'Under Agreement',
        'Awarded',
        'Closing Underway'
      ])
    ) {
      $publicStage = 'City Planning Process';
    }

    // Rule G.
    if (in_array($projectRecordType, ['Disposition'])
      && in_array($projectStatus, ['Active'])
      && in_array($projectStage, ['In construction'])
    ) {
      $publicStage = 'In Construction';
    }

    // Rule H.
    if (in_array($projectRecordType, ['Disposition'])
      && in_array($projectStatus, ['Active'])
      && in_array($projectStage, ['Construction complete - Project open'])
    ) {
      $publicStage = 'Project Completed';
    }

    // Rule I.
    if (in_array($projectRecordType, ['Disposition'])
      && in_array($projectStatus, ['Completed'])
      // @TODO: ? What if the ProjectCompleteDate is null?
      && strtotime($projectCompeteDate) >= strtotime('-1 year')
    ) {
      $publicStage = 'Project Completed';
    }

    // Rule J.
    if (in_array($projectRecordType, ['NHD Development'])
      && in_array($projectStatus, ['Active'])
      && in_array($projectStage, [
        'Commitment',
        'Awarded',
        'Pre-Commitment Process',
        'Closing Underway',
        'Pre-development'
      ])
    ) {
      $publicStage = 'City Planning Process';
    }

    // Rule K.
    if (in_array($projectRecordType, ['NHD Development'])
      && in_array($projectStatus, ['Active'])
      && in_array($projectStage, ['In construction'])
    ) {
      $publicStage = 'In Construction';
    }

    // Rule L.
    if (in_array($projectRecordType, ['NHD Development'])
      && in_array($projectStatus, ['Active'])
      && in_array($projectStage, ['Construction complete - Project open'])
    ) {
      $publicStage = 'Project Completed';
    }

    // Rule M.
    if (in_array($projectRecordType, ['NHD Development'])
      && in_array($projectStatus, ['Completed'])
      && strtotime($projectCompeteDate) >= strtotime('-1 year')
    ) {
      $publicStage = 'Project Completed';
    }

    // Set the Public Stage on the Project or unset it if no rules apply.
    if ($publicStage) {
      $entity->set('field_bh_public_stage', [$publicStages[$publicStage]]);
    }
    else {
      $entity->set('field_bh_public_stage', []);
    }

    return $this->publicStage = $publicStage;
  }

  /**
   * Get a Project's Record Type (Disposition or Development)
   *
   * @param \Drupal\Core\Entity\EntityInterface $projectEntity
   *   Building Housing Project Entity.
   *
   * @return string|null
   *   Record Type of Project or null.
   */
  public static function getProjectRecordType(EntityInterface $projectEntity) {
    if (NULL == $projectEntity->get('field_bh_record_type')->target_id) {
      return NULL;
    }
    $projectRecordType = Term::load($projectEntity->get('field_bh_record_type')->target_id)->name->value ?? NULL;
    $projectRecordType = $projectRecordType == '0120y0000007rw7AAA' ? 'Disposition' : $projectRecordType;
    $projectRecordType = $projectRecordType == '012C0000000Hqw0IAC' ? 'NHD Development' : $projectRecordType;

    return $projectRecordType;
  }

  /**
   * Set a Street View Photo given coordinates.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Building Housing Project or Parcel Entity.
   * @param string $fieldName
   *   Name of the field to save the Street View Photo.
   *
   * @return bool
   *   True or False is a street view was found and saved to the project.
   */
  public function setStreetViewPhoto(EntityInterface &$entity, string $fieldName = 'field_bh_street_view_photo') {
    $streetViewPhotoSet = FALSE;

    if ($this->publicStage && $coordinates = $entity->get('field_bh_coordinates')->value) {

      $endpoint = 'https://maps.googleapis.com/maps/api/streetview/metadata';
      $googleMapsApiKey = 'AIzaSyD8aXv_AZ9dpY8asHiqIsxdNMOBmCGYguY';
      $size = '600x300';

      $client = \Drupal::httpClient();
      $streetViewMetaData = $client->get("$endpoint?size=$size&location=$coordinates&key=$googleMapsApiKey");
      $streetViewMetaData = $streetViewMetaData->getBody() ? json_decode($streetViewMetaData->getBody()) : NULL;

    }

    return $streetViewPhotoSet;
  }

  /**
   * Update project goals field display.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Building Housing Project Entity.
   */
  public function updateProjectGoalsFieldDisplay(EntityInterface &$entity) {

  }

  /**
   * Set (Create/Update) a Calendar Event (node:event) Entity from a BH Meeting.
   *
   * @param \Drupal\Core\Entity\EntityInterface $bh_meeting
   *   Building Housing Meeting Entity (bh_meeting).
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function setMeetingEvent(EntityInterface &$bh_meeting) {

    $contactEmail = $this::bh_email["email"];
    $contactName = $this::bh_email["name"];

    if ($bh_update = !$bh_meeting->get("field_bh_update_ref")
      ->isEmpty() ? $bh_meeting->get('field_bh_update_ref')
      ->referencedEntities()[0] : NULL) {
      $bh_project = !$bh_update->get('field_bh_project_ref')
        ->isEmpty() ? $bh_update->get('field_bh_project_ref')
        ->referencedEntities()[0] : NULL;

      if ($bh_project) {
        $contactEmail = $bh_project->get('field_project_manager_email')->value ?? $this::bh_email["email"];
        $contactName = $bh_project->get('field_bh_project_manager_name')->value ?? $this::bh_email["name"];
      }

    }

    // $event will be a meeting node (i.e. content type from main website def)
    if (!$bh_meeting->get('field_bh_event_ref')->isEmpty()) {

      // event already exists, so update it.
      $event = $bh_meeting->get('field_bh_event_ref')->referencedEntities()[0];
      $event->set('title', $bh_meeting->getTitle() ?? '');
      $event->set('body', $bh_meeting->get('body')->value ?? '');
      $event->set('field_intro_text', $bh_meeting->get('field_bh_meeting_goal')->value ?? '');
      $event->set('field_event_contact', $contactName);
      $event->set('field_email', $contactEmail);
      $event->set('field_event_date_recur', [
        'value' => $bh_meeting->get('field_bh_meeting_start_time')->value ?? '',
        'end_value' => $bh_meeting->get('field_bh_meeting_end_time')->value ?? '',
        'timezone' => 'America/New_York',
      ]);

      $this->_manage_meeting_location($event, $bh_meeting);
      $event->save();

    }
    else {
      // Create a new event.
      // Event Type: "Civic Engagement".
      $event_type = 1831;
      $event = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->create([
          'type' => 'event',
          'title' => $bh_meeting->getTitle() ?? '',
          'body' => $bh_meeting->get('body')->value ?? '',
          'field_intro_text' => $bh_meeting->get('field_bh_meeting_goal')->value ?? '',
          'field_event_contact' => $contactName,
          'field_email' => $contactEmail,
          'field_event_type' => [['target_id' => $event_type]],
          'field_event_date_recur' => [
            'value' => $bh_meeting->field_bh_meeting_start_time->value ?? '',
            'end_value' => $bh_meeting->field_bh_meeting_end_time->value ?? '',
            'timezone' => 'America/New_York',
          ],
        ]);

      $this->_manage_meeting_location($event, $bh_meeting);
      $event->setPublished(TRUE);
      $event->set('moderation_state', 'published');
      $event->save();

      $bh_meeting->set('field_bh_event_ref', ['target_id' => $event->id()]);
    }
  }

  public function setParcelGeoPolyData(EntityInterface &$entity) {
    $geoPolySet = FALSE;

    if ($entity) {

      try {

        $parcelId = $entity->getTitle();

        $endpoint = "https://services.arcgis.com/sFnw0xNflSi8J0uh/arcgis/rest/services/Parcels_2020/FeatureServer/8/query?outFields='geometry'&f=pgeojson&where=PID_LONG%20%3D%20'$parcelId'";

        $client = \Drupal::httpClient();
        $geoPolyMetaData = $client->get("$endpoint");
        $geoPolyMetaData = $geoPolyMetaData->getBody() ? json_decode($geoPolyMetaData->getBody()) : NULL;

        $points = [];

        if ($geoPolyMetaData && !empty($geoPolyMetaData->features)) {

          $coordinates = isset($geoPolyMetaData
              ->features[0]
              ->geometry
              ->coordinates)
            ? $geoPolyMetaData
              ->features[0]
              ->geometry
              ->coordinates[0]
            : [];

          foreach ($coordinates as $geoPoint) {
            if (is_string($geoPoint)) {
              $points[] = $geoPoint;
            }
            else if (count($geoPoint) != count($geoPoint, COUNT_RECURSIVE)) {
              // Array contains array elements
              BuildingHousingUtils::log("clean", "Geolocation from arcGIS is a multi-dimensional array: ParcelID: {$parcelId} - data: " . json_encode($geoPoint) . "\n");
            }
            else {
              $points[] = implode(' ', $geoPoint);
            }
          }

          if (count($points) > 0) {
            $points = implode(', ', $points);

            $polyString = "POLYGON (($points))";
            $entity->set('field_parcel_geo_polygon', ['wkt' => $polyString]);
            $geoPolySet = TRUE;
          }
        }

      }
      catch (Exception $exception) {
        //@TODO: Add error log msg
        return $geoPolySet;
      }
    }
    return $geoPolySet;
  }

  /**
   * Takes a string and tries to coerce into an address object
   *
   * @param string $address
   *
   * @return array
   */
  public static function setDrupalAddress(string $address, bool $isBoston = TRUE) {
    // Use this object because there are some nice address processing functions
    // we may leverage in the future....
    $drupal_address = new Address();

    // Country code is a required field.
    $drupal_address = $drupal_address->withCountryCode('US');

    if ($isBoston) {
      $drupal_address = $drupal_address
        ->withAdministrativeArea('MA')
        ->withLocality('Boston');

      // Do our best to take these parts out of the address string.
      $address = preg_replace("/[ ,]?us(a)?[ ,]?/i", "", $address);
      $address = str_ireplace("united states", "", $address);
      $address = str_ireplace("united states of america", "", $address);
      $address = preg_replace("/[ ,]?ma[, ]?/i", "", $address);
      $address = str_ireplace("massachusetts", "", $address);
      $address = str_ireplace("boston", "", $address);
    }

    // Try to parse the address
    foreach ([",", " "] as $separator) {

      if (strpos($address, $separator) !== FALSE) {

        $addparts = explode($separator, $address);

        foreach ($addparts as $addpart) {

          $addpart = trim($addpart);

          // Detect postal code
          if (is_numeric($addpart) && strlen($addpart) == 5) {
            if (!$drupal_address->getPostalCode()) {
              // Save postcode.
              $drupal_address = $drupal_address->withPostalCode($addpart);
            }
            // Remove postcode from the address string.
            $address = str_replace($addpart, "", $address);
          }

          if (strtolower($addpart) == "us"
            || strtolower($addpart) == "usa"
            || strtolower($addpart) == "united states"
            || strtolower($addpart) == "united states of america") {
            // Remove country from the address string.
            $address = str_replace($addpart, "", $address);
          }

          // Detect the state - shuld just be MA
          if (strtolower($addpart) == "ma"
            || strtolower($addpart) == "massachusetts") {
            if (!$drupal_address->getAdministrativeArea()) {
              // Save state (if MA) - kind of redundant, but good practice.
              $drupal_address = $drupal_address->withAdministrativeArea('MA');
            }
            // Remove state from the address string.
            $address = str_replace($addpart, "", $address);
          }

          // Detect boston
          if (strtolower($addpart) == "boston") {
            if (!$drupal_address->getLocality()) {
              // Save Boston as locality.
              $drupal_address = $drupal_address->withLocality('Boston');
            }
            // Remove Boston from the address string.
            $address = str_replace($addpart, "", $address);
          }

        }
      }

    }

    // Put whatever is left of the address string into the address line 1 ....
    $drupal_address = $drupal_address
      ->withAddressLine1(trim($address, ", "));

    return [
      'country_code' => 'US',
      'address_line1' => $drupal_address->getAddressLine1(),
      'locality' => $drupal_address->getLocality(),
      'administrative_area' => $drupal_address->getAdministrativeArea(),
      'postal_code' => $drupal_address->getPostalCode(),
    ];

  }

  /**
   * Sets the $event address and virtual meeting information.
   *
   * @param $event \Drupal\node\Entity\Node a bh_meeting node
   * @param $entity \Drupal\node\Entity\Node a bh_
   *
   * @return void
   */
  private function _manage_meeting_location(&$event, $bh_meeting) {

    // First set the virtual meeting (if there is one).
    if ($bh_meeting->get('field_bh_virt_meeting_web_addr')->value) {
      $event
        ->set('field_details_link', [
          'uri' => $bh_meeting->get('field_bh_virt_meeting_web_addr')->value,
          'title' => t('Join the Meeting'),
          'options' => [
            'attributes' => [
              'target' => '_blank',
            ],
          ],
        ])
        // Set a default address in case there is no physical address.
        ->set('field_address', [
          'address_line1' => t('THIS MEETING WILL BE HELD VIRTUALLY.'),
          'country_code' => "US", // required
        ]);
    }

    // Now set the physical address (if there is one).
    if ($bh_meeting->get('field_address')[0]
      && $bh_meeting->get('field_address')[0]->getValue()["address_line1"]) {
      $address = $bh_meeting->get('field_address')[0];
      $address->set("country_code", "US"); // required
      $event
        ->set('field_address', $address->toArray());
    }

  }

  public static function delete_bh_project($projects, $delete_parcel, $delete = FALSE, $log = FALSE) {

    // Can use
    //    drush php:eval "use Drupal\node_buildinghousing\BuildingHousingUtils; BuildingHousingUtils::delete_bh_project([13693871],TRUE);"
    // and re-import using:
    //    drush salesforce_pull:pull-query building_housing_projects --where="Id='a040y00000Z88KlAAJ'" --force-pull
    //    drush salesforce_pull:pull-query bh_parcel_project_assoc --where="Project__c='a040y00000Z88KlAAJ'" --force-pull
    //    drush salesforce_pull:pull-query bh_website_update --where="Project__c='a040y00000Z88KlAAJ'" --force-pull
    //    drush salesforce_pull:pull-query building_housing_project_update --where="Project__c='a040y00000Z88KlAAJ'" --force-pull
    //    drush salesforce_pull:pull-query bh_community_meeting_event --where="website_update__c = 'a560y000000WIP2AAO'" --force-pull
    // then to see what's imported:
    //    drush queue:list
    // then to import queue:
    //    drush queue:run cron_salesforce_pull (optionally --items-limit=X to restrict import)

    $node_storage = \Drupal::entityTypeManager()->getStorage("node");

    // Delete Projects and linked items.
    $log && self::log("cleanup", "\n=== PURGE STARTS\n");
    $log && self::log("cleanup", "Processing " . count($projects) . " Project Records. \n");

    $count = 0;
    foreach ($node_storage->loadMultiple($projects) as $bh_project) {
      self::deleteProject($bh_project, $delete_parcel, $delete, $log);
      $count++;
    }

    $log && self::log("cleanup", "=== PURGE ENDS\n");

    return $count;

  }

  /**
   * Completely deletes all objects imported during the Slaesforce Sync
   * processes.
   *
   * @param $delete bool Flag if delete should occur (FALSE === dry-run)
   * @param $log bool Should this action be logged
   * @param $lock DatabaseLockBackend Lock to manage flow
   *
   * @return int|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function delete_all_bh_objects($delete_parcel, $delete = FALSE, $log = FALSE, $lock = NULL) {

    $node_storage = \Drupal::entityTypeManager()->getStorage("node");

    if (!$lock) {
      $lock = \Drupal::lock();
    }

    $log && self::log("cleanup", "\n===CLEANUP STARTS\n");

    // Delete Projects and linked items.
    $projects = $node_storage->loadByProperties(["type" => "bh_project"]);
    $count = count($projects);
    $log && self::log("cleanup", "Processing " . count($projects) . " Project Records. \n");
    foreach ($projects as $bh_project) {
      $lock->acquire(SalesforceSyncSettings::lockname, 30);
      self::deleteProject($bh_project, $delete_parcel, $delete, $log);
    }
    unset($projects);

    // Delete any orphaned Updates and linked items.
    $updates = $node_storage->getQuery()
      ->accessCheck(TRUE)
      ->condition("type", "bh_update")
      ->execute();
    $log && self::log("cleanup", "\nThere are " . count($updates) . " orphaned Project Updates. \n");
    if ($delete) {
      foreach (array_chunk($updates, 500) as $chunk) {
        $upds = $node_storage->loadMultiple($chunk);
        foreach ($upds as $bh_update) {
          $lock->acquire(SalesforceSyncSettings::lockname, 30);
          self::deleteUpdate(NULL, $bh_update, $delete, $log);
        }
      }
      unset($upds);
    }
    unset($updates);

    // Delete orphaned Parcel Assocs.
    $parcel_assocs = $node_storage->getQuery()
      ->accessCheck(TRUE)
      ->condition("type", "bh_parcel_project_assoc")
      ->execute();
    $log && self::log("cleanup", "\nThere are " . count($parcel_assocs) . " orphaned parcel-project associations. \n");
    if ($delete) {
      foreach (array_chunk($parcel_assocs, 500) as $chunk) {
        $lock->acquire(SalesforceSyncSettings::lockname, 300);
        if (!empty($chunk) && count($chunk) >= 1) {
          self::deleteParcelAssoc($chunk, $delete, $log, $delete_parcel);
        }
      }
      unset($chunk);
      $log && self::log("cleanup", "  DELETED " . count($parcel_assocs) . " parcel-project associations. \n");
    }
    unset($parcel_assocs);

    // Delete orphaned Parcels.
    $parcels = $node_storage->getQuery()
      ->accessCheck(TRUE)
      ->condition("type", "bh_parcel")
      ->execute();
    $log && self::log("cleanup", "\nThere are " . count($parcels) . " orphaned parcels. \n");
    if ($delete) {
      foreach (array_chunk($parcels, 500) as $chunk) {
        $lock->acquire(SalesforceSyncSettings::lockname, 300);
        if (!empty($chunk) && count($chunk) >= 1) {
          self::deleteParcel($chunk, $delete, $log);
        }
      }
      $log && self::log("cleanup", "    DELETED " . count($parcels) . " parcel records.\n");
      unset($chunk);
    }
    unset($parcels);

    // Delete orphaned Meetings.
    $meetings = $node_storage->getQuery()
      ->accessCheck(TRUE)
      ->condition("type", "bh_meeting")
      ->execute();
    $log && self::log("cleanup", "\nThere are " . count($meetings) . " orphaned meetings. \n");
    if ($delete) {
      foreach (array_chunk($meetings, 500) as $chunk) {
        $mtgs = $node_storage->loadMultiple($chunk);
        foreach($mtgs as $mtg) {
          $lock->acquire(SalesforceSyncSettings::lockname, 30);
          self::deleteMeeting($mtg, $delete, $log);
        }
      }
      unset($chunk);
      $log && self::log("cleanup", "    DELETED " . count($meetings) . " meeeting records.\n");
    }
    unset($meetings);

    $log && self::log("cleanup", "===CLEANUP ends\n");

    return $count;

  }

  /**
   * Completely deletes a single project and related objects from the CMS.
   *
   * @param $bh_project EntityInterface The project to delete
   * @param $del_parcel bool Flag if parcels should also be deleted
   * @param $delete bool Flag if delete should occur (FALSE === dry-run)
   * @param $log bool Should this action be logged.
   *
   * @return void
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function deleteProject($bh_project, $delete_parcel, $delete, $log) {

    $log && self::log("cleanup", "Scanning PROJECT {$bh_project->getTitle()} ({$bh_project->id()})\n");


    $node_storage = \Drupal::entityTypeManager()->getStorage("node");

    // Find associated WebUpdates and delete those.
    // Do this first b/c images and docs are linked to both project and update.
    foreach ($node_storage->loadByProperties([
      "type" => "bh_update",
      "field_bh_project_ref" => $bh_project->id()
    ]) as $bh_update) {
      // Note: this deletes Website Updates objects and also images and files
      // linked to those objects.
      self::deleteUpdate($bh_project, $bh_update, $delete, $log);
    }

    // Now delete any images. These should be deleted by now, but do this just
    // in case.
    $images = $bh_project->get('field_bh_project_images')->referencedEntities();
    foreach ($images as $file) {
      self::deleteFile($file, [$bh_project->id()], $delete, $log);
    }

    // Now delete any docs. These should be deleted by now, but do this just
    // in case.
    $attachments = $bh_project->get('field_bh_attachment')
      ->referencedEntities();
    foreach ($attachments as $file) {
      self::deleteFile($file, [$bh_project->id()], $delete, $log);
    }

    if ($delete_parcel == NULL) {
      $config = \Drupal::config('node_buildinghousing.settings');
      $delete_parcel = $config->get('delete_parcel') ?? FALSE;
    }

    // Find Parcel-Project Associations and delete those.
    if ($bh_project->get('field_bh_parcel_id')->value) {
      foreach ($node_storage->loadByProperties([
        "type" => "bh_parcel_project_assoc",
        "field_bh_project_ref" => $bh_project->id(),
      ]) as $bh_parcel_assoc) {
        // Note deletes parcel-project association mapping objects, and also
        // the parcel objects referenced by the association mappings.
        self::deleteParcelAssoc($bh_parcel_assoc, $delete, $log, $delete_parcel);
      }
    }

    // If the bh_project itself is linked to a parcel, then delete that now.
    // Find associated Parcels and delete those.
    if ($delete_parcel && $bh_project->get('field_bh_parcel_id')->value) {
      foreach ($node_storage->loadByProperties([
        "type" => "bh_parcel",
        "title" => $bh_project->get('field_bh_parcel_id')->value,
      ]) as $bh_parcel) {
        self::deleteParcel($bh_parcel, $delete , $log, $delete_parcel);
      }
    }

    if ($delete) {
      $projectName = basename($bh_project->toUrl()->toString()) ?? 'unknown';
      $path = \Drupal::root() . \Drupal::service('file_url_generator')->generateString("public://buildinghousing/project/{$projectName}");
      // Remove the project folder from the system.
      self::recursiveDeleteFolder($path, $log);
      $bh_project->delete();
      $log && self::log("cleanup", "DELETED PROJECT {$bh_project->getTitle()} ({$bh_project->id()})\n\n");
    }
    else {
      $log && self::log("cleanup", "    Dry-run {$bh_project->getTitle()} ({$bh_project->id()}) NOT DELETED\n");
    }

  }

  public static function deleteUpdate($bh_project, $bh_update, $delete, $log) {

    $log && self::log("cleanup", "  Scanning UPDATE {$bh_update->getTitle()} ({$bh_update->id()})\n");

    $node_storage = \Drupal::entityTypeManager()->getStorage("node");

    $ids = [$bh_update->id()];
    !empty($bh_project) && $ids[] = $bh_project->id();

    $images = $bh_update->get('field_bh_project_images')->referencedEntities();
    foreach ($images as $file) {
      self::deleteFile($file, $ids, $delete, $log);
    }

    $attachments = $bh_update->get('field_bh_attachment')->referencedEntities();
    foreach ($attachments as $file) {
      self::deleteFile($file, $ids, $delete, $log);
    }

    // Find associated meetings and delete those.
    foreach ($node_storage->loadByProperties([
      "type" => "bh_meeting",
      "field_bh_update_ref" => $bh_update->id()
    ]) as $bh_meeting) {
      self::deleteMeeting($bh_meeting, $delete, $log);
    }

    if ($delete) {
      $count = ($bh_update->hasField("field_bh_text_updates") ? count($bh_update->field_bh_text_updates) : 0);
      $bh_update->delete();
      $count && $log && self::log("cleanup", "    Summary: {$count} text messages deleted\n");
      $log && self::log("cleanup", "  DELETED UPDATE {$bh_update->getTitle()} ({$bh_update->id()})\n");
    }
    else {
      $log && self::log("cleanup", "    Dry-run {$bh_update->getTitle()} ({$bh_update->id()}) NOT DELETED\n");
    }

  }

  public static function deleteMeeting($bh_meeting, $delete, $log) {

    $log && self::log("cleanup", "    Scanning MEETING {$bh_meeting->getTitle()} ({$bh_meeting->id()})\n");

    if ($bh_meeting->hasField('field_bh_event_ref')) {

      // Find events and delete those.
      $events = $bh_meeting->get('field_bh_event_ref')
        ->referencedEntities();

      foreach ($events as $event) {
        if ($delete) {
          $event->delete();
          $log && self::log("cleanup", "      DELETED EVENT {$event->getTitle()}\n");
        }
      }
    }
    if ($delete) {
      $log && self::log("cleanup", "      DELETED MEETING {$bh_meeting->getTitle()} ({$bh_meeting->id()})\n");
      $bh_meeting->delete();
    }
    else {
      $log && self::log("cleanup", "    Dry-run {$bh_meeting->getTitle()} ({$bh_meeting->id()}) NOT DELETED\n");
    }

  }

  public static function deleteParcel($bh_parcel, $delete, $log, $delete_parcel = NULL) {

    if ($delete_parcel === NULL) {
      $config = \Drupal::config('node_buildinghousing.settings');
      $delete_parcel = ($config->get('delete_parcel') === 1) ?? FALSE;
    }

    if ($delete_parcel) {

      if ($delete) {
        if (is_array($bh_parcel)) {
          $entities = \Drupal::entityTypeManager()
            ->getStorage("node")
            ->loadMultiple($bh_parcel);
          \Drupal::entityTypeManager()->getStorage("node")->delete($entities);
          $log && self::log("cleanup", "    DELETED " . number_format(count($bh_parcel), 0) . " PARCELS \n");
          unset($entities);
        }
        else {
          $bh_parcel->delete();
          $log && self::log("cleanup", "    DELETED PARCEL {$bh_parcel->get('field_bh_street_address_temp')->value} ({$bh_parcel->getTitle()})\n");
        }
      }

      else {
        $log && self::log("cleanup", "    Dry-run {$bh_parcel->get('field_bh_street_address_temp')->value} ({$bh_parcel->getTitle()}) NOT DELETED\n");
      }

    }

  }

  public static function deleteParcelAssoc($bh_parcel_assoc, $delete, $log, $delete_parcel) {

    if ($delete) {
      if (is_array($bh_parcel_assoc)) {
        $entities = \Drupal::entityTypeManager()->getStorage("node")->loadMultiple($bh_parcel_assoc);
        // Delete any parcels which are linked to this assoc.
        if ($delete_parcel) {
          $parcels = [];
          foreach ($entities as $entity) {
            if ($entity->hasField("bh_parcel_ref")) {
              $parcels[] = $entity->fields("bh_parcel_ref")->value;
            }
          }
          self::deleteParcel($parcels, $delete, $log);
        }

        \Drupal::entityTypeManager()->getStorage("node")->delete($entities);
        unset($entities);
      }
      else {
        $bh_parcel_assoc->delete();
        $log && self::log("cleanup", "  DELETED PROJECT-PARCEL ASSOC {$bh_parcel_assoc->getTitle()}\n");
      }
    }
    else {
      $log && self::log("cleanup", "    Dry-run {$bh_parcel_assoc->getTitle()} NOT DELETED\n");
    }

  }

  /**
   * Will check the reported usage of a file, and if the only entities using
   * this file are in the $ids array, then it is safe to delete the file object.
   * Deleting the file object, will also delete the associated physical file.
   *
   * @param $file FileEntity The file object we are checking if we can delete.
   * @param $ids array An array of "parent" entity ID's we are going to delete.
   * @param $delete bool Flag. FALSE = dry-run (nothing deleted)
   * @param $log bool Should we log this deletion?
   *
   * @return void
   */
  private static function deleteFile(FileEntity $file, array $ids, bool $delete, bool $log) {
    // Find all entities which reference this file.
    $usage = file_get_file_references($file,NULL, EntityStorageInterface::FIELD_LOAD_CURRENT);
    $count = 0;
    foreach ($usage as $field) {
      // Cycle through each usage and see if the linked entity_id is the entity
      // we are unlinking this file from.
      if (!empty($field["node"])) {
        foreach($ids as $id) {
          if ($id && array_key_exists($id, $field["node"])) {
            unset($field["node"][$id]);
          }
        }
        // $count = count of entities using this file which are not this entity.
        $count += count($field["node"]);
      }
    }
    if ($count == 0) {
      // If count = 0 then no other entity is using this file, and we can safely
      // delete it now.
      if ($delete) {
        if (!file_exists($file->getFileUri())) {
          $log && self::log("cleanup", "      NOTE: physical file '{$file->get("filename")->value}' ({$file->id()}) not found in filesystem\n");
        }
        $file->delete();
        $log && self::log("cleanup", "    DELETED FILE '{$file->get("filename")->value}' ({$file->id()})\n");
      }
      else {
        $log && self::log("cleanup", "      Dry-run '{$file->get("filename")->value}' ({$file->id()}) NOT DELETED\n");
      }

    }
    else {
      $log && self::log("cleanup", "    NOTE: '{$file->get("filename")->value}' ({$file->id()}) is linked by other entities.\n");
    }

  }

  public static function recursiveDeleteFolder($path, $log = FALSE) {
    $path = "/" . trim($path, "/");

    if (is_dir($path)) {
      foreach (scandir($path) as $file) {
        if ($file != ".." && $file != ".") {
          $file = "{$path}/{$file}";
          if (is_dir($file)) {
            self::recursiveDeleteFolder($file, $log);
            }
          else {
            self::log("cleanup", "    WARNING found and deleted orphaned file '{$file}'.\n");
            unlink($file);
          }
        }
      }
      rmdir($path);
    }
  }

  public static function log($file, $msg, $dated = FALSE) {
    switch ($file) {
      case "cleanup":
        $file = "public://buildinghousing/cleanup.log";
        break;
      case "violations":
        $file = "public://buildinghousing/entity-violations.log";
        break;
    }
    if ($dated) {
      $dt = new \DateTime();
      $msg = $dt->format("m/d H:i:s: ") . $msg;
    }

    $fs = fopen($file, 'a');
    fwrite($fs, $msg);
    fclose($fs);
  }

  public static function removeDateFilter(&$query) {
    // If the query has a date condition we want to remove it when drush
    // and --force-pull is used.
    // For some reason the --force-pull argument is not supported.
    if (!empty($query->conditions)
      && in_array("--force-pull", $_SERVER["argv"] ?? [])) {
      $dels = [];
      foreach ($query->conditions as $key => $condition) {
        if (!empty($condition["field"]) && str_contains(strtolower($condition["field"]), "date")) {
          $dels[] = $key;
        }
      }
      foreach (array_reverse($dels) as $key) {
        unset($query->conditions[$key]);
      }
    }

  }

  /**
   * Remove unwanted tags, nbsp's, extra spaces and wrap in-line URL's so that
   * a string will display well on the timeline.
   *
   * @param $body string The string to clean up.
   *
   * @return string|null reformatted string.
   */
  public static function sanitizeTimelineText(string $body) {
    // Remove unwanted tags, nbsp's and extra spaces in string.
    $body = strip_tags($body, "<a><b><i><br>");
    $body = html_entity_decode(str_replace("&nbsp;", " ", htmlentities($body)));
    $body = str_replace("<br>", " ", $body);
    $body = preg_replace("/\s{2,}/", " ", $body);
    // Ensure plain-text, in-line URLs are properly wrapped with anchors.
    $body = preg_replace(['/([^"\'])(http[s]?:.*?)([\s\<]|$)/'], ['${1}<a href="${2}">${2}</a>${3}'], $body);
    return $body;
  }

  public static function appendTemp(string $var="temp_var", $data=NULL, $expiry=0, $shared=FALSE) {

    $service = $shared ? "tempstore.shared" : "keyvalue.expirable";

    if (!empty($data)) {

      $existing_val = \Drupal::service($service)
        ->get(self::this_module)
        ->get($var);

      if (!empty($existing_val)) {

        if (is_array($existing_val)) {
          if (!is_array($data)) {
            $data = [$data];
          }
          $data = array_merge($existing_val, $data);
        }

        elseif (is_string($existing_val)) {
          if (is_array($data)) {
            $existing_val = [$existing_val];
            $data = array_merge($existing_val, $data);
          }
          else {
            $data = $existing_val . (string) $data;
          }
        }

        else {
          $data = (string) $existing_val . (string) $data;
        }

      }

      self::setTemp($var, $data, $expiry, $shared);
    }

  }

  public static function setTemp(string $var="temp_var", $data=NULL, $expiry=0, $shared=FALSE) {

    $service = $shared ? "tempstore.shared" : "keyvalue.expirable";
    // Shared key:value store has no expiry concept.
    $expiry = $shared ? 0 : $expiry;

    $thisKey = \Drupal::service($service)
      ->get(self::this_module);

    if ($expiry) {
        $thisKey->setWithExpire($var, $data, $expiry);
    }
    else {
        $thisKey->set($var, $data);
    }
  }

  public static function getTemp(string $var = "temp_var", $shared=FALSE) {
    $service = $shared ? "tempstore.shared" : "keyvalue.expirable";

    return \Drupal::service($service)
      ->get(self::this_module)
      ->get($var) ?? "";
  }

  public static function resetTemp(string $var = "temp_var", $shared=FALSE) {
    self::setTemp($var, NULL, $shared);
  }

  public static function clearQueueLeases($queue = "cron_salesforce_pull", $mapping = "", $force = FALSE) {
    $query = \Drupal::database()->update('queue')
      ->fields(['expire' => 0])
      ->condition('expire', 0, '<>')
      ->condition('name', $queue, '=');

    if (!empty($mapping) && strtolower($mapping) != "all") {
      $query->condition('data', "%mappingId%{$mapping}%", 'like');
    }

    if (!$force) {
      $query->condition('expire', \Drupal::time()->getRequestTime(), '<');
    }

    $query->execute();

  }

  public static function logViolations($check, $title, $nid) {
    if (!$check->count() == 0) {
      BuildingHousingUtils::log("violations", "\nViolations found for {$title} (id={$nid}):\n");
      foreach ($check as $violation) {
        $msg = strip_tags($violation->getMessage());
        BuildingHousingUtils::log("violations", " -> {$msg}\n");
        BuildingHousingUtils::log("violations", "    : {$violation->getPropertyPath()}\n");
        $bad = $violation->getInvalidValue();
        if (is_object($bad)) {
          if ($violation->getRoot()->getEntity()->getEntityTypeId() == "file") {
            $uri = $bad->getRoot()->getEntity()->uri->value;
            BuildingHousingUtils::log("violations", "    : {$uri}\n");
          }
        }
      }
    }

  }

  public static function hasEntityViolations(&$entity, $fix = FALSE) {
    try {
      if ($check = $entity->validate()) {

        $count = $check->count();
        if ($count > 0) {

          // Log all violations.
          if ($entity->getEntityTypeId() == "file") {
            self::logViolations($check, $entity->getFileUri(), $entity->id());
          }
          else {
            self::logViolations($check, $entity->getTitle(), $entity->id());
          }

          // Fix things we can fix.
          if ($fix) {
            foreach ($check as $violation) {
              $field = $violation->getPropertyPath();
              if (str_contains($field, "alt_text") && str_contains($violation->getMessageTemplate(), "should not be null")) {
                $entity->set($field, "Shows Project Image");
                $count--;
              }
              elseif (str_contains($field, "title_text") && str_contains($violation->getMessageTemplate(), "should not be null")) {
                $entity->set($field, "Building Project Image");
                $count--;
              }
              elseif ((str_contains($field, "field_bh_attachment") || str_contains($field, "field_bh_project_images"))
                && str_contains($violation->getMessageTemplate(), "does not exist")) {
                $field = explode(".", $field);
                if (count($field) == 3) {
                  $fileItems = $entity->get($field[0]);
                  if ($fileItems[$field[1]]->{$field[2]} == $violation->getInvalidValue()) {
                    unset($fileItems[$field[1]]);
                    $count--;
                  }
                  else {
                    $c = 0;
                    while($fileItems[$c]->{$field[2]} != $violation->getInvalidValue()) {
                      $c++;
                      if ($c >= count($fileItems)) {
                        break;
                      }
                    }
                    if ($fileItems[$c]->{$field[2]} == $violation->getInvalidValue()) {
                      unset($fileItems[$c]);
                      $count--;
                    }
                  }
                }
              }
            }
          }

          // Find violations we don't really care about and ignore them.
          if ($count > 0) {
            foreach ($check as $violation) {
              $field = $violation->getPropertyPath();
              if (str_contains($violation->getPropertyPath(), "alt_text") && str_contains($violation->getMessageTemplate(), "should not be null")) {
                $count--;
              }
              elseif (str_contains($violation->getPropertyPath(), "title_text") && str_contains($violation->getMessageTemplate(), "should not be null")) {
                $count--;
              }
              elseif ((str_contains($field, "field_bh_attachment") || str_contains($field, "field_bh_project_images"))
                && str_contains($violation->getMessageTemplate(), "following extensions")) {
                $count--;
              }
            }
          }

        }
      }
    }
    catch (\Exception $e) {
      return TRUE;
    }

    return $count != 0;
  }

  /**
   * Fetch an array of $nids for entities which have a mapping for a SFID.
   *
   * @param string $sfid The salesforceID to search salesforce entity maps for.
   *
   * @return array|bool key=entityId, value=entityRevisionId FALSE if no mapped entity found.
   */
  public static function findEntityIdBySFID(string $sfid, string $type = NULL): array|bool {

    try {
      $nids = \Drupal::database()
        ->select('salesforce_mapped_object', 'sfo')
        ->fields('sfo', ['drupal_entity__target_id'])
        ->condition('salesforce_id', $sfid, '=')
        ->execute()
        ->fetchAll();

      // Filter for the desired type.
      if ($type) {
        $new = [];
        foreach ($nids as $nid) {
          $nodes = \Drupal::entityTypeManager()->getStorage("node");
          if (($entity = $nodes->load($nid->drupal_entity__target_id))
            && $entity->getType() == $type) {
            $new[] = $nid;
          }
        }
        $nids = $new;
      }

      return $nids;
    }
    catch (\Exception $e) {
      \Drupal::logger("building_housing")->error("{In BuildingHousingUtils.findEntityIdBySFID({$sfid}): $e->getMessage()}");
      return FALSE;
    }

  }

  /**
   * Fetch the SFID mapped to an entity.
   *
   * @param string|int|EntityInterface $nid The entity nid.
   *
   * @return string|bool SFID or FALSE if no mapped entity found.
   */
  public static function findMappedSFIDForEntity(int|string|EntityInterface $nid): string|bool {

    try {

      if (!is_string($nid) && !is_numeric($nid)) {
        if (!empty($nid->getEntityTypeId()) && $nid->getEntityTypeId() == "node") {
          $nid = $nid->id();
        }
        else {
          return FALSE;
        }
      }

      $sfids = \Drupal::database()
        ->select('salesforce_mapped_object', 'sfo')
        ->fields('sfo', ['salesforce_id'])
        ->condition('drupal_entity__target_id', $nid, '=')
        ->execute()
        ->fetchAll();
      $sfids = array_reverse($sfids);
      $sfid = array_pop($sfids);
      return $sfid->salesforce_id;

    }
    catch (\Exception $e) {
      \Drupal::logger("building_housing")->error("{In BuildingHousingUtils.findMappedSFIDForEntity({$nid}): $e->getMessage()}");
      return FALSE;
    }

  }

  /**
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param string $state
   *
   * @return bool TRUE if updated, FALSE if not
   */
  public function setProjectModerationState(EntityInterface &$entity): bool {

    if ($state = $this->findBannerTaxonomy($entity->field_bh_banner_status->target_id)) {

      // Now update the project (controls whether project homepage is visible).
      if ($project_nid = $entity->field_bh_project_ref->target_id) {
        if ($project = \Drupal::entityTypeManager()->getStorage("node")->load($project_nid)) {
          if (empty($project->get("moderation_state")->value)
            || strtolower($project->get("moderation_state")->value) != strtolower($state["mod_state"])) {
            $project->set("moderation_state", strtolower($state["mod_state"]));
            $project->setNewRevision(TRUE);
            try {
              $project->save();
            }
            catch(\Exception $e) {
              return FALSE;
            }
          }
          return TRUE;
        }
      }

    }

    return FALSE;

  }

  /**
   * Find the requested tid (assumes project_banner_bh_ vocab)
   *
   * @param $tid Taxonomy Id (aka target id on parent entity)
   *
   * @return array|bool
   */
  public static function findBannerTaxonomy($tid):array|bool {
    if (!$term = Term::load($tid)) {
      return FALSE;
    }
    return [
      "name" => $term->getName(),
      "tid" => $tid,
      "mod_state" => $term->field_banner_moderation_state->value,
      "show_banner" => $term->field_show_banner->value,
      "term" => $term
    ];
  }

  /**
   * Find the requested term (assumes project_banner_bh_ vocab) by its name
   *
   * @param $name String
   *
   * @return array|bool
   */
  public static function findBannerTaxonomyByName(string $name):array|bool {
    if (!$term = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(['name' => $name, "vid" => "project_banner_bh_"])) {
      return FALSE;
    }
    $term = reset($term);
    return [
      "tid" => $term->id(),
      "name" => $name,
      "mod_state" => $term->field_banner_moderation_state->value,
      "show_banner" => $term->field_show_banner->value,
      "term" => $term
    ];
  }

  /**
   * Checks if a supplied state exists in project_banner_bh_ taxonomy.
   *
   * @param $state the state to check.
   *
   * @return bool
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function isAllowedState($state):bool {
     return in_array(strtolower($state), self::getAllowedStates());
  }

  /**
   * Returns an array of $tid=>$name pairs for project_banner_bh_ taxonomy.
   *
   * @param $state
   *
   * @return array|bool
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function getAllowedStates(): array|bool {
    $output = [];
    foreach(\Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadTree('project_banner_bh_') ?? [] as $term) {
      $output[$term->tid] = strtolower($term->name);
    };
    return $output;
  }

}
