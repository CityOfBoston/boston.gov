<?php

namespace Drupal\bos_email\Plugin\EmailProcessor;

use Drupal\bos_email\CobEmail;
use Drupal\bos_email\Plugin\EmailProcessor\EmailProcessorBase;

/**
 * EmailProcessor class for Metrolist Listing Confirmation Emails.
 */
class MetrolistListingConfirmation extends EmailProcessorBase {

  /**
   * @inheritDoc
   */
  public static function templatePlainText(array &$payload, CobEmail &$email_object):void {

    $vars = self::_getRequestParams();

    $text = "
Thank you for your submission to Metrolist.\n
{$vars["submission_type"]}
Property Name: {$vars["property_name"]}
Number of Units Updated: {$vars["units_updated"]}
Number of Units Added: {$vars["units_added"]}\n
We will review your submission and contact you if we have any questions.\n
IMPORTANT: If you need to submit listings for additional properties, please request a new form.\n\n
Thank you.
Mayor's Office of Housing.\n
--------------------------------
This message was sent using the Metrolist Listing form on Boston.gov.
 The request was initiated by {$payload['name']} from {$payload['to_address']} from the page at " . urldecode($payload['url']) . ".\n
--------------------------------
";

    $email_object->setField("TextBody", $text);

  }

  /**
   * @inheritDoc
   */
  public static function templateHtmlText(array &$payload, CobEmail &$email_object):void {

    $vars = self::_getRequestParams();

    // todo: add the submission link to the email.
    $submission_link = "";
    if (!empty($vars["submission"])) {
//      $submission_link = "<a class=\"button\" tabindex=\"-1\" href='{$vars["submission"]}' target='_blank'>View Submission</a>";
//      $submission_link = "<tr><td colspan='2'>{$submission_link}</td></tr>\n";
    }
    $units_added = "";
    if (!empty($vars["units_added"])) {
      $units_added = "<tr><td><span class='txt-b'>Number of Units Added:</span></td><td>{$vars["units_added"]}</td></tr>\n";
    }
    $units_updated = "";
    if (!empty($vars["units_updated"])) {
      $units_updated = "<tr><td><span class='txt-b'>Number of Units Updated:</span></td><td>{$vars["units_updated"]}</td></tr>\n";
    }

    $html = "
<img class='ml-icon' height='34' src='https://assets.boston.gov/icons/metrolist/metrolist-logo_email.png' />\n
<p class='txt'>Thank you for your submission to Metrolist.</p>\n
<p class='txt'>{$vars["submission_type"]}</p>\n
<p class='txt'><table class='moh-signature' cellpadding='0' cellspacing='0' border='0'>\n
<tr><td><span class='txt-b'>Property Name:</span></td><td>{$vars["property_name"]}</td></tr>\n
{$units_added}
{$units_updated}
{$submission_link}
</table></p>\n
<p class='txt'>We will review your submission and contact you if we have any questions.</p>
<p class='txt'><span class='txt-b'>Important:</span> If you need to submit listings for additional properties, please request a new form.</p>\n
<p class='txt'><br /><table class='moh-signature' cellpadding='0' cellspacing='0' border='0'><tr>\n
<td><a href='https://content.boston.gov/departments/housing' target='_blank'>
  <img height='34' class='ml-icon' src='https://assets.boston.gov/icons/metrolist/neighborhood_development_logo_email.png' />
  </a></td>\n
<td>Thank you<br /><span class='txt-b'>The Mayor's Office of Housing</span></td>\n
</tr></table></p>\n
<hr>\n
<p class='txt'>This message was sent after completing the Metrolist Listing form on Boston.gov.</p>\n
<p class='txt'>The form was submitted by {$payload['name']} ({$payload['to_address']}) from the page at " . urldecode($payload['url']) . ".</p>
<hr>\n
";

    $html = self::_makeHtml($html,  $payload["subject"]);

    $email_object->setField("HtmlBody", $html);

  }

  /**
   * @inheritDoc
   */
  public static function parseEmailFields(array &$payload, CobEmail &$email_object): void {

    // Do the base email fields processing first.
    parent::parseEmailFields($payload, $email_object);

    $email_object->setField("Tag", "metrolist confirmation");

    self::templatePlainText($payload, $email_object);
    if (!empty($payload["useHtml"])) {
      self::templateHtmlText($payload, $email_object);
    }

  }

  /**
   * @inheritDoc
   */
  public static function getCss() {
    $css = parent::getCss();
    return "<style>
        {$css}
        .ml-icon {
          height: 34px;
        }
        table.moh-signature {
          border: none;
          border-collapse: collapse;
        }
        table.moh-signature tr,
        table.moh-signature td {
          padding: 3px;
          border-collapse: collapse;
        }
        </style>";
  }

  /**
   * Helper function to extract and process request parameters.
   *
   * @return array
   */
  public static function _getRequestParams() {
    $request = \Drupal::request();
    $output = [
      "property_name" => $request->get("property_name",""),
      "add_additional_units" => $request->get("add_additional_units",0),
      "update_unit_information" => $request->get("update_unit_information",0),
      "new" => ($request->get("select_development", "") == "new"),
      "current_units" => count($request->get("current_units", [])), // units that existed before submission
      "units" => count($request->get("units", [])), // units created by submission
      "units_updated" => 0,
      "units_added" => 0,
      "sid" => $request->get("sid",""),
      "serial" => $request->get("serial",""),
      "submission_type" => "New property listing."
    ];
    $decisions = [];

    if (empty($output["new"])) {

      if (!empty( $request->get("update_my_contact_information"))) {
        // should be first for sentance construction logic to work
        $decisions[] = "your contact details";
      }
      if (!empty( $request->get("update_building_information"))) {
        $decisions[] = "building information";
      }
      if (!empty( $request->get("update_public_listing_information"))) {
        $decisions[] = "public-listing information";
      }
      if (!empty($output["update_unit_information"])) {
        $decisions[] = "existing unit information";
      }
      if (!empty($output["add_additional_units"])) {
        // Must be last for sentance construction logic to work.
        $decisions[] = "add new units";
      }

      if (empty($decisions) || count($decisions) == 0) {
        $output["submission_type"] = "You have requested to update the following property.";
      }
      else {
        if (count($decisions) == 1) {
          if ($decisions[0] != "add new units") {
            $decisions[0] = "update {$decisions[0]}";
          }
          $output["submission_type"] = "You have requested to {$decisions[0]} for the following property.";
        }
        else {
          $decisions = implode(", ", $decisions);
          $decisions = substr_replace($decisions, " and ", strrpos($decisions, ", "), 2);
          $output["submission_type"] = "You have requested to update {$decisions} for the following property.";
        }
      }

    }

    // Try to establish how many units have been updated.
    if (!empty($output["update_unit_information"])) {
      // These are units which exist before the form is submitted.
      // One record per unit.
      foreach($request->get("current_units") as $unit) {
        if (!empty($unit["relist_unit"])) {
          // if updating units, then relist will be 0 or 1 where 1 means relist.
          $output["units_updated"] += 1;
        }
      }
    }

    if ($output["new"] || !empty($output["add_additional_units"])) {
      // These are the units add by the form submission.
      // One record per row of the table in the form.
      foreach($request->get("units") as $unit) {
        if (!empty($unit["unit_count"])) {
          $output["units_added"] += intval($unit["unit_count"]);
        }
      }
    }

    if ($request->get("token", FALSE)) {
      $output["submission"] = "/webform/metrolist_listing/submissions/{$output["sid"]}?token=" . $request->get("token");
    }

    return $output;
  }

  /**
   * Ensures an html string is top and tailed as a full html message.
   *
   * @param string $html Html message to format
   * @param string $title Title (in head) for the html object
   *
   * @return string
   */
  public static function _makeHtml(string $html, string $title) {
    // check for complete-ness of html
    if (stripos($html, "<html") === FALSE) {
      $output = "<html>\n<head></head>\n<body>{$html}</body>\n</html>";
    }

    // Add a title
    $output = preg_replace(
      "/\<\/head\>/i",
      "<title>{$title}</title>\n</head>",
      $output);
    // Add in the css
    $css = self::getCss();
    $output = preg_replace(
      "/\<\/head\>/i",
      "{$css}\n</head>",
      $output);

    return $output;

  }

  /**
   * @inheritDoc
   */
  public static function getGroupID(): string {
    return "metrolist";
  }

}
