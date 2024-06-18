<?php

namespace Drupal\bos_core\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;

/**
 * Admin Settings form for bos_core.
 *
 * @see https://developers.google.com/analytics/devguides/collection/protocol/v1/reference
 */

/**
 * Class BosCoreSettingsForm.
 *
 * @package Drupal\bos_core\Form
 */
class QueryForm extends ConfigFormBase {

  // These content types are not included in the listing, and are not searched.
  private $always_exclude = [
    "bh_account",
    "bh_contact",
    "bh_meeting",
    "bh_parcel",
    "bh_parcel_project_assoc",
    "bh_project",
    "bh_update",
    "election_report",
    "emergency_alert",
    "metrolist_affordable_housing",
    "metrolist_development",
    "metrolist_unit",
    "neighborhood_lookup",
    "status_item"
  ];

  // These content types are included in the listing, but are not selected.
  private $unselected_cts = [
    "public_notice",
    "change",
    "site_alert"
  ];


  private $node_text_tables = [
    ["table_name" => "node__field_title", "search_field"=>"field_title_value"],
    ["table_name" => "node__title_field", "search_field"=>"title_field_value"],
    ["table_name" => "node__field_description", "search_field"=>"field_description_value"],
    ["table_name" => "node__field_intro_text", "search_field"=>"field_intro_text_value"],
    ["table_name" => "node__field_extra_info", "search_field"=>"field_extra_info_value"],
    ["table_name" => "node__field_additional_information", "search_field"=>"field_additional_information_value"],
    ["table_name" => "node__field_address", "search_field"=>"field_address_address_line1"],
    ["table_name" => "node__field_address", "search_field"=>"field_address_address_line2"],
    ["table_name" => "node__field_event_contact", "search_field"=>"field_event_contact_value"],
    ["table_name" => "node__field_first_name", "search_field"=>"field_first_name_value"],
    ["table_name" => "node__field_last_name", "search_field"=>"field_last_name_value"],
    ["table_name" => "node__field_need_to_know", "search_field"=>"field_need_to_know_value"],
    ["table_name" => "node__field_related_links", "search_field"=>"field_related_links_title"],
    ["table_name" => "node__field_email", "search_field"=>"field_email_value"],
    ["table_name" => "node__field_thanks", "search_field"=>"field_thanks_value"],
    ["table_name" => "node__field_url", "search_field"=>"field_url_value"],
  ];

  private $node_link_tables = [
    ["table_name" => "node__field_details_link", "search_field"=>"field_details_link_uri"],
    ["table_name" => "node__field_related_links", "search_field"=>"field_related_links_uri"],
    ["table_name" => "node__field_email", "search_field"=>"field_email_value"],
    ["table_name" => "node__field_related_links", "search_field"=>"field_related_links_uri"],
    ["table_name" => "node__field_source_file", "search_field"=>"field_source_file_title"],
    ["table_name" => "node__field_source_file", "search_field"=>"field_source_file_uri"],
    ["table_name" => "node__field_document", "search_field"=>"field_document_description"],
    ["table_name" => "node__field_url", "search_field"=>"field_url_value"],
  ];

  private $media_tables = [
    ["table_name" => "file__field_file_image_title_text", "search_field"=>"field_file_image_title_text_value"],
    ["table_name" => "file__field_image_caption", "search_field"=>"field_image_caption_value"],
    ["table_name" => "node__field_source_file", "search_field"=>"field_source_file_title"],
    ["table_name" => "node__field_source_file", "search_field"=>"field_source_file_uri"],
    ["table_name" => "media__field_document", "search_field"=>"field_document_description"],
  ];

  private $para_text_tables = [
    ["table_name" => "paragraph__field_intro_text", "search_field"=>"field_intro_text_value"],
    ["table_name" => "paragraph__field_description", "search_field"=>"field_description_value"],
    ["table_name" => "paragraph__field_title", "search_field"=>"field_title_value"],
    ["table_name" => "paragraph__field_component_title", "search_field"=>"field_component_title_value"],
    ["table_name" => "paragraph__field_message", "search_field"=>"field_message_value"],
    ["table_name" => "paragraph__field_subheader", "search_field"=>"field_subheader_value"],
    ["table_name" => "paragraph__field_additional_information", "search_field"=>"field_additional_information_value"],
    ["table_name" => "paragraph__field_extra_info", "search_field"=>"field_extra_info_value"],
    ["table_name" => "paragraph__field_column_title", "search_field"=>"field_column_title_value"],
    ["table_name" => "paragraph__field_column_description", "search_field"=>"field_column_description_value"],
    ["table_name" => "paragraph__field_left_column", "search_field"=>"field_left_column_value"],
    ["table_name" => "paragraph__field_middle_column", "search_field"=>"field_middle_column_value"],
    ["table_name" => "paragraph__field_right_column", "search_field"=>"field_right_column_value"],
    ["table_name" => "paragraph__field_short_description", "search_field"=>"field_short_description_value"],
    ["table_name" => "paragraph__field_short_title", "search_field"=>"field_short_title_value"],
    ["table_name" => "paragraph__field_sidebar_text", "search_field"=>"field_sidebar_text_value"],
    ["table_name" => "paragraph__field_step_details", "search_field"=>"field_step_details_value"],
    ["table_name" => "paragraph__field_address", "search_field"=>"field_address_address_line1"],
    ["table_name" => "paragraph__field_address", "search_field"=>"field_address_address_line2"],
    ["table_name" => "paragraph__field_internal_link", "search_field"=>"field_internal_link_title"],
    ["table_name" => "paragraph__field_external_link", "search_field"=>"field_external_link_title"],
    ["table_name" => "paragraph__field_message_link_url", "search_field"=>"field_message_link_url_uri"],
    ["table_name" => "paragraph__field_message_link_url", "search_field"=>"field_message_link_url_title"],
    ["table_name" => "paragraph__field_first_name", "search_field"=>"field_first_name_value"],
    ["table_name" => "paragraph__field_last_name", "search_field"=>"field_last_name_value"],
    ["table_name" => "paragraph__field_header", "search_field"=>"field_header_value"],
    ["table_name" => "paragraph__field_how_to_title", "search_field"=>"field_how_to_title_value"],
    ["table_name" => "paragraph__field_keep_in_mind", "search_field"=>"field_keep_in_mind_value"],
    ["table_name" => "paragraph__field_quote", "search_field"=>"field_quote_value"],
  ];
  private $para_link_tables = [
    ["table_name" => "paragraph__field_source_url", "search_field"=>"field_source_url_value"],
    ["table_name" => "paragraph__field_external_link", "search_field"=>"field_external_link_uri"],
    ["table_name" => "paragraph__field_external_link", "search_field"=>"field_external_link_title"],
    ["table_name" => "paragraph__field_internal_link", "search_field"=>"field_internal_link_uri"],
    ["table_name" => "paragraph__field_internal_link", "search_field"=>"field_internal_link_title"],
    ["table_name" => "paragraph__field_lightbox_link", "search_field"=>"field_lightbox_link_uri"],
    ["table_name" => "paragraph__field_lightbox_link", "search_field"=>"field_lightbox_link_title"],
    ["table_name" => "paragraph__field_source_url", "search_field"=>"field_source_url_value"],
    ["table_name" => "paragraph__field_document", "search_field"=>"field_document_description"],
  ];

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bos_core_query';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ["bos_core.settings"];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $defs = \Drupal::entityTypeManager()
      ->getStorage('node_type')
      ->loadMultiple();
    $content_types = [];
    foreach($defs as $def) {
      if (!in_array($def->id(), $this->always_exclude)) {
        $content_types[$def->id()] = $def->id();
      }
    }
    $form = [
      '#tree' => TRUE,
      'bos_core' => [
        '#type' => 'fieldset',
        '#title' => 'Site Search Functions',
        '#description' => 'Run deep, field-level searches for content occurances in site components.',
        '#description_display' => "before",
        '#collapsible' => FALSE,

        "conditions" => [
          "#type" => "details",
          "#title" => "Search Conditions",
          '#description' => "This section allows you to select content types and field types to scan during the search.",
          '#description_display' => "before",
          'nodes' => [
            "#type" => "details",
            '#collapsible' => TRUE,
            "#title" => "Select Content Types to scan:",
            '#description' => "Select one or more content-types to search in.",
            '#description_display' => "before",
            "content_types" => [
              "#type" => "checkboxes",
              '#required' => TRUE,
              "#options" => $content_types,
            ],
          ],
          'fields' => [
            "#type" => "details",
            "#title" => "Select types of field to scan:",
            '#description' => "Select one or more field types to search in.",
            '#description_display' => "before",
            'field_types' => [
              "#type" => "checkboxes",
              '#required' => TRUE,
              "#options" => [
                'text_tables' => 'Text fields',
                'link_tables' => 'Link and Email fields',
                'media_tables' => 'Image and document fields',
              ],
              'text_tables' => [
                '#description' => 'Text and title fields on selected content types. Includes email links, linked images, linked files and "scripts" embedded in text fields.',
                '#default_value' => 'text_tables',
              ],
              'link_tables' => [
                '#description' => 'Specific email fields on selected content types',
                '#default_value' => 0,
              ],
              'media_tables' => [
                '#description' => 'Specific URL, file and image fields on selected content types. Note there is a separate search <a href="abc">here</a> that can be used to find embedded occurances of media objects.',
                '#default_value' => 0,
              ],
            ],
          ],
        ],
        "query" => [
          "#type" => "details",
          "#title" => "Text Search",
          "#description" => "Enter a text string to search for.<br><i>This searches the source HTML and script tags rather than the rendered page, so you can look for embedded URL's and HTML attributes.</i>",
          '#description_display' => "before",
          '#open' => TRUE,
          "search_text" => [
            '#type' => 'textfield',
            '#required' => TRUE,
            "#attributes" => ["style" => ["float:left"],],
          ],
          "search_button" => [
            "#type" => "button",
            '#value' => "Search",
            "#attributes" => ["class" => ["button", "button--primary"],],
            '#ajax' => [
              'callback' => '::makeSearch',
              'event' => 'click',
              'disable-refocus' => FALSE,
              'wrapper' => "edit-bos-core-query-results",
              'progress' => [
                'type' => 'throbber',
              ],
            ],
          ],
          "results" => [
            "#type" => "fieldset",
            "#attributes" => [
              'style' => ["display:none;"],
            ],
          ],
        ],
        "file" => [
          "#type" => "details",
          "#title" => "Advanced Search",
          '#description' => "Advanced search functions.",
          '#description_display' => "before",
          "email_checker" => [
            "#type" => "fieldset",
            "#title" => "Email List Search",
            '#description' => "Supply a csv file with a single email address on each line and this function will provide a file with information on occurances found.",
            '#description_display' => "before",
            'loader' => [
              '#type' => 'managed_file',
              '#name' => 'searchfile',
              '#title' => t('Search Input File'),
              '#size' => 20,
              '#description' => t('CSV format only'),
              '#upload_validators' => array(
                'file_validate_extensions' => array('csv txt'),
                'file_validate_size' => array(5*1024*1024),
              ),
              '#upload_location' => 'public://tmp/',
            ],
            "advanced_button" => [
              "#type" => "button",
              '#value' => "Search Using File",
              "#attributes" => ["class" => ["button", "button--primary"],],
              '#ajax' => [
                'callback' => '::makeAdvancedSearch',
                'event' => 'click',
                'disable-refocus' => FALSE,
                'wrapper' => "edit-bos-core-file-advresults",
                'progress' => [
                  'type' => 'throbber',
                ]
              ],
            ],
          ],
          "advresults" => [
            "#type" => "fieldset",
            "#attributes" => [
              'style' => ["display:none;"],
            ],
          ]
        ]
      ],
    ];

    // select all items, but the ones optionally excluded.
    foreach($form["bos_core"]["conditions"]["nodes"]["content_types"]["#options"] as $ct) {
      if (!in_array($ct, $this->unselected_cts)) {
        $form["bos_core"]["conditions"]["nodes"]["content_types"][$ct]["#default_value"] = $ct;
      }
    }

    $form = parent::buildForm($form, $form_state);
    $form['actions']['submit']['#attributes']=['style'=>['display:none']];
    return $form;
  }

  public function makeSearch(array &$form, FormStateInterface $form_state) {
    $search = $form_state->getValue("bos_core")["query"]["search_text"];
    $search = str_replace("%", "", $search);
    $fields = $form["bos_core"]["conditions"]["fields"]["field_types"];

    $results = $this->makeQuery($search, $fields["#value"], $this->getExclusions($form_state));

    $formatted_results = $this->formatSearchResults($search, $results);

    $form["bos_core"]["query"]["results"] = [
      "#type" => "fieldset",
      "#attributes" => [
        "id" => "edit-bos-core-query-results",
        "style" => ["display:block;"],
      ],
      "markup" => [
        "#markup" => Markup::create($formatted_results)
      ]
    ];
    return $form["bos_core"]["query"]["results"];
  }

  private function getExclusions(FormStateInterface $form_state) {
    $cts = $form_state->getValues()["bos_core"]["conditions"]["nodes"]["content_types"];
    $exclusions = $this->always_exclude;
    foreach($cts as $key => $ct) {
      if ($key != $ct) {
        $exclusions[] = $key;
      }
    }
    return $exclusions;
  }

  private function makeQuery(string $search, array $fields, array $exclude) {

    $node_tables = !empty($fields["text_tables"]) ? $this->node_text_tables : [];
    $node_tables = !empty($fields["link_tables"]) ? array_merge($node_tables, $this->node_link_tables) : $node_tables;
    $paragraph_tables = !empty($fields["text_tables"]) ? $this->para_text_tables : [];
    $paragraph_tables = !empty($fields["link_tables"]) ? array_merge($paragraph_tables, $this->para_link_tables) : $paragraph_tables;
    $media_tables = !empty($fields["media_tables"]) ? $this->media_tables : [];

    $union1 = [];
    $union2 = [];
    $conn = \Drupal::database();

    if (!empty($fields["text_tables"])) {
      // Search the node title
      $union1[] = "SELECT nid component_id,  type content_type, 'node.title' component, title result, nid nid
	              FROM node_field_data
	              WHERE title LIKE @search";

      // Search the node body
      $union1[] = "SELECT entity_id component_id,  bundle content_type, 'node.body' component, body_value result, entity_id nid
	              FROM node__body
	              WHERE body_value like @search";
    }

    // Search the node fields
    foreach($node_tables as $table) {
      $type = str_replace("node__field_","", $table["table_name"]);
      $union1[] = "
	      SELECT entity_id component_id,  bundle content_type, 'node.{$type}' component, {$table["search_field"]} result, entity_id nid
	      FROM {$table["table_name"]}
	      WHERE {$table["search_field"]} LIKE @search";
    }

    foreach($paragraph_tables as $para) {
      $find_parent = "IF(para.parent_type = 'node', para.parent_id,
                        IF(para2.parent_type = 'node', para2.parent_id,
                          IF(para3.parent_type = 'node', para3.parent_id,
                            IF(para4.parent_type = 'node', para4.parent_id,
                            para5.parent_id))))";
      $union1[] = "
        SELECT DISTINCT
		        entity_id component_id
            , (SELECT type FROM node WHERE nid = $find_parent) content_type
		        , txt.bundle component, txt.{$para["search_field"]} result
		        , {$find_parent} node_id
        FROM {$para["table_name"]} txt
            INNER JOIN paragraphs_item_field_data para ON para.id = txt.entity_id
            LEFT OUTER JOIN paragraphs_item_field_data para2 ON para2.id = para.parent_id
            LEFT OUTER JOIN paragraphs_item_field_data para3 ON para3.id = para2.parent_id
            LEFT OUTER JOIN paragraphs_item_field_data para4 ON para4.id = para3.parent_id
            LEFT OUTER JOIN paragraphs_item_field_data para5 ON para5.id = para4.parent_id
        WHERE txt.{$para["search_field"]} LIKE @search
        HAVING node_id IS NOT NULL";
    }

    if (!empty($union1)) {
      $query1 = "CREATE TEMPORARY TABLE COBQUERY1 \n";
      $query1 .= implode("\nUNION \n", $union1);

      try {
        $conn->query("DROP TABLE IF EXISTS COBQUERY1")->execute();
        $conn->query("SET @search = '%{$search}%'")->execute();

        // Do the search
        $conn->query($query1);
      }
      catch (\Exception $e) {
        return FALSE;
      }
    }

    if (!empty($fields["media_tables"])) {

      // Check the managed files table
      $union2[] = "SELECT fid component_id,  'file' content_type, 'file.filename' component, filename result, fid nid
	              FROM file_managed
	              WHERE filename LIKE @search";
      $union2[] = "SELECT fid component_id,  'file' content_type, 'file.uri' component, uri result, fid nid
	              FROM file_managed
	              WHERE uri LIKE @search";
      $union2[] = "SELECT mid component_id,  'media' content_type, 'media.name' component, name result, mid nid
	              FROM media_field_data
	              WHERE name LIKE @search";
      $union2[] = "SELECT entity_id component_id,  'media' content_type, 'media.title' component, image_title result, entity_id nid
	              FROM media__image
	              WHERE image_title LIKE @search";

      foreach($media_tables as $file) {
        $type = str_replace("field_","", $file["search_field"]);
        $component = explode("_", $file["table_name"])[0];
        $union2[] = "
            SELECT entity_id component_id,  bundle content_type, '${component}.{$type}' component, {$file["search_field"]} result, entity_id nid
	          FROM {$file["table_name"]}
	          WHERE {$file["search_field"]} LIKE @search";
      }

      if (!empty($union2)) {

        $query2 = "CREATE TEMPORARY TABLE COBQUERY2 \n";

        $query2 .= implode("\nUNION \n", $union2);

        try {
          $conn->query("DROP TABLE IF EXISTS COBQUERY2")->execute();
          $conn->query("SET @search = '%{$search}%'")->execute();

          // Do the search
          $conn->query($query2);
        }
        catch (\Exception $e) {
          return FALSE;
        }
      }
    }

    /*
     * We now have 1 or 2 tables created by some big union queries, containing
     * the search results.
     * Now need to filter and format the results.
     */

    // Reformat and filter the search results
    $query = "";
    if ($union1) {

      // Set up exclusions
      $exclude_condition = "";
      if (!empty($exclude)) {
        $exclude_condition = implode("','", $exclude);
        $exclude_condition = "AND content_type NOT IN ('{$exclude_condition}') ";
      }

      $query .= "
        SELECT DISTINCT
            'content' grp
            ,  content_type
            , (SELECT CONCAT('https://-base-/node', alias) FROM path_alias WHERE path =  CONCAT('/node/', outs.nid) AND status = 1) alias
            , (SELECT moderation_state FROM content_moderation_state_field_data WHERE content_entity_id = outs.nid) mod_state
            , component
          , REPLACE(REPLACE(result, '\"', ''''), '\r\n', '') result
          , nid
        FROM COBQUERY1 outs
        WHERE content_type IS NOT NULL
        {$exclude_condition}";
    }

    if ($union1 && $union2) {
      $query .= "\nUNION\n";
    }

    if ($union2) {
      $query .= "
        SELECT DISTINCT
          'media' grp
          , content_type
          , '' alias
          , (SELECT count(*) FROM drupal.file_usage where fid = outs2.nid) mod_state
          , component
          , result
          , nid
        FROM COBQUERY2 outs2 ";
    }

    if ($query) {
      $query .= "\nORDER BY grp, content_type, component";
      try {
        if ($qry = $conn->query($query)) {
          return $qry->fetchAll();
        }
      }
      catch (\Exception $e) {
        return FALSE;
      }
    }

    return FALSE;

  }

  private function formatSearchResults($search, $results) {
    if ($results) {
      $base = \Drupal::request()->getHttpHost();
      if ($base == "boston.gov" || $base = "www.boston.gov") {
        $base = "content.boston.gov";
      }

      $content = [];
      $media = [];
      foreach($results as $result) {
        $result->grp == "content" ? $content[] = $result : $media[] = $result;
      }
      $output = "";
      if ($content) {
        $count = count($content);
        $dedup = [];
        $output .= "<h3>There were {$count} matches for '{$search}' on pages in the site</h3><h4>The following list of pages has the search string in one or more components:</h4>";
        $output .= "<table><thead><tr><th style='max-width: 50%; width: 50%;'>Page</th><th>Content Type</th><th>State</th><th>Actions</th></tr></thead>";
        foreach ($content as $result) {
          if (!in_array($result->nid, $dedup)) {
            $result->alias = str_replace('-base-', $base, $result->alias ?: '');
            $page = ($result->alias != "" ? "{$result->alias} ({$result->nid})" : "NODE {$result->nid}");
            $alias = ($result->mod_state == "published" ? $result->alias : "https://{$base}/node/{$result->nid}");
            $output .= "<tr>
              <td>{$page}</td>
              <td>{$result->content_type} ({$result->component})</td>
              <td>{$result->mod_state}</td>
              <td>
                <a href='{$alias}' class='button'>View</a><a href='https://{$base}/node/{$result->nid}/edit' class='button'>Edit</a>
              </td>
              </tr>";
            $dedup[] = $result->nid;
          }
        }
        $output .= "</table>";
      }

      if ($media) {
        $count = count($media);
        $dedup = [];
        $output .= "<h3>There were {$count} matches for '{$search}' in media saved in the site</h3>";
        $output .= "<table><thead><tr><th>File ID</th><th>Type</th><th style='max-width:60%;width:60%;'>Match String</th><th>Occurance</th><th>Actions</th></tr></thead>";
        foreach ($media as $result) {
          if (!in_array($result->nid, $dedup)) {
            $output .= "<tr>
              <td>{$result->nid}</td>
              <td>{$result->content_type}</td>
              <td>({$result->component}):<br>{$result->result}</td>
              <td>{$result->mod_state}</td>
              <td>
                <a href='https://{$base}/file/{$result->nid}/edit' class='button'>Edit</a> <a href='https://{$base}/file/{$result->nid}/usage' class='button'>Usage</a>
              </td>
              </tr>";
            $dedup[] = $result->nid;
          }
        }
        $output .= "</table>";
      }
    }
    else {
      $output = "Sorry the string '{$search}' was not found.<br/>Please alter the search and try again.";
    }
    return $output;
  }

  public function makeAdvancedSearch(array &$form, FormStateInterface $form_state) {
    if ($fid = $form_state->getValues()['bos_core']["file"]["email_checker"]["loader"][0]) {
      if ($file = \Drupal::entityTypeManager()->getStorage("file")->load($fid)) {

        $file_path = $file->get("uri")[0]->value;

        if ($emails = file_get_contents($file_path)) {
          $emails = explode(',', $emails);
          $ran = strtotime("now");
          $downloadfile = "public://tmp/download{$ran}.csv";

          $exclusions = $this->getExclusions($form_state);

          $fs = fopen($downloadfile, "w");
          fwrite($fs, "\"email\", \"count\",\n");

          foreach ($emails as $email) {
            $email = strtolower(trim(str_replace(['"', "\n"], '', $email)));
            if (!empty($email)) {
              try {
                $count = $this->countOccurrances($email, $exclusions);
              }
              catch (\Exception $e) {
                $count = "";
              }
            }
            else {
              $count = "";
            }
            $msg = "\"{$email}\",{$count},\n";
            fwrite($fs, $msg);
          }

          fclose($fs);

//          $response = new BinaryFileResponse($downloadfile);
//          $form_state->setResponse($response);

          $download_link = \Drupal::service('stream_wrapper_manager')->getViaUri($downloadfile)->getExternalUrl();

          return [
            "#type" => "fieldset",
            "#attributes" => [
              "id" => "edit-bos-core-file-advresults",
              "style" => ["display:block;"],
            ],
            "markup" => [
              "#markup" => Markup::create("Site Successfully scanned. Results can be <a class='button' href='$download_link'>downloaded here</a>.")
            ]
          ];
        }
      }
    }
  }

  private function countOccurrances($search, $exclusions) {
    try {
      if ($result = $this->makeQuery($search, $exclusions)) {
        return count($result);
      }
      else {
        return "";
      }
    }
    catch (\exception $e) {
      return "";
    }

  }
}
