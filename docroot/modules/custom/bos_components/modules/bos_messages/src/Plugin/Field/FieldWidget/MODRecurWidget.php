<?php

declare(strict_types = 1);

namespace Drupal\bos_messages\Plugin\Field\FieldWidget;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\date_recur\DateRecurPartGrid;
use Drupal\date_recur\DateRecurRuleInterface;
use Drupal\date_recur\Exception\DateRecurHelperArgumentException;
use Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem;
use Drupal\date_recur_modular\DateRecurModularWidgetFieldsTrait;
use Drupal\date_recur_modular\DateRecurModularWidgetOptions;
use Drupal\date_recur_modular\Plugin\Field\FieldWidget\DateRecurModularWidgetBase;

/**
 * Message of the day date recurrance field widget.
 *
 * @FieldWidget(
 *   id = "bos_messages_mod_recur",
 *   label = @Translation("Message of the day"),
 *   field_types = {
 *     "date_recur"
 *   }
 * )
 */
class MODRecurWidget extends DateRecurModularWidgetBase {

  use DateRecurModularWidgetFieldsTrait;

  protected const MODE_ONCE = 'once';
  protected const MODE_DAILY = 'daily';
  protected const MODE_WEEKLY = 'weekly';
  protected const MODE_MONTHLY = 'monthly';
  protected const MODE_YEARLY = 'yearly';
  protected const WEEKDAY = 'MO,TU,WE,TH,FR';
  protected const WEEKEND = 'SA,SU';

  /**
   * Part grid for this list.
   *
   * @var \Drupal\date_recur\DateRecurPartGrid
   */
  protected $partGrid;

  /**
   * {@inheritdoc}
   */
  protected function getModes(): array {
    return [
      static::MODE_ONCE => $this->t('Once'),
      static::MODE_DAILY => $this->t('Daily'),
      static::MODE_WEEKLY => $this->t('Weekly'),
      static::MODE_MONTHLY => $this->t('Monthly'),
      static::MODE_YEARLY => $this->t('Yearly'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getMode(DateRecurItem $item): ?string {
    try {
      $helper = $item->getHelper();
    }
    catch (DateRecurHelperArgumentException $e) {
      return NULL;
    }

    $rules = $helper->getRules();
    $rule = reset($rules);
    if (FALSE === $rule) {
      // This widget supports one RRULE per field value.
      return NULL;
    }

    $frequency = $rule->getFrequency();
    $parts = $rule->getParts();

    if ('ONCE' === $frequency) {
      return static::MODE_ONCE;
    }
    elseif ('DAILY' === $frequency) {
      return static::MODE_DAILY;
    }
    elseif ('WEEKLY' === $frequency) {
      /** @var int|null $interval */
      $interval = $parts['INTERVAL'] ?? NULL;
      return $interval == 1 ? static::MODE_WEEKLY : NULL;
    }
    elseif ('MONTHLY' === $frequency) {
      return static::MODE_MONTHLY;
    }
    elseif ('YEARLY' === $frequency) {
      return static::MODE_YEARLY;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    /** @var \Drupal\date_recur\Plugin\Field\FieldType\DateRecurFieldItemList|\Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem[] $items */
    $elementParents = array_merge($element['#field_parents'], [
      $this->fieldDefinition->getName(),
      $delta,
    ]);
    $element['#element_validate'][] = [static::class, 'validateModularWidget'];
    $element['#after_build'][] = [static::class, 'afterBuildModularWidget'];
    $element['#theme'] = 'bos_messages_mod_recur_widget';

    $item = $items[$delta];

    $grid = $items->getPartGrid();
    $rule = $this->getRule($item);
    $parts = $rule ? $rule->getParts() : [];
    $count = $parts['COUNT'] ?? NULL;
    $timeZone = $this->getDefaultTimeZone($item);
    $endsDate = NULL;
    try {
      $until = $parts['UNTIL'] ?? NULL;
      if (is_string($until)) {
        $endsDate = new \DateTime($until);
      }
      elseif ($until instanceof \DateTimeInterface) {
        $endsDate = $until;
      }
      if ($endsDate) {
        // UNTIL is _usually_ in UTC, adjust it to the field time zone.
        $endsDate->setTimezone(new \DateTimeZone($timeZone));
      }
    }
    catch (\Exception $e) {
    }

    $fieldModes = $this->getFieldModes($grid);

    // We are intentionally locking down recurrance periods to a single day.
    // A day is 24 hours, starting at 00:00:01 and ending at 23:59:59.
    // We do this by only allowing content editors to edit the start date. We
    // then set the end date based on the start date. start date, end date and
    // tiemzone are rquired fields for date_recur field type. We revoke access
    // to end and timezone fields becuase we don't want users manipulating them.
    $element['start'] = [
      '#type' => 'date',
      '#title' => $this->t('Start visibility date'),
      '#default_value' => !empty($item->start_date) ? $item->start_date->format('Y-m-d') : "",
      '#description' => $this->t('A message is visible for one day, midnight to midnight. <br>To make it visible for morer than one day, or to make it repeat on a schedule, change the "Message frequency" select list to an appropriate interval.'),
      // \Drupal\Core\Datetime\Element\Datetime::valueCallback tries to change
      // the time zone to current users timezone if not set, Set the timezone
      // here so the value doesn't change.
      '#date_timezone' => $timeZone,
    ];
    $element['end'] = [
      '#title' => $this->t('End visibility'),
      '#type' => 'date',
      '#default_value' => !empty($item->end_date) ? $item->start_date->format('Y-m-d') : "",
      '#date_timezone' => $timeZone,
      '#access' => FALSE,
    ];
    $element['time_zone'] = $this->getFieldTimeZone($timeZone);
    $element['time_zone']['#access'] = FALSE;

    $element['mode'] = $this->getFieldMode($item);
    $element['mode']['#title'] = 'Message frequency';

    $element['daily_frequency'] = [
      '#type' => 'radios',
      '#title' => $this->t('Interval of repetition'),
      '#options' => [
        'every_day' => $this->t('Every Day'),
        'every_weekday' => $this->t('Every Weekday'),
        'every_weekend' => $this->t('Every Weekend Day'),
      ],
      '#states' => $this->getVisibilityStates($element, $fieldModes['daily_frequency'] ?? []),
      '#default_value' => 'every_day',
    ];

    if (!empty($parts['FREQ']) && $parts['FREQ'] == 'DAILY' && !empty($parts['BYDAY'])) {
      if ($parts['BYDAY'] == static::WEEKDAY) {
        $element['daily_frequency']['#default_value'] = 'every_weekday';
      }
      elseif ($parts['BYDAY'] == static::WEEKEND) {
        $element['daily_frequency']['#default_value'] = 'every_weekend';
      }
    }

    $element['weekdays'] = $this->getFieldByDay($rule);
    $element['weekdays']['#states'] = $this->getVisibilityStates($element, $fieldModes['weekdays'] ?? []);
    $element['weekdays']['#title'] = $this->t('Repeats on:');
    $element['weekdays']['#title_display'] = 'visible';

    $element['ordinals'] = $this->getFieldMonthlyByDayOrdinals($element, $rule);
    $element['ordinals']['#states'] = $this->getVisibilityStates($element, $fieldModes['ordinals'] ?? []);
    $element['ordinals']['#title_display'] = 'visible';
    $element['ordinals']['#label_display'] = 'before';

    $element['month_of_year'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Months'),
      '#title_display' => 'visible',
      '#options' => [
        '1' => 'January',
        '2' => 'February',
        '3' => 'March',
        '4' => 'April',
        '5' => 'May',
        '6' => 'June',
        '7' => 'July',
        '8' => 'August',
        '9' => 'September',
        '10' => 'October',
        '11' => 'November',
        '12' => 'December',
      ],
      '#states' => $this->getVisibilityStates($element, $fieldModes['month_of_year'] ?? []),
      '#attributes' => [
        'class' => ['container-inline'],
      ],
    ];

    if (!empty($parts['BYMONTH'])) {
      $element['month_of_year']['#default_value'] = explode(',', $parts['BYMONTH']);
    }

    $endsModeDefault = $endsDate ? DateRecurModularWidgetOptions::ENDS_MODE_ON_DATE : ($count > 0 ? DateRecurModularWidgetOptions::ENDS_MODE_OCCURRENCES : DateRecurModularWidgetOptions::ENDS_MODE_INFINITE);
    $element['ends_mode'] = $this->getFieldEndsMode();
    $element['ends_mode']['#states'] = $this->getVisibilityStates($element, $fieldModes['ends_mode'] ?? []);
    $element['ends_mode']['#title'] = $this->t('End visibility');
    $element['ends_mode']['#title_display'] = 'before';
    $element['ends_mode']['#default_value'] = $endsModeDefault;
    // Hide or show 'On date' / 'number of occurrences' checkboxes depending on
    // selected mode.
    $element['ends_mode'][DateRecurModularWidgetOptions::ENDS_MODE_OCCURRENCES]['#states'] = $this->getVisibilityStates($element, $fieldModes['ends_count'] ?? []);
    $element['ends_mode'][DateRecurModularWidgetOptions::ENDS_MODE_ON_DATE]['#states'] = $this->getVisibilityStates($element, $fieldModes['ends_date'] ?? []);

    $element['ends_count'] = [
      '#type' => 'number',
      '#title' => $this->t('End after number of occurrences'),
      '#title_display' => 'invisible',
      '#field_prefix' => $this->t('after'),
      '#field_suffix' => $this->t('occurrences'),
      '#default_value' => $count ?? 1,
      '#min' => 1,
      '#access' => count($fieldModes['ends_count'] ?? []) > 0,
    ];
    $nameMode = $this->getName($element, ['mode']);
    $nameEndsMode = $this->getName($element, ['ends_mode']);
    $element['ends_count']['#states']['visible'] = [];
    foreach ($fieldModes['ends_count'] ?? [] as $mode) {
      $element['ends_count']['#states']['visible'][] = [
        ':input[name="' . $nameMode . '"]' => ['value' => $mode],
        ':input[name="' . $nameEndsMode . '"]' => ['value' => DateRecurModularWidgetOptions::ENDS_MODE_OCCURRENCES],
      ];
    }

    // States dont yet work on date time so put it in a container.
    // @see https://www.drupal.org/project/drupal/issues/2419131
    $element['ends_date'] = [
      '#type' => 'container',
    ];
    $element['ends_date']['ends_date'] = [
      '#type' => 'date',
      '#title' => $this->t('End before this date'),
      '#title_display' => 'visible',
      '#description' => $this->t('No messages will show after this date.'),
      '#default_value' => $endsDate ? $endsDate->format("Y-m-d") : NULL,
      // Fix values tree thanks to state+container hack.
      '#parents' => array_merge($elementParents, ['ends_date']),
      // \Drupal\Core\Datetime\Element\Datetime::valueCallback tries to change
      // the time zone to current users timezone if not set, Set the timezone
      // here so the value doesn't change.
      '#date_timezone' => $timeZone,
    ];
    $element['ends_date']['#states']['visible'] = [];
    foreach ($fieldModes['ends_date'] ?? [] as $mode) {
      $element['ends_date']['#states']['visible'][] = [
        ':input[name="' . $nameMode . '"]' => ['value' => $mode],
        ':input[name="' . $nameEndsMode . '"]' => ['value' => DateRecurModularWidgetOptions::ENDS_MODE_ON_DATE],
      ];
    }

    $element['exceptions'] = [
      '#type' => 'textarea',
      '#access' => FALSE,
      /* '#title' => $this->t('Excluded Days'),
      '#description' => $this->t('List of days to exclude, one per line. Ex 2012-06-09'),*/
      '#states' => [
        'invisible' => [
          [':input[name="' . $nameMode . '"]' => ['value' => static::MODE_ONCE]],
        ],
      ],
    ];

    /* if (empty($item->timezone)) {
    $z = $this->getDefaultTimeZone($item);
    $item->set("timezone", $z);
    }
    $helper = $item->getHelper();
    if (method_exists($helper, 'getExdates')) {
    $exdates = $helper->getExdates();
    $exdate_parts = [];
    foreach ($exdates as $exdate) {
    $exdate_parts[] = $exdate->format('Y-m-d');
    }
    $element['exceptions']['#default_value'] = implode("\n", $exdate_parts);
    } */

    return $element;
  }

  /**
   * Validates the widget.
   *
   * @param array $element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   */
  public static function validateModularWidget(array &$element, FormStateInterface $form_state, array &$complete_form): void {
    // Each of these values can be array if input was invalid. E.g date or time
    // not provided.
    /** @var \Drupal\Core\Datetime\DrupalDateTime|array|null $start */
    $start = $form_state->getValue(array_merge($element['#parents'], ['start']));
    /** @var \Drupal\Core\Datetime\DrupalDateTime|array|null $end */
    $end = $form_state->getValue(array_merge($element['#parents'], ['end']));
    /** @var string|null $timeZone */
    $timeZone = $form_state->getValue(array_merge($element['#parents'], ['time_zone']));

    if ($start && !$timeZone) {
      $form_state->setError($element['start'], \t('Time zone must be set if start date is set.'));
    }
    if ($end && !$timeZone) {
      $form_state->setError($element['end'], \t('Time zone must be set if end date is set.'));
    }
    if (($start instanceof DrupalDateTime || $end instanceof DrupalDateTime) && (!$start instanceof DrupalDateTime || !$end instanceof DrupalDateTime)) {
      $form_state->setError($element, \t('Start date and end date must be provided.'));
    }

    // Recreate datetime object with exactly the same date and time but
    // different timezone.
    $element["start"]["#value"] .= "T00:00:00";
    $form_state->setValueForElement($element['start'], $element["start"]["#value"]);
    $element["end"]["#value"] .= "T00:00:00";
    $form_state->setValueForElement($element['end'], $element["end"]["#value"]);
    if (isset($element["ends_date"]["#value"])) {
      $endsDate = $form_state->getValue(array_merge($element['#parents'], ['ends_date'])) . "T00:00:00";
      $form_state->setValueForElement($element['ends_date'], $endsDate);
    }

    $exceptions = $form_state->getValue(array_merge($element['#parents'], ['exceptions']));
    if (!empty($exceptions)) {
      $exception_parts = explode("\n", $exceptions);
      foreach ($exception_parts as $exception) {
        $date = new DrupalDateTime(trim($exception));
        if ($date->hasErrors()) {
          $form_state->setError($element, \t('One of your recurrance exceptions contains an improperly formatted date.'));
        }
      }
    }
  }

  /**
   * After build callback for the widget.
   *
   * @param array $element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The element.
   */
  public static function afterBuildModularWidget(array $element, FormStateInterface $form_state) {
    // Wait until ID is created, and after
    // \Drupal\Core\Render\Element\Checkboxes::processCheckboxes is run so
    // states are not replicated to children.
    $weekdaysId = $element['weekdays']['#id'];
    $element['ordinals']['#states']['visible'][0]['#' . $weekdaysId . ' input[type="checkbox"]'] = ['checked' => TRUE];
    $element['ordinals']['#states']['visible'][2]['#' . $weekdaysId . ' input[type="checkbox"]'] = ['checked' => TRUE];

    // Add container classes to compact checkboxes.
    $element['weekdays']['#attributes']['class'][] = 'container-inline';
    $element['ordinals']['#attributes']['class'][] = 'container-inline';

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    /** @var \Drupal\date_recur\Plugin\Field\FieldType\DateRecurFieldItemList $items */
    $this->partGrid = $items->getPartGrid();
    parent::extractFormValues(...func_get_args());
    unset($this->partGrid);
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as $delta => $value) {
      if (!empty($value["start"])) {
        $start_date = $value['start'];
        $values[$delta]['start'] = $this->buildDateBoundaries($start_date, $value['time_zone'], 'start');
        // This looks like a mistake, but it's intentional. We're setting the end
        // date to the same date as the start date because we only allow single
        // day recurrance windows.
        $values[$delta]['end'] = $this->buildDateBoundaries($start_date, $value['time_zone'], 'end');
      }
      if (!empty($value["ends_date"])) {
        $values[$delta]['ends_date'] = $this->buildDateBoundaries($value["ends_date"], $value['time_zone'], 'end');
      }
    }
    $values = parent::massageFormValues($values, $form, $form_state);
    $dateStorageFormat = $this->fieldDefinition->getSetting('datetime_type') == DateRecurItem::DATETIME_TYPE_DATE ? DateRecurItem::DATE_STORAGE_FORMAT : DateRecurItem::DATETIME_STORAGE_FORMAT;
    $dateStorageTimeZone = new \DateTimezone(DateRecurItem::STORAGE_TIMEZONE);
    $grid = $this->partGrid;

    $returnValues = [];
    foreach ($values as $delta => $value) {
      // Call to parent invalidates and empties individual values.
      if (empty($value)) {
        continue;
      }

      $item = [];

      $start = $value['start'] ?? NULL;
      assert(!isset($start) || $start instanceof DrupalDateTime);
      $end = $value['end'] ?? NULL;
      assert(!isset($end) || $end instanceof DrupalDateTime);
      $timeZone = $value['time_zone'] ?? NULL;
      $mode = $value['mode'] ?? NULL;
      $endsMode = $value['ends_mode'] ?? NULL;
      $endsDate = $value['ends_date'] ?? new DrupalDateTime(NULL);

      // Adjust the date for storage.
      $start->setTimezone($dateStorageTimeZone);
      $item['value'] = $start->format($dateStorageFormat);
      $end->setTimezone($dateStorageTimeZone);
      $item['end_value'] = $end->format($dateStorageFormat);
      $item['timezone'] = $timeZone;
      $weekDays = array_values(array_filter($value['weekdays']));
      $byDayStr = implode(',', $weekDays);

      $rule = [];
      if ($mode === static::MODE_DAILY) {
        $rule['FREQ'] = 'DAILY';
        $rule['INTERVAL'] = 1;
        if (!empty($value['daily_frequency'])) {
          switch ($value['daily_frequency']) {
            case 'every_weekday':
              $rule['BYDAY'] = static::WEEKDAY;
              break;

            case 'every_weekend':
              $rule['BYDAY'] = static::WEEKEND;
              break;
          }
        }
      }
      elseif ($mode === static::MODE_WEEKLY) {
        $rule['FREQ'] = 'WEEKLY';
        $rule['INTERVAL'] = 1;
        $rule['BYDAY'] = $byDayStr;
      }
      elseif ($mode === static::MODE_MONTHLY) {
        $rule['FREQ'] = 'MONTHLY';
        $rule['INTERVAL'] = 1;
        $rule['BYDAY'] = $byDayStr;
        $rule['BYSETPOS'] = $this->handleOrdinals($value['ordinals'], $weekDays);
      }
      elseif ($mode === static::MODE_YEARLY) {
        $rule['FREQ'] = 'YEARLY';
        $rule['INTERVAL'] = 1;
        $rule['BYDAY'] = $byDayStr;
        $rule['BYSETPOS'] = $this->handleOrdinals($value['ordinals'], $weekDays);
        $months = array_filter($value['month_of_year'], function ($item) {
          // Empty values are returned as integers. Populated values are
          // returned as a string representation of an integer. ex: "1"
          // This works but feels brittle.
          return is_string($item);
        });
        if (!empty($months)) {
          $rule['BYMONTH'] = implode(',', $months);
        }
      }

      // Ends mode.
      if ($endsMode === DateRecurModularWidgetOptions::ENDS_MODE_OCCURRENCES) {
        $rule['COUNT'] = (int) $value['ends_count'];
      }
      elseif ($endsMode === DateRecurModularWidgetOptions::ENDS_MODE_ON_DATE && $endsDate instanceof DrupalDateTime) {
        $endsDateUtcAdjusted = (clone $endsDate)
          ->setTimezone(new \DateTimeZone('UTC'));
        $rule['UNTIL'] = $endsDateUtcAdjusted->format('Ymd\THis\Z');
      }

      if (isset($rule['FREQ'])) {
        $rule = array_filter($rule);
        $rrule = $this->buildRruleString($rule, $grid);
        if (!empty($value['exceptions'])) {
          $exceptions = [];
          $exception_parts = explode("\n", $value['exceptions']);
          foreach ($exception_parts as $exception) {
            $date = new DrupalDateTime(trim($exception));
            $exceptions[] = $date->format('Ymd\THis\Z');
          }
          $rrule = 'RRULE:' . $rrule . "\n" . 'EXDATE:' . implode(',', $exceptions);
        }

        $item['rrule'] = $rrule;
      }

      $returnValues[] = $item;
    }

    return $returnValues;
  }

  /**
   * Builds date boundaries to span single day.
   *
   * @param string $date
   *   Date formated YYYY-MM-DD, because that's what the filed formater returns.
   * @param string $time_zone
   *   The time zone to use.
   * @param string $end
   *   Either start or end.
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime
   *   Date object.
   *
   * @internal
   */
  protected function buildDateBoundaries(string $date, string $time_zone, string $end) {
    assert(in_array($end, ['start', 'end']));
    $start = $end === 'start';
    $date = explode('-', $date);

    // We are intentionally locking down recurrance periods to a single day.
    // A day is 24 hours, starting at 00:00:01 and ending at 23:59:59.
    $dateParts = [
      'year' => $date['0'],
      'month' => $date['1'],
      'day' => explode("T", $date['2'])[0],
      'hour' => $start ? 00 : 23,
      'minute' => $start ? 00 : 59,
      'second' => $start ? 01 : 59,
    ];

    return DrupalDateTime::createFromArray($dateParts, $time_zone);
  }

  /**
   * Deals with ordinals.
   *
   * @param array $ordinals
   *   The ordinal form values.
   * @param array $weekDays
   *   The week days form values.
   *
   * @return string
   *   Value to set in RRule.
   */
  protected function handleOrdinals(array $ordinals, array $weekDays) {
    // Funge ordinals appropriately.
    $ordinalCheckboxes = array_filter($ordinals);
    $ordinals = [];
    if (count($ordinalCheckboxes) && count($weekDays)) {
      $weekdayCount = count($weekDays);

      // Expand simplified ordinals into spec compliant BYSETPOS ordinals.
      foreach ($ordinalCheckboxes as $ordinal) {
        $end = $ordinal * $weekdayCount;
        $diff = ($weekdayCount - 1);
        $start = ($end > 0) ? $end - $diff : $end + $diff;
        $range = range($start, $end);
        array_push($ordinals, ...$range);
      }

      // Order doesn't matter but simplifies testing.
      sort($ordinals);
      return implode(',', $ordinals);
    }
  }

  /**
   * Ordinals (BYSETPOS).
   *
   * Designed for MONTHLY / YEARLY combined with BYDAY.
   *
   * @param array $element
   *   The currently built element.
   * @param \Drupal\date_recur\DateRecurRuleInterface|null $rule
   *   Optional rule for which default value is derived.
   *
   * @return array
   *   A render array.
   */
  protected function getFieldMonthlyByDayOrdinals(array $element, ?DateRecurRuleInterface $rule): array {
    $parts = $rule ? $rule->getParts() : [];

    $ordinals = [];
    $bySetPos = !empty($parts['BYSETPOS']) ? explode(',', $parts['BYSETPOS']) : [];
    if (count($bySetPos) > 0) {
      $weekdayCount = count($element['weekdays']['#default_value']);
      sort($bySetPos);

      // Collapse all ordinals into simplified ordinals.
      $chunks = array_chunk($bySetPos, $weekdayCount);
      foreach ($chunks as $chunk) {
        $first = reset($chunk);
        $end = ($first < 0) ? min($chunk) : max($chunk);
        $ordinals[] = $end / $weekdayCount;
      }
    }

    return [
      '#type' => 'checkboxes',
      '#title' => $this->t('Weekday Ordinals'),
      '#options' => [
        1 => $this->t('First'),
        2 => $this->t('Second'),
        3 => $this->t('Third'),
        4 => $this->t('Fourth'),
        5 => $this->t('Fifth'),
        -1 => $this->t('Last'),
      ],
      '#default_value' => $ordinals,
    ];
  }

  /**
   * Get field modes for generating #states arrays.
   *
   * Determines whether some fields should be visible.
   *
   * @param \Drupal\date_recur\DateRecurPartGrid $grid
   *   A part grid object.
   *
   * @return array
   *   Field modes.
   */
  protected function getFieldModes(DateRecurPartGrid $grid): array {
    $fieldModes = [];

    $count = $grid->isPartAllowed('DAILY', 'COUNT');
    $until = $grid->isPartAllowed('DAILY', 'UNTIL');
    if ($count || $until) {
      $fieldModes['ends_mode'][] = static::MODE_DAILY;
      $fieldModes['daily_frequency'][] = static::MODE_DAILY;
      if ($count) {
        $fieldModes['ends_count'][] = static::MODE_DAILY;
      }
      if ($until) {
        $fieldModes['ends_date'][] = static::MODE_DAILY;
      }
    }

    if ($grid->isPartAllowed('WEEKLY', 'BYDAY')) {
      $fieldModes['weekdays'][] = static::MODE_WEEKLY;
    }
    $count = $grid->isPartAllowed('WEEKLY', 'COUNT');
    $until = $grid->isPartAllowed('WEEKLY', 'UNTIL');
    if ($count || $until) {
      $fieldModes['ends_mode'][] = static::MODE_WEEKLY;
      if ($count) {
        $fieldModes['ends_count'][] = static::MODE_WEEKLY;
      }
      if ($until) {
        $fieldModes['ends_date'][] = static::MODE_WEEKLY;
      }
    }

    if ($grid->isPartAllowed('MONTHLY', 'BYSETPOS')) {
      $fieldModes['ordinals'][] = static::MODE_MONTHLY;
    }
    if ($grid->isPartAllowed('MONTHLY', 'BYDAY')) {
      $fieldModes['weekdays'][] = static::MODE_MONTHLY;
    }
    $count = $grid->isPartAllowed('MONTHLY', 'COUNT');
    $until = $grid->isPartAllowed('MONTHLY', 'UNTIL');
    if ($count || $until) {
      $fieldModes['ends_mode'][] = static::MODE_MONTHLY;
      if ($count) {
        $fieldModes['ends_count'][] = static::MODE_MONTHLY;
      }
      if ($until) {
        $fieldModes['ends_date'][] = static::MODE_MONTHLY;
      }
    }

    if ($grid->isPartAllowed('YEARLY', 'BYSETPOS')) {
      $fieldModes['ordinals'][] = static::MODE_YEARLY;
    }
    if ($grid->isPartAllowed('YEARLY', 'BYDAY')) {
      $fieldModes['weekdays'][] = static::MODE_YEARLY;
    }
    $fieldModes['month_of_year'][] = static::MODE_YEARLY;
    $count = $grid->isPartAllowed('YEARLY', 'COUNT');
    $until = $grid->isPartAllowed('YEARLY', 'UNTIL');
    if ($count || $until) {
      $fieldModes['ends_mode'][] = static::MODE_YEARLY;
      if ($count) {
        $fieldModes['ends_count'][] = static::MODE_YEARLY;
      }
      if ($until) {
        $fieldModes['ends_date'][] = static::MODE_YEARLY;
      }
    }

    return $fieldModes;
  }

}
