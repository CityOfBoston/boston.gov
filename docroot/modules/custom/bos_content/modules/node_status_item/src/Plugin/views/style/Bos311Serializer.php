<?php

namespace Drupal\node_status_item\Plugin\views\style;

use Drupal\rest\Plugin\views\style\Serializer;

/**
 * The style plugin for serialized output formats.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "node_status_item",
 *   title = @Translation("Status Item"),
 *   help = @Translation("Serializes views row data using the Serializer
 *   component."), display_types = {"data"}
 * )
 */
class Bos311Serializer extends Serializer {
  public function render() {
    $output = [];

    // Get the feed output.
    $feed = json_decode(parent::render());

    // Reformat the feed output, aggregating the language variants into a
    // single row per status_item.
    foreach($feed as $status_item) {

      !empty($output[$status_item->id]) ?: $output[$status_item->id] = [];

      foreach ($status_item as $field => $value) {
        switch ($field) {
          case "body":
          case "title":
            // Translatable fields.
            if (empty($output[$status_item->id][$field])) {
              $output[$status_item->id][$field] = [];
            }
            $output[$status_item->id][$field][$status_item->language] = trim(str_ireplace("\n", "", $value));

            // Will create zh and zh-hant translations by cloning zh-hans.
            // @see https://bostondoit.atlassian.net/browse/DIG-824
            if ($status_item->language == "zh-hans") {
              $output[$status_item->id][$field]["zh"] = $output[$status_item->id][$field][$status_item->language];
              $output[$status_item->id][$field]["zh-hant"] = $output[$status_item->id][$field][$status_item->language];
            }

            break;

          case "media":
            $value = $this->_clean_url($value);
            if (!empty($value)) {
              $output[$status_item->id][$field] = [$value];
            }
            break;

          case "311_link_label":
            if (!empty($value) && $value !== "none") {
              if (!empty($status_item->{"311_link"})) {
                $suffix_link = $this->_clean_url($status_item->{"311_link"});
              }
              elseif (!empty($status_item->cta_link)) {
                $suffix_link = $this->_clean_url($status_item->cta_link);
              }
              else {
                $suffix_link = "";
              }
              if (!empty($suffix_link)) {
                $suffix_label = t($status_item->{'311_link_label'}, [], ['langcode' => $status_item->language]);
                //                $suffix = "<br/><a href=\"{$suffix_link}\">{$suffix_label}</a>";
                $suffix = " {$suffix_label}: {$suffix_link}";
                if (empty($output[$status_item->id]["body"])) {
                  $output[$status_item->id]["body"][$status_item->language] = $suffix;
                }
                else {
                  $output[$status_item->id]["body"][$status_item->language] .= $suffix;
                }
              }
            }
            break;

          case "311_link":
          case "cta_link":
            # not output
            break;

          case "show":
          case "published_at":
            // Only use published field from the base (en) variant.
            if ($status_item->language == "en") {
              $output[$status_item->id][$field] = strip_tags(str_ireplace("\n", "", $value));
            }
            break;

          case "changed":
            // Make sure the most recent update date is recorded.
            $date = strip_tags(str_ireplace("\n", "", $value));
            if (strtotime($date) > strtotime($output[$status_item->id]["updated_at"]??"2000-01-01")) {
              $output[$status_item->id]["updated_at"] = $date;
            }
            break;

          default:
            // Non-Translatable fields.
            $output[$status_item->id][$field] = str_ireplace("\n", "", $value);
            break;

        }
      }

      // Enabled flag will only be true if:
      //  - The node is Published and the Node has field_enabled = True.
      if ($status_item->language == "en") {
        $output[$status_item->id]["enabled"] = (($status_item->enabled == "True") && ($status_item->isPublished == "True"));
        // Convert to a string.
        $output[$status_item->id]["enabled"] = ($output[$status_item->id]["enabled"] ? "True" : "False");
      }

    }

    // Now cleanup the output.
    foreach($output as $key => &$row) {
      if (count($row) == 0) {
        unset($output[$key]);
        continue;
      }
      unset($row["show"]);    // redundant
      unset($row["changed"]);  // redundant
      unset($row["isPublished"]);  // redundant
      unset($row["language"]);  // not relevant any longer
    }

    $output = array_values($output);
    return json_encode($output);
  }

  /**
   * Convert html encoded link/image into a plain url.
   *
   * @param string $url
   *
   * @return string
   */
  private function _clean_url(string $url) {

    $url = trim(str_ireplace("\n", "", $url));
    if (!empty($url)) {
      preg_match('/="(.*?)"/', $url, $url_match);
      if (!empty($url_match) && isset($url_match[1])) {
        if (substr($url_match[1], 0, 4) != "http") {
          // URL was provided as an internal URL, expand out.
          $url_match[1] = (substr($url_match[1], 0, 1) != "/" ? "/" . $url_match[1] : $url_match[1]);
          $url_match[1] = \Drupal::request()
              ->getSchemeAndHttpHost() . $url_match[1];
        }
      }
      else {
        $url = \Drupal::pathValidator()->getUrlIfValid($url);
        if ($url) {
          if ($url->isExternal()) {
            $url_match = [1 => $url->getUri()];
          }
          else {
            $url_match = [
              1 => \Drupal::request()
                  ->getSchemeAndHttpHost() . "/" . $url->getInternalPath()
            ];
          }
        }
        else {
          $url_match = [1 => ""];
        }
      }

      return $url_match[1];
    }
    return "";
  }
}
