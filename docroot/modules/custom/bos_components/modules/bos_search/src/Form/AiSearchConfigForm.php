<?php

namespace Drupal\bos_search\Form;

use Drupal\bos_core\Controllers\Settings\CobSettings;
use Drupal\bos_google_cloud\Services\GcAgentBuilderInterface;
use Drupal\bos_google_cloud\Services\GcServiceInterface;
use Drupal\bos_search\AiSearch;
use Drupal\bos_search\Plugin\AiSearch\AiSearchPluginManager;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SearchConfigForm.
 *
 * - Allows the management of Search Presets used by AiSearchForm.
 *
 *  david 06 2024
 *
 * @file docroot/modules/custom/bos_components/modules/bos_search/src/Form/SearchConfigForm.php
 */
class AiSearchConfigForm extends ConfigFormBase {

  /** @var $pluginManagerAiSearch AiSearchPluginManager */
  protected $pluginManagerAiSearch;

  public function __construct(ConfigFactoryInterface $config_factory, AiSearchPluginManager $plugin_manager_aisearch, protected $typedConfigManager = NULL) {
    parent::__construct($config_factory, $typedConfigManager);
    $this->pluginManagerAiSearch  = $plugin_manager_aisearch;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('plugin.manager.aisearch'),
      $container->get('config.typed')
    );
  }
  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'bos_search_SearchConfigForm';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ["bos_search.settings"];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $presets = AiSearch::getPresets();
    $form = [
      'SearchConfigForm' => [
        '#tree' => TRUE,
        '#type' => 'fieldset',
        '#title' => 'AI Search Configuration',
        'presets' => [
          "#type" => "fieldset",
          '#title' => "presets",
          '#attributes' => ["id" => "edit-presets"],
          '#description_display' => 'before',
          '#description' => "You can define any number of presets and use these in search form implementations.",
        ],
        'actions' => [
          'add' => [
            "#type" => "button",
            "#value" => "Add Preset",
            '#ajax' => [
              'callback' => '::ajaxAddPreset',
              'event' => 'click',
              'wrapper' => 'edit-presets',
              'disable-refocus' => FALSE,
              'limit' => FALSE,
            ],
          ],
        ],
      ],
    ];

    // Get and populate each existing preset.
    foreach ($presets as $pid => $preset) {
      $form['SearchConfigForm']['presets'][$pid] = $this->preset($pid);
    }

    if ($form_state->isRebuilding()) {
      // A rebuild does occur when an ajax button is clicked.
      if ($form_state->getTriggeringElement()["#value"] == $form["SearchConfigForm"]["actions"]["add"]["#value"]) {
        // The ajax "Add Preset" button has been clicked.
        $this->addPreset($form, $form_state);
      }
      elseif ($form_state->getTriggeringElement()["#value"] == "Delete Preset") {
        // An ajax "Delete Preset" button has been clicked.
        $this->deletePreset($form, $form_state);
      }
    }

    $form = parent::buildForm($form, $form_state);
    return $form;

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Save the presets and any other fields to the settings config object.
    $values = $form_state->getUserInput();
    $config = $this->config('bos_search.settings');
    $params = [];
    foreach ($values["SearchConfigForm"]["presets"] as &$preset) {
      if (empty($preset['pid'])) {
        $preset['pid'] = AiSearch::machineName($preset["name"]);
      }
      unset($preset["actions"]);
      $params[$preset['pid']] = $preset;
    }
    $config->set("presets", $params);
    $config->save();
    // Optional.
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $values = $form_state->getValues();

    foreach($values["SearchConfigForm"]["presets"] as $preset => $setting) {
      $plugin = $this->pluginManagerAiSearch->createInstance($setting["plugin"]);
      if (!$plugin->hasFollowup()) {
        // TODO: should introduce a no-conversation version of the AiSearch component.
        $form_state->setError($form["SearchConfigForm"]["presets"][$preset]["plugin"],"The selected Service ($preset) does not support conversations.");
      }
    }

  }

  /**
   * Callback for Add Preset button on form.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return mixed
   */
  public function ajaxAddPreset(array &$form, FormStateInterface $form_state) {
    // The buildForm will have been called twice by this time, once as a build,
    // and once as a rebuild.
    return $form['SearchConfigForm']['presets'];
  }

  /**
   * Add a New preset to the form object.
   * NOTE: nothing is saved until the config form is saved (submitted)
   *
   * @param $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return void
   */
  public function addPreset(&$form, FormStateInterface $form_state) {
    $pid = count($form_state->getValues()['SearchConfigForm']['presets'] ?? []);
    $form['SearchConfigForm']['presets'][$pid] = $this->preset();
    $rand = intval(microtime(TRUE) * 1000);
    foreach ($form['SearchConfigForm']['presets'][$pid] as $key => &$preset) {
      if (!str_contains($key, "#")) {
        $preset["#id"] = "edit-searchconfigform-presets-$pid-$key--$rand";
        $preset["#attributes"] = [
          "data-drupal-selector" => "edit-searchconfigform-presets-$pid-$key",
        ];
      }
    }
    $form['SearchConfigForm']['presets'][$pid]["#title"] = "New Preset";
    $form['SearchConfigForm']['presets'][$pid]["#open"] = TRUE;
    $form['SearchConfigForm']['presets'][$pid]["#id"] = "edit-searchconfigform-presets-$pid--$rand";
    $form['SearchConfigForm']['presets'][$pid]["#attributes"] = [
      "data-drupal-selector" => "edit-searchconfigform-presets-$pid",
    ];
  }

  /**
   * Callback for Delete Preset button on form.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return mixed
   */

  public function ajaxDeletePreset(array &$form, FormStateInterface $form_state) {
    return $form['SearchConfigForm']['presets'];
  }

  /**
   * Delete the selected Preset.
   * NOTE: nothing is actually deleted until the config form is saved (submitted)
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return void
   */
  public function deletePreset(array &$form, FormStateInterface $form_state) {
    $pid = $form_state->getTriggeringElement()['#attributes']['data-pid'];
    unset($form['SearchConfigForm']['presets'][$pid]);
  }

  /**
   * Defines a preset fieldset on the form.
   *
   * @param string $pid
   *
   * @return array
   */
  private function preset(string $pid = "") {

    $preset = AiSearch::getPresetValues($pid) ?? [];

    /**
     * @var $service_plugins \Drupal\bos_search\Plugin\AiSearch\AiSearchPluginManager Registered AI Search Service Plugins
     */
    $service_plugins = $this->pluginManagerAiSearch->getDefinitions();
    // Populate an array of service plugins.
    $service_opts = [];
    foreach ($service_plugins as $service_plugin) {
      $service_opts[$service_plugin["id"]] = $service_plugin["id"];
    }
    // Get info on the service this preset is referencing.
    $this_service_plugin = $service_plugins[$preset["plugin"]];
    $this_service_id = $this_service_plugin["service"];
    $this_service = \Drupal::service($this_service_id);
    $this_service_settings = $this_service->getSettings();

    // Get the projectname.
    $project_name = $this->getProjects($this_service)[$this_service_settings["project_id"]];

    // Get the Engines/apps.
    $engine_name = $preset['model_tuning']['overrides']['engine_id'] ?? "default";
    $optEngines = [];
    $engines = $this->getEngines($this_service, ($preset['model_tuning']['overrides']['service_account'] ?? "default"), ($preset['model_tuning']['overrides']['project_id'] ?? "default"));
    foreach ($engines ?? [] as $key => $engine) {
      $optEngines[$key] = $engine["name"];
    }
    $mds = $engines[$engine_name]["multi-ds"];

    // Get Datastores.
    $optDataStores = $engines[$engine_name]["datastores"] ?? ["default"];

    $themes = AiSearch::getFormThemes();

    $output = [
      '#type' => 'details',
      '#title' => (empty($preset) ? "" : $preset['name']) . (empty($preset) ? "" : " (" . $this_service->id() . ")"),
      '#open' => FALSE,

      'name' => [
        '#type' => 'textfield',
        '#required' => TRUE,
        '#title' => $this->t("Preset Name"),
        "#default_value" => empty($preset) ? "" : ($preset['pid'] ?? ""),
        '#placeholder' => "Enter the name for this preset",
      ],
      'plugin' => [
        '#type' => 'select',
        '#options' => $service_opts,
        "#default_value" => empty($preset) ? "" : ($preset['plugin'] ?? ""),
        '#title' => $this->t("Select the AI Service Plugin to use:"),
        '#ajax' => [
          'callback' => '::ajaxCallbackChangedModel',
          'progress' => [
            'type' => 'throbber',
            'message' => "Reloading default configs ...",
          ],
        ],
      ],
      'prompt' => [
        '#type' => 'select',
        '#options' => $this->getPrompts($this_service),
        "#default_value" => empty($preset) ? "" : ($preset['prompt'] ?? "") ,
        '#title' => $this->t("Select the prompt for the AI Model to use:"),
        '#description' => $this->t("Prompts are set from the admin page for the model selected."),
        '#description_display' => 'after',
        '#prefix' => "<div id='edit-prompt'>",
        '#suffix' => "</div>",
      ],
      'model_tuning' => [
        '#type' => "details",
        '#title' => "Advanced AI Model Tuning",

        'overrides' => [
          '#type' => "fieldset",
          '#title' => 'Service Plugin Override',
          '#description' => $this->t("The default Service Settings are set on the <a href='/admin/config/system/boston/googlecloud'>Google Cloud Conversation configuration page</a>."),
          '#description_display' => 'before',

          'service_account' => [
            '#type' => 'select',
            '#options' => $this->getServiceAccounts($this_service),
            "#default_value" => empty($preset) ? "default" : ($preset['model_tuning']['overrides']['service_account'] ?? "default") ,
            '#title' => $this->t("Override the default Service Account for the AI Model to use:"),
            '#description' => $this->t("The current default Service Account is:") . " <b>{$this_service_settings["service_account"]}</b>",
            '#description_display' => 'after',
            '#prefix' => "<div id='edit-svsact'>",
            '#suffix' => "</div>",
            '#validated' => TRUE,
            '#ajax' => [
              'callback' => '::ajaxCallbackGetProjects',
              'event' => 'change',
              'progress' => [
                'type' => 'throbber',
                'message' => "Finding Projects ...",
              ],
            ],
          ],

          'project_id' => [
            '#type' => 'select',
            '#options' => $this->getProjects($this_service, ($preset['model_tuning']['overrides']['service_account'] ?? "default")),
            "#default_value" => empty($preset) ? "" : ($preset['model_tuning']['overrides']['project_id'] ?? ""),
            '#title' => $this->t("Override the Project for the AI Model to use."),
            '#description' => $this->t("Leave empty to use the default.<br>The current default Project is:") . " <b>$project_name</b>",
            '#description_display' => 'after',
            '#validated' => TRUE,
            '#prefix' => "<div id='edit-project'>",
            '#suffix' => "</div>",
            '#ajax' => [
              'callback' => '::ajaxCallbackGetEngines',
              'event' => 'change',
              'progress' => [
                'type' => 'throbber',
                'message' => "Finding Engines ...",
              ],
            ],
          ],
          'engine_id' => [
            '#type' => 'select',
            '#options' => $optEngines,
            "#default_value" => $engine_name,
            '#title' => $this->t("Override the default Engine (App) for the AI Model to use:"),
            '#description' => $this->t("The current default engine (app) is:") . " <b>{$this_service_settings["engine_id"]}</b>",
            '#description_display' => 'after',
            '#validated' => TRUE,
            '#prefix' => "<div id='edit-engine'>",
            '#suffix' => "</div>",
            '#ajax' => [
              'callback' => '::ajaxCallbackGetEngines',
              'event' => 'change',
              'progress' => [
                'type' => 'throbber',
                'message' => "Checking Engines ...",
              ],
            ],
          ],
          'engine_mds' => [
            '#type' => 'hidden',
            "#value" => $mds,
            '#prefix' => "<div id='edit-engine-mds'>",
            '#suffix' => "</div>",
          ],
           // At the moment we are not allowing users to specify a DataStore.
           // We always use the default datastores defined in the engine/app.
          'datastore_id' => [
            '#type' => 'select',
            '#multiple' => TRUE,
            '#options' => $optDataStores,
            "#default_value" => $optDataStores,
            '#title' => $this->t("Override the default Datastore for the AI Model to use:"),
            '#description' => $this->t("These are DataStores defined by the Engine/App. Changing them is not supported at this time."),
            '#description_display' => 'after',
            '#disabled' => TRUE,
            '#validated' => TRUE,
            '#size' => 5,
            '#prefix' => "<div id='edit-datastore'>",
            '#suffix' => "</div>",
            '#ajax' => [
              'callback' => '::ajaxCallbackGetDataStores',
              'event' => 'focus',
              'progress' => [
                'type' => 'throbber',
                'message' => "Finding Datastores ...",
              ],
            ],
          ],
          'model' => [
            '#type' => 'select',
            '#title' => t('Override the LLM model to use'),
            '#description' => t("Select the AI Model that will be used by the Engine."),
            '#default_value' => $preset['model_tuning']['overrides']['model'] ?? 'default',
            '#options' => [
              'default' => 'Use Default',
              'stable' => 'Stable',
              'preview' => 'Preview',
            ],
            '#required' => TRUE,
          ],
        ],
        'summary' => [
          '#type' => "fieldset",
          '#title' => 'Fine-tune Summarization',
          'ignoreAdversarialQuery' => [
            '#type' => 'checkbox',
            "#default_value" => empty($preset) ? 1 : ($preset['model_tuning']['summary']['ignoreAdversarialQuery'] ?? 0),
            '#title' => $this->t("Ignore Adverserial Queries."),
            '#description' => 'When selected, no summary is returned if the search query is classified as an adversarial query. For example, a user might ask a question regarding negative comments about the company or submit a query designed to generate unsafe, policy-violating output.',
          ],
          'ignoreNonSummarySeekingQuery' => [
            '#type' => 'checkbox',
            "#default_value" => empty($preset) ? 1 : ($preset['model_tuning']['summary']['ignoreNonSummarySeekingQuery'] ?? 0),
            '#title' => $this->t("Ignore Non-summary Seeking Queries."),
            '#description' => 'When selected, no summary is returned if the search query is classified as a non-summary seeking query. For example, why is the sky blue and Who is the best soccer player in the world? are summary-seeking queries, but SFO airport and world cup 2026 are not.',
          ],
          'ignoreLowRelevantContent' => [
            '#type' => 'checkbox',
            "#default_value" => empty($preset) ? 1 : ($preset['model_tuning']['summary']['ignoreLowRelevantContent'] ?? 0),
            '#title' => $this->t("Ignore Low Relevant Content."),
            '#description' => 'When selected, only queries with high relevance search results will generate answers.',
          ],
          'ignoreJailBreakingQuery' => [
            '#type' => 'checkbox',
            "#default_value" => empty($preset) ? 1 : ($preset['model_tuning']['summary']['ignoreJailBreakingQuery'] ?? 0),
            '#title' => $this->t("Ignore Jail-breaking Queries."),
            '#description' => "When selected, search-query classification is applied to detect queries that attempts to exploit vulnerabilities or weaknesses in the model's design or training data. No summary is returned if the search query is classified as a jail-breaking query.",
          ],
          'semantic_chunks' => [
            '#type' => 'checkbox',
            "#default_value" => empty($preset) ? 0 : ($preset['model_tuning']['summary']['semantic_chunks'] ?? 0),
            '#title' => $this->t("Enable Semantic Chunk Search."),
            '#description' => 'When selected, the summary will be generated from most relevant chunks from top search results. This feature will improve summary quality. Note that with this feature enabled, not all top search results will be referenced and included in the reference list, so the citation source index only points to the search results listed in the reference list.',
          ],
        ],
        'search' => [
          '#type' => "fieldset",
          '#title' => 'Fine-tune Search',
          'safe_search' => [
            '#type' => 'checkbox',
            "#default_value" => empty($preset) ? 1 : ($preset['model_tuning']['search']['safe_search'] ?? 0),
            '#title' => $this->t("Enable Safe Search."),
            '#description' => 'When selected, significantly reduces the level of explicit content that the system can display in the results. This is similar to the feature used in Google Search, where you can modify your settings to filter explicit content, such as nudity, violence, and other adult content, from the search results.',
          ],
        ],
      ],
      'searchform' => [
        '#type' => 'details',
        '#collapsible' => TRUE,
        '#title' => 'Search Form Configuration and Styling',
        'theme' => [
          '#type' => 'select',
          '#options' => $themes,
          "#default_value" => empty($preset) ? "" : ($preset['results']['theme'] ?? ""),
          '#title' => $this->t("Select the theme for the form configured by this preset"),
        ],
        'disclaimer' => [
          '#type' => 'fieldset',
          '#title' => $this->t("Pop-up Disclaimer"),
          '#description' => $this->t("Control the presence and content of an interstitial disclaimer which shows before the search form is shown."),
          '#description_display' => 'before',
          'enabled' => [
            '#type' => 'checkbox',
            "#default_value" => empty($preset) ? 0 : ($preset['searchform']['disclaimer']['enabled'] ?? 0),
            '#title' => $this->t("Show disclaimer window"),
          ],
          'show_once' => [
            '#type' => 'checkbox',
            "#default_value" => empty($preset) ? 0 : ($preset['searchform']['disclaimer']['show_once'] ?? 0),
            '#title' => $this->t("Only Show Once"),
            '#description' => $this->t("When checked, the disclaimer window will only appear the first time the search form loads, when unselected the disclaimer window will show every time the search form opens for the user. This is a session-based rule."),
            '#states' => [
              'visible' => [
                ':input[name="SearchConfigForm[presets][' . $pid . '][searchform][disclaimer][enabled]"]' => ['checked' => TRUE],
              ],
            ],
          ],
          'text' => [
            '#type' => 'textarea',
            "#default_value" => empty($preset) ? "" : ($preset['searchform']['disclaimer']['text'] ?? ""),
            '#title' => $this->t("Popup Disclaimer"),
            '#description' => $this->t("Disclaimer text to appear as an interstitial popup when first showing the form."),
            '#description_display' => 'before',
            '#states' => [
              'visible' => [
                ':input[name="SearchConfigForm[presets][' . $pid . '][searchform][disclaimer][enabled]"]' => ['checked' => TRUE],
              ],
              'required' => [
                ':input[name="SearchConfigForm[presets][' . $pid . '][searchform][disclaimer][enabled]"]' => ['checked' => TRUE],
              ],
            ],
          ],
        ],
        'modal_titlebartitle' => [
          '#type' => 'textfield',
          '#title' => $this->t("Modal Form Title"),
          "#default_value" => empty($preset) ? "" : ($preset['searchform']['modal_titlebartitle'] ?? ""),
          '#description' => $this->t("Leave blank for no title on the search form when it is a modal window."),
          '#description_display' => 'before',
        ],
        'welcome' => [
          '#type' => 'fieldset',
          '#title' => $this->t("Main Form Body"),
          '#description' => $this->t("Configure the initial information displayed to the user"),
          '#description_display' => 'before',
          'body_title' => [
            '#type' => 'textfield',
            '#title' => $this->t("Form Body Title"),
            "#default_value" => empty($preset) ? 0 : ($preset['searchform']['welcome']['body_title'] ?? ""),
            '#placeholder' => "What are you looking for?",
            '#description' => $this->t("Add a title for the search form. Can be blank."),
            '#description_display' => 'after',
          ],
          'body_text' => [
            '#type' => 'textarea',
            '#title' => $this->t("Form Body Copy"),
            "#default_value" => empty($preset) ? 0 : ($preset['searchform']['welcome']['body_text'] ?? ""),
            '#description' => $this->t("Add follow-on/body copy to appear on the search form. Can be blank."),
            '#description_display' => 'before',
          ],
          'cards' => [
            '#type' => 'fieldset',
            '#title' => $this->t("Example/Suggested Searches"),
            '#description' => $this->t("Example search terms presented as cards"),
            'enabled' => [
              '#type' => 'checkbox',
              "#default_value" => empty($preset) ? 0 : ($preset['searchform']['welcome']['cards']['enabled'] ?? 0),
              '#title' => $this->t("Enable cards."),
            ],
            'card_1' => [
              '#type' => 'textfield',
              '#title' => $this->t("Example Question 1"),
              "#default_value" => empty($preset) ? "" : ($preset['searchform']['welcome']["cards"]['card_1'] ?? ""),
              '#placeholder' => "How do I open a new business in Boston?",
              '#description' => $this->t("Enter text for the example question to place in the card."),
              '#description_display' => 'after',
              '#states' => [
                'visible' => [
                  ':input[name="SearchConfigForm[presets][' . $pid . '][searchform][welcome][cards][enabled]"]' => ['checked' => TRUE],
                ],
                'required' => [
                  ':input[name="SearchConfigForm[presets][' . $pid . '][searchform][welcome][cards][enabled]"]' => ['checked' => TRUE],
                ],
              ],
            ],
            'card_2' => [
              '#type' => 'textfield',
              '#title' => $this->t("Example Question 2"),
              "#default_value" => empty($preset) ? "" : ($preset['searchform']['welcome']["cards"]['card_2'] ?? ""),
              '#placeholder' => "When is the next meeting for the small business forum?",
              '#description' => $this->t("Enter text for the example question to place in the card."),
              '#description_display' => 'after',
              '#states' => [
                'visible' => [
                  ':input[name="SearchConfigForm[presets][' . $pid . '][searchform][welcome][cards][enabled]"]' => ['checked' => TRUE],
                ],
                'required' => [
                  ':input[name="SearchConfigForm[presets][' . $pid . '][searchform][welcome][cards][enabled]"]' => ['checked' => TRUE],
                ],
              ],
            ],
            'card_3' => [
              '#type' => 'textfield',
              '#title' => $this->t("Example Question 3"),
              "#default_value" => empty($preset) ? "" : ($preset['searchform']['welcome']["cards"]['card_3'] ?? ""),
              '#placeholder' => "How do I become a certified Boston Equity Applicant?",
              '#description' => $this->t("Enter text for the example question to place in the card."),
              '#description_display' => 'after',
              '#states' => [
                'visible' => [
                  ':input[name="SearchConfigForm[presets][' . $pid . '][searchform][welcome][cards][enabled]"]' => ['checked' => TRUE],
                ],
                'required' => [
                  ':input[name="SearchConfigForm[presets][' . $pid . '][searchform][welcome][cards][enabled]"]' => ['checked' => TRUE],
                ],
              ],
            ],
          ],
        ],

        'searchbar' => [
          '#type' => 'fieldset',
          '#title' => $this->t("Searchbar Configuration"),
          '#description' => $this->t("Display settings for the main search bar"),
          '#description_display' => 'before',
          'allow_conversation' => [
            '#type' => 'checkbox',
            '#title' => $this->t("Allow follow-on questions during search."),
            "#default_value" => empty($preset) ? "" : ($preset['searchform']['searchbar']['allow_reset'] ?? 0),
          ],
          'allow_reset' => [
            '#type' => 'checkbox',
            '#title' => $this->t("Allow the user to reset the search history"),
            "#default_value" => empty($preset) ? "" : ($preset['searchform']['searchbar']['allow_reset'] ?? 0),
            '#states' => [
              'visible' => [
                ':input[name="SearchConfigForm[presets][' . $pid . '][searchform][searchbar][allow_conversation]"]' => ['checked' => TRUE],
              ],
            ],
          ],
          'search_text' => [
            '#type' => 'textfield',
            '#title' => $this->t("Search Prompt"),
            "#default_value" => empty($preset) ? "" : ($preset['searchform']['searchbar']['search_text'] ?? ""),
            '#placeholder' => "How can we help you ?",
          ],
          'waiting_text' => [
            '#type' => 'textfield',
            '#title' => $this->t("Text to show in searchbar when waiting for search results"),
            "#default_value" => empty($preset) ? "" : ($preset['searchform']['searchbar']['waiting_text'] ?? ""),
            '#placeholder' => "Searching Boston.gov?",
          ],
          'audio_search_input' => [
            '#type' => 'checkbox',
            '#title' => $this->t("Allow Audio input to searchbar"),
            "#default_value" => empty($preset) ? "" : ($preset['searchform']['searchbar']['audio_search_input'] ?? 0),
          ],
          'search_note' => [
            '#type' => 'textarea',
            "#default_value" => empty($preset) ? "" : ($preset['searchform']['searchbar']['search_note'] ?? ""),
            '#title' => $this->t("Search Help"),
            '#description' => $this->t("Any help notes to appear under the search box. Can be left blank."),
            '#description_display' => 'after',
          ],
        ],
      ],
      'results' => [
        '#type' => 'details',
        '#collapsible' => TRUE,
        '#title' => 'Search Results Configuration',
        'result_count' => [
          '#type' => 'select',
          '#options' => [
            0 => "All",
            1 => "1",
            3 => "3",
            5 => "5",
            10 => "10",
            15 => "15",
            20 => "20",
          ],
          "#default_value" => empty($preset) ? 0 : ($preset['results']['result_count'] ?? 0),
          '#title' => $this->t("How many results should be returned?"),
        ],
        'summary' => [
          '#type' => 'checkbox',
          "#default_value" => empty($preset) ? 0 : ($preset['results']['summary'] ?? 0),
          '#title' => $this->t("Show AI Model generated summary text in results output."),
        ],
        'no_result_text' => [
          '#type' => 'textarea',
          "#default_value" => empty($preset) ? "" : ($preset['results']['no_result_text'] ?? ""),
          '#title' => $this->t("No Results Text"),
          '#description' => $this->t("Text that should appear when the AI Model is unable to answer a question."),
          '#description_display' => 'after',
          '#states' => [
            'visible' => [
              ':input[name="SearchConfigForm[presets][' . $pid . '][results][summary]"]' => ['checked' => TRUE],
            ],
          ],
        ],
        'violations_text' => [
          '#type' => 'textarea',
          "#default_value" => empty($preset) ? "" : ($preset['results']['violations_text'] ?? ""),
          '#title' => $this->t("Query Violations Text"),
          '#description' => $this->t("Text that should appear when the question fed to the AI Model was rejected."),
          '#description_display' => 'after',
          '#states' => [
            'visible' => [
              ':input[name="SearchConfigForm[presets][' . $pid . '][results][summary]"]' => ['checked' => TRUE],
            ],
          ],
        ],
        'citations' => [
          '#type' => 'checkbox',
          "#default_value" => empty($preset) ? 0 : ($preset['results']['citations'] ?? 0),
          '#title' => $this->t("Show citations in results output (if available)."),
          '#states' => [
            'visible' => [
              ':input[name="SearchConfigForm[presets][' . $pid . '][results][summary]"]' => ['checked' => TRUE],
            ],
          ],
        ],
        'min_citation_relevance' => [
          '#type' => 'select',
          '#options' => [
            "0" => "Show All",
            "0.3" => "0.3",
            "0.5" => "0.5",
            "0.6" => "0.6",
            "0.7" => "0.7",
            "0.75" => "0.75",
            "0.8" => "0.8",
            "0.85" => "0.85",
            "0.9" => "0.9",
            "0.95" => "0.95",
          ],
          "#default_value" => empty($preset) ? 0 : ($preset['results']['min_citation_relevance'] ?? 0) ,
          '#title' => $this->t("The minimum relevance for sitations to appear in list."),
          '#description' => $this->t("References with relevance scores below this number will be suppressed in Citations marked in the Summary."),
          '#description_display' => "below",
          '#states' => [
            'visible' => [
              [
                ':input[name="SearchConfigForm[presets][' . $pid . '][results][citations]"]' => ['checked' => TRUE],
              ],
            ],
          ],
        ],
        'searchresults' => [
          '#type' => 'checkbox',
          "#default_value" => empty($preset) ? 0 : ($preset['results']['searchresults'] ?? 0),
          '#title' => $this->t("Show search results in results output."),
        ],
        'no_dup_citations' => [
          '#type' => 'checkbox',
          "#default_value" => empty($preset) ? 0 : ($preset['results']['no_dup_citations'] ?? 0),
          '#title' => $this->t("Remove search result links that already appear in the citations listing."),
          '#states' => [
            'visible' => [
              [
                ':input[name="SearchConfigForm[presets][' . $pid . '][results][citations]"]' => ['checked' => TRUE],
                ':input[name="SearchConfigForm[presets][' . $pid . '][results][searchresults]"]' => ['checked' => TRUE],
              ],
            ],
          ],
        ],
        'related_questions' => [
          '#type' => 'checkbox',
          "#default_value" => empty($preset) ? 0 : ($preset['results']['related_questions'] ?? 0),
          '#title' => $this->t("Show related questions (suggested questions) after query results."),
        ],
        'feedback' => [
          '#type' => 'checkbox',
          "#default_value" => empty($preset) ? 0 : ($preset['results']['feedback'] ?? 0),
          '#title' => $this->t("Show feedback buttons below results output."),
        ],
        'metadata' => [
          '#type' => 'checkbox',
          "#default_value" => empty($preset) ? 0 : ($preset['results']['metadata'] ?? 0),
          '#title' => $this->t("Show AI Model metadata in results output (if available)."),
        ],
        'pid' => [
          '#type' => 'hidden',
          "#default_value" => $pid,
          "#value" => $pid,
        ],
      ],
      'actions' => [
        '#type' => "button",
        '#value' => "Delete Preset",
        '#attributes' => [
          "class" => [
            "button--danger",
          ],
          "data-pid" => "$pid",
        ],
        '#ajax' => [
          'callback' => '::ajaxDeletePreset',
          'event' => 'click',
          'wrapper' => 'edit-presets',
          'disable-refocus' => FALSE,
          'limit' => FALSE,
        ],
      ],
    ];

    if (!isset($pid) || $pid == "") {
      // Configure for a new Preset.
      unset($output["actions"]);
      foreach ($output as &$row) {
        if (is_array($row) && $row["#type"] == "textarea") {
          $row["#value"] = "";
        }
      }

    }

    if (!empty($preset['model_tuning']['overrides']['service_account']) && $preset['model_tuning']['overrides']['service_account'] != "default") {
      $output["model_tuning"]["overrides"]["service_account"]["#value"] = $preset['model_tuning']['overrides']['service_account'];
      if (!array_key_exists($preset["model_tuning"]["overrides"]["service_account"], $output["model_tuning"]["overrides"]["service_account"]["#options"])) {
        $output["model_tuning"]["overrides"]["service_account"]["#options"][$preset["model_tuning"]["overrides"]["service_account"]] = $preset["model_tuning"]["overrides"]["service_account"];
      }
    }

    if (!empty($preset['model_tuning']['overrides']['datastore_id']) && $preset['model_tuning']['overrides']['datastore_id'] != "default") {
      $output["model_tuning"]["overrides"]["datastore_id"]["#value"] = $preset['model_tuning']['overrides']['datastore_id'];
      if (!array_key_exists($preset["model_tuning"]["overrides"]["datastore_id"][0], $output["model_tuning"]["overrides"]["datastore_id"]["#options"])) {
        $output["model_tuning"]["overrides"]["datastore_id"]["#options"][$preset["model_tuning"]["overrides"]["datastore_id"]] = $preset["model_tuning"]["overrides"]["datastore_id"];
      }
    }

    if (!empty($preset['model_tuning']['overrides']['engine_id']) && $preset['model_tuning']['overrides']['engine_id'] != "default") {
      $output["model_tuning"]["overrides"]["engine_id"]["#value"] = $preset['model_tuning']['overrides']['engine_id'];
      if (!array_key_exists($preset["model_tuning"]["overrides"]["engine_id"], $output["model_tuning"]["overrides"]["engine_id"]["#options"])) {
        $output["model_tuning"]["overrides"]["engine_id"]["#options"][$preset["model_tuning"]["overrides"]["engine_id"]] = $preset["model_tuning"]["overrides"]["engine_id"];
      }
    }

    return $output;

  }

  /**
   * Handles AJAX callbacks for getting the service account for the form.
   *
   * Service Accounts are defined via the bos_google_cloud config form.
   *
   * @param array $form
   *   The form array.
   * @param FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return AjaxResponse
   *   The response containing the updated service account selection options.
   */
  public function ajaxCallbackGetServiceAccount(array $form, FormStateInterface $form_state): AjaxResponse {
    $trigger = $form_state->getTriggeringElement();
    $active_preset_id = $trigger["#parents"][2];
    $active_preset = $form_state->getValues()["SearchConfigForm"]["presets"][$active_preset_id];
    // Find the selected service and its prompts.
    $service_plugins = $this->pluginManagerAiSearch->getDefinitions();
    $this_service_plugin = $service_plugins[$active_preset["plugin"]];
    $this_service = \Drupal::service($this_service_plugin["service"]);

    $output = new AjaxResponse();
    $target_preset = '#edit-searchconfigform-presets-' . str_replace("_", "-", $active_preset_id);
    $html = "";
    foreach ($this->getServiceAccounts($this_service) as $key => $service) {
      if ($trigger["#value"] && $trigger["#value"] == $key) {
        $html .= '<option value="' . $key . '" selected>' . $service . '</option>';
      }
      else {
        $html .= '<option value="' . $key . '">' . $service . '</option>';
      }
    }
    $output->addCommand(new HtmlCommand($target_preset . ' #edit-svsact select', $html));

    return $output;

  }

  /**
   * Handles AJAX callback for changing the model in the form.
   *
   * @param array $form The form structure array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse The response object containing AJAX commands to update the form.
   */
  public function ajaxCallbackChangedModel(array $form, FormStateInterface $form_state): AjaxResponse {

    // Get info from submitted form changes.
    $trigger = $form_state->getTriggeringElement();
    $active_preset_id = $trigger["#parents"][2];
    $active_preset = $form_state->getValues()["SearchConfigForm"]["presets"][$active_preset_id];
    // Find the selected service and its prompts.
    $service_plugins = $this->pluginManagerAiSearch->getDefinitions();
    $this_service_plugin = $service_plugins[$trigger['#value']];
    $this_service = \Drupal::service($this_service_plugin["service"]);
    $prompts = $this->getPrompts($this_service);
    $this_service_settings = $this_service->getSettings();
    $project_name = $this->getProjects($this_service)[$this_service_settings["project_id"]];

    $output = new AjaxResponse();

    // Update the prompts available to this service. If the current
    // prompt exists, then use it, otherwise use the "default".
    $target_preset = '#edit-searchconfigform-presets-' . str_replace("_", "-", $active_preset_id);
    $output->addCommand(new ReplaceCommand($target_preset . ' #edit-prompt', [
      'prompt' => [
        '#type' => 'select',
        '#options' => $prompts,
        '#title' => $this->t("Select the prompt for the AI Model to use:"),
        "#default_value" => empty($prompts) ? "default" : ($prompts[$active_preset['prompt']] ?? "default") ,
        '#description' => $this->t("Prompts are set from the admin page for the model selected."),
        '#description_display' => 'after',
        '#prefix' => "<div id='edit-prompt'>",
        '#suffix' => "</div>",
      ],
    ]));
    // Set notification below Service Account.
    $target_preset = '#edit-searchconfigform-presets-' . str_replace("_", "-", $active_preset_id) . "-model-tuning-overrides-service-account--description b";
    $output->addCommand(new HtmlCommand($target_preset, $this_service_settings["service_account"]));
    // Set notification below Project.
    $target_preset = '#edit-searchconfigform-presets-' . str_replace("_", "-", $active_preset_id) . "-model-tuning-overrides-project-id--description b";
    $output->addCommand(new HtmlCommand($target_preset, $project_name));
    // Set notification below DataStore.
//    $target_preset = '#edit-searchconfigform-presets-' . str_replace("_", "-", $active_preset_id) . "-model-tuning-overrides-datastore-id--description b";
//    $output->addCommand(new HtmlCommand($target_preset, $this_service_settings["datastore_id"]));
    // Set notification below Engine.
    $target_preset = '#edit-searchconfigform-presets-' . str_replace("_", "-", $active_preset_id) . "-model-tuning-overrides-engine-id--description b";
    $output->addCommand(new HtmlCommand($target_preset, $this_service_settings["engine_id"]));

    return $output;

  }

  /**
   * Handles the AJAX callback to get the list of projects and update the project
   * selection options in the form.
   *
   * Projects are read from Google Cloud
   *
   *
   * @param array $form The form array containing the form elements.
   * @param \Drupal\Core\Form\FormStateInterface $form_state The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse The AJAX response with the updated project options.
   */
  public function ajaxCallbackGetProjects(array $form, FormStateInterface $form_state): AjaxResponse {
    // Get info from submitted form changes
    $trigger = $form_state->getTriggeringElement();
    $active_preset_id = $trigger["#parents"][2];
    $active_preset = $form_state->getValues()["SearchConfigForm"]["presets"][$active_preset_id];
    // Find the selected service and its prompts
    $service_plugins = $this->pluginManagerAiSearch->getDefinitions();
    $this_service_plugin = $service_plugins[$active_preset["plugin"]];
    $service = \Drupal::service($this_service_plugin["service"]);

    $output = new AjaxResponse();
    $html = "";
    $p = $form_state->getUserInput()['SearchConfigForm']['presets'][$active_preset_id]['model_tuning']['overrides']['project_id'] ?: NULL;
    foreach ($this->getProjects($service) as $key => $project) {
      if ($trigger["#value"] && $p == $key) {
        $html .= '<option value="' . $key . '" selected>' . $project . '</option>';
      }
      else {
        $html .= '<option value="' . $key . '">' . $project . '</option>';
      }
    }
    $target_preset = '#edit-searchconfigform-presets-' . str_replace("_", "-", $active_preset_id);
    $output->addCommand(new HtmlCommand($target_preset . ' #edit-project select', $html));
    return $output;
  }

  /**
   * Handles AJAX callbacks to update datastores in the form.
   *
   * Datastores are read from Google Cloud
   *
   * @param array $form The current state of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state The state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse The response object containing set of commands to update the form.
   */
  public function ajaxCallbackGetDataStores(array $form, FormStateInterface $form_state): AjaxResponse {

    // Get info from submitted form changes.
    $trigger = $form_state->getTriggeringElement();
    $active_preset_id = $trigger["#parents"][2];
    $active_preset = $form_state->getValues()["SearchConfigForm"]["presets"][$active_preset_id];
    $overrides = $active_preset["model_tuning"]["overrides"];
    // Find the selected service and its prompts.
    $service_plugins = $this->pluginManagerAiSearch->getDefinitions();
    $this_service_plugin = $service_plugins[$active_preset["plugin"]];
    $service = \Drupal::service($this_service_plugin["service"]);

    $output = new AjaxResponse();

    // Update the datastores available to this project. If the current
    // datastore exists, then use it, otherwise use the "default".
    $target_preset = '#edit-searchconfigform-presets-' . str_replace("_", "-", $active_preset_id) . "-model-tuning-overrides";
    $service_account = $overrides["service_account"] == "default" ? "" : $overrides["service_account"];
    $project = $overrides["project_id"] == "default" ? "" : $overrides["project_id"];

    $html = "";
    $ds = $form_state->getUserInput()['SearchConfigForm']['presets'][$active_preset_id]['model_tuning']['overrides']['datastore_id'] ?: NULL;
    $found_datastores = $this->getDatastores($service, $service_account, $project);
    $new_datastore = array_key_first($found_datastores);
    foreach ($found_datastores as $key => $datastore) {
      if ($trigger["#value"] && $ds == $key || count($found_datastores) == 1 || $form["select_all_datastores"]) {
        $html .= '<option value="' . $key . '" selected>' . $datastore . '</option>';
        $new_datastore = $key;
      }
      else {
        $html .= '<option value="' . $key . '">' . $datastore . '</option>';
      }
    }
    $output->addCommand(new HtmlCommand($target_preset . ' #edit-datastore select', $html));
    $output->addCommand(new InvokeCommand($target_preset . ' #edit-datastore select', 'attr', ['value', $new_datastore]));

    return $output;

  }

  /**
   * Handles AJAX callbacks for retrieving and updating available engines based on form input.
   *
   *  Engines are read from Google Cloud
   *
   * @param array $form The form structure array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse Contains commands to update the frontend with available engines.
   */
  public function ajaxCallbackGetEngines(array $form, FormStateInterface $form_state): AjaxResponse {

    // Get info from submitted form changes.
    $trigger = $form_state->getTriggeringElement();
    $active_preset_id = $trigger["#parents"][2];
    $active_preset = $form_state->getValues()["SearchConfigForm"]["presets"][$active_preset_id];
    $overrides = $active_preset["model_tuning"]["overrides"];
    // Find the selected service and its prompts.
    $service_plugins = $this->pluginManagerAiSearch->getDefinitions();
    $this_service_plugin = $service_plugins[$active_preset["plugin"]];
    $service = \Drupal::service($this_service_plugin["service"]);

    // Update the datastores available to this project. If the current
    // datastore exists, then use it, otherwise use the "default".
    $target_preset = 'edit-searchconfigform-presets-' . str_replace("_", "-", $active_preset_id) . "-model-tuning";
    $service_account = $overrides["service_account"] == "default" ? "" : $overrides["service_account"];
    $project = $overrides["project_id"] == "default" ? "" : $overrides["project_id"];

    $html = "";
    $ds = $form_state->getUserInput()['SearchConfigForm']['presets'][$active_preset_id]['model_tuning']['overrides']['engine_id'] ?: NULL;

    $found_engines = $this->getEngines($service, $service_account, $project);
    $new_engine = array_key_first($found_engines);

    $output = new AjaxResponse();
    foreach ($found_engines as $key => $engine) {
      if ($trigger["#value"] && $ds == $key) {
        $html .= '<option value="' . $key . '" selected>' . $engine["name"] . '</option>';
        $new_engine = $key;
      }
      else {
        $html .= '<option value="' . $key . '">' . $engine["name"] . '</option>';
      }
    }
    $trigger_name = array_pop($trigger["#array_parents"]);
    if ($trigger_name == "project_id") {
      $output->addCommand(new HtmlCommand("#" . $target_preset . ' #edit-engine select', $html));
      $output->addCommand(new InvokeCommand("#" . $target_preset . ' #edit-engine select', 'attr', [
        'value',
        $new_engine,
      ]));
    }
    if ($trigger_name == "engine_id") {
      // Find the datastores for this app.
      $html = "";
      foreach ($found_engines[$new_engine]["datastores"] as $key => $datastore) {
        $html .= '<option value="' . $key . '" selected>' . $datastore . '</option>';
      }
      $output->addCommand(new HtmlCommand("#" . $target_preset . ' #edit-datastore select', $html));
      $output->addCommand(new InvokeCommand("#" . $target_preset . ' #edit-datastore select', 'attr', [
        'value',
        $found_engines[$new_engine]["datastores"],
      ]));
      // Add the multi-datastore flag into the AjaxResponse object.
      $output->addCommand(new ReplaceCommand("#" . $target_preset . ' #edit-engine-mds', [
        'engine-mds' => [
          '#type' => 'hidden',
          "#value" => "{$found_engines[$new_engine]["multi-ds"]}",
          '#prefix' => "<div id='edit-engine-mds'>",
          '#suffix' => "</div>",
          "#attributes" => [
            "data-drupal-selector" => $target_preset . "-overrides-engine-mds",
            "name" => "SearchConfigForm[presets][vertex_search][model_tuning][overrides][engine_mds]",
          ],
        ],
      ]));
    }

    return $output;

  }

  /**
   * Retrieves an array of available prompts from the given service.
   *
   * @param GcServiceInterface $service The service instance to retrieve prompts from.
   *
   * @return array An array of available prompts.
   */
  private function getPrompts(GcServiceInterface $service) {
    return $service->availablePrompts();
  }

  /**
   * Retrieves a list of service accounts configured in the GCAPI settings.
   *
   * @param GcServiceInterface $service The service interface instance used for fetching the settings.
   *
   * @return array An associative array of service accounts where keys and values represent the account names.
   */
  private function getServiceAccounts(GcServiceInterface $service): array {
    $settings = CobSettings::getSettings("GCAPI_SETTINGS", "bos_google_cloud");
    $output = ["default" => "use default"];
    foreach ($settings["auth"] as $acct => $setting) {
      $output[$acct] = $acct;
    }
    return $output;
  }

  /**
   * Retrieves a list of projects from the given service.
   *
   * @param GcServiceInterface $service
   *   The service from which to retrieve the projects.
   *
   * @return array
   *   An associative array of project identifiers and their corresponding names.
   */
  private function getProjects(GcServiceInterface $service, ?string $service_account = NULL): array {
    return ["default" => "use default"] + $service->availableProjects($service_account);
  }

  /**
   * Retrieves an array of available datastores combined with a default option.
   *
   * @param GcServiceInterface|GcAgentBuilderInterface $service The service instance to retrieve datastores from.
   * @param string|null $service_account The service account to use, or NULL to use the default.
   * @param string|null $project The project to query datastores for, or NULL to use the default project.
   *
   * @return array An array of available datastores with a default option included.
   */
  private function getDatastores(GcServiceInterface|GcAgentBuilderInterface $service, ?string $service_account = NULL, ?string $project_id = NULL, ?string $engine_id = NULL): array {
//    return ["default" => "use default"] + $service->availableDatastores($service_account, $project_id);
    $engines = $service->availableEngines($service_account, $engine_id);
    return $engines[$project_id] ?? ["default" => "use default"];
  }

  /**
   * @param \Drupal\bos_google_cloud\Services\GcServiceInterface|\Drupal\bos_google_cloud\Services\GcAgentBuilderInterface $service
   * @param string|null $service_account
   * @param string|null $project_id
   *
   * @return string[]
   */
  private function getEngines(GcServiceInterface|GcAgentBuilderInterface $service, ?string $service_account = NULL, ?string $project_id = NULL): array {
    return ["default" => ["name" => "use default", "multi-ds" => "true"]] + $service->availableEngines($service_account, $project_id);
  }

}
