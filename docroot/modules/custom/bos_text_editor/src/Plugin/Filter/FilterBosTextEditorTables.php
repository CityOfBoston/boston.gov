<?php

namespace Drupal\bos_text_editor\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

/**
 * @Filter(
 *   id = "filter_bos_text_editor_tables",
 *   title = @Translation("BOS Tables Filter"),
 *   description = @Translation("Used to transform standard tables into responsive tables for BOS."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_MARKUP_LANGUAGE,
 * )
 */
class FilterBosTextEditorTables extends FilterBase {


  /**
   * @param string $text
   * @param string $langcode
   * @return FilterProcessResult
   */
  public function process($text, $langcode) {

    $html = new Html();
    $dom = $html->load($text);

    foreach ($dom->getElementsByTagName('table') as $table) {
      $headers = [];

      $table->setAttribute('class', 'responsive-table');

      //Build out Table header if it is a horizontal table
      $tableHeader = $table->getElementsByTagName('thead');
      if ($tableHeader->count()) {
        $table->setAttribute('class', 'responsive-table responsive-table--horizontal');
        foreach ($tableHeader->item(0)->getElementsByTagName('th') as $header) {
          $headers[] = $header->nodeValue;
        }
      }

      $tableBody = $table->getElementsByTagName('tbody');
      foreach ($tableBody->item(0)->getElementsByTagName('tr') as $row) {

        if ($vert_header = $row->getElementsByTagName('th')->item(0)->nodeValue ?? null) {
          $table->setAttribute('class', 'responsive-table responsive-table--vertical');
        }

        foreach ($row->getElementsByTagName('td') as $colNumber => $cell){
          //Set data-label for horizontal table or vertical table
          if ($headers) {
            $cell->setAttribute('data-label', $headers[$colNumber]);
          }elseif ($vert_header) {
            $cell->setAttribute('data-label', $vert_header);
          }
        }
      }
    }

    return new FilterProcessResult($html::serialize($dom));
  }

}
