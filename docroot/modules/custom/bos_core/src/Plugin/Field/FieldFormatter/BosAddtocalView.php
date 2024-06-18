<?php

namespace Drupal\bos_core\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\addtocal\Plugin\Field\FieldFormatter\AddtocalView;
use Spatie\CalendarLinks\Link;

/**
 * Boston Add to Cal view formatter.
 *
 * @FieldFormatter(
 *  id = "bosaddtocal_view",
 *  label = @Translation("Boston Add to Cal"),
 *  field_types = {
 *    "date",
 *    "datestamp",
 *    "datetime",
 *    "daterange",
 *    "date_recur",
 *  }
 * )
 */
class BosAddtocalView extends AddtocalView {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);

    // The following is a fix fot the date_recur field type.
    if (isset($elements) && (count($elements) > 0)) {
      foreach ($items as $delta => $item) {
        $helper = $item->getHelper();
        $now = new \DateTime();
        $events = $helper->getOccurrences($now, NULL);

        if ($events) {
          $next_event = $events[0];
          $start_date = $next_event->getStart();
          $end_date = $next_event->getEnd() ?? $start_date;

          $elements[$delta]['start_date']['#plain_text'] = $this->formatDate($start_date);
          $elements[$delta]['end_date']['#plain_text'] = $this->formatDate($end_date);

          $title = strip_tags($elements[$delta]["addtocal"]["#addtocal_link"]->title);
          $address = $elements[$delta]["addtocal"]["#addtocal_link"]->address;
          $description = strip_tags($elements[$delta]["addtocal"]["#addtocal_link"]->description);
          $link = Link::create($title, $start_date, $end_date);
          $link->address($address);
          $link->description($description);
          $elements[0]["addtocal"]["#addtocal_link"] = $link;

          $elements[$delta]["addtocal"]["#access"] = TRUE;
        }
        else {
          $elements[$delta]["addtocal"]["#access"] = FALSE;
        }
      }
    }

    return $elements;

  }

}
