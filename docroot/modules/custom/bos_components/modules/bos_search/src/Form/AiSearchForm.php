<?php

namespace Drupal\bos_search\Form;

use Drupal\bos_search\AiSearch;
use Drupal\bos_search\Model\AiSearchRequest;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\SettingsCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;

/*
  class PromptTesterForm
  - Performs AI Searches using the requested preset.

  david 04 2024
  @file docroot/modules/custom/bos_components/modules/bos_google_cloud/src/Form/src/Form/PromptTesterForm.php
*/

class AiSearchForm extends FormBase {

  /**
   * This form allows a user to submit a conversation-based search.
   */

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'bos_search_AISearchForm';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $form = [
      "#attached" => ["library" => ["bos_search/core"]],
      '#modal_title' => $config["searchform"]["modal_titlebartitle"] ?? "",
    ];

    $preset = $form_state->getBuildInfo()["args"][0];
    if (empty($preset)) {
      $preset = AiSearch::getPreset();
    }
    $config = $this->config("bos_search.settings")->get("presets.$preset");
    if (empty($config)) {
      $form += [
        '#errors' => true,
        "problem" => [
          "message" => [
            "#markup" => "<h2 class='warning'>Configuration Error:</h2><div>The Preset for this form is not correctly setup.<br>Please set up a configuration at /admin/config/system/boston/aisearch</div>"
          ],
        ],
      ];
      return $form;
    }

    $form += [
      'AiSearchForm' => [
        '#tree' => FALSE,

        'content' => [
          '#type' => 'container',
          '#attributes' => [
            'id' => ['edit-aisearchform'],
          ],
          'preset' => [
            '#type' => 'hidden',
            '#default_value' => $preset,
          ],
          'messages' => [
            '#type' => 'container',
            '#attributes' => ['id' => ['edit-messages']],
          ],
          'searchresults' => [
            '#type' => 'container',
            '#attributes' => ['id' => ['edit-searchresults']],
            'welcome' => [
              '#type' => 'container',
              '#attributes' => [
                "id" => "edit-welcome",
              ],
              "title" => [
                '#markup' => Markup::create($config["searchform"]['welcome']["body_title"]),
              ],
              "body" => [
                '#markup' => Markup::create($config["searchform"]['welcome']["body_text"]),
              ],
              "cards" => [
                '#type' => 'grid_of_cards',
                '#theme' => 'grid_of_cards',
                "#title" => "Example",
                "#title_attributes" => [],
                '#cards' => [
                  [
                    '#type' => 'card',
                    '#theme' => 'card',
                    '#attributes' => [
                      'class' => ['br--4', "bg--lb"],
                    ],
                    '#content' => $config["searchform"]['welcome']["cards"]["card_1"] ?: "",
                  ],
                  [
                    '#type' => 'card',
                    '#theme' => 'card',
                    '#attributes' => [
                      'class' => ['br--4', "bg--lb"],
                    ],
                    '#content' => $config["searchform"]['welcome']["cards"]["card_2"] ?: "",
                  ],
                  [
                    '#type' => 'card',
                    '#theme' => 'card',
                    '#attributes' => [
                      'class' => ['br--4', "bg--lb"],
                    ],
                    '#content' => $config["searchform"]['welcome']["cards"]["card_3"] ?: "",
                  ],
                ],
              ],
            ],
          ],
          'session_id' => [
            '#type' => 'hidden',
            '#prefix' => "<div id='edit-session_id'>",
            '#suffix' => "</div>",
            '#default_value' => $form_state->getValue("session_id") ?: "",
          ],
        ],
        'actions' => [
          '#type' => 'button',
          '#value' => 'Search',
          "#attributes" => [
            "class" => ["hidden"],
          ],
          '#ajax' => [
            'callback' => '::ajaxCallbackSearch',
            'progress' => [
              'type' => 'none',
            ],
          ],
        ],
        'searchbar' => [
          '#theme' => 'search_bar',
          '#default_value' => "",
          '#audio_search_input' => $config["searchform"]['searchbar']["audio_search_input"] ?? FALSE,
          '#attributes' => [
            "placeholder" => $config["searchform"]['searchbar']["search_text"] ?? "",
          ],
          "#description" => $config["searchform"]['searchbar']["search_note"] ?? "",
          "#description_display" => "after",
        ],
      ],
    ];

    if (!$config["searchform"]["welcome"]["cards"]["enabled"]) {
      unset($form["AiSearchForm"]['content']["searchresults"]["welcome"]["cards"]);
    }

    if ($config["results"]["feedback"] ?: 0) {
      $form["#attached"]["library"][] = "bos_search/snippet.search_feedback";
    }

    return $form;

  }

  /**
   * Ajax callback to run the desired test against the selected AI Model.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   * @throws \Exception
   */
  public function ajaxCallbackSearch(array $form, FormStateInterface $form_state): AjaxResponse {
    if ($form_state->getErrors()) {
      return new AjaxResponse();
    }
    $config = \Drupal::config("bos_search.settings")->get("presets");
    $form_values = $form_state->getUserInput();
    // If TRUE = don't actually send to AI Model.
    $fake = FALSE;

    try {

      // Find the plugin being used (from the preset).
      $preset_name = $form_state->getBuildInfo()["args"][0] ?: $config[$form_values["preset"]];
      $preset = $config[$preset_name] ?: FALSE;
      if (!$preset) {
        throw new \Exception("Cannot find the preset {$preset_name}.}");
      }
      if (empty($preset['plugin'])) {
        throw new \Exception("The preset {$preset['plugin']} is not defined.");
      }
      $plugin_id = $preset["plugin"];

      // Create the search request object.
      $request = new AiSearchRequest($form["#form_id"], $form_values["searchbar"], $preset['results']["result_count"] ?? 0);
      $request->set("preset", $preset);

      if ($preset["searchform"]["searchbar"]["allow_conversation"]
        && !empty($form_values["session_id"])) {
        // Set the conversationid. This causes any history for the conversation
        // to be reloaded into the $request object.
        $request->set("session_id", $form_values["session_id"]);
      }

      // Instantiate the plugin, and call the search using the search object.
      /** @var \Drupal\bos_search\AiSearchInterface $plugin */
      $plugin = \Drupal::service("plugin.manager.aisearch")
        ->createInstance($plugin_id);

      $response = $plugin->search($request, $fake);

    }
    catch (\Exception $e) {
      $output = new AjaxResponse();
      $output->addCommand(new AppendCommand('#search-conversation-wrapper', [
        "#markup" => "
<div class='search-response-wrapper'>
<div class='search-response'>
<div class='search-response-wrapper-text'>
  There was an error with this query, please try again.
</div>
<div class='search-response-wrapper-text hidden'>
  {$e->getMessage()}
</div>
</div>
</div>
"
      ]));
      $output->addCommand(new ReplaceCommand('#edit-session_id', [
        'session_id' => [
          '#type' => 'hidden',
          '#attributes' => [
            "data-drupal-selector" => "edit-conversation-id",
            "name" => "session_id",
          ],
          '#prefix' => "<div id='edit-session_id'>",
          '#suffix' => "</div>",
          '#value' => $request->get("session_id") ?: "",
        ],
      ]));
      $output->addCommand(new SettingsCommand(["has_results" => TRUE], TRUE));

      return $output;
    }

    // Save this search so we can continue the conversation later.
    if ($preset["searchform"]["searchbar"]["allow_conversation"] && $request->get("session_id") != $response->getAll()["session_id"]) {
      // Either the session_id was not yet created, or else the session
      // for the original conversation has timed-out.
      // Load the session_id into the request.
      $request->set("session_id", $response->getAll()["session_id"]);
    }
    $request->addHistory($response);
    $request->save();

    // This will render the output form using the input array.
    $rendered_result = [
      "#type" => "inline_template",
      "#template" => $response->build(),
    ];
    $output = new AjaxResponse();
    $output->addCommand(new AppendCommand('#search-conversation-wrapper', $rendered_result));
    $output->addCommand(new ReplaceCommand('#edit-session_id', [
      'session_id' => [
        '#type' => 'hidden',
        '#attributes' => [
          "data-drupal-selector" => "edit-conversation-id",
          "name" => "session_id",
        ],
        '#prefix' => "<div id='edit-session_id'>",
        '#suffix' => "</div>",
        '#value' => $request->get("session_id") ?: "",
      ],
    ]));
    $output->addCommand(new SettingsCommand(["has_results" => TRUE], TRUE));

    return $output;

  }

  /**
   * @inheritDoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Not required for this form.
  }

}
