<?php

namespace Drupal\bos_google_cloud\Form;

use Drupal\bos_google_cloud\GcGenerationPrompt;
use Drupal\bos_google_cloud\Services\GcCacheAI;
use Drupal\bos_google_cloud\Services\GcTextSummarizer;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;

/*
  class PromptTesterForm
  Creates the Administration/Configuration form for bos_google_cloud

  david 04 2024
  @file docroot/modules/custom/bos_components/modules/bos_google_cloud/src/Form/src/Form/PromptTesterForm.php
*/

class PromptTesterForm extends FormBase {

  /**
   * This form allows a user to test the genAI functions for Summarizing
   * Rewriting and Translating text.
   * The user can select from prompts already defined in the system, and can
   * provide their own text to test operations upon.
   */

  const TYPEMAP = [
    "select" => "",
    "summarizer" => "Summarize",
    "rewriter" => "Rewrite",
    "translation" => "Translate",
  ];

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'bos_google_cloud_PromptTesterForm';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    if ($values = $form_state->getValues()) {
      $type = $values["type"];
      $prompt = $values["prompt"];
    }
    else {
      $type = "select";
      $prompt = "default";
    }
    $type_label = self::TYPEMAP[$type];

    $form = [
      'PromptTesterForm' => [
        '#tree' => FALSE,
        '#type' => 'fieldset',
        'type' => [
          '#type' => 'select',
          '#title' => $this->t('Select AI Action'),
          '#options' => [
            'select' => 'Select Action',
            'rewriter' => 'Rewrite',
            'summarizer' => 'Summarize',
            'translation' => 'Translate',
          ],
          '#default_value' => $type??"select",
          '#ajax' => [
            'event' => 'change',
            'callback' => '::ajaxCallbackPrompts',
            'wrapper' => 'edit-testdata',
          ]
        ],
        'testdata' => [
          '#type' => 'container',
          '#attributes' => [
            'id' => ['edit-testdata'],
            'style' => ['visibility: hidden'],
          ],
          'prompt' => [
            '#type' => 'select',
            '#title' => $this->t("The $type_label prompt to use"),
            '#default_value' => $prompt??"default",
            '#options' => $type=="select" ? [] : $this->fetchPrompts($type),
          ],
          'text' => [
            '#type' => 'textarea',
            '#title' => $this->t("Text to $type_label"),
            '#default_value' => "Paste or input text here",
          ],
          'submit' => [
            '#type' => 'button',
            '#value' => ucwords($this->t($type_label)),
            '#ajax' => [
              'callback' => '::ajaxCallbackProcess',
              'wrapper' => 'edit-testresults',
            ],

          ],
          'testresults' => [
            '#type' => 'container',
            '#attributes' => ['id' => ['edit-testresults']],
          ],
        ],
      ],
    ];
    return $form;
  }

  /**
   * Ajax callback to make the input area visible.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  public function ajaxCallbackPrompts(array $form, FormStateInterface $form_state): array {

    $values = $form_state->getValues();
    $type = $values["type"];
    $type_label = self::TYPEMAP[$type];

    if ($type != "select") {
      // Make test form visible.
      unset($form['PromptTesterForm']['testdata']['#attributes']['style']);
    }

    return $form['PromptTesterForm']['testdata'];

  }

  /**
   * Ajax callback to run the desired test against google_cloud.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   * @throws \Exception
   */
  public function ajaxCallbackProcess(array $form, FormStateInterface $form_state): array {

    $values = $form_state->getValues();
    $type = $values["type"];
    $type_label = self::TYPEMAP[$type];

    switch($type) {
      case "rewriter":
        /**
         * @var \Drupal\bos_google_cloud\Services\GcTextRewriter $processor
         */
        $processor = \Drupal::service("bos_google_cloud.GcTextRewriter");
        $processor->setExpiry(GcCacheAI::CACHE_EXPIRY_NO_CACHE);
        $result = $processor->execute(["text"=>$values["text"], "prompt"=>$values["prompt"]]);
        break;

      case "summarizer":
        /**
         * @var GcTextSummarizer $processor
         */
        $processor = \Drupal::service("bos_google_cloud.GcTextSummarizer");
        $processor->setExpiry(GcCacheAI::CACHE_EXPIRY_NO_CACHE);
        $result = $processor->execute(["text" => $values["text"], "prompt"=> $values["prompt"]]);
        break;

      case "translation":
        /**
         * @var \Drupal\bos_google_cloud\Services\GcTranslation $processor
         */
        $processor = \Drupal::service("bos_google_cloud.GcTranslate");
        $processor->setExpiry(GcCacheAI::CACHE_EXPIRY_NO_CACHE);
        $result = $processor->execute(["text"=>$values["text"], "lang"=>$values["prompt"], "prompt"=>"default"]);
        break;
    }

    $form["PromptTesterForm"]["testdata"] = $this->ajaxCallbackPrompts($form, $form_state);

    $result = $result ?? "No result from google_cloud (Vertex)";
    $response = $processor->response();
    $request = $processor->request();
    $fullprompt = $request["body"]["contents"][0]["parts"];
    $prompt = array_pop($fullprompt)["text"];
    $fullprompt = implode("<br> - ", array_column($fullprompt, "text"));

    $ai_engine = $response["ai_engine"] ?? "gemini-pro";

    $requestSafety = [];
    foreach($request["body"]["safetySettings"] as $safe) {
      $requestSafety[] = "{$safe["category"]} threshold: {$safe["threshold"]}";
    }
    $requestSafety = implode("<br>", $requestSafety);

    $responseSafety = [];
    foreach($response[$ai_engine]["ratings"] as $safety) {
      if (!empty($safety["category"])) {
        $responseSafety[] = "{$safety["category"]} probability: {$safety["probabilityScore"]} ({$safety["probability"]})";
        $responseSafety[] = "{$safety["category"]} severity: {$safety["severityScore"]} ({$safety["severity"]})";
      }
      else {
        $responseSafety[] = "OVERALL probability: {$safety["probabilityScore"]} ({$safety["probability"]})";
        $responseSafety[] = "OVERALL severity: {$safety["severityScore"]} ({$safety["severity"]})";
      }
    }
    $responseSafety = implode("<br>", $responseSafety);

    $responseMetadata = [];
    if ($response[$ai_engine]["usageMetadata"]) {
      $responseMetadata[] = "Prompt Token Count: " . $response[$ai_engine]["usageMetadata"]["promptTokenCount"] ?? "N/A";
      $responseMetadata[] = "Candidates Token Count: " . $response[$ai_engine]["usageMetadata"]["candidatesTokenCount"] ?? "N/A";
      $responseMetadata[] = "Total Token Count: " . $response[$ai_engine]["usageMetadata"]["totalTokenCount"] ?? "N/A";
    }
    $responseMetadata[] = "Analysis time: " . (intval(($response["elapsedTime"] ?? 0) * 10000) / 10000) . " sec";
    $responseMetadata = implode("<br>", $responseMetadata);

    $engineConfig = [];
    $engineConfig[] = "Temperature: {$request["body"]["generationConfig"]["temperature"]}";
    $engineConfig[] = "Max Tokens: {$request["body"]["generationConfig"]["maxOutputTokens"]}";
    $engineConfig[] = "TopK: {$request["body"]["generationConfig"]["topK"]}";
    $engineConfig[] = "TopP: {$request["body"]["generationConfig"]["topP"]}";
    $engineConfig = implode("<br>", $engineConfig);

    $form["PromptTesterForm"]["testdata"]["testresults"] = [
      "#type" => "container",
      '#attributes' => ['id' => ['edit-testresults']],
      "output" => [
        "#type" => "fieldset",
        "#title" => Markup::create("$type_label results"),
        "outputtext" => [
          "#markup" => Markup::create($result)
        ],
        "moreinfo" => [
          "#type" => "details",
          "#title" => "More Information",
          "info" => [
            "#markup" => "<table>
  <tr><td><b>Action</b></td><td>$type_label</td></tr>
  <tr><td><b>Full Prompt</b></td><td>$fullprompt</td></tr>
  <tr><td><b>AI Engine</b></td><td>$ai_engine (<i>{$request["endpoint"]}</i>)</td></tr>
  <tr><td><b>AI Engine Config</b></td><td>$engineConfig</td></tr>
  <tr><td><b>AI Post-Performance Data</b></td><td>" . ($responseMetadata??"N/a") . "</td></tr>
  <tr><td><b>Safety Parameters</b></td><td>" . ($requestSafety??"N/a") . "</td></tr>
  <tr><td><b>Reported Safety</b></td><td>" . ($responseSafety??"N/a") . "</td></tr>
</table>",
          ]
        ]
      ]
    ];

    return $form["PromptTesterForm"]["testdata"]["testresults"];
  }

  /**
   * Fetch the prompts for the indicated AI action.
   * @param $type
   *
   * @return array
   */
  private function fetchPrompts($type) {
    $prompts = GcGenerationPrompt::getPrompts($type);
    return $prompts;
  }

  /**
   * @inheritDoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Not required for this test form.
  }

}
