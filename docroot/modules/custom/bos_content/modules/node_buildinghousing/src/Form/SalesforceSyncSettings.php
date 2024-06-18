<?php

namespace Drupal\node_buildinghousing\Form;

use Drupal\Component\Utility\Environment;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Queue\DelayableQueueInterface;
use Drupal\Core\Queue\DelayedRequeueException;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\Core\Render\Markup;
use Drupal\Core\Utility\Error;
use Drupal\node_buildinghousing\BuildingHousingUtils;
use Drupal\salesforce\Event\SalesforceErrorEvent;
use Drupal\salesforce\Event\SalesforceEvents;
use Drupal\salesforce\Rest\RestClientInterface;
use Drupal\salesforce\SelectQuery;
use Drupal\salesforce\SFID;
use Drupal\salesforce_mapping\Event\SalesforceQueryEvent;
use Drupal\salesforce_pull\QueueHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Creates CoB settings form for Salesforce.
 */
class SalesforceSyncSettings extends ConfigFormBase {

  /**
   * The Salesforce REST client.
   *
   * @var \Drupal\salesforce\Rest\RestClientInterface
   */
  protected $client;

  protected $processor;


  protected $lock;
  public const lockname = "SalesforceSyncSettings";

  /**
   * The sevent dispatcher service..
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $eventDispatcher;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\salesforce\Rest\RestClientInterface $salesforce_client
   *   The factory for configuration objects.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(ConfigFactoryInterface $config_factory, RestClientInterface $salesforce_client, EventDispatcherInterface $event_dispatcher) {
    parent::__construct($config_factory);
    $this->client = $salesforce_client;
    $this->eventDispatcher = $event_dispatcher;

    $this->lock = \Drupal::lock();

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('salesforce.client'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cob_salesforce_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'node_buildinghousing.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    /**
     * @var $queue_manager \Drupal\bos_mnl\Plugin\QueueWorker\MNLProcessImport
     */
    $config = $this->config('node_buildinghousing.settings');

    $query =  new SelectQuery('Project__c');
    $query->fields = [
      'Id',
      'Name',
    ];
    $query->addCondition("RecordTypeID", "('0120y0000007rw7', '012C0000000Hqw0')", "IN");
    $query->order = ["Name"=>"ASC"];
    $results = \Drupal::service('salesforce.client')->query($query);
    $sfoptions = ["" => "Select Project"];
    foreach ($results->records() as $sfid => $data) {
      $sfoptions[$sfid] = $data->field("Name");
    }
    unset($results);

    $logfile = \Drupal::service('file_url_generator')->generateAbsoluteString("public://buildinghousing/cleanup.log");

    $form = [
      'pm' => [
        '#type' => 'fieldset',
        '#title' => 'Project Management',

        'explanation' => [
          '#markup' => "<div class='form-item'>The Salesforce synchronization polls Salesforce every
            few minutes and copies newly added, changed or updated Building
            Housing Project records from Salesforce to Drupal.<br>
            Some information (chatter messages and attachments) are not directly
            monitored and are only updated when their parent Website Update
            record is updated in Salesforce.<br>
            In some cases information may not sync properly between Drupal and
            Salesforce.  This page allows you to manually re-sync data as
            needed.<br>
            <i><b>Note:</b> Buttons on this page ONLY change data in Drupal.
            No records are changed or removed from Salesforce.</i></div>",
        ],

//        'pause_auto' => [
//          "#type" => "checkbox",
//          "#title" => "Pause automated synchronization.",
//          "#description" => "This will pause automated salesforce synchronizations until unchecked.<br>
//                             <i><b>Note: </b>Synchronizations are temporarily paused while actions are run from this form. This avoids conficts between automated processess and 'manual' processes adding and removing elements from the Salesforce processing queues.</i>",
//          '#description_display' => "after",
//          "#default_value" => $config->get("pause_auto") ?? 0,
//          '#ajax' => [
//            'callback' => '::submitForm',
//            'event' => 'change',
//            'disable-refocus' => TRUE,
//            'wrapper' => "edit-cron",
//            'progress' => [
//              'type' => 'throbber',
//            ]
//          ],
//        ],
        'delete_parcel' => [
          "#type" => "checkbox",
          "#title" => "Delete Parcel Entities.",
          "#description" => "Setting this checkbox will process CoB Parcel entities (180k records) during operations on this page, and cron initiated synchronizations.<br>Parcel data changes infrequently, so it is recommended to leave this unchecked unless you have issues with the parcel information (e.g. Geospatial data).",
          '#description_display' => "after",
          "#default_value" => $config->get("delete_parcel") ?? 0,
          '#ajax' => [
            'callback' => '::submitForm',
            'event' => 'change',
            'disable-refocus' => TRUE,
            'wrapper' => "edit-cron",
            'progress' => [
              'type' => 'throbber',
            ]
          ],

        ],
        'log_actions' => [
          "#type" => "checkbox",
          "#title" => "Log the actions performed from this page.",
          "#description" => "This will append actions to the log file <a href='{$logfile}' target='bhlog'>found here</a>.",
          '#description_display' => "after",
          "#default_value" => $config->get("log_actions") ?? 0,
          '#ajax' => [
            'callback' => '::submitForm',
            'event' => 'change',
            'disable-refocus' => TRUE,
            'wrapper' => "edit-cron",
            'progress' => [
              'type' => 'throbber',
            ]
          ],
        ],
        'del' => [
          "#type" => "button",
          "#value" => "Reset Logfile",
          "#disabled" => !\Drupal::currentUser()->hasPermission('Administer Salesforce mapping'),
          '#attributes' => [
            'class' => ['button--danger'],
            'title' => "This will Erase the contents of the logfile."
          ],
          '#ajax' => [
            'callback' => '::deleteLogfile',
            'event' => 'click',
            'progress' => [
              'type' => 'throbber',
            ]
          ],
        ],
        'settings' => [
          '#type' => 'fieldset',
          '#title' => 'Settings',
          "maxfilesize" => [
            "#type" => "textfield",
            '#default_value' => $config->get("maxfilesize") ?: 50,
            "#title" => "Max attachment Filesize from Salesforce (in MB)",
            '#description' => "This sets the maximum filesize for PDF's and images etc imported from Salesforce Website Updates.",
            '#ajax' => [
              'wrapper' => "edit-settings",
              'callback' => '::saveMaxFilesize',
              'event' => 'change',
              'progress' => [
                'type' => 'throbber',
              ]
            ],
          ],
        ],

        'queue' => [
          '#type' => 'fieldset',
          '#title' => 'Salesforce Queue',
          '#description' => "This is the current queue for the salesforce sync.<br>As part of the Salesforce-sync process, Salesforce items are temporarily queued and then various processes import queued items into Drupal. This table shows records which are queued.<br>Note: The automated cron tasks (which run every 5 mins) also use the queue, so watch the queue for a few minutes and see if the numbers are dropping before manually clearing using the buttons in this table.",
          '#description_display' => "before",
          'queue_table' => [$this->makeQueueTable()],

        ],

        'remove' => [
          '#type' => 'fieldset',
          '#title' => 'Remove Specific BH Project from Website',
          '#description' => "Remove one or more Building Housing Projects from the website.",
          '#description_display' => "before",

          'select-container--remove' => [
            "#type" => "container",
            '#attributes' => [
              "class" => "layout-container container-inline",
              "style" => ["display: inline-flex;", "align-items: flex-end; column-gap: 20px;"]
            ],
            'project' => [
              '#type' => "entity_autocomplete",
              '#title' => "Select Project (on Website)",
              '#attributes' => [
                'onclick' => "javascript: jQuery('#remove-result').html('');"
              ],
              "#target_type" => 'node',
              '#selection_settings' => [
                'target_bundles' => ['bh_project'],
                'sort' => ["field" => "field_bh_project_name", "direction" => "ascending"],
              ],
            ],
            'remove' => [
              '#type' => 'button',
              "#value" => "Remove Project",
              "#disabled" =>  !\Drupal::currentUser()->hasPermission('View Salesforce mapping'),
              '#attributes' => [
                'class' => ['button', 'button--primary', "form-item"],
                'title' => "This will remove the Project selected from the website, along with its updates, meetings and documents."
              ],
              '#ajax' => [
                'callback' => '::removeProject',
                'event' => 'click',
                'wrapper' => 'edit-remove',
                'disable-refocus' => FALSE,
                'progress' => [
                  'type' => 'throbber',
                  'message' => "Please wait: Deleting Project & associated data.",
                ]
              ],
            ],
          ],
          'remove-result' => [
            '#markup' => "<div id='remove-result' class='js-hide'></div>",
          ],
        ],
        'remove-all' => [
          '#type' => 'fieldset',
          '#title' => 'Remove ALL BH Projects from Website',
          '#description' => "Remove all Building Housing Projects from the website.",
          '#description_display' => "before",

          'select-container--remove-all' => [
            "#type" => "container",
            '#attributes' => [
              "class" => "layout-container container-inline",
              "style" => ["display: inline-flex;", "align-items: flex-end; column-gap: 20px;"]
            ],
            'remove' => [
              '#type' => 'submit',
              "#value" => "Remove All",
              "#disabled" => !\Drupal::currentUser()->hasPermission('Administer Salesforce mapping'),
              '#attributes' => [
                'class' => ['button', 'button--primary', "form-item"],
                'title' => "This will remove all Building Housing records, updates, meetings and documents from the website."
              ],
            ],
          ],
          'remove-all-result' => [
            '#markup' => "<div class='js-hide'></div>",
          ],
        ],

        'update' => [
          '#type' => 'fieldset',
          '#title' => 'Update Existing BH Project',
          '#description' => "Update a Building Housing Project already on the website.<br>This forces an immediate website Project sync'd with data in Salesforce.",
          '#description_display' => "before",

          'select-container--update' => [
            "#type" => "container",
            '#attributes' => [
              "class" => "layout-container container-inline",
              "style" => ["display: inline-flex;", "align-items: flex-end; column-gap: 20px;"]
            ],

            'update-project' => [
              '#type' => "entity_autocomplete",
              '#title' => "Select Project (on Website)",
              "#target_type" => 'node',
              '#attributes' => [
                'onclick' => "javascript: jQuery('#update-result').html('');"
              ],
              '#selection_settings' => [
                'target_bundles' => ['bh_project'],
                'sort' => ["field" => "field_bh_project_name", "direction" => "ascending"],
              ],
            ],
            'update' => [
              '#type' => 'button',
              "#value" => "Update Project",
              "#disabled" =>  !\Drupal::currentUser()->hasPermission('View Salesforce mapping'),
              '#attributes' => [
                'class' => ['button', 'button--primary', "form-item"],
                'title' => "This will sync the Project selected in Drupal with its most recent values from Salesforce, along with new updates, meetings and documents.",
              ],
              '#ajax' => [
                'callback' => '::updateProject',
                'event' => 'click',
                'wrapper' => 'edit-update',
                'progress' => [
                  'type' => 'throbber',
                  'message' => "Please wait: Syncing Project & associated data.",
                ]
              ],
            ],
          ],
          'update-result' => [
            '#markup' => "<div id='update-result' class='js-hide'></div>",
          ],
        ],

        'overwrite' => [
          '#type' => 'fieldset',
          '#title' => 'Overwrite BH Project',
          '#description' => "Overwrite (delete then re-import) a Building Housing Project with current data from Salesforce.",
          '#description_display' => "before",

          'select-container--overwrite' => [
            "#type" => "container",
            '#attributes' => [
              "class" => "layout-container container-inline",
              "style" => ["display: inline-flex;", "align-items: flex-end; column-gap: 20px;"]
            ],
            'overwrite_project' => [
              '#type' => 'select',
              '#attributes' => [
                'onclick' => "javascript: jQuery('#overwrite-result').html('');"
              ],
              "#title" => "Select Salesforce Project",
              '#options' => $sfoptions,
            ],
            'overwrite' => [
              '#type' => 'button',
              "#value" => "Overwite Project",
              "#disabled" =>  !\Drupal::currentUser()->hasPermission('View Salesforce mapping'),
              '#attributes' => [
                'class' => ['button', 'button--primary', 'form-item'],
                'title' => "This will preform a full re-import the Selected SF Project, along with its updates, meetings and documents (replacing the existing data in Drupal)."
              ],
              '#ajax' => [
                'callback' => '::overwriteProject',
                'event' => 'click',
                'wrapper' => 'edit-overwrite',
                'disable-refocus' => FALSE,
                'progress' => [
                  'type' => 'throbber',
                  'message' => "Please wait: Ovewriting Project & associated data.",
                ]
              ],
            ],
          ],
          'overwrite-result' => [
            '#markup' => "<div id='overwrite-result' class='js-hide'></div>",
          ],
        ],
        'overwrite-all' => [
          '#type' => 'fieldset',
          '#title' => 'Overwrite All BH Projects',
          '#description' => "Overwrite (delete then re-import) ALL Building Housing Projects from Salesforce.",
          '#description_display' => "before",
          'select-container--overwrite-all' => [
            "#type" => "container",
            '#attributes' => [
              "class" => "layout-container container-inline",
              "style" => ["display: inline-flex;", "align-items: flex-end; column-gap: 20px;"]
            ],
            'overwrite' => [

              '#type' => 'submit',

              "#value" => "Overwrite All",
              "#disabled" =>  !\Drupal::currentUser()->hasPermission('Administer Salesforce mapping'),
              '#attributes' => [
                'class' => ['button', 'button--primary', 'form-item'],
                'title' => "This will preform a full re-import for all SF Projects, along with updates, meetings and documents (replacing the existing data in Drupal)."
              ],
            ],
          ],
          'overwrite-all-result' => [
            '#markup' => "<div class='js-hide'></div>",
          ],
        ],

        'orphans' => [
          '#type' => 'fieldset',
          '#title' => 'Remove Orphaned Entities',
          '#description' => "Remove Projects, Updates, Meetings, Parcels and Project-Parcel Associations which are not in Salesforce or are not linked back to a Project.",
          '#description_display' => "before",
          'select-container--orphans' => [
            "#type" => "container",
            '#attributes' => [
              "class" => "layout-container container-inline",
              "style" => ["display: inline-flex;", "align-items: flex-end; column-gap: 20px;"]
            ],
            'orphans-button' => [
              '#type' => 'submit',
              "#value" => "Remove Orphans",
              "#disabled" =>  !\Drupal::currentUser()->hasPermission('Administer Salesforce mapping'),
              '#attributes' => [
                'class' => ['button', 'button--primary', 'form-item'],
                'title' => "This removes entities from Drupal.\nFirst it finds entities in Drupal which are not in Salesforce, then it finds entities which are not linked to a Project in Drupal."
              ],
            ],
          ],
          'orphans-result' => [
            '#markup' => "<div class='js-hide'></div>",
          ],
        ],

        'pull-management' => [
          '#type' => 'fieldset',
          '#title' => 'Salesforce Pull Management',
          '#description' => "Allows some control over the last updated date for sync's.",
          '#description_display' => "before",

          'current' => [$this->makeSFTable()],

          'select-container--pull-management' => [
            "#type" => "container",
            '#attributes' => [
              "class" => "layout-container container-inline",
              "style" => ["display: inline-flex;", "align-items: flex-end; column-gap: 20px;"]
            ],
            'mapping' => [
              '#type' => 'select',
              '#attributes' => ["placeholder" => "Mapping"],
              '#options' => $this->makeMappingDropdown(),
            ],
            'time' => [
              '#type' => 'textfield',
              '#attributes' => ["style" => ["width:150px"]],
              '#default_value' => date("Y-m-d H:i:s", strtotime("now")),
            ],
            'reset' => [
              '#type' => 'button',
              "#value" => "Set Last Run Time",
              "#disabled" =>  !\Drupal::currentUser()->hasPermission('View Salesforce mapping'),
              '#attributes' => [
                'class' => ['button', 'button--primary', "form-item"],
                'title' => "This will set the Last Run Date (high water mark) for the mapping.<br>The next sync of this mapping will only import records updated after this date."
              ],
              '#ajax' => [
                'callback' => '::resetLastRunDate',
                'event' => 'click',
                'wrapper' => 'edit-pull-management',
                'disable-refocus' => FALSE,
                'progress' => [
                  'type' => 'throbber',
                  'message' => "Please wait: Resetting last-run-time.",
                ]
              ],
            ],
          ],
          'pull-result' => [
            '#markup' => "<div class='js-hide'></div>",
          ],
        ],
      ],
    ];

    if (\Drupal::service('queue')
        ->get(QueueHandler::PULL_QUEUE_NAME)
        ->numberOfItems() > 0) {
      $warning = "<span class='description error'>WARNING: These queue items will automatically be processed when any updates or overwrites are performed from this page.</span>";
      $form["pm"]["queue"]["#description"] .= "<br>{$warning}";
    }

//    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    parent::submitForm($form, $form_state);
    \Drupal::messenger()->deleteByType("status");

    switch ($form_state->getTriggeringElement()["#type"]) {
      case "checkbox":
        $config = $this->config('node_buildinghousing.settings');
//        $config->set('pause_auto', $form_state->getValue('pause_auto'));
        $config->set('delete_parcel', $form_state->getValue('delete_parcel'));
        $config->set('log_actions', $form_state->getValue('log_actions'));
        $config->save();
        \Drupal::messenger()->addStatus("The configuration options have been saved.");
        break;

      case "submit":
        switch ($form_state->getTriggeringElement()["#value"]) {
          case "Remove All":
            $batch = [
              'init_message' => t('Initializing'),
              'title' => t('Building Housing: Removing All Projects'),
              'operations' => [
                [
                  'bh_initializeBatch',
                  ["REMOVE"],
                ],
                [
                  'bh_removeAllBatch',
                  ["bh_project"],
                ],
                [
                  'bh_removeAllBatch',
                  ["bh_update"],
                ],
                [
                  'bh_removeAllBatch',
                  ["bh_meeting"],
                ],
                [
                  'bh_removeAllBatch',
                  ["bh_parcel_project_assoc"],
                ],
                [
                  'bh_removeAllBatch',
                  ["bh_parcel"],
                ],
                [
                  'bh_finalizeBatch',
                  [],
                ],
              ],
              'finished' => 'buildForm',
              'progress_message' => 'Processing',
              'file' => dirname(\Drupal::service('extension.list.module')->getPathname("node_buildinghousing")) . "/src/Batch/SalesforceSyncBatch.inc",
            ];
            batch_set($batch);
            break;

          case "Overwrite All":
            $batch = [
              'init_message' => t('Initializing'),
              'title' => t('Building Housing: Overwriting All Projects'),
              'operations' => [
                [
                  'bh_initializeBatch',
                  ["OVERWRITE"],
                ],
                [
                  'bh_removeAllBatch',
                  ["bh_project"],
                ],
                [
                  'bh_removeAllBatch',
                  ["bh_update"],
                ],
                [
                  'bh_removeAllBatch',
                  ["bh_meeting"],
                ],
                [
                  'bh_removeAllBatch',
                  ["bh_parcel_project_assoc"],
                ],
                [
                  'bh_removeAllBatch',
                  ["bh_parcel"],
                ],
                [
                  'bh_queueAllBatch',
                  ["building_housing_projects", TRUE],
                ],
                [
                  'bh_queueAllBatch',
                  ["bh_website_update", TRUE],
                ],
                [
                  'bh_queueAllBatch',
                  ["bh_community_meeting_event", TRUE],
                ],
                [
                  'bh_queueAllBatch',
                  ["building_housing_parcels", TRUE],
                ],
                [
                  'bh_queueAllBatch',
                  ["bh_parcel_project_assoc", TRUE],
                ],
                [
                  'bh_processQueueBatch',
                  ["ALL"],
                ],
                [
                  'bh_finalizeBatch',
                  [],
                ],
              ],
              'finished' => 'buildForm',
              'progress_message' => 'Processing',
              'file' => dirname(\Drupal::service('extension.list.module')->getPathname("node_buildinghousing")) . "/src/Batch/SalesforceSyncBatch.inc",
            ];
            batch_set($batch);
            break;

          case "Remove Orphans":
            // NOTE: Order of operations is important.
            $batch = [
              'init_message' => t('Initializing'),
              'title' => t('Building Housing: Finding and removing orphans'),
              'operations' => [
                [
                  'bh_initializeBatch',
                  ["ORPHANS"],
                ],
                [
                  'bh_removeDeletedBatch',
                  ["bh_meeting"],
                ],
                [
                  'bh_removeDeletedBatch',
                  ["bh_update"],
                ],
                [
                  'bh_removeDeletedBatch',
                  ["bh_project"],
                ],
                [
                  'bh_removeOrphansBatch',
                  ["bh_parcel_project_assoc"],
                ],
                [
                  'bh_removeOrphansBatch',
                  ["bh_parcel"],
                ],
                [
                  'bh_removeOrphansBatch',
                  ["bh_update"],
                ],
                [
                  'bh_removeOrphansBatch',
                  ["bh_meeting"],
                ],
                [
                  'bh_finalizeBatch',
                  [],
                ],
              ],
              'finished' => 'buildForm',
              'progress_message' => 'Processing',
              'file' => dirname(\Drupal::service('extension.list.module')->getPathname("node_buildinghousing")) . "/src/Batch/SalesforceSyncBatch.inc",
            ];
            batch_set($batch);
            break;

          case "Clear Entire Queue":
            $queue_name = QueueHandler::PULL_QUEUE_NAME;
            $db = \Drupal::database();
            $query = $db->delete("queue")
              ->condition("name", $queue_name , "=");
            $result = $query->execute();

            \Drupal::messenger()->addStatus("The entire salesforce pull queue {$queue_name} has been cleared.");

            break;

          case "Process_Entire_Queue":
            $config = $this->config('node_buildinghousing.settings');
            $log = $config->get("log_actions");
            BuildingHousingUtils::clearQueueLeases("cron_salesforce_pull", "ALL", TRUE);
            $this->processSfQueue("ALL", $log);
            break;

          case "Clear Queue Objects":

            $mapping = $form_state->getTriggeringElement()["#name"] ?? "";
            $queue_name = QueueHandler::PULL_QUEUE_NAME;
            $db = \Drupal::database();
            $query = $db->delete("queue")
              ->condition("name", $queue_name , "=");
            $query->condition("data", "%mappingId%{$mapping}%", "like");
            $result = $query->execute();

            $mapping = \Drupal::entityTypeManager()->getStorage("salesforce_mapping")->load($mapping);
            $mapping_name = ucwords(str_replace(["__c", "_"], ["", " "], $mapping->getSalesforceObjectType()));

            \Drupal::messenger()->addStatus("{$mapping_name} objects in the salesforce pull queue ({$queue_name}) have been cleared.");

            break;

          case "Process Queue Objects":
            $mapping = $form_state->getTriggeringElement()["#name"] ?? "";
            $map = \Drupal::entityTypeManager()
              ->getStorage("salesforce_mapping")
              ->load($mapping);
            BuildingHousingUtils::clearQueueLeases("cron_salesforce_pull",$mapping, TRUE);
            $type = ucwords(str_replace("_", " ", str_replace("__c", "", $map->getSalesforceObjectType())));
            $batch = [
              'init_message' => t('Initializing'),
              'title' => t("Building Housing: Processing {$type} objects from queue"),
              'operations' => [
                [
                  'bh_initializeBatch',
                  ["Clearing {$type} objects from queue"],
                ],
                [
                  'bh_processQueueBatch',
                  [$map->id()],
                ],
                [
                  'bh_finalizeBatch',
                  [],
                ],
              ],
              'finished' => 'buildForm',
              'progress_message' => 'Processing',
              'file' => dirname(\Drupal::service('extension.list.module')->getPathname("node_buildinghousing")) . "/src/Batch/SalesforceSyncBatch.inc",
            ];
            batch_set($batch);
            break;

          default:
            $mapping = $form_state->getTriggeringElement()["#name"] ?? "";
            $map = \Drupal::entityTypeManager()
              ->getStorage("salesforce_mapping")
              ->load($mapping);

            switch ($mapping) {
              case "building_housing_projects":
              case "bh_website_update":
              case "bh_community_meeting_event":
              case "building_housing_parcels":
              case "bh_parcel_project_assoc":
                $type = ucwords(str_replace("_", " ", str_replace("__c", "", $map->getSalesforceObjectType())));
                $batch = [
                  'init_message' => t('Initializing'),
                  'title' => t("Building Housing: {$type} Sync"),
                  'operations' => [
                    [
                      'bh_initializeBatch',
                      ["SYNC"],
                    ],
                    [
                      'bh_queueAllBatch',
                      [$mapping, FALSE],
                    ],
                    [
                      'bh_processQueueBatch',
                      [$map->id()],
                    ],
                    [
                      'bh_finalizeBatch',
                      [],
                    ],
                  ],
                  'finished' => 'buildForm',
                  'progress_message' => 'Processing',
                  'file' => dirname(\Drupal::service('extension.list.module')->getPathname("node_buildinghousing")) . "/src/Batch/SalesforceSyncBatch.inc",
                ];
                batch_set($batch);
                break;

              case "metrolist_development":
              case "metrolist_unit":
                $type = ucwords(str_replace("_", " ", str_replace("__c", "", $map->getSalesforceObjectType())));
                $batch = [
                  'init_message' => t('Initializing'),
                  'title' => t("Metrolist: {$type} Sync"),
                  'operations' => [
                    [
                      'bh_initializeBatch',
                      ["SYNC"],
                    ],
                    [
                      'bh_queueAllBatch',
                      [$mapping, FALSE],
                    ],
                    [
                      'bh_processQueueBatch',
                      [$map->id()],
                    ],
                    [
                      'bh_finalizeBatch',
                      [],
                    ],
                  ],
                  'finished' => 'buildForm',
                  'progress_message' => 'Processing',
                  'file' => dirname(\Drupal::service('extension.list.module')->getPathname("node_buildinghousing")) . "/src/Batch/SalesforceSyncBatch.inc",
                ];
                batch_set($batch);
                break;
            }
        }
        break;
    }
    return $form;
  }

  public function saveMaxFilesize(array &$form, FormStateInterface $form_state) {
    if ($form_state->getTriggeringElement()["#name"] == "maxfilesize") {

      $form["pm"]["settings"]["maxfilesize"]["#ajax"]["wrapper"] = "edit-settings";
      $form["pm"]["settings"]["#id"] = "edit-settings";
      $maxsize = $form_state->getValue("maxfilesize") ?: 0;
      if (!is_numeric($maxsize) || $maxsize < 0 || $maxsize > 150) {
        $form["pm"]["settings"]["maxfilesize"]["#attributes"]["class"][] = "error";
        $form["pm"]["settings"]["maxfilesize"]["#description"] = Markup::create("<span style='color:red'><b>Value must be numeric and between 0 and 150</b></span>");
      }
      else {
        $config = $this->config('node_buildinghousing.settings');
        $config->set('maxfilesize', $form_state->getValue('maxfilesize'));
        $config->save();
      }
      return $form["pm"]["settings"];
    }
  }
  public function deleteLogfile(array &$form, FormStateInterface $form_state) {

    unlink("public://buildinghousing/cleanup.log");
    BuildingHousingUtils::log("cleanup", "File Reset.\n", TRUE);
    return $form;
  }

  public function removeProject(array &$form, FormStateInterface $form_state)  {

    \Drupal::messenger()->deleteAll();
    $config = $this->config('node_buildinghousing.settings');
    $log = $config->get("log_actions");
    $delete_parcel = ($config->get("log_actions") == 1) ?? FALSE;

    $msgtitle = "Project: {$form["pm"]["remove"]["select-container--remove"]["project"]["#value"]}";

    if ($nid = $form_state->getValue("project")) {

      $log && BuildingHousingUtils::log("cleanup", "\nSTART Single Project Removal.\n", TRUE);

      if (!$this->lock->acquire(self::lockname, 600)) {
        $message = "A Utility process is already running.";
        $form["pm"]["remove"]["remove-result"] = $this->makeResponse("remove-result", "", $message,"warning");
        $form["pm"]["remove"]["#id"] = "edit-remove";
        return $form["pm"]["remove"];
      }

      BuildingHousingUtils::delete_bh_project([$nid], $delete_parcel, TRUE, $log);

      $this->lock->release(self::lockname);
      $message = "Project Removed.";
      $status = "success";
    }
    else {
      $message = "Project not found: Nothing Done.";
      $status = "warning";
    }

    $log && BuildingHousingUtils::log("cleanup", "END Single Project Removal.\n", TRUE);

    $form["pm"]["remove"]["remove-result"] = $this->makeResponse('remove-result', $msgtitle, $message, $status);
    $form["pm"]["remove"]["select-container--remove"]["project"]["#value"] = "";
    $form["pm"]["remove"]["#id"] = "edit-remove";
    return $form["pm"]["remove"];
  }

  public function removeAllProjects(array &$form, FormStateInterface $form_state) {


    $existing_project_count = 0;

    \Drupal::messenger()->deleteAll();
    $config = $this->config('node_buildinghousing.settings');
    $log = $config->get("log_actions") ?? FALSE;

    if (!\Drupal::lock()->lockMayBeAvailable(self::lockname)) {
      $message = "A Utility process is already running.";
      $form["pm"]["remove-all"]["remove-all-result"] = $this->makeResponse("", "", $message,"warning");
      $form["pm"]["remove-all"]["#id"] = "edit-remove-all";
      return $form["pm"]["remove-all"];
    }

    $log && BuildingHousingUtils::log("cleanup", "\nSTART ALL Project Removal.\n", TRUE);

    $existing_project_count = BuildingHousingUtils::delete_all_bh_objects(TRUE, $log, NULL);

    $log && BuildingHousingUtils::log("cleanup", "END ALL Project Removal.\n", TRUE);

    \Drupal::lock()->release(self::lockname);

    $message = "All Projects ({$existing_project_count}) removed.";
    $form["pm"]["remove-all"]["remove-all-result"] = $this->makeResponse("", "", $message,"success");
    $form["pm"]["remove-all"]["#id"] = "edit-remove-all";
    return $form["pm"]["remove-all"];
  }

  public function updateProject(array &$form, FormStateInterface $form_state)  {

    $existing_project_count = 0;
    $new_projects = 0;
    $count = 0;

    \Drupal::messenger()->deleteAll();
    $config = $this->config('node_buildinghousing.settings');
    $log = $config->get("log_actions");

    $pull_info = \Drupal::state()->get('salesforce.mapping_pull_info');

    $log && BuildingHousingUtils::log("cleanup", "\nSTART Project Update.\n", TRUE);

    if ($nid = $form_state->getValue("update-project")) {
      if ($sf = \Drupal::entityTypeManager()
        ->getStorage("salesforce_mapped_object")
        ->loadByProperties(["drupal_entity" => $nid])) {
        $sf = reset($sf);
        $sfid = $sf->get("salesforce_id")->value;
        $new_projects = $this->enqueueSfRecords($sfid, $log);
      }

      $msgtitle = "Project: {$form["pm"]["update"]["select-container--update"]["update-project"]["#value"]}";

      if ($new_projects == 1) {
        try {
          $count = $this->processSfQueue("ALL", $log);
          $status = "success";
        }
        catch (\Exception $e) {
          $status = "warning";
        }
        $message = "{$new_projects} Project updated using {$count} SF objects.";
      }
    }

    if ($status == "warning") {
      $message .= "<br>Some errors were encountered - check logs.";
    }

    if ($new_projects == 0) {
      $message = "Project not found: Nothing Done.";
      $status = "warning";
    }

    // Reset the last pull info so we don't mess up automated updates.
    \Drupal::state()->set('salesforce.mapping_pull_info', $pull_info);

    $log && BuildingHousingUtils::log("cleanup", "END Project Update.\n", TRUE);

    $form["pm"]["update"]["update-result"] = $this->makeResponse("update-result", $msgtitle ?? "", $message, $status);
    $form["pm"]["update"]["select-container--update"]["update-project"]["#value"] = "";
    $form["pm"]["update"]["#id"] = "edit-update";
    return $form["pm"]["update"];
  }

  public function overwriteProject(array &$form, FormStateInterface $form_state)  {

    $existing_project_count = 0;
    $new_projects = 0;
    $count = 0;

    \Drupal::messenger()->deleteAll();
    $config = $this->config('node_buildinghousing.settings');
    $log = $config->get("log_actions");
    $delete_parcel = ($config->get("delete_parcel") == 1) ?? FALSE;

    $log && BuildingHousingUtils::log("cleanup", "\nSTART Project Overwrite.\n", TRUE);

    $pull_info = \Drupal::state()->get('salesforce.mapping_pull_info');

    if ($sfid = $form_state->getValue("overwrite_project")) {

      if (!$this->lock->acquire(self::lockname, 90)) {
        $message = "A Utility process is already running.";
        $form["pm"]["overwrite"]["overwrite-result"] = $this->makeResponse("overwrite-result", "", $message, "warning");
        $form["pm"]["overwrite"]["select-container--overwrite"]["overwrite_project"]["#value"] = "";
        $form["pm"]["overwrite"]["#id"] = "edit-overwrite";
        return $form["pm"]["overwrite"];
      }

      if ($nid = \Drupal::entityTypeManager()
        ->getStorage("salesforce_mapped_object")
        ->loadByProperties(["salesforce_id" => $sfid])) {
        $nid = reset($nid);
        $existing_project_count = BuildingHousingUtils::delete_bh_project([$nid->get("drupal_entity")[0]->target_id], $delete_parcel, TRUE, $log);
      }

      $this->lock->release(self::lockname);

      $sf_project_name = "{$form["pm"]["overwrite"]["select-container--overwrite"]["overwrite_project"]["#options"][$sfid]} ({$sfid})";
      $sfid = new SFID($sfid);

      $new_projects = $this->enqueueSfRecords($sfid, $log);
      $status = "warning";
      if ($new_projects > 0) {
        try {
          $count = $this->processSfQueue("ALL", $log);
          $status = "success";
        }
        catch (\Exception $e) {
          $status = "warning";
        }
      }

      $msgtitle = "Project: {$sf_project_name}";
      if ($existing_project_count == 0) {
        $message = "New Drupal Project created from {$new_projects} Salesforce Project using {$count} Salesforce objects.";
      }
      elseif ($new_projects > 0) {
        $message = "{$existing_project_count} Drupal Project overwritten with {$new_projects} Salesforce Project using {$count} Salesforce objects.";
      }
      if ($status == "warning") {
        $message .= "<br>Some errors were encountered - check logs.";
      }
    }

    if ($new_projects == 0) {
      $message = "Project not found: Nothing Done.";
      $status = "warning";
    }

    // Reset the last pull info so we don't mess up automated updates.
    \Drupal::state()->set('salesforce.mapping_pull_info', $pull_info);

    $log && BuildingHousingUtils::log("cleanup", "END Project Overwrite.\n", TRUE);

    $form["pm"]["overwrite"]["overwrite-result"] = $this->makeResponse("overwrite-result", $msgtitle, $message, $status);
    $form["pm"]["overwrite"]["select-container--overwrite"]["overwrite_project"]["#value"] = "";
    $form["pm"]["overwrite"]["#id"] = "edit-overwrite";
    return $form["pm"]["overwrite"];

  }

  public function overwriteAllProjects(array &$form, FormStateInterface $form_state) {

    $existing_project_count = 0;
    $new_projects = 0;
    $count = 0;

    \Drupal::messenger()->deleteAll();
    $config = $this->config('node_buildinghousing.settings');
    $log = $config->get("log_actions") ?? FALSE;

    if (!$this->lock->acquire(self::lockname, 30)) {
      $message = "A Utility process is already running.";
      $form["pm"]["overwrite-all"]["overwrite-all-result"] = $this->makeResponse("", "", $message, "warning");
      $form["pm"]["overwrite-all"]["#id"] = "edit-overwrite-all";
      return $form["pm"]["overwrite-all"];
    }

    BuildingHousingUtils::log("cleanup", "\nSTART ALL Project Overwrite.\n", TRUE);

    $existing_project_count = BuildingHousingUtils::delete_all_bh_objects(TRUE, $log, $this->lock);

    $this->lock->release(self::lockname);

    $new_projects = $this->enqueueSfRecords(NULL, $log);

    $msgtitle = "";
    if ($new_projects > 0 || $existing_project_count > 0) {
      try {
        $count = $this->processSfQueue("ALL", $log);
        $status = "success";
      }
      catch (\Exception $e) {
        $status = "warning";
      }
      $message = "{$existing_project_count} Drupal Projects overwritten with {$new_projects} Salesforce Projects using {$count} Salesforce objects.";
    }

    if ($status == "warning") {
      $message .= "<br>Some errors were encountered - check logs.";
    }

    if ($new_projects == 0) {
      $message = "No projects found! - Nothing Done.";
      $status ="warning";
    }

    BuildingHousingUtils::log("cleanup", "END ALL Project Overwrite.\n", TRUE);

    $form["pm"]["overwrite-all"]["overwrite-all-result"] = $this->makeResponse("", $msgtitle, $message, $status);
    $form["pm"]["overwrite-all"]["#id"] = "edit-overwrite-all";
    return $form["pm"]["overwrite-all"];
  }

  public function resetLastRunDate(array &$form, FormStateInterface $form_state) {

    if (($mapping = $form_state->getValue("mapping"))
      && ($time = $form_state->getValue("time"))) {

      if (strtotime($time) === FALSE) {
        $form["pm"]["pull-management"]["pull-result"] = ["#markup" => Markup::create("
            <div class='form-item color-warning'>
              Mapping: {$mapping}<br/>
              <img src='/core/misc/icons/e29700/warning.svg' /> <b>Time needs to be in the format Y-M-D H:M:S</b>
            </div>")];
      }
      if ($pull_info = \Drupal::state()->get('salesforce.mapping_pull_info')) {

        if (isset($pull_info[$mapping])) {
          $pull_info[$mapping]["last_pull_timestamp"] = strtotime($time);
          \Drupal::state()->set('salesforce.mapping_pull_info', $pull_info);

          $newtime = \Drupal::state()
            ->get('salesforce.mapping_pull_info')[$mapping]["last_pull_timestamp"];
          $newtime = date("Y-m-d H:i:s", $newtime);

          $form["pm"]["pull-management"]["pull-result"] = ["#markup" => Markup::create("
            <div class='form-item color-success'>
              Mapping: {$mapping}<br/>
              <img src='/core/misc/icons/73b355/check.svg' /> <b>Last Run Time reset to {$newtime}</b>
            </div>")];

        }
        else {
          $form["pm"]["pull-management"]["pull-result"] = ["#markup" => Markup::create("
            <div class='form-item color-warning'>
              Mapping: {$mapping}<br/>
              <img src='/core/misc/icons/e29700/warning.svg' /> <b>Mapping not found: Nothing Done</b>
            </div>")];
        }
      }

    }
    else {
      $form["pm"]["pull-management"]["pull-result"] = ["#markup" => Markup::create("
        <div class='form-item color-warning'>
          <img src='/core/misc/icons/e29700/warning.svg' /> <b>Mapping or time missing</b>
        </div>")];
    }

    $form["pm"]["pull-management"]["current"] = $this->makeSFTable();
    $form["pm"]["pull-management"]["select-container--overwrite"]["mapping"]["#value"] = "";
    $form["pm"]["pull-management"]["select-container--overwrite"]["time"]["#value"] =  date("Y-m-d H:i:s", strtotime("now"));
    $form["pm"]["pull-management"]["#id"] = "edit-pull-management";
    return $form["pm"]["pull-management"];
  }

  private function enqueueSfRecords($sfid = NULL, $log = FALSE) {

    $container = \Drupal::getContainer();
    $this->processor = new QueueHandler(
      $this->client,
      $container->get('entity_type.manager'),
      $container->get('queue.database'),
      $container->get('config.factory'),
      $container->get('event_dispatcher'),
      $container->get('datetime.time')
    );

    $map = \Drupal::entityTypeManager()->getStorage("salesforce_mapping");

    if (!empty($sfid)) {

      $id = new SFID($sfid);

      // Get a lock for the duration of this (single record) update.
      if (!$this->lock->acquire(self::lockname, 60)) {
        $msg = "ERROR: Could not obtain a lock processing SF Pull Queue.  Some other process is already running.";
        BuildingHousingUtils::log("cleanup", "{$msg}\n", TRUE);
        \Drupal::logger("BuildingHousing")->error($msg);
        return 0;
      }

      $count = $this->getSingleRecord($map->load("building_housing_projects"), (string) $id, TRUE);
      $count && $log && BuildingHousingUtils::log("cleanup", "QUEUED {$count} record/s from Salesforce using building_housing_projects mapping.\n");

      if ($count == 1) {

        $config = $this->config('node_buildinghousing.settings');
        $a = 0;
        $b = 0;
        $query = new SelectQuery('ParcelProject_Association__c');
        $query->fields = ['Id', "Project__c", "Parcel__c"];
        $query->addCondition("Project__c", "'{$sfid}'", "=");
        $results = $this->client->query($query);
        foreach ($results->records() as $data) {
          if ($config->get('delete_parcel') ?? FALSE) {
            $a += $this->getSingleRecord($map->load("building_housing_parcels"), $data->field("Parcel__c"), TRUE);
          }
          $b += $this->getSingleRecord($map->load("bh_parcel_project_assoc"), $data->field("Id"), TRUE);
        }
        $a && $log && BuildingHousingUtils::log("cleanup", "QUEUED {$a} record/s from Salesforce using building_housing_parcels mapping.\n");
        $b && $log && BuildingHousingUtils::log("cleanup", "QUEUED {$b} record/s from Salesforce using bh_parcel_project_assoc mapping.\n");

        $query = new SelectQuery('Website_Update__c');
        $query->fields = ['Id', "Project__c"];
        $query->addCondition("Project__c", "'{$sfid}'", "=");
        $results = $this->client->query($query);
        $a = 0;
        $b = 0;
        foreach ($results->records() as $data) {
          $a += $this->getSingleRecord($map->load("bh_website_update"), $data->field("Id"), TRUE);

          $query1 = new SelectQuery('Community_Meeting_Event__c');
          $query1->fields = ['Id', "website_update__c"];
          $query1->addCondition("website_update__c", "'{$data->field("Id")}'", "=");
          $results1 = $this->client->query($query1);
          foreach ($results1->records() as $data1) {
            $b += $this->getSingleRecord($map->load("bh_community_meeting_event"), $data1->field("Id"), TRUE);
          }

        }
        $a && $log && BuildingHousingUtils::log("cleanup", "QUEUED {$a} record/s from Salesforce using bh_website_update mapping.\n");
        $b && $log && BuildingHousingUtils::log("cleanup", "QUEUED {$b} record/s from Salesforce using bh_community_meeting_event mapping.\n");

      }

      $this->lock->release(self::lockname);

    }

    else {

      $mappings = [
        "bh_parcel_project_assoc",
        "bh_community_meeting_event",
        "bh_website_update",
        "building_housing_projects"
      ];

      $config = $this->config('node_buildinghousing.settings');
      if ($config->get('delete_parcel') ?? FALSE) {
        $mappings = array_merge(["building_housing_parcels"], $mappings);
      }

      foreach ($mappings as $mapping) {
        if (!$this->lock->acquire(self::lockname, 180)) {
          $msg = "ERROR: Could not obtain a lock fetching records from SF.  Some other process is already running.";
          BuildingHousingUtils::log("cleanup", "{$msg}\n", TRUE);
          \Drupal::logger("BuildingHousing")->error($msg);
          return 0;
        }

        try {
          $c = $this->processor->getUpdatedRecordsForMapping($map->load($mapping), TRUE, 1420070400, strtotime("now"));
          $c && $log && BuildingHousingUtils::log("cleanup", "QUEUED {$c} record/s from Salesforce using '{$mapping}' mapping.\n");
        }
        catch (\Exception $e) {
          // Have an error, so log it and then proceed.
          $log && BuildingHousingUtils::log("cleanup", "***** ERROR Queueing record/s from Salesforce using '{$mapping}' mapping.\n");
          \Drupal::logger("BuildingHousing")->error("***** ERROR Queueing record/s from Salesforce using '{$mapping}' mapping.");
        }

        if ($mapping == "building_housing_projects") {
          $count = $c;
        }

      }

      $this->lock->release(self::lockname);

    }

    return $count ?? 0;
  }

  private function processSfQueue(string $type = "ALL", bool $log = FALSE) {
    $queue_name = QueueHandler::PULL_QUEUE_NAME;
    $queue_factory = \Drupal::service('queue');
    $queue_manager = \Drupal::service('plugin.manager.queue_worker');
    $queue_worker = $queue_manager->createInstance($queue_name);
    $queue = $queue_factory->get($queue_name);
    $count = 0;
    $already_claimed = [];
    Environment::setTimeLimit(300);

    while ($item = $queue->claimItem(30)) {

      if (isset($already_claimed[$item->item_id])) {
        $queue->delayItem($item, 60);
        continue;
      }
      elseif ($type != "ALL" && $item->data->getMappingId() != $type) {
        // Only process items of the desired type.
        $queue->releaseItem($item);
        $already_claimed[$item->item_id] = $item->item_id;
        if (count($already_claimed) == $queue->numberOfItems()) {
          // We have tried everything, so stop scanning the queue.
          break;
        }
        continue;
      }

      if (!$this->lock->acquire(self::lockname, 15)) {
        $msg = "ERROR: Could not obtain a lock processing SF Pull Queue.  Some other process is already running.";
        $log && BuildingHousingUtils::log("cleanup", "{$msg}\n", TRUE);
        \Drupal::logger("BuildingHousing")->error($msg);
        return $count;
      }

      try {
        $queue_worker->processItem($item->data);
        $queue->deleteItem($item);
        $count++;
      }
      catch (DelayedRequeueException $e) {
        if ($queue instanceof DelayableQueueInterface) {
          $queue->delayItem($item, $e->getDelay());
        }
      }
      catch (RequeueException $e) {
        $queue->releaseItem($item);
      }
      catch (SuspendQueueException $e) {
        $queue->releaseItem($item);
        continue;
      }

      catch (\Exception $e) {
        // Some other sort of error - delay this item for 15mins
        $queue->delayItem($item, 900);
        $log && BuildingHousingUtils::log("cleanup", "Queue Item {$item->data->getSObject()->field("Name")} (a {$item->data->getSObject()->type()}) from {$item->data->getMappingId()} could not be processed.\n    Error: {$e->getMessage()}\n    - Retry item in 15mins.\n", TRUE);
        \Drupal::logger("BuildingHousing")->error("Queue Item {$item->data->getSObject()->field("Name")} (a {$item->data->getSObject()->type()}) from {$item->data->getMappingId()} could not be processed. ERROR: {$e->getMessage()}");
      }
    }

    unset($already_claimed);
    $this->lock->release(self::lockname);

    if ($count == 0 && $queue->numberOfItems() > 0) {
      \Drupal::messenger()->addWarning("No records could be selected in the Queue. Remaining records are locked. Please unlock or clear the queue");
    }

    return $count;
  }

  private function getSingleRecord($mapping, $id, $force_pull) {

    try {
      $soql = $mapping->getPullQuery();
      $_SERVER["argv"] = ($force_pull ? ["--force-pull"] : []);
      $this->eventDispatcher->dispatch(
        new SalesforceQueryEvent($mapping, $soql),
        SalesforceEvents::PULL_QUERY
      );
      $soql->conditions[] = ["field" => "Id", "operator" => "=", "value" => "'{$id}'"];
      $results = $this->client->query($soql);

      if ($results) {
        $this->processor->enqueueAllResults($mapping, $results, $force_pull);
        return $results->size();
      }
    }
    catch (\Exception $e) {
      $message = '%type: @message in %function (line %line of %file).';
      $args = Error::decodeException($e);
      $this->eventDispatcher->dispatch(new SalesforceErrorEvent($e, $message, $args), SalesforceEvents::ERROR);
    }
    return 0;
  }

  private function makeResponse(string $id, string $title = "", string $message = "", string $status = "success") {
    $markup  = "<div id='{$id}' class='form-item~class~'>~title~~icon~<b>{$message}</b></div>";

    if ($status == "success") {
      $markup = str_replace("~class~", " color-success" , $markup);
      $markup = str_replace("~icon~", "<img src='/core/misc/icons/73b355/check.svg' /> ", $markup);
    }
    else if ($status == "warning") {
      $markup = str_replace("~class~", " color-warning ", $markup);
      $markup = str_replace("~icon~", "<img src='/core/misc/icons/e29700/warning.svg' /> ", $markup);
    }
    else {
      $markup = str_replace("~class~", "", $markup);
      $markup = str_replace("~icon~", "", $markup);
    }

    if (!empty($title)) {
      $markup = str_replace("~title~", "{$title}<br/>", $markup);
    }
    else {
      $markup = str_replace("~title~", "", $markup);
    }

    return ["#markup" => Markup::create($markup)];

  }

  private function makeSFTable() {
    $mappings = \Drupal::entityTypeManager()->getStorage("salesforce_mapping")->loadMultiple();
    $currentTable = [
      '#type' => 'table',
      '#header' => ['SF Mapping', 'Last Updated', 'Count', 'Status', 'Launch','', ''],
    ];

    $sort = [];
    foreach ($mappings as $key => $mapping) {
      $sort[$mapping->weight] = $mapping;
    }
    ksort($sort);
    foreach($sort as $key => $mapping) {
      $dt = date('Y-m-d H:i:s', $mapping->getLastPullTime());
      $status = $mapping->get("status") ? "Enabled" : "Disabled";
      $num = \Drupal::entityQuery("node")
        ->accessCheck(TRUE)
        ->condition("type", $mapping->getDrupalBundle())
        ->count()
        ->execute();
      $num = number_format($num,0);
      $mode = $mapping->get("pull_standalone") ? "Manual" : "Cron";
      $type = ucwords(str_replace("_", " ", str_replace("__c", "", $mapping->getSalesforceObjectType())));
      $currentTable[$mapping->id()] = [
        '#weight' => $mapping->get('weight'),
        "map" => ["#plain_text" => $mapping->label()],
        "updated" => ["#plain_text" => $dt],
        "count" => ["#plain_text" => $num],
        "status" => ["#plain_text" => $status],
        "launch" => ["#plain_text" => $mode],
        "sync" => [
          '#type' => 'actions',
          'submit' => [
            '#type' => 'submit',
            '#name' => $mapping->id(),
            '#value' => "Sync {$type}s",
            '#attributes' => [
              "id" => $mapping->getDrupalBundle(),
              "class" => ["button", "button--primary", "button--small"]
            ]
          ],
        ],
        'weight' => [
          '#type' => 'weight',
          '#title' => t('Weight for @title', ['@title' => $mapping->label()]),
          '#title_display' => 'invisible',
          '#attributes' => ["style" => ["visibility: hidden;"]],
          '#default_value' => $mapping->get('weight'),
        ],
      ];
    }
    unset($mappings);
    return $currentTable;
  }

  private function makeQueueTable() {
    $queue_name = QueueHandler::PULL_QUEUE_NAME;
    $queue_factory = \Drupal::service('queue');
    $queue = $queue_factory->get($queue_name);

    $currentTable = [
      '#type' => 'table',
      '#header' => ['QUEUE', 'Object', 'Number of Items', ''],
    ];

    // Main queue summary
    $currentTable[-10] = [
      '#weight' => -10,
      "title" => ["#plain_text" => $queue_name],
      "mapping" => ["#plain_text" => "TOTAL"],
      "number" => ["#plain_text" => number_format($queue->numberOfItems(), 0)],
      "operations" => [
        '#type' => 'actions',
        'process' => [
          '#type' => 'submit',
          '#name' => 'edit-process-sfqueue',
          '#access' => ($queue->numberOfItems() != 0),
          '#value' => "Process Entire Queue",
          '#attributes' => [
            "id" => "edit-process-sfqueue",
            "class" => ["button", "button--primary", "button--small"]
          ]
        ],
        'clear' => [
          '#type' => 'submit',
          '#name' => 'edit-clear-sfqueue',
          '#access' => ($queue->numberOfItems() != 0),
          '#value' => "Clear Entire Queue",
          '#attributes' => [
            "id" => "edit-clear-sfqueue",
            "class" => ["button", "button--danger", "button--small"]
          ]
        ],
      ],
    ];

    if ($queue->numberOfItems() > 0) {

      $db = \Drupal::database();
      $mappings = \Drupal::entityTypeManager()->getStorage("salesforce_mapping")->loadMultiple();
      foreach ($mappings as $mapping) {
        $query = $db->select("queue", "q")
          ->fields("q", ["name", "data"])
          ->condition("name", $queue_name , "=");
        $query->condition("data", "%mappingId%{$mapping->id()}%", "like");
        $result = $query->countQuery()->execute()->fetchField();
        $mapping_name = ucwords(str_replace(["__c", "_"], ["", " "], $mapping->getSalesforceObjectType()));

        if ($result > 0) {
          $currentTable[$mapping_name] = [
            '#weight' => $mapping->get('weight'),
            "title" => ["#plain_text" => ""],
            "mapping" => ["#plain_text" => $mapping_name],
            "number" => ["#plain_text" => number_format($result, 0)],
            "operations" => [
              '#type' => 'actions',
              'process' => [
                '#type' => 'submit',
                '#name' => $mapping->id(),
                '#value' => "Process Queue Objects",
                '#access' => ($result != 0),
                '#attributes' => [
                  "id" => $mapping->id(),
                  "class" => ["button", "button--primary", "button--small"],
                ],
              ],
              'clear' => [
                '#type' => 'submit',
                '#name' => $mapping->id(),
                '#value' => "Clear Queue Objects",
                '#access' => ($result != 0),
                '#attributes' => [
                  "id" => $mapping->id(),
                  "class" => ["button", "button--danger", "button--small"],
                ],
              ],
            ],
            'weight' => [
              '#type' => 'weight',
              '#title' => t('Weight for @title', ['@title' => $mapping->label()]),
              '#title_display' => 'invisible',
              '#attributes' => ["style" => ["visibility: hidden;"]],
              '#default_value' => $mapping->get('weight'),
            ],
          ];
        }
      }

    }

    return $currentTable;
  }

  private function makeMappingDropdown() {
    $mappings = \Drupal::entityTypeManager()
      ->getStorage("salesforce_mapping")
      ->loadMultiple();
    $sort = [];
    foreach ($mappings as $key => $mapping) {
      $sort[$mapping->weight] = $mapping;
    }
    ksort($sort);

    $mapoptions = [0 => "Select mapping"];
    foreach ($sort as $wieght => $mapping) {
      $mapoptions[$mapping->id()] = $mapping->label();
    }
    return $mapoptions;
  }
}
