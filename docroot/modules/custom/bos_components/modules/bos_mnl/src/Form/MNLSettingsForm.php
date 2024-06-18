<?php

namespace Drupal\bos_mnl\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\bos_mnl\Controller\MnlUtilities;
use Drupal\Core\Render\Markup;

/**
 * Class MNLSettingsForm.
 *
 * @package Drupal\bos_mnl\Form
 */
class MNLSettingsForm extends ConfigFormBase {

  /**
   * Implements getFormId().
   */
  public function getFormId() {
    return 'mnl_admin_settings';
  }

  /**
   * Implements getEditableConfigNames().
   */
  protected function getEditableConfigNames() {
    return ["bos_mnl.settings"];
  }

  /**
   * Implements buildForm()
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('bos_mnl.settings');
    $app_update_status = $this->_buildStatus($config->get("mnl_update_import_status"));
    $app_update_flag = ($config->get("mnl_update_flag") == MnlUtilities::MNL_IMPORT_PROCESSING ? date("Y-m-d H:i:s", $config->get("mnl_update_flag")) : "");
    $app_import_status = $this->_buildStatus($config->get("mnl_import_import_status"));
    $app_import_flag = ($config->get("mnl_import_flag") == MnlUtilities::MNL_IMPORT_PROCESSING ? date("Y-m-d H:i:s", $config->get("mnl_import_flag")) : "");

    $mnl_import = \Drupal::queue("mnl_import")->numberOfItems();
    $mnl_update = \Drupal::queue("mnl_update")->numberOfItems();

    $queuelinks = ["all"=>"","import"=>"","update"=>""];
    if ( \Drupal::service('module_handler')->moduleExists('queue_ui')) {
      $queueLinks["all"] = "<th><a href='/admin/config/system/queue-ui'>View All Queues</a></th>";
      $queueLinks["import"] = "<td><a href='/admin/config/system/queue-ui/inspect/mnl_import'>Inspect Queue</a></td>";
      $queueLinks["update"] = "<td><a href='/admin/config/system/queue-ui/inspect/mnl_update'>Inspect Queue</a></td>";
    }
    $count2 = MnlUtilities::MnlSelectCleanUpRecords("2 days ago", TRUE);
    $count5 = MnlUtilities::MnlSelectCleanUpRecords("5 days ago", TRUE);
    $count7 = MnlUtilities::MnlSelectCleanUpRecords("7 days ago", TRUE);

    $form = [
      '#tree' => TRUE,
      'label1' => [
        "#markup" => "<h3>This module exposes REST endpoints at <b><i>/rest/mnl/xxxx</i>.</b></h3><p>See documentation below for REST endpoint syntax.</p>"
      ],
      'mnl_admin' => [
        '#type' => 'fieldset',
        '#title' => 'MNL App Configuration Settings',
        '#description' => 'Configuration for My Neighborhood Lookup custom API endpoints.',
        'auth_token' => [
          '#type' => 'textfield',
          '#title' => t('API KEY / Token'),
          '#description' => t('Enter a random string to authenticate API calls.'),
          '#default_value' => $config->get('auth_token'),
          '#required' => FALSE,
        ],
        'use_entity' => [
          '#type' => 'checkbox',
          '#title' => t('Use Entity Manager'),
          '#description' => t('Select to use the Drupal Entity Manager to execute node updates. When unchecked, code directly changes DB records.'),
          '#default_value' => $config->get('use_entity'),
          '#required' => FALSE,
        ],
        'cleanup' => [
          '#type' => 'checkbox',
          '#title' => t("Purge SAM ID's" ),
          '#description' => t('Select to have the import process delete records. After the full import is completed records which have not been for 7 days will be purged (deleted) from the DB.'),
          '#default_value' => $config->get('cleanup'),
          '#required' => FALSE,
        ],
      ],
      'status' => [
        '#type' => 'fieldset',
        '#title' => 'MNL App Status',
        'label' => [
          "#markup" => Markup::create("
            <table>
            <tr><th>Process</th><th>Status</th></tr>
            <tr><td>Update (daily differential)</td><td>{$app_update_status} {$app_update_flag}</td></tr>
            <tr><td>Import (full sync)</td><td>{$app_import_status} {$app_import_flag}</td></tr>
            </table>
          "),
        ],
        'purge_candidates' => [
          '#type' => 'details',
          '#title' => 'Purge Forecasting',
          '#collapsible' => TRUE,
          'info' => [
            "#markup" => "This is a report showing the number of records which would be purged if \"drush bos:mnl-purge\" commands were executed.<br>
            <table>
            <tr><th>bos:mnl-purge 2<br/>(Records not updated in last 2 days)</th><th>bos:mnl-purge 5<br/>(Records not updated in last 5 days)</th><th>bos:mnl-purge 7<br/>(Records not updated in last 7 days)</th></tr>
            <tr><td>" . number_format($count2,0) . "</td><td>" . number_format($count5, 0) . "</td><td>" . number_format($count7, 0) . "</td></tr>
            </table>"
          ],
        ],
      ],
      'cachelist' => [
        '#type' => 'fieldset',
        '#title' => 'MNL Lookup - Clear JSON Caches',
        '#description_display' => 'before',
        '#description' => 'The JSON:API caches very agressively and a regular cache rebuild will not necessarily refresh records.  If there is old data persisting in the My Neighborhood section, then try clearing caches here.',
        'samids' => [
          '#type' => 'textfield',
          '#title' => t('SAM ID\'s'),
          '#attributes' => [
            'placeholder' => 'Enter a comma separated list of SAM ID\'s'
          ],
          '#required' => FALSE,
        ],
        'container1' => [
          '#type' => 'container',
          'clear' => [
            '#type' => 'submit',
            '#value' => 'Reset Cache'
          ],
          'cleardesc' => [
            '#markup' => t("Enter the SAM ID\'s to clear, or leave blank to clear all.<br/><br/>"),
          ],
        ],
        'container2' => [
          '#type' => 'fieldset',
          '#title' => 'Clear Cache for all <span style="font-weight:bold">neighborhood_lookup</span> nodes',
          '#description_display' => 'before',
          '#description' => t("<span style='color: red !important'><span style='font-weight: bold'>WARNING:</span> USE CARE - this process may take some time to complete and use significant server resources.</span><br><br>"),
          'clearall' => [
            '#type' => 'submit',
            '#value' => 'Reset Cache - All Records'
          ],
        ],
      ],
      'diagnostics' => [
        '#type' => 'fieldset',
        '#title' => 'Syncronization Reporting',
        'current_queue' => [
          '#type' => 'details',
          '#title' => 'Current Queue Status',
          '#collapsible' => TRUE,
          'info' => [
            "#markup" => "
              <table>
                <tr><th>Queue Name</th><th>Description</th><th>Queue Size</th>{$queueLinks["all"]}</tr>
                <tr><td>mnl_update</td><td>SAM Records queued for process from update endpoint (incremental data import).</td><td>" . $mnl_update . "</td>{$queueLinks["update"]}</tr>
                <tr><td>mnl_import</td><td>SAM Records queued for process from import endpoint (data universe import).</td><td>" . $mnl_import . "</td>{$queueLinks["import"]}</tr>
              </table>"
          ],
        ],
        'last_import' => [
          '#type' => 'details',
          '#title' => 'Last 5 REST Imports',
          '#collapsible' => TRUE,
          'info' => [
            "#markup" => "This is a report on the most recent transfers of data to the REST endpoint at /rest/mnl/<br>
              <table>
              <tr><th>mnl_update<th>mnl_import</th></tr>
              <tr><td>" . ($config->get("last_inbound_mnl_update") ?: "Never Run") . "</td><td>" . ($config->get("last_inbound_mnl_import") ?: "Never Run") . "</td></tr>
              <tr><td>" . ($config->get("last_inbound_mnl_update_1") ?: "") . "</td><td>" . ($config->get("last_inbound_mnl_import_1") ?: "") . "</td></tr>
              <tr><td>" . ($config->get("last_inbound_mnl_update_2") ?: "") . "</td><td>" . ($config->get("last_inbound_mnl_import_2") ?: "") . "</td></tr>
              <tr><td>" . ($config->get("last_inbound_mnl_update_3") ?: "") . "</td><td>" . ($config->get("last_inbound_mnl_import_3") ?: "") . "</td></tr>
              </table>"
          ],
        ],
        'last_process' => [
          '#type' => 'details',
          '#title' => 'Last queue processes',
          '#collapsible' => TRUE,
          'info' => [
            "#markup" => "This is a report on processing from the last queue worker for each of the identified queues. The queue worker is activated by a scheduled task, or a manual execution of \"drush queue:run\".<br>
              <table>
              <tr><th>mnl_update<th>mnl_import</th></tr>
              <tr><td>" . ($config->get("last_mnl_update") ?: "Never Run") . "</td><td>" . ($config->get("last_mnl_import") ?: "Never Run") . "</td></tr>
              </table>"
          ],
        ],
        'last_purge' => [
          '#type' => 'details',
          '#title' => 'Last Purge',
          '#collapsible' => TRUE,
          'info' => [
            "#markup" => "<h4>Last Purge Results:</h4>This is a report showing the results of the last purge which was completed after a full import.<br>
              <table>
              <tr><td>" . ($config->get("last_purge") ?: "Never Run") . "</td></tr>
              </table>"
          ],
        ],
      ],
      'documentation' => [
        '#type' => 'fieldset',
        '#title' => 'Documentation',
        'doc' => [
          "#markup" => "This section documents the use of the MNL App and API."
        ],
        'overview' => [
          '#type' => 'details',
          '#title' => 'App Overview',
          '#collapsible' => TRUE,
          'doc' => [
            "#markup" => Markup::create("
              <h4>App Function</h4>
              This app is used to syncronise geo-tagged data between City of
              Boston managed authoritative sources and Drupal.<br>
              The data received is used in the My Neighborhood Lookup app on the
              website, but is stored as a 'neighborhood_lookup' node, so it can
              be used for any purpose on the website.<br>
              The data itself is community information associated with physical
              addresses in the City of Boston. It is extracted and compiled from
              a wide variety of CoB internal databases and data sources.
              <h4>Synchronisation Process</h4>
              The actual syncronisation is performed between Civis and Drupal,
              with Civis initiating and push the data on a schedule (see below).
              <br>
              There are approx 400,000 unique address records being managed with
              the unique address identifier for each record being a CoB
              generated <b>'SAM ID'</b>.<br>
              The SAM ID is a GIS identifier and which has a 1:1 relationship
              with a physical property address.<br><br>
              There are 2 syncronization processes:
              <ol>
                <li>
                  <b>Differential Updates:</b> Updates a set of SAM records
                  which have changed in some way from the previous differential
                  update.<br>
                  <span style='color:blue'><i>Differential updates are sent
                  daily at 9 PM (EST)</i></span>
                </li>
                <li>
                  <b>Full Imports:</b> Updates the complete SAM recordset
                  which is sent to be sure that Drupal is properly sync'd and to
                  allow Drupal to identify records that have been deleted.<br>
                  <span style='color:blue'><i>Full Imports are sent on the last
                  day of each month at 9 PM (EST)<br>
                  (Differential update is not sent on this day)</i></span>
                </li>
              </ol>
              <h4>Workflow</h4>
              This workflow applies for both differential updates and full
              imports.
              <ol>
                <li>
                  When Civis pushes to Drupal, the data is loaded into a queue.
                  <br>
                  Civis transfers the data in chunks of 50,000 records, and when
                  the transfer is complete, sends a completion message.
                </li>
                <li>
                  Every 5 mins Drupal checks the queue. If the transfer is
                  complete, then Drupal starts to process the queue in chunks.
                  <br>
                  Processing means that the data in the queue is compared to the
                  data held in the node for that SAM ID, and is created or
                  updated as needed.
                </li>
                <li>
                  (Full Import only) (optional - see configuration)<br>
                  At the end of the processing, Drupal deletes the nodes which
                  were not updated.
                </li>
              </ol>
            ")
          ],
        ],
        'configuration' => [
          '#type' => 'details',
          '#title' => 'Configuration Settings',
          '#collapsible' => TRUE,
          'doc' => [
            "#markup" => Markup::create("
              <h4>API Key/Token</h4>
              This is a key which Civis must use to authenticate when sending
              data to Drupal.<br>
              <span style='color:blue'><i>Changing this key is immediate and the
              previous key is deleted.</i></span><br>
              <b>All API communications must use the new key immediately after
              it is changed.</b>
              <h4>Use Entity Manager</h4>
              For performance, the syncronization interracts directly with the
              database and does not use the Drupal framework to update the data
              in the 'neighborhood_lookup' nodes.<br>
              Selecting this box forces the synchronization to use the Drupal
              entity manager, which is slower, but is likely to be more robust
              as Drupal releases future versions.
              <h4>Purge SAM ID's</h4>
              Selecting this checkbox will cause the synchronization process to
              purge (cleanup) records at the end of a full import.<br>
              If a record has not been updated during a full impoort, it clearly
              has been deleted from the CoB records and should be deleted from
              Drupal.<br>
              Enabling this function deletes any records that were not updated
              during the full import, or by any of the differential imports in
              the preceeding 7 days.<br>
              Disabling the function means no purging occurs after a full
              import.
            ")
          ],
        ],
        'api_notes' => [
          '#type' => 'details',
          '#title' => 'API Notes',
          '#collapsible' => TRUE,
          'doc' => [
            "#markup" => "
              All POSTS to endpoint must have format:<br><br>
              <pre>/rest/mnl/YYY?api_key=XXX</pre><br>
              <ul>
                <li>Where YYYY is one of 'update', 'import', 'purge' or 'manual'.</li>
                <li>Where api_key XXX is the <i><b>API KEY / Token</b></i> defined above.</li>
              </ul><br>
              <h4>import & update</h4>
              'import' and 'update' endpoint usage must include a json string
              in the body in the format:
              <ul>
                <pre>[
  {\"sam_address_id\":123,\"full_address\":\"address\",\"data\":jsonstring\"},
  {\"sam_address_id\":123,\"full_address\":\"address\",\"data\":jsonstring\"}
]</pre>
                <li>The 'jsonstring' field must be a valid json object in string
                form and can contain any data provided it represents a single
                record and can be decoded by PHP's json_decode function.</li>
              </ul>
              <h4>manual</h4>
              'manual' endpoint requires no body (any body will be ignored) but
              must have an extended querystring in the format:
              <ul>
                <pre>?api_key=XXX&path=/path-on-server/file.json&limit=N&mode=ZZZ</pre>
                <i>Required:</i>
                <ul>
                  <li>'path' is path on remote server (or local container) to
                  import file.</li>
                </ul>
                <i>Optional:</i>
                <ul>
                  <li>'limit' process the first N records in the import file
                  (defaults all).</li>
                  <li>'mode' ZZZ can be either 'import' or 'update' (defaults
                  to 'update').</li>
                </ul>
                <b>Note:</b>
                <ul>
                  <li>
                    <b>UPDATE</b> updates 'full address' and 'data' of SAM records in the DB with
                    'full address' and 'data' from queued records with a matching 'sam_address_id'
                    from the json payload. New records will be created if an existing
                    'sam_address_id' is not found in the DB. If there are duplicate
                    'sam_address_id's in the json payload, only the first one of the duplicate records
                    will be processed and the rest will be skipped. No records will be deleted or marked for deletion.
                   </li>
                  <li>
                    <b>IMPORT</b> also updates 'full address' and 'data' of SAM records in the DB with
                    'full address' and 'data' from queued records with a matching 'sam_address_id'
                    from the json payload. New records will also be created if an existing
                    'sam_address_id' is not found in the DB. If there are duplicate
                    'sam_address_id's in the json payload, only the first one of the duplicate records
                    will be processed and the rest will be skipped.  If the database contains a record (ie. a
                    'sam_address_id') which is not in the import file, then after the import
                    completes, those 'orphaned' records will be cleaned, by removing them
                    from the database.
                   </li>
                </ul>
              </ul>
              <h4>purge</h4>
              'purge' endpoint requires no body (any body will be ignored) but
              must have an extended querystring in the format:
              <ul>
                <pre>?api_key=XXX&cutoff=[purgedate]</pre>
                <i>Required:</i>
                <ul>
                  <li>
                    purgedate is a string with the PHP function strtotime can
                    parse, or an actual unix timestamp (int = seconds since epoch)
                  </li>
                </ul>
              </ul>
            "
            ],
        ],
        'queue_processing' => [
          '#type' => 'details',
          '#title' => 'Queue Processing',
          '#collapsible' => TRUE,
          'doc' => [
            "#markup" => "
              <ol>
                <li>The REST endpoint merely loads data into a queue.</li>
                <li>To perform and automate the update of MNL/SAM entities/nodes
                for use by the MNL module, 2 scheduled tasks must be created.<br>
                <ul>
                  <li>The first task should execute the following command:
                  <pre>drush queue:run mnl_import --items-limit=5000 && drush p:queue-work --finish &> /dev/null </pre>
                  </li>
                  <li>and the second task should execute
                  <pre>drush queue:run mnl_update --items-limit=5000 && drush p:queue-work --finish &> /dev/null</pre>
                  </li>
                </ul>
                The tasks should be scheduled to run every 5 minutes.</li>
              </ol>
            <hr>
            <h4>If the scheduled tasks above are not created then the website will not be updated until the commands above are run manually.</h4>            "
          ],
        ],
      ],
    ];
    if (!$config->get('cleanup')) {
      unset($form['diagnostics']['label3']);
    }
    return parent::buildForm($form, $form_state);
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $values = $form_state->getValues();
    if ($values['op'] == 'Reset Cache') {
      if (empty($values['cachelist']['samids'])) {
        $form_state->setErrorByName('caches][samids', 'Require at least one SAM ID.');
        $form['cachelist']['samids']['#attributes']['autofocus'] = "";
      }
    }
  }

  /**
   * Implements submitForm().
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    if ($values['op'] == 'Reset Cache') {
      $samid = preg_split("/[, ]/", $values['cachelist']['samids']);
      $result = MnlUtilities::MnlCacheClear($samid);
      \Drupal::messenger()->addStatus("${result} records refreshed in all caches.");
    }
    elseif ($values['op'] == 'Reset Cache - All Records') {
      $result = MnlUtilities::MnlCacheClear([]);
      \Drupal::messenger()->addStatus("${result} records refreshed in all caches.");
    }
    else {
      $settings = $form_state->getValue('mnl_admin');

      $this->config('bos_mnl.settings')
        ->set('auth_token', $settings['auth_token'])
        ->set('use_entity', $settings['use_entity'])
        ->set('cleanup', $settings['cleanup'])
        ->save();
      parent::submitForm($form, $form_state);
    }
  }

  /**
   * Converts status (int) into an html string.
   *
   * @param int $status
   *
   * @return string
   */
  private function _buildStatus($status = -1) {
    $css = "padding:10px;font-weight:bold;display: inline;";
    switch ($status) {
      case MnlUtilities::MNL_IMPORT_IDLE:
        $css = "background-color:grey;color:black;{$css}";
        $htmlstatus = "IDLE";
        break;
      case MnlUtilities::MNL_IMPORT_IMPORTING:
        $css = "background-color:goldenrod;color:black;{$css}";
        $htmlstatus = "RECEIVING DATA";
        break;
      case MnlUtilities::MNL_IMPORT_READY:
        $css = "background-color:darkgreen;color:white;{$css}";
        $htmlstatus = "READY TO UPDATE";
        break;
      case MnlUtilities::MNL_IMPORT_PROCESSING:
        $css = "background-color:goldenrod;color:black;{$css}";
        $htmlstatus = "UPDATING SAM RECORDS";
        break;
      case MnlUtilities::MNL_IMPORT_CLEANUP:
        $css = "background-color:springgreen;color:black;{$css}";
        $htmlstatus = "PURGING";
        break;
      default:
        $css = "background-color:purple;color:white;{$css}";
        $htmlstatus = "UNKNOWN OR NOT SET";
        break;
    }
    $htmlstatus = "<div style='{$css}'>{$htmlstatus}</div>";
    return $htmlstatus;
  }

}
