<?php

namespace Drupal\node_buildinghousing\EventSubscriber;

use Drupal\Component\Utility\Environment;
use Drupal\Core\File\Exception\DirectoryNotReadyException;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node_buildinghousing\BuildingHousingUtils;
use Drupal\node_buildinghousing\Form\SalesforceSyncSettings;
use Drupal\salesforce\Event\SalesforceEvents;
use Drupal\salesforce\Exception;
use Drupal\salesforce\SelectQuery;
use Drupal\salesforce_mapping\Event\SalesforcePullEntityValueEvent;
use Drupal\salesforce_mapping\Event\SalesforcePullEvent;
use Drupal\salesforce_mapping\Event\SalesforceQueryEvent;
use Robo\Task\Docker\Build;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use \DateTime;
use \DateTimeZone;

/**
 * Class SalesforceBuildingHousingUpdateSubscriber.
 *
 * @package Drupal\node_buildinghousing
 */
class SalesforceBuildingHousingUpdateSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;
  private $now;

  /**
   * @var int The default maximum download size allowed when syncing docs from SF.
   *
   * From July 2023, this value is set on BH settings form.
   */
  private const maxdownload = 50; //megabytes

  private array $pull_info;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      // SalesforceEvents::PUSH_ALLOWED => 'pushAllowed',
      // SalesforceEvents::PUSH_PARAMS => 'pushParamsAlter',
      // SalesforceEvents::PUSH_SUCCESS => 'pushSuccess',
      // SalesforceEvents::PUSH_FAIL => 'pushFail',.
      SalesforceEvents::PULL_PRESAVE => 'pullPresave',
      SalesforceEvents::PULL_QUERY => 'pullQueryAlter',
      SalesforceEvents::PULL_PREPULL => 'pullPrepull',
      SalesforceEvents::PULL_ENTITY_VALUE => 'pullEntityvalue'
    ];
    return $events;
  }

  /**
   * SalesforceQueryEvent pull query alter event callback.
   *
   * @param \Drupal\salesforce_mapping\Event\SalesforceQueryEvent $event
   *   The event.
   */
  public function pullQueryAlter(SalesforceQueryEvent $event) {
    $mapping = $event->getMapping();
    $query = $event->getQuery();
    switch ($mapping->id()) {
      case 'building_housing_projects':
        // The project filter defined in settings for the mapping filters for
        // NHD type or disposition type projects.
        $query->fields['Project_Manager__c'] = 'Project_Manager__c';
        break;

      case 'bh_website_update':
        // Gather related attachment and document records.
        $query->fields["CreatedById"] ="CreatedById";
        $query->fields["LastModifiedById"] ="LastModifiedById";
        $query->fields[] = "(SELECT Id, ContentType, Name, Description, BodyLength FROM Attachments LIMIT 20)";
        $query->fields[] = "(SELECT ContentDocumentId, ContentDocument.CreatedDate, ContentDocument.Description, ContentDocument.ContentModifiedDate, ContentDocument.FileExtension, ContentDocument.Title, ContentDocument.ContentSize, ContentDocument.FileType, ContentDocument.LatestPublishedVersionId FROM ContentDocumentLinks LIMIT 20)";
        break;

      case 'building_housing_project_update':
        // LEGACY UPDATES: Gather related attachment records.
        $query->fields[] = "(SELECT Id, ContentType, Name, Description FROM Attachments LIMIT 20)";

        break;

      case 'bh_parcel_project_assoc':
        // Filter for NHD type or disposition type - sames as filter on the
        // project objects.
        $query->fields[] = "Project__r.RecordTypeID";
        $query->addCondition("Project__r.RecordTypeID", "('0120y0000007rw7', '012C0000000Hqw0')", "IN");
        break;

      case 'building_housing_parcels':
        // Add in the Parcel_Project_Association relationship so PullPrepull can
        // filter out parcels without an association.
        // For some reason the SOQL "join" cannot be used to filter out nulls so
        // all 180,000 parcels are added to the queue at this point.
        $query->fields[] = "(SELECT Id, Name FROM Parcel_Projects__r)";
        //        $query->limit = 200;  // for dev/testing
        // This will effectively stop parcels from being queued when the flag
        // is set in the config object.
        $config = \Drupal::config('node_buildinghousing.settings');
        $delete_parcel = ($config->get('delete_parcel') === 1) ?? FALSE;
        if (!$delete_parcel) {
          $query->addCondition("Name", "'neverresolves'", "=");
        }
        break;
    }
    BuildingHousingUtils::removeDateFilter($query);
  }

  /**
   * {@inheritdoc}
   */
  function pullPrepull(SalesforcePullEvent $event) {

    $mapping = $event->getMapping();

    switch ($mapping->id()) {
      case "bh_parcel_project_assoc":
        return;

      case "building_housing_parcels":

        $sf_data = $event->getMappedObject()->getSalesforceRecord();

        // This filter is used to prevent parcels in the queue which do not
        // have associated Project_Parcel_Associations from being processed.
        // IMPORTANT or else 180k records get added and 178k are not needed ...
        if ($event->isPullAllowed()
          && empty($sf_data->field("Parcel_Projects__r"))) {
          // disallowPull() stops this $event (i.e. sf sync) from processing.
          // However, be aware the queue worker thinks it has been sucessfully
          // processed and the original item is removed from the queue.
          $event->disallowPull();
        }

        // This filter is an absolute trap to stop any parcels which have queued
        // from being processed.
        if ($event->isPullAllowed()) {
          $config = \Drupal::config('node_buildinghousing.settings');
          if (!($config->get('delete_parcel') === 1) ?? FALSE) {
            // disallowPull() stops this $event (i.e. sf sync) from processing.
            // However, be aware the queue worker thinks it has been sucessfully
            // processed and the original item is removed from the queue.
            $event->disallowPull();
          }
        }

    }

  }

  function pullEntityvalue(SalesforcePullEntityValueEvent $event) {
    return;
  }

  /**
   * Pull presave event callback.
   *
   * @param \Drupal\salesforce_mapping\Event\SalesforcePullEvent $trigger_event
   *   The event.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function pullPresave(SalesforcePullEvent $trigger_event) {

    $mapping = $trigger_event->getMapping();
    $config = \Drupal::config('node_buildinghousing.settings');

    $this->pull_info = $mapping->get("pull_info");

    // Run additional actions to be performed during a PULL event.
    switch ($mapping->id()) {

      case 'bh_community_meeting_event':
        try {
          $bh_meeting = $trigger_event->getEntity();
          $sf_data = $trigger_event->getMappedObject()->getSalesforceRecord();

          $log = $config->get("log_actions");
          $log && BuildingHousingUtils::log("cleanup", "    ADDING MEETING {$sf_data->field("Title__c")}.\n");
          $log && BuildingHousingUtils::log("cleanup", "      ADDING EVENT {$sf_data->field("Title__c")}.\n");

          if ($supportedLanguages = $sf_data->field('Languages_supported__c')) {
            $supportedLanguages = explode(';', $supportedLanguages);
            $supportedLanguages = implode(', ', $supportedLanguages);
            $bh_meeting->set('field_bh_languages_supported', $supportedLanguages);
          }

          // Validate all URL-based fields in the object
          foreach ([
                     'Virtual_meeting_web_address__c' => 'field_bh_virt_meeting_web_addr',
                     'Post_meeting_recording__c' => 'field_bh_post_meeting_recording'
                   ] as $sf_field => $drupal_field) {
            if ($url = $sf_data->field($sf_field)) {
              $url = $this->_validateUrl($url, $sf_field, $sf_data);
              $bh_meeting->set($drupal_field, $url);
            }
          }

          // Try to break apart the address into parts for Drupal.
          if ($address = $sf_data->field('Address__c')) {
            $bh_meeting->set("field_address", BuildingHousingUtils::setDrupalAddress($address));
          }
        }
        catch (Exception $exception) {
          // nothing to do
        }

        // Todo: check if this is redundant.
        //   if it is, then remove it here and in each case statement below.
        // $bh_meeting->save();

        break;

      case "bh_parcel_project_assoc":
      case "building_housing_parcels":

        $sf_data = $trigger_event->getMappedObject()->getSalesforceRecord();
        $log = $config->get("log_actions");
        $space = ($mapping->id() == $mapping->id() ? "  ": "    ");
        $log && BuildingHousingUtils::log("cleanup", "{$space}PROCESSING {$mapping->id()} {$sf_data->field("Name")} ({$sf_data->field("Id")}).\n");
        break;

      case 'building_housing_projects':

        $bh_project = $trigger_event->getEntity();
        $sf_data = $trigger_event->getMappedObject()->getSalesforceRecord();
        $client = \Drupal::service('salesforce.client');

        $log = $config->get("log_actions");
        $log && BuildingHousingUtils::log("cleanup", "PROCESSING Project {$sf_data->field("Name")} ({$sf_data->field("Id")}).\n");

        try {
          $projectManagerId = $sf_data->field('Project_Manager__c')
            ?? $client->objectRead('Project__c', $sf_data->id())
            ->field('Project_Manager__c')
            ?? NULL;

          if ($projectManagerId) {
            $projectManager = $client->objectRead('User', $projectManagerId);
          }
          else {
            $projectManager = NULL;
          }
        }
        catch (Exception $exception) {
          $projectManager = NULL;
        }

        if ($projectManager) {
          $bh_project->set('field_bh_project_manager_name', $projectManager->field('Name'));
          $bh_project->set('field_project_manager_email', $projectManager->field('Email'));
          $bh_project->set('field_bh_project_manger_phone', $projectManager->field('Phone'));
        }

//        $bh_project->save();
        break;

      /*
       * building_housing_project_update is a mapping between the bh_update
       * object in Drupal and the Update__c object in SF.
       * The Update__c object is embedded in the Project__c object (on Related
       * tab) and is the *original* way that users added messages and files to
       * a bh_update object (for inclusion in the bh_project timeline).
       * In SF, files are attached to the Update__c record - these are
       * imported and attached to the Drupal bh_update and bh_project records.
       * Messages of Type "Text Update" made against the Website_Update__c
       * record are imported and attached to the Drupal bh_update record for
       * inclusion as messages on the timeline.
       *
       * bh_website_update is a mapping between the bh_update object in Drupal
       * and the Website_Update__c object in SF.
       * The Website_Update__c object is embedded in the Project__c object (on
       * the Related tab).
       * There should only be one Website_Update_c record per Project__c record.
       * In SF, files are attached to the Website_Update__c record - these are
       * imported and attached to the Drupal bh_update and bh_project records.
       * Chatter messages made against the Website_Update__c record are imported
       * and attached to the Drupal bh_update record for inclusion as messages
       * on the timeline.
       */
      case 'building_housing_project_update':
      case 'bh_website_update':
        $bh_update = $trigger_event->getEntity();
        $sf_data = $trigger_event->getMappedObject()->getSalesforceRecord();
        $this->now = (new DateTime("Now", new DateTimeZone("Z")))
          ->format("Y-m-d\TH:i:s.vT");

        if ($trigger_event->getOp() == "pull_delete") {
          return;
        }

        $log = $config->get("log_actions");
        $log && BuildingHousingUtils::log("cleanup", "  PROCESSING Website Update {$sf_data->field("Name")} ({$sf_data->field("Id")}).\n");

        // This is a new or updated bh_record.
        // Validate all URL-based fields in the object
        foreach (['Boston_gov_Link__c' => 'field_bh_project_web_link'] as $sf_field => $drupal_field) {
          if ($url = $sf_data->field($sf_field)) {
            $url = $this->_validateUrl($url, $sf_field, $sf_data);
            $bh_update->set($drupal_field, $url);
          }
        }

        // Read and process messages to go onto timeline.
        $delAttachments = TRUE;
        try {
          $text_updates = [];
          $this->getChatterMessages($text_updates, $sf_data, $bh_update);
          $this->getLegacyMessages($text_updates, $sf_data);
        }
        catch (\Exception $e) {
          $text = "Failed to request and parse text feeds. \n {$e->getMessage()}";
          \Drupal::logger('BuildingHousing')->error($text);
        }

        if ($text_updates) {
          try {
            $this->processTextUpdates($text_updates, $bh_update);
          }
          catch (\Exception $e) {
            // Unable to fetch file data from SF.
            $text = "Could not process Chatter messages.\nError reported: {$e->getMessage()}";
            \Drupal::logger('BuildingHousing')->error($text);
            // not fatal.
          }
        }

        // Read and process the (legacy) files attached to update__c object.
        $attachments = [];
        $query = new SelectQuery('Update__c');
        $query->fields = [
          'Id',
          'Name',
          "Project__c",
          "Type__c",
          "Publish_to_Web__c",
          "Update_Body__c",
          "Website_Update_Record__c",
        ];
        $query->fields[] = "(SELECT Id, ContentType, Name, Description, BodyLength FROM Attachments LIMIT 20)";
        $query->fields[] = "(SELECT ContentDocumentId, ContentDocument.CreatedDate, ContentDocument.ContentModifiedDate, ContentDocument.Description, ContentDocument.FileExtension, ContentDocument.Title, ContentDocument.FileType, ContentDocument.LatestPublishedVersionId, ContentDocument.ContentSize FROM ContentDocumentLinks LIMIT 20)";
        $query->addCondition("Project__c", "'{$sf_data->field('Project__c')}'", "=");
        try {
          $results = \Drupal::service('salesforce.client')->query($query);
        }
        catch (\Exception $e) {}
        foreach ($results->records() as $sfid => $update__c) {
          $this->getAttachments($attachments, $update__c);
        }

        // Read and process files attached to website_update__c object.
        $this->getAttachments($attachments, $sf_data);
        if (!empty($attachments)) {
          $this->processAttachments($bh_update, $attachments, $delAttachments);
        }

      break;
    }
  }

  private function getAttachments(&$attachments, $sf_data) {

    $fileTypeToDirMappings = [
      'image/jpeg' => 'image',
      'JPEG' => 'image',
      'image/jpg' => 'image',
      'JPG' => 'image',
      'image/png' => 'image',
      'PNG' => 'image',
      'application/pdf' => 'document',
      'PDF' => 'document',
    ];
    $authProvider = \Drupal::service('plugin.manager.salesforce.auth_providers');
    $docs = [];

    if ($sf_data->hasField('ContentDocumentLinks')
        && !empty($sf_data->field('ContentDocumentLinks'))) {
      foreach ($sf_data->field('ContentDocumentLinks')['records'] as $key => $attachment) {
        $attachmentVersionId = $attachment['ContentDocument']['LatestPublishedVersionId'] ?? '';
        $sf_download_url = $authProvider->getProvider()->getApiEndpoint();
        $sf_download_url .= "sobjects/ContentVersion/{$attachmentVersionId}";
        $docs[] = [
          "fileType" => $fileTypeToDirMappings[$attachment['ContentDocument']['FileType']] ?? 'other',
          "fileName" => $this->_sanitizeFilename("{$attachment['ContentDocument']['Title']}.{$attachment['ContentDocument']['FileExtension']}"),
          "createdDateTime" => strtotime($attachment['ContentDocument']['CreatedDate']) ?? time(),
          "updatedDateTime" => strtotime($attachment['ContentDocument']['ContentModifiedDate']) ?? time(),
          "sf_download_url" =>  "{$sf_download_url}/VersionData",
          "sf_id" => $attachment["ContentDocumentId"],
          "sf_version" => $attachment["ContentDocument"]["LatestPublishedVersionId"],
          "fileSize" => $attachment["ContentDocument"]["ContentSize"],
          "description" => $attachment["ContentDocument"]["Description"] ?? ($attachment['ContentDocument']['Title'] ?? ""),
          "fileExtension" => strtolower($attachment['ContentDocument']['FileExtension']),
        ];
      }
    }

    if ($sf_data->hasField('Attachments')
      && !empty($sf_data->field('Attachments'))) {
      // The Attachments field will contain a URL from which we can
      // download the attached file. We assume the file is a binary.
      // @see https://developer.salesforce.com/docs/atlas.en-us.api_rest.meta/api_rest/dome_sobject_blob_retrieve.htm
      $sf_download_url = $authProvider->getProvider()->getInstanceUrl();
      foreach ($attachments['records'] as $key => $attachment) {
        $sf_download_url = $attachment['attributes']['url'] . $sf_download_url . '/Body';
        foreach ($attachments['records'] as $key => $attachment) {
          $docs[] = [
            'fileType' => $fileTypeToDirMappings[$attachment['ContentType']] ?? 'other',
            "fileName" => $this->_sanitizeFilename($attachment['Name']),
            'createdDateTime' => strtotime($sf_data->field('CreatedDate')) ?? time(),
            'updatedDateTime' => strtotime($sf_data->field('LastModifiedDate')) ?? time(),
            "description" => $attachment["Description"] ?? ($attachment['Name'] ?? ""),
            "fileSize" => $attachment["BodyLength"] ?? 0,
            'sf_download_url' => $sf_download_url,
          ];
        }
      }
    }

    if (empty($docs)) {
      return $attachments;
    }
    $attachments = array_merge($attachments, $docs);
    return $attachments;

  }

  private function processAttachments($bh_update, $attachments, $delAttachments) {

    $config = \Drupal::config('node_buildinghousing.settings');
    $log = $config->get("log_actions");

    if (empty($attachments) || empty($bh_update)) {
      return;
    }
    if (empty($bh_update->get('field_bh_project_ref')->referencedEntities())
      || !$bh_project = $bh_update->get('field_bh_project_ref')->referencedEntities()[0]) {
      return;
    }

    $ea = array_merge($bh_update->get('field_bh_attachment')->referencedEntities(), $bh_update->get('field_bh_project_images')->referencedEntities());
    $existing_attachments = [];
    foreach ($ea as $existing_file) {
      $existing_attachments[$existing_file->id()] = $existing_file;
    }
    unset($ea);

    $save = FALSE;
    $projectName = basename($bh_project->toUrl()->toString()) ??  'unknown';
    $count_valid = 0;
    $count_new = 0;
    $count_update = 0;

    if (is_numeric(ini_get('memory_limit'))) {
      $mem_limit = intval(ini_get('memory_limit'));
    }
    else if (str_contains(ini_get('memory_limit'), "K") || str_contains(ini_get('memory_limit'), "k")) {
      $mem_limit = intval(str_replace(["K","k"], "", ini_get('memory_limit'))) * 1024;
    }
    else if (str_contains(ini_get('memory_limit'), "M") || str_contains(ini_get('memory_limit'), "m")) {
      $mem_limit = intval(str_replace(["M", "m"], "", ini_get('memory_limit'))) * 1024 * 1024;
    }
    else if (str_contains(ini_get('memory_limit'), "G") || str_contains(ini_get('memory_limit'), "g")) {
      $mem_limit = intval(str_replace(["G", "g"], "", ini_get('memory_limit'))) * 1024 * 1024 * 1024;
    }
    else {
      $mem_limit = 512 * 1024 * 1024;
    }

    foreach ($attachments as $key => $attachment) {

        $storageDirPath = "public://buildinghousing/project/{$projectName}/attachment/{$attachment["fileType"]}/";

        if (!\Drupal::service('file_system')->prepareDirectory($storageDirPath, FileSystemInterface::CREATE_DIRECTORY)) {
          // Issue with finding or creating the folder, try to continue to
          // next record.
          continue;
        }
        $destination = "{$storageDirPath}{$attachment["sf_id"]}.{$attachment["fileExtension"]}";

        $count_valid++ ;
        // Check the filesize
        $hasMemAvail = TRUE;
        $sz = number_format($attachment["fileSize"]/(1024 * 1024),"1");
        // Use the minumum of the filesize constant set in this module and
        // the max filesize set in PHP.
        $maxsz = $config->get("maxfilesize") ?: self::maxdownload;
        $canDownload = ($attachment["fileSize"] < ($maxsz * 1024 * 1024));
        // See if we have enough memory left to do this
        if ($canDownload) {
          $maxmem = ($mem_limit - memory_get_usage(TRUE)) / (1024 * 1024);
          $hasMemAvail = 0 < ($mem_limit - memory_get_usage(TRUE) - ($attachment["fileSize"] + (1024 * 1024)));
          $maxmem = number_format($maxmem, 1);
          if ($hasMemAvail) {
            $hasMemAvail = Environment::checkMemoryLimit($attachment["fileSize"]);
          }
        }

        if (!file_exists($destination)) {
          // New file, or updated file version, so save it.
          if (!$canDownload) {
            // File is greater than max allowable size - log and move on.
            // @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Range_requests
            // for ideas on using $client->httpRequest with headers to download
            // big files.
            \Drupal::logger('BuildingHousing')
              ->error("Did not fetch attachment {$attachment["sf_download_url"]} - size is greater that BH settings limit {$maxsz}MB (filesize={$sz}MB)");
            $log && BuildingHousingUtils::log("cleanup", "WARNING: Did not fetch attachment {$attachment["sf_download_url"]} - size is over {$maxsz}MB (filesize={$sz}MB)\n");
            continue;
          }
          if (!$hasMemAvail) {
            // Not enough memory allocated
            Environment::checkMemoryLimit($attachment["fileSize"]);
            \Drupal::logger('BuildingHousing')
              ->error("Did not fetch attachment {$attachment["sf_download_url"]} - size is greater than available memory {$maxmem}MB (filesize={$sz}MB)");
            $log && BuildingHousingUtils::log("cleanup", "WARNING: Did not fetch attachment {$attachment["sf_download_url"]} - size is greater than available memory {$maxmem}MB (filesize={$sz}MB)\n");
            continue;
          }
          if (!$file_data = $this->downloadAttachment($attachment["sf_download_url"])) {
            continue;
          }
          if (!$file = $this->saveAttachment($file_data, $destination, $attachment)) {
            unset($file_data);
            continue;
          }
          else {
            unset($file_data);
            $count_new++;
            $log && BuildingHousingUtils::log("cleanup", "    UPLOAD FILE {$attachment["fileName"]}.\n");

          }
        }
        else {
          // File already exists, so load it.
          if (!$file = $this->loadAttachment($destination, $attachment["createdDateTime"])) {
            continue;
          }
          if ($file->getChangedTime() != $attachment["updatedDateTime"]) {

            if (!$canDownload) {
              // File is greater than max allowable size - log and move on.
              // @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Range_requests
              // for ideas on using $client->httpRequest with headers to download
              // big files.
              \Drupal::logger('BuildingHousing')
                ->error("Did not fetch attachment {$attachment["sf_download_url"]} - size is greater that BH settings limit {$maxsz}MB (filesize={$sz}MB)");
              $log && BuildingHousingUtils::log("cleanup", "WARNING: Did not fetch attachment {$attachment["sf_download_url"]} - size is over {$maxsz}MB (filesize={$sz}MB)\n");
              continue;
            }
            if (!$hasMemAvail) {
              // Not enough memory allocated
              \Drupal::logger('BuildingHousing')
                ->error("Did not fetch attachment {$attachment["sf_download_url"]} - size is greater than available memory {$maxmem}MB (filesize={$sz}MB)");
              $log && BuildingHousingUtils::log("cleanup", "WARNING: Did not fetch attachment {$attachment["sf_download_url"]} - size is greater than available memory {$maxsz}MB (filesize={$sz}MB)\n");
              continue;
            }
            if (!$file_data = $this->downloadAttachment($attachment["sf_download_url"])) {
              continue;
            }
            if (!$file = $this->saveAttachment($file_data, $destination, $attachment)) {
              unset($file_data);
              continue;
            }
            else {
              unset($file_data);
              $log && BuildingHousingUtils::log("cleanup", "    UPDATE FILE OBJECT {$attachment["fileName"]}.\n");
              $count_update++;
            }
          }
        }
        // Now make a link between the file object amd the bh_ objects.
        if ($file && $this->linkAttachment($attachment, $file, [$bh_project, $bh_update])) {
          $save = TRUE;
        }
        unset($existing_attachments[$file->id()]);

    }

    // Remove any deleted files.
    $count_delete = count($existing_attachments);
    if ($delAttachments && $count_delete > 0) {
      foreach ($existing_attachments as $existing_file) {
        $log && BuildingHousingUtils::log("cleanup", "    DELETED FILE '{$existing_file->filename->value}'.\n");
        $existing_file->delete();
      }
    }


    $log && BuildingHousingUtils::log("cleanup", "      Summary: {$count_valid} File Attachments. {$count_new} Added: {$count_update} Updated: {$count_delete} Deleted.\n");

    if ($save) {
      // $bh_update gets automatically saved (later on) by the calling process.
      if (!BuildingHousingUtils::hasEntityViolations($bh_project, TRUE)) {
        $bh_project->save();
      }
      BuildingHousingUtils::hasEntityViolations($bh_update, TRUE);

    }

  }

  private function downloadAttachment($sf_download_url) {
    $client = \Drupal::service('salesforce.client');
    try {
      $file_data = $client->httpRequestRaw($sf_download_url);
    }
    catch (\Exception $e) {
      // Unable to fetch file data from SF.
      \Drupal::logger('BuildingHousing')
        ->error("Failed to fetch attachment {$sf_download_url} from Salesforce");
      return FALSE;
    }
    return $file_data;
  }

  private function saveAttachment($file_data, $destination, $attachment) {
    try {
      // If the file already exists, it will be overwritten.
      $file = \Drupal::service('file.repository')
        ->writeData($file_data, $destination, FileSystemInterface::EXISTS_REPLACE);
      if ($file) {
        // Set the created date specifically because we want it
        // to sync with the files' create datetime in Salesforce.
        $file->set('filename', $attachment['fileName']);
        $file->set('created', $attachment["createdDateTime"]);
        $file->set('changed', $attachment["updatedDateTime"]);
        $file->save();

        if (BuildingHousingUtils::hasEntityViolations($file, TRUE)) {
          throw new \Exception("File validation failed.");
        }
        return $file;
      }
    }
    catch (DirectoryNotReadyException|FileException|\Exception $e) {
      \Drupal::logger('BuildingHousing')
        ->error("Failed to save attachment to {$destination} from Salesforce. {$e->getMessage()}");

    }
    return FALSE;
  }

  private function loadAttachment($filepath, $createdDateTime) {
    try {
      $file = \Drupal::service('file.repository')->loadByUri($filepath);
      if (!$file) {
        $file = \Drupal::entityTypeManager()
          ->getStorage('file')
          ->loadByProperties(['uri' => $filepath]);
        if ($file) {
          $file = reset($file);
        }
      }
      // If the created date for the file is not the same as the
      // created date in Salesforce - then update the created date.
      // This is likely because a user changed the created date in
      // Salesforce in order to locate the file correctly on the
      // timeline.
      if ($file) {
        if ($file->get('created')->value != $createdDateTime) {
          $file->set('created', $createdDateTime);
          $file->save();
        }

        if (BuildingHousingUtils::hasEntityViolations($file, TRUE)) {
          throw new \Exception("File validation failed.");
        }

        return $file;
      }
    }
    catch (Exception $e) {
      \Drupal::logger('BuildingHousing')
        ->error("Failed to load an existing attachment file {$filepath} from local file system. {$e->getMessage()}");
    }
    return FALSE;
  }

  /**
   * @param $fileType string "image" or "document"
   * @param $file int The Id for a file object
   * @param $update_entities array of node entites to linke the file to
   *
   * @return bool True if a file was linked to a bh_project entity. False if no
   *              file was linked or if a file was linked to a bh_update.
   *              (bh_update will already be saved by the event calling process,
   *              so no need to save early in this class)
   */
  private function linkAttachment($attachment, $file, $update_entities ) {

    $fieldName = ($attachment["fileType"] == 'image' ? 'field_bh_project_images' : 'field_bh_attachment');

    if (empty($attachment["description"])) {
      $file_description = explode(".", $this->_sanitizeFilename($attachment["fileName"]));
      array_pop($file_description);
      $attachment["description"] = implode(" ", $file_description);
    }

    // Link the file to the two entities
    $save = FALSE;
    foreach($update_entities as $bh_entity) {
      if ($file->id() && $bh_entity->get($fieldName)->isEmpty()) {

        // Entity has no files linked, so simply link this file now.
        $target = [
          'target_id' => $file->id(),
          'alt' => "Project {$attachment["fileType"]}",
          'title' => $attachment["description"],
        ];
        if ($fieldName == "field_bh_attachment") {
          $target["description"] = $attachment["description"];
        }
        $bh_entity->set($fieldName, $target);
        $save = $save || ($bh_entity->bundle() !== "bh_update");
      }

      else {
        // Entity has files linked, so check if this file is linked
        $islinked = FALSE;
        if ($linked_files = $bh_entity->get($fieldName)->getValue()) {
          foreach ($linked_files as $linked_file) {
            if ($linked_file["target_id"] == $file->id()) {
              // OK, so the Entity already has this file linked - do nothing.
              $islinked = TRUE;
              continue;
            }
          }
        }
        if (!$islinked && $file->id()) {
          // Entity does not have the file already linked so link the file.
          $item = [
            'target_id' => $file->id(),
            'alt' => "Project {$attachment["fileType"]}",
            'title' => $attachment["description"]
          ];
          if ($fieldName == "field_bh_attachment") {
            $item["description"] = $attachment["description"];
          }
          $bh_entity->get($fieldName)->appendItem($item);
          $save = $save || ($bh_entity->bundle() !== "bh_update");
        }
      }
    }
    return $save;

  }

  private function getChatterMessages(&$text_updates, $sf_data, $bh_update) {

    try {
      $client = \Drupal::service('salesforce.client');
      $authProvider = \Drupal::service('plugin.manager.salesforce.auth_providers');
      $chatterFeedURL = $authProvider->getProvider()->getApiEndpoint() . "chatter/feeds/record/" . $sf_data->id() . "/feed-elements";
      $chatterData = $client->httpRequestRaw($chatterFeedURL);
      $text_updates = json_decode($chatterData, TRUE);
      $text_updates = $text_updates["elements"] ?? [];
      return $text_updates;
    }

    catch (\Exception $e) {
      $this->mailException([
        '@url' => "URL: {$chatterFeedURL}",
        '@err' => $e->getMessage(),
        '@update_id' => $bh_update->id()]);
      throw new \Exception("Chatter: {$e->getMessage()}", $e->getCode());
    }
  }

  private function getLegacyMessages(&$text_updates, $sf_data) {

    $query = new SelectQuery('Update__c');
    $client = \Drupal::service('salesforce.client');
    $query->fields = [
      'Id',
      'Name',
      "Project__c",
      "Type__c",
      "Publish_to_Web__c",
      "Update_Body__c",
      "Website_Update_Record__c",
      "CreatedById",
      "CreatedDate",
      "LastModifiedById",
      "LastModifiedDate",
    ];
    $query->addCondition("Project__c", "'{$sf_data->field("Project__c")}'", "=");
    $query->addCondition("Publish_to_Web__c", "TRUE", "=");
    $query->addCondition("Type__c", "'Text Update'", "=");
    try {
      $results = $client->query($query);
    }
    catch (\Exception $e) {
      $results = [];
    }

    $legacy_data = [];
    foreach($results->records() as $sfid => $update) {

      $pm_name = 'City of Boston';
      if ($projectManager = $client->objectRead('User', $update->field("LastModifiedById"))) {
        $pm_name = $projectManager->field('Name') ?? 'City of Boston';
      }

      if (!empty($update->field("Update_Body__c"))) {
        $legacy_data[] = [
          "type" => "TextPost",
          "body" => [
            "text" => BuildingHousingUtils::sanitizeTimelineText($update->field("Update_Body__c")),
            "isRichText" => FALSE,
          ],
          "actor" => [
            "displayName" => $pm_name,
          ],
          "capabilities" => [
            "edit" => [
              "lastEditedDate" => strtotime($update->field("LastModifiedDate")),
            ],
          ],
          "createdDate" => strtotime($update->field("CreatedDate")),
          'id' => $sfid,
        ];
      }
    }

    $text_updates = array_merge($text_updates, $legacy_data);
    return $text_updates ?? [];

  }

  private function processTextUpdates($textUpdateData, $bh_update) {
    // Check for chatter text updates.
    try {

      $currentTextUpdateIds = [];

      $config = \Drupal::config('node_buildinghousing.settings');
      $log = $config->get("log_actions");

      // Cache existing text updates in bh_update record for this property.
      foreach ($bh_update->field_bh_text_updates as $key => $currentTextUpdate) {
        $textData = $currentTextUpdate->getValue();
        $textData = json_decode($textData['value']);
        $currentTextUpdateIds[$textData->id] = $key;
      }

      $allowed_chatters = ["TextPost", "ContentPost"];
      $count_valid = 0;
      $count_new = 0;
      $count_update = 0;

      $log && BuildingHousingUtils::log("cleanup", "    PROCESSING Chatter and legacy messages.\n");

      // Process the Chatter messages provided.
      foreach ($textUpdateData as $post) {
        if (in_array($post["type"], $allowed_chatters)) {
          if (!empty($post["body"]["text"])) {
            $count_valid++;
            $message = (!empty($post["body"]["isRichText"]) ? $post["body"]["messageSegments"] : $post["body"]["text"]);
            $drupalPost = [
              'text' => $this->_reformatTextMessage($message, $post["body"]["isRichText"] ?? FALSE),
              'author' => $post["actor"]["displayName"] ?? 'City of Boston',
              'date' => $post["createdDate"] ?? $this->now,
              'updated' => $post["capabilities"]["edit"]["lastEditedDate"] ?? ($post["modifiedDate"] ?? $this->now),
              'id' => $post["id"] ?? '',
            ];
            if (!array_key_exists($post["id"], $currentTextUpdateIds)) {
              // New posts.
              $bh_update->field_bh_text_updates->appendItem(json_encode($drupalPost));
              $count_new++;
            }
            else {
              $key = $currentTextUpdateIds[$post["id"]];
              $textData = json_decode($bh_update->field_bh_text_updates[$key]->value);

              // Only update if the chatter message has been altered since the
              // last pull date. This means we can replay chatter messages as
              // well as webupdates, projects etc by manipulating the last
              // update flag.
              $textDataUpdated = $post["capabilities"]["edit"]["lastEditedDate"] ?? $post["modifiedDate"];
              if ($this->pull_info["last_pull_timestamp"] < $textDataUpdated
                  || strtotime($textData->updated) < $textDataUpdated) {
                // Updated posts.
                $bh_update->field_bh_text_updates->set($key, json_encode($drupalPost));
                $count_update++;

              }
              unset($currentTextUpdateIds[$post["id"]]);
            }
            if (!empty($currentTextUpdateIds) && array_key_exists($post["id"], $currentTextUpdateIds)) {
              unset($currentTextUpdateIds[$post["id"]]);
            }
          }
        }
      }

      if (!empty($currentTextUpdateIds) && count($currentTextUpdateIds) > 0) {
        // Now remove any chatter items that have been deleted in SF.
        $currentTextUpdateIds = array_flip($currentTextUpdateIds);
        $currentTextUpdateIds = array_reverse($currentTextUpdateIds, TRUE);
        foreach ($currentTextUpdateIds as $key => $post_id) {
          // Delete un-matched posts.
          $bh_update->field_bh_text_updates->removeItem($key);
        }
      }

      $log && BuildingHousingUtils::log("cleanup", "      Summary: {$count_valid} text messages. {$count_new} Added, {$count_update} Updated, " . count($currentTextUpdateIds) . " Deleted.\n");

    }

    catch (\Exception $e) {
      $this->mailException([
        '@url' => "Post ID: {$post["id"]}",
        '@err' => $e->getMessage(),
        '@update_id' => $bh_update->id()]);
      throw new \Exception($e->getMessage(), $e->getCode());
    }
  }

  /**
   * Tries to return a valid URL when only the domain is provided.
   *
   * @param string $url The URL to be validated.
   * @param string $field The SF sync field providing the URL (for reporting).
   * @param \Drupal\salesforce\SObject $sf_data The SF Object (for reporting).
   *
   * @return string|void A valid URL, or else an empty string.
   */
  private function _validateUrl(string $url, string $field, \Drupal\salesforce\SObject $sf_data) {

    if (\Drupal::pathValidator()->isValid($url)) {
      return $this->_unwrapUrl($url);
    }
    else {
      // Try to build the url out.
      if (!\Drupal::pathValidator()->isValid("http://" . $url)) {
        // just missing the protocol
        $url = "http://{$url}";
        return $this->_unwrapUrl($url);
      }
      else {
        // Don't know what else to do, return empty string
        try {
          // Send an email alert.
          $mailManager = \Drupal::service('plugin.manager.mail');
          $params = [
            'url' => $url,
            'sf_field' => $field,
            'sf_id' => $sf_data->id(),
            'sf_title' => $sf_data->field("Title__c"),
          ];
          $mailManager->mail("node_buildinghousing", 'sync_alert_badurl', "david.upton@boston.gov", "en", $params, NULL, TRUE);
        }
        catch(Exception $e) {
          // Nothing to do
        }
        finally {
          // Ensure the empty string is returned.
          return "";
        }
      }
    }
  }

  /**
   * Tries to remove proofpoint if the url has been cut/pasted from an email.
   *
   * @param string $url
   *
   * @return string
   */
  private function _unwrapUrl(string $url) {
    if (stripos($url, 'https://urldefense') !== FALSE) {
      $results = [];
      if (preg_match("/__.*__?/", $url, $results)) {
        if ($results && count($results) == 1) {
          if (\Drupal::pathValidator()->isValid($results[0])) {
            $url = trim($results[0], "_");
          }
        }
      }
    }
    return $url;
  }

  /**
   * Removes characters from string that would be problematic if that string
   * was used as a filename.
   * @see https://stackoverflow.com/questions/2021624/string-sanitizer-for-filename
   *
   * @param string $text The filename to be sanitized
   *
   * @return false|string|null The santized filename
   */
  private function _sanitizeFilename(string $text) {
    // Remove anything which isn't a word, whitespace, number
    // or any of the following caracters -_~,;[]().
    $text = preg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $text);
    // Remove any runs of periods
    return preg_replace("([\.]{2,})", '', $text);
  }

  /**
   * If $richText flag is set, parses a rich-text array (from SF Chatter) into
   * an HTML formatted string (i,e, with markup).
   * The string is then searched and various characters (extra spaces
   * chatter in-line emojis,links and images) removed or replaced.
   *
   * @param $message string The message body to be reformatted.
   * @param $richText bool Is this message a rich text (array)
   *
   * @return string The reformatted string.
   */
  private function _reformatTextMessage(string|array $message, bool $richText = FALSE) {
    if ($richText && is_array($message)) {
      $build_msg = "";
      $allowed_tags = ["b", "i", "a", "p"];
      foreach ($message as $msgPart) {
        $msgPart = (array) $msgPart;
        switch ($msgPart["type"]) {
          case "Text":
            $msgPart["text"] = html_entity_decode($msgPart["text"]);
            $msgPart["text"] = html_entity_decode(preg_replace("/&nbsp;/i", " ", htmlentities($msgPart["text"])));
            $build_msg .= $msgPart["text"] ?? "";
            break;

          case "Link":
            $msgPart["text"] = strtolower(trim(html_entity_decode($msgPart["text"]), "/"));
            $build_msg .= "<a href='{$msgPart["url"]}'>{$msgPart["text"]}</a>&nbsp;";
            break;

          case "MarkupBegin":
            if (!empty($msgPart["htmlTag"]) && in_array(strtolower($msgPart["htmlTag"]), $allowed_tags)) {
              switch (strtolower($msgPart["htmlTag"])) {
                case "p":
                  // Strip out paragraphs. A timeline entry is to be a single
                  // paragraph, so just concatinate paragraphs with a space.
                  $build_msg .= " ";
                  break;
                case "b":
                  $build_msg .= "<span class='font-weight: bold;'>";
                  break;
                case "i":
                  $build_msg .= "<span class='font-style: italic;'>";
                  break;
                case "a":
                  $build_msg .= "<a href=\"{$msgPart["url"]}\">";
                  break;
              }
            }
            break;
          case "MarkupEnd":
            if (!empty($msgPart["htmlTag"]) && in_array(strtolower($msgPart["htmlTag"]), $allowed_tags)) {
              switch (strtolower($msgPart["htmlTag"])) {
                case "b":
                case "i":
                  $build_msg .= "</span> ";
                  break;

                case "p":
                  $build_msg .= $msgPart["text"] ?? "";
                  break;

                case "a":
                  $build_msg .= "</a>";
                  break;
              }
            }
            break;
        }
      }
    }
    else {
      $build_msg = $message;
    }
    $build_msg = preg_replace('/\s*\n\s*\n\s*/', " ", $build_msg);
    // Cleanup the $message - remove images, emoji's etc.
    // @see http://unicode.org/emoji/charts/full-emoji-list.html
    $replacements = [
      "/\[Image:.*\]/u",        // Embedded Images
      '/[\x{0080}-\x{02AF}'
      .'\x{0300}-\x{03FF}'
      .'\x{0600}-\x{06FF}'
      .'\x{0C00}-\x{0C7F}'
      .'\x{1DC0}-\x{1DFF}'
      .'\x{1E00}-\x{1EFF}'
      .'\x{2000}-\x{209F}'
      .'\x{20D0}-\x{214F}'
      .'\x{2190}-\x{23FF}'
      .'\x{2460}-\x{25FF}'
      .'\x{2600}-\x{27EF}'
      .'\x{2900}-\x{29FF}'
      .'\x{2B00}-\x{2BFF}'
      .'\x{2C60}-\x{2C7F}'
      .'\x{2E00}-\x{2E7F}'
      .'\x{3000}-\x{303F}'
      .'\x{A490}-\x{A4CF}'
      .'\x{E000}-\x{F8FF}'
      .'\x{FE00}-\x{FE0F}'
      .'\x{FE30}-\x{FE4F}'
      .'\x{1F000}-\x{1F02F}'
      .'\x{1F0A0}-\x{1F0FF}'
      .'\x{1F100}-\x{1F64F}'
      .'\x{1F680}-\x{1F6FF}'
      .'\x{1F910}-\x{1F96B}'
      .'\x{1F980}-\x{1F9E0}]/u',  // Comprehensive emoji list
    ];
    return preg_replace($replacements, "", $build_msg);
  }

  /**
   * Email out an exception. (uses
   * node_buildinghousing.module::node_buildinghousing_mail.)
   *
   * @param $params array Array of fields to build into email .
   *
   * @return void
   */
  private function mailException($params) {
    $mailManager = \Drupal::service('plugin.manager.mail');
    $mailManager->mail(
      "node_buildinghousing",
      "sync_webupdate_failed",
      "david.upton@boston.gov",
      "en",
      $params,
      NULL,
      TRUE);
  }
}
