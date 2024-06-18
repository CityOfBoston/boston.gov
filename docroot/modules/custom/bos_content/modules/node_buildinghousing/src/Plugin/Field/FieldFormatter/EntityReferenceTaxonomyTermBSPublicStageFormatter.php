<?php

namespace Drupal\node_buildinghousing\Plugin\Field\FieldFormatter;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\node_buildinghousing\BuildingHousingUtils as BHUtils;

/**
 * Plugin implementation of the 'entity reference taxonomy term Building Housing Public Stage' formatter.
 *
 * @FieldFormatter(
 *   id = "entity_reference_bh_public_stage",
 *   label = @Translation("Building Housing Public Stage"),
 *   description = @Translation("Display reference to taxonomy term for Building Housing Public Stage."),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class EntityReferenceTaxonomyTermBSPublicStageFormatter extends EntityReferenceFormatterBase {

  /**
   * IsActive Project Flag.
   *
   * @var bool
   */
  private $isActive = TRUE;

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    // This formatter is only available for taxonomy terms.
    $isTaxonomyTerm = $field_definition->getFieldStorageDefinition()->getSetting('target_type') == 'taxonomy_term';
    $isNode = $field_definition->getTargetEntityTypeId();
    $isBHProject = $field_definition->getTargetBundle();

    if ($isTaxonomyTerm && $isNode && $isBHProject) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $parent_entity = $items->getEntity();

    $elements = [];
    $elements['documents'] = $this->getDocuments($parent_entity);
    $elements['rfp'] = $this->getRfp($parent_entity);
    $elements['textPosts'] = $this->getTexts($parent_entity);
    $elements['meetings'] = $this->getMeetings($parent_entity);

    $termStorage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

    // past, present, future.
    $moments = [];
    $stageCurrentState = 'past';

    foreach ($this->getPublicStages() as $delta => $publicStage) {

      $stageIsActive = $parent_entity->get('field_bh_public_stage')->target_id == $publicStage->tid;

      if ($stageCurrentState == 'past' && $stageIsActive) {
        $stageCurrentState = 'present';
      }
      elseif ($stageCurrentState == 'present' && !$stageIsActive) {
        $stageCurrentState = 'future';
      }

      $publicStageTerm = $termStorage->load($publicStage->tid);
      $vars = [];

      $stageTitle = $publicStageTerm->get('field_display_title') ?? NULL;
      $stageIcon = $this->getStageIcon($publicStageTerm->getName(), $stageCurrentState) ?? NULL;
      $stageDescription = $publicStageTerm->get('description') ?? NULL;
      $stageDate = $this->getStageDate($parent_entity, $publicStageTerm, 'seasonal');

      if ($publicStageTerm->getName() == 'Not Active') {

        if ($stageIsActive) {
          $elements[] = $this->getInactiveProjectContent($publicStageTerm);
          $this->isActive = FALSE;
          return $elements;
        }
        else {
          continue;
        }
      }

      $vars['icon'] = $stageIcon;
      $vars['label'] = $stageTitle->view(['label' => 'hidden']);
      $vars['stage'] = $publicStageTerm->getName() ?? '';
      $vars['body'] = $stageDescription->view(['label' => 'hidden']);
      $vars['date'] = $stageDate;
      $vars['currentState'] = $stageCurrentState;

      switch ($stageCurrentState) {
        case 'past':
          break;

        case 'present':
          break;

        case 'future':

          $stageDate = $stageDate ? $stageDate : 'To Be Determined';

          $vars['icon'] = \Drupal::theme()->render("bh_icons", ['type' => NULL]);
          $vars['body'] = t('Predicted Date: ') . $stageDate;
          $vars['date'] = '';
          break;
      }

      if (_node_buildinghousing_get_computed_project_type($parent_entity)['label'] == 'Open Space'
        && $publicStageTerm->getName() == 'City Planning Process'
        && $stageCurrentState != 'future') {
        $vars['body'] = t("We're working with the developer on the final design, budget, and financing.");
      }

      if ($recordType = BHUtils::getProjectRecordType($parent_entity) == 'NHD Development') {

        if ($publicStageTerm->getName() == 'Project Launch') {
          $vars['icon'] = \Drupal::theme()->render("bh_icons", ['type' => 'funding-awarded']);
          $vars['body'] = t('Mayor’s Office of Housing approved funding for this project.');
          $vars['label'] = t('Funding awarded');
        }

        if ($publicStageTerm->getName() == 'Selecting Developer') {
         continue;
        }

      }

      $sortTimestamp = $this->getStageDate($parent_entity, $publicStageTerm, 'timestamp');
      if ($publicStageTerm->getName() == 'Project Launch' && (empty($sortTimestamp) || $recordType == 'NHD Development')) {
        $sortTimestamp = $delta;
      }
      elseif (empty($sortTimestamp)) {
        $sortTimestamp = $delta;
      }
      $vars['timeStamp'] = $sortTimestamp;
      $moments[] = $vars;
    }

    $moments = array_reverse($moments);
    foreach ($moments as $delta => $moment) {
      $sortedElements = [];
      // @TODO: Add fix for showing items with no date
      foreach ($elements as $elementTypes => $typeElements) {
        foreach ($typeElements as $time => $renderElement) {

          if ($time >= $moment['timeStamp'] && $moment['timeStamp'] > 999) {
            $sortedElements[$time][] = $renderElement;
            unset($elements[$elementTypes][$time]);
          }elseif ($moment['stage'] == 'Project Launch') {
            $sortedElements[$time][] = $renderElement;
            unset($elements[$elementTypes][$time]);
          }

          ksort($sortedElements);
        }
      }

      $moment['items'] = $sortedElements;
      $moments[$delta] = ['#markup' => \Drupal::theme()->render("bh_project_timeline_moment", $moment)];
    }

    return [
      '#markup' => \Drupal::theme()->render("bh_project_timeline", [
        'items' => array_reverse($moments),
        'label' => $this->isActive ? t('Timeline') : NULL,
        'isDevelopment' => BHUtils::getProjectRecordType($parent_entity) == 'NHD Development' ? true : false,
      ])
    ];

    // Return $elements;.
  }

  /**
   * Get Project Documents.
   *
   * @param \Drupal\Core\Entity\EntityInterface $project
   *   Building Housing Project Entity.
   *
   * @return array
   *   Array of Documents
   */
  public function getDocuments(EntityInterface $project) {
    $elements = [];

    $attachments = $project->get('field_bh_attachment')->referencedEntities() ?? NULL;

    if (empty($attachments)) {
      return $elements;
    }

    $data = [
      // 'icon' => \Drupal::theme()->render("bh_icons", ['type' => 'dot-filled']),
      // "fileIcon" => \Drupal::theme()->render("bh_icons", ['type' => 'file-pdf']),
      // 'date' => 'DEC 15, 2020',
      // 'date' => 'DOCUMENTS', //@TODO: TEMP
      // 'currentState' => 'present',
    ];

    foreach ($attachments as $key => $attachment) {
      $date = date('Ymd', $attachment->getCreatedTime());
      $data['documents'][strtotime($date)][] = [
        'link' => str_replace("_", " ", $attachment->getFilename()),
        'url' => $attachment->createFileUrl(),
      ];
    }

    foreach ($data['documents'] as $documentDate => $documents) {

      $formattedDate = date('M d Y', $documentDate);

      $currentState = time() > $documentDate ? 'past' : 'future';

      $documentSet = [
        'icon' => $currentState == 'past'
          ? \Drupal::theme()->render("bh_icons", ['type' => 'timeline-document', 'fill' => 'cb'])
          : \Drupal::theme()->render("bh_icons", ['type' => 'timeline-document', 'fill' => 'ob']),
        'fileIcon' => \Drupal::theme()->render("bh_icons", ['type' => 'file-pdf']),
        'date' => $formattedDate,
        'currentState' => $currentState,
        'dateId' => $documentDate
      ];
      $documentSet['documents'] = $documents;
      $elements[$documentDate] = ['#markup' => \Drupal::theme()->render("bh_project_timeline_document", $documentSet)];
    }

    return $elements;
  }

  /**
   * Get Project RFP.
   *
   * @param \Drupal\Core\Entity\EntityInterface $project
   *   Building Housing Project Entity.
   *
   * @return array
   *   Array of Documents
   *
   * @throws \Exception
   */
  public function getRfp(EntityInterface $project) {
    $elements = [];

    $today = new \DateTime('now');
    $rfpDate = $project->get('field_bh_rfp_issued_date')->value ?? NULL;

    if ($rfpDate) {

      $rfpDate = new \DateTime($rfpDate);

      $currentState = time() > $rfpDate->getTimestamp() ? 'past' : 'future';

      $data = [
        'label' => t('GO TO RFP LISTINGS'),
        // @TODO: change out with config?
        'url' => '/departments/neighborhood-development/requests-proposals',
        'title' => t('Request for Proposals (RFP) Opened for Bidding'),
        'body' => null,
        'icon' => $currentState == 'past'
          ? \Drupal::theme()->render("bh_icons", [
          'type' => 'timeline-building',
          'fill' => '#091F2F'
          ])
          : \Drupal::theme()->render("bh_icons", [
          'type' => 'timeline-building',
          'fill' => '#288BE4'
          ]),
        'rfpListIcon' => \Drupal::theme()->render("bh_icons", ['type' => 'rfp-building-permit']),
        'date' => $rfpDate->format('M j, Y'),
        'currentState' => $currentState,
      ];

      if ($today->getTimestamp() >= $rfpDate->getTimestamp()) {
        $elements[$rfpDate->getTimestamp() . '.5'] = ['#markup' => \Drupal::theme()->render("bh_project_timeline_rfp", $data)];
      }
    }

    return $elements;
  }

  /**
   * Get Project Texts.
   *
   * @param \Drupal\Core\Entity\EntityInterface $project
   *   Building Housing Project Entity.
   *
   * @return array
   *   Array of Texts
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getTexts(EntityInterface $project) {
    $elements = [];

    $webUpdate = BHUtils::getWebUpdate($project);

    if ($webUpdate) {
      $textUpdatesField = $webUpdate->field_bh_text_updates;
      $textUpdatesData = [];

      foreach ($textUpdatesField->getValue() as $key => $currentTextUpdate) {
        $textData = json_decode($currentTextUpdate['value']);
        $textUpdatesData[$textData->id] = $textData;
      }

      if ($textUpdatesData) {
        foreach ($textUpdatesData as $sfid => $textUpdate) {

          if (!empty($textUpdate->text)) {
            if (is_string($textUpdate->date)) {
              $textUpdate->date = '@' . strtotime($textUpdate->date);
              $formattedDate = new \DateTime('@' . strtotime($textUpdate->date));
            }
            else {
              $formattedDate = (new \DateTime())->setTimestamp($textUpdate->date);
            }
            $currentState = time() > $formattedDate->getTimestamp() ? 'past' : 'future';

            $textUpdate->text = preg_replace(['/([^"\'\>])(http[s]?:.*?)([\s\<]|$)/'], ['${1}<a href="${2}">${2}</a>${3}'], $textUpdate->text);

            $data = [
              'label' => $textUpdate->author == "City of Boston" ? "" : t('Project Manager'),
              'title' => $textUpdate->author,
              'body' => $textUpdate->text,
              'icon' => \Drupal::theme()
                ->render("bh_icons", ['type' => 'chat']),
              'date' => $formattedDate->format('M d Y'),
              'currentState' => $currentState,
            ];

            $elements[$formattedDate->getTimestamp()][] = [
              '#markup' => \Drupal::theme()
                ->render("bh_project_timeline_text", $data)
            ];
          }

        }
      }

    }

    return $elements;
  }

  /**
   * Get Project Meetings.
   *
   * @param \Drupal\Core\Entity\EntityInterface $project
   *   Building Housing Project Entity.
   *
   * @return array
   *   Array of Meetings
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getMeetings(EntityInterface $project) {
    $elements = [];

    $webUpdate = BHUtils::getWebUpdate($project);
    $meetings = $webUpdate ? BHUtils::getMeetingsFromWebUpdateId($webUpdate->id()) : NULL;
    if ($meetings) {

      foreach ($meetings as $meetingId => $meeting) {

        $startDate = \DateTime::createFromFormat('Y-m-d\TH:i:s', $meeting->field_bh_meeting_start_time->value);
        $endDate = \DateTime::createFromFormat('Y-m-d\TH:i:s', $meeting->field_bh_meeting_end_time->value);

        //Fix for the odd timezone rendering being off by local GMT of -5 and -4(DST)
        $hoursOffset = 5 - $startDate->format('I');
        $dateOffset = \DateInterval::createFromDateString("$hoursOffset hours");
        $startDate->sub($dateOffset);
        $endDate->sub($dateOffset);

        if ($startDate->getTimestamp() > time()) {
          $event = $meeting->field_bh_event_ref->isEmpty() ? NULL : $meeting->field_bh_event_ref->referencedEntities()[0];

          $addToCal = $event->field_event_date_recur->view('add_to_calendar');
          unset($addToCal[0]['start_date']);
          unset($addToCal[0]['separator']);
          unset($addToCal[0]['end_date']);

          $date = $startDate->format('M d Y');
          $time = $startDate->format('g:i') . '-' . $endDate->format('g:iA');
          $icon = \Drupal::theme()->render("bh_icons", ['type' => 'calendar', 'fill' => 'ob']);
          $label = t('UPCOMING COMMUNITY MEETING');
          $currentState = 'future';
          $link = $event ? $event->toURL()->toString() : '/events';

          $bodyFieldView = $meeting->body->view('default');
          $bodyFieldView[0]['#text'] = $this->renderReadMoreText($bodyFieldView[0]['#text'], 200);
          $bodyFieldView[0]['#format'] = 'full_html';

          $body = \Drupal::service('renderer')->render($bodyFieldView);
          $body = strip_tags($body, '<div><span><label><input><a>');

          $recordingPassword = NULL;
          $attendees = NULL;
        }
        else {
          // $label = t('PAST COMMUNITY MEETING');
          $label = t('VIEW MEETING RECORDINGS');
          $icon = \Drupal::theme()->render("bh_icons", [
            'type' => 'timeline-calendar',
            'fill' => 'cb'
          ]);
          $time = $startDate->format('g:i') . '-' . $endDate->format('g:iA');
          $date = $endDate->format('M d Y');
          $currentState = 'past';
          $addToCal = NULL;
          $link = $meeting->field_bh_post_meeting_recording->value ?? NULL;
          $body = $this->renderReadMoreText($meeting->field_bh_post_meeting_notes->value ?? '', 200) ?? '';
          $attendees = $meeting->field_bh_number_of_attendees && $meeting->field_bh_number_of_attendees->value ? $meeting->field_bh_number_of_attendees->value . t(' ATTENDEES') : NULL;
          $recordingPassword = $meeting->field_bh_meeting_recording_pass->value ?? NULL;
        }

        $data = [
          'label' => $label,
          'title' => $meeting->getTitle(),
          'body' => $body,
          'icon' => $icon,
          'date' => $date,
          'time' => $time,
          'link' => $link,
          'currentState' => $currentState,
          'addToCal' => $addToCal,
          'recordingLinkIcon' => NULL,
          'recordingPassword' => $recordingPassword,
          'attendees' => $attendees,
        ];

        $elements[$startDate->getTimestamp()][] = ['#markup' => \Drupal::theme()->render("bh_project_timeline_meeting", $data)];

      }

    }

    return $elements;
  }

  /**
   * Split Text field into two parts with read more action.
   *
   * @param string $text
   *   Text to split.
   * @param int $maxChars
   *   Max chart to split the text at.
   *
   * @return array|MarkupInterface|string
   *   String with new read-more html added.
   */
  private function renderReadMoreText(string $text, int $maxChars = 200) {

    if (strlen($text) <= $maxChars) {
      return \Drupal\Core\Render\Markup::create($text);
    }

    $text = strip_tags($text, '<a><div><span>');

    $lessText = substr($text, 0, $maxChars);
    $moreText = substr($text, $maxChars);

    $readMoreText = \Drupal::theme()->render("bh_read_more_text", [
      'textId' => 'read-more-text-' . rand(1000, 9999),
      'lessText' => $lessText,
      'moreText' => $moreText,
    ]);

    return $readMoreText;
  }

  /**
   * Get Public Stage.
   *
   * @return array
   *   Array of Public Stages
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getPublicStages() {
    $publicStages = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('bh_public_stage') ?? NULL;
    return $publicStages;
  }

  /**
   * Get Stage Icon.
   *
   * @param string $stage
   *   Project Stage string / title.
   * @param string $stageCurrentState
   *   Current state of Project.
   *
   * @return array|MarkupInterface|string
   *   Render array for an icon
   */
  private function getStageIcon(string $stage, string $stageCurrentState) {

    $stageIconMapping = [
      'Project Launch' => 'community-feedback',
      'Selecting Developer' => 'selecting-a-developer',
      'City Planning Process' => 'in-city-planning',
      'In Construction' => 'in-construction',
      'Project Completed' => 'completed',
      'Not Active' => '',
    ];

    $color = $stageCurrentState == 'present' ? 'ob' : 'cb';

    return \Drupal::theme()->render('bh_icons', [
      'type' => $stageIconMapping[$stage],
        'fill' => $color
      ])
      ?? [];
  }

  /**
   * Get Stage date from major Project dates.
   *
   * @param \Drupal\Core\Entity\EntityInterface $project
   *   Building Housing Project Entity.
   * @param \Drupal\Core\Entity\EntityInterface $stage
   *   Project Stage string / title.
   * @param string $format
   *   Format of the display date.
   *
   * @return false|int|string|null
   *   Formatted date string
   */
  private function getStageDate(EntityInterface $project, EntityInterface $stage, $format = 'timestamp') {

    switch ($stage->getName()) {
      case 'Project Launch':
        if (BHUtils::getProjectRecordType($project) == 'NHD Development') {
          $date = $project->get('field_bh_dnd_funding_award_date')->value ?? NULL;
        }else{
          $date = $project->get('field_bh_project_start_date')->value ?? NULL;
        }
        break;

      case 'Selecting Developer':
        $date = $project->get('field_bh_rfp_issued_date')->value ?? NULL;
        break;

      case 'City Planning Process':
        switch (BHUtils::getProjectRecordType($project)) {
          case 'Disposition':
            $date = $project->get('field_bh_initial_td_vote_date')->value ?? NULL;
            break;

          case 'NHD Development':
            $date = $project->get('field_bh_dnd_funding_award_date')->value ?? NULL;
            break;

          default:
            $date = NULL;
        }
        break;

      case 'In Construction':
        $date = $project->get('field_bh_construction_start_date')->value ?? NULL;
        break;

      case 'Project Completed':
        $date = $project->get('field_bh_construct_complete_date')->value ?? NULL;
        break;

      default:
        $date = NULL;
    }

    if ($format == 'seasonal') {
      $stageDate = $date ? $this->dateToSeason($date) : '';
    }
    elseif ($date) {
      $stageDate = strtotime($date);
    }
    else {
      $stageDate = "";
    }

    return $stageDate ?? $date ?? '';
  }

  /**
   * Date string to Season Year string.
   *
   * @param string $date
   *   Date timestamp.
   *
   * @return string
   *   Date string as a season and year
   *
   * @throws \Exception
   */
  private function dateToSeason(string $date) {
    $season = '';
    $seasonDate = new \DateTime($date);
    $monthDayDate = $seasonDate->format('md');

    switch (TRUE) {
      // Spring runs from March 1 (0301) to May 31 (0531)
      case $monthDayDate >= '0301' && $monthDayDate <= '0531':
        $season = 'Spring';
        break;

      // Summer runs from June 1 (0601) to August 31 (0831)
      case $monthDayDate >= '0601' && $monthDayDate <= '0831':
        $season = 'Summer';
        break;

      // Fall (autumn) runs from September 1 (0901) to November 30 (1130)
      case $monthDayDate >= '0901' && $monthDayDate <= '1130':
        $season = 'Fall';
        break;

      // Winter runs from December 1 (1201) to February 28+1 (0229)
      case $monthDayDate >= '1201' || $monthDayDate <= '0229':
        $season = 'Winter';
        break;
    }

    return $season . ' ' . $seasonDate->format('Y');
  }

  /**
   * Get the pre-build Inactive Project render array content.
   *
   * @param object $publicStageTerm
   *   Public Stage Term.
   *
   * @return array
   *   Render array
   */
  private function getInactiveProjectContent(object $publicStageTerm) {
    $render = [];

    $copy = $publicStageTerm->description->value ?? 'Inactive Project';

    $render[] = ['#markup' => $copy];

    return $render;
  }

}
