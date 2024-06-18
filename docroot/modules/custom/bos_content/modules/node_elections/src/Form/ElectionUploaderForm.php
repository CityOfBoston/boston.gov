<?php

namespace Drupal\node_elections\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\node_elections\Controller\ElectionFileUploader;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class ElectionUploaderForm.
 *
 * @package Drupal\node_elections\Form
 */
class ElectionUploaderForm extends FormBase {

  /**
   * @var \Drupal\node_elections\Form\ElectionUploaderForm
   *   Controller class used to manage the actual uplad process.
   */
  protected ElectionFileUploader $importer;

  /**
   * Implements getFormId().
   */
  public function getFormId() {
    return 'node_elections_uploader';
  }

  /**
   * Implements buildForm().
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('node_elections.settings');

    // Manage the upload folder.
    if (!$directory = $config->get('upload_directory')) {
      $directory = 'public://election_results';
      $config = \Drupal::service('config.factory')->getEditable("node_elections.settings");
      $config->set('upload_directory', $directory)->save();
    }

    if (!file_exists($directory)) {
      mkdir($directory);
    }
    $directory_is_writable = file_exists($directory) && is_writable($directory);
    if (!$directory_is_writable) {
      $msg = Markup::create("The directory {$directory} does not exist (or is not writable). <i>Error 9100</i>");
      $this->messenger()->addError($msg);
    }

    // Create the form.
    $form = [
      '#theme' => 'system_config_form',
      '#attached' => [
        'library' => 'node_elections/election_admin',
      ],
      'node_elections' => [
        '#type' => 'fieldset',
        '#title' => 'Unoffical Elections Results',
        'history_wrapper' => [
          '#type' => 'details',
          '#title' => $this->t('Last 20 Uploads'),  // DIG-4111 increase history
          '#weight' => 1,
          'history' => [
            '#markup' => $this->parseHistory($config),
            '#prefix' => '<div id="edit-history">',
            '#suffix' => '</div>',
          ],
          'clear_history' => [
            '#type' => 'button',
            "#value" => "Clear History",
            '#tooltip' => 'foo',
            '#attributes' => [
              'class' => ['button', 'button--primary'],
              'title' => "This will delete the history items shown on this form.\nNo actual data is altered."
            ],
            '#access' => in_array("administrator", $this->currentUser()->getRoles()),
            '#ajax' => [
              'callback' => '::clearHistory',
              'event' => 'click',
              'wrapper' => 'edit-history',
              'disable-refocus' => FALSE,
              'progress' => [
                'type' => 'throbber',
                'message' => "Please wait: Clearing election history.",
              ]
            ],

          ],
          'delete_history' => [
            '#type' => 'button',
            "#value" => "Delete All Data",
            '#attributes' => [
              'class' => ['button', 'button--danger', 'button-boxed-content-t'],
              "title" => "CARE!\nThis will delete all previously uploaded elections from the website:\n - All data will be removed and this cannot be undone,\n - Any and all elections data will need to be re-imported."
            ],
            '#access' => in_array("administrator", $this->currentUser()->getRoles()),
            '#ajax' => [
              'callback' => '::deleteHistory',
              'event' => 'click',
              'wrapper' => 'edit-history',
              'progress' => [
                'type' => 'throbber',
                'message' => "Please wait: Deleting old elections..",
              ]
            ],

          ],
        ],

        'config_wrapper' => [
          '#type' => 'container',
          'selector' => [
            '#type' => 'fieldset',
            '#title' => $this->t('Upload File'),
            '#attributes' => [
              'class' => ['flex']
            ],
            '#weight' => 2,
            'election_type' => [
              '#type' => 'select',
              '#title' => $this->t('Election Type:'),
              '#required' => TRUE,
              '#default_value' => '',
              '#options' => $this->electionTypeOptions(),
            ],
            'result_type' => [
              '#type' => 'select',
              '#title' => $this->t('Results Type:'),
              '#default_value' => '0',
              '#options' => [
                '0' => 'Unoffical',
                '1' => 'Official',
              ],
            ],
            'upload' => [
              '#type' => 'managed_file',
              '#required' => TRUE,
              '#upload_location' => $directory,
              '#upload_validators' => [
                'file_validate_extensions' => ['xml']
              ],
              '#size' => 20,
              '#title' => $this->t('Election Results File:'),
              '#description' => $this->t('S'.'elect a file which has been exported from the elections system.<br/>Allowed file type is <i>.xml</i>'),
            ],
          ],
          'actions' => [
            '#type' => 'actions',
            'submit' => [
              '#type' => 'submit',
              '#value' => $this->t('Process File'),
              '#button_type' => 'primary',
              '#disabled' => !$directory_is_writable,
            ],
            '#weight' => 3,
          ],
        ],
        'corrections_wrapper' => [
          '#type' => 'fieldset',
          '#title' => $this->t('Candidate Name Rewriting'),
          '#attributes' => [
            'class' => ['flex']
          ],
          '#weight' => 4,
          'corrected_fullname' => [
            '#type' => 'textarea',
            '#title' => $this->t('Candidate full name to rewite:'),
            '#description' => "These replacements will be performed during import, and also when the page is rendered.<br>Will replace the whole Candidates name on an exact match.<br><b>FORMAT:</b> colon separated, one replacement pair per line - Find:replace",
            '#default_value' => $this::stringifyReplacements($config->get('corrected_fullname') ?? ""),
          ],
          'corrected_parts' => [
            '#type' => 'textarea',
            '#title' => $this->t('Candidate name-parts to rewrite:'),
            '#description' => "These replacements will only be performed during import.<br>Will replace 'name-parts' in the Candidates name.<br><b>FORMAT:</b> colon separated, one replacement pair per line - Find:replace",
            '#default_value' => $this::stringifyReplacements($config->get('corrected_parts') ?? ""),
          ],
        ],
        'page_actions' => [
          '#type' => 'actions',
          '#weight' => 5,
          'submit' => [
            '#type' => 'submit',
            '#value' => $this->t('Save Configuration'),
            '#button_type' => 'primary',
          ],
          'cancel' => [
          '#type' => 'link',
          '#title' => $this->t('Return to Homepage'),
          '#url' => Url::fromUserInput("/"),
          '#attributes' => ['class' => ['button']],
        ],
        ],
      ]
    ];

    return $form;
  }

  /**
   * Implements submitForm().
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    // When the file is uploaded, this validateForm function is called.
    // We cannot check the file because it's not uploaded yet, so exit.
    $action = $form_state->getTriggeringElement()["#value"]->getUntranslatedString();
    if ($action == "Save Configuration") {
      // When saving the form, nothing to validate, just exit.
      $form_state->clearErrors();
      return;
    }

    if (in_array($action, [
      "Upload",
      "Remove",
      "Clear History",
      "Delete All Data",
    ])) {
      return;
    }

    // This will locate and validate the uploaded file.
    // The file contents are saved in the $this->uploader->results object
    $this->importer = new ElectionFileUploader();

    $file_id = $form_state->getValue('upload', FALSE);

    if ($file_id && count($file_id) == 1) {
      if ($file = File::load($file_id[0])) {
        $file_path = $file->getFileUri();
        if ($file_path && file_exists($file_path)) {
          $this->importer->readElectionResults($form_state, $file_path);
        }
        else {
          $form_state->setErrorByName('upload', Markup::create("The file does not exist. <i>Error 9007</i>."));
        }
      }
      else {
        $form_state->setErrorByName('upload', Markup::create("The file did not upload properly. Try again. <i>Error 9008.</i>"));
      }
    }
    else {
      $form_state->setErrorByName('upload', Markup::create("Please provide a file to upload. <i>Error 9009</i>."));
    }

    if ($form_state->isSubmitted()
      && $form_state->getTriggeringElement()["#name"] == "op") {
      // This validation occurred when the process file button was clicked.
      // (i.e. the main submit action button)
      if (count($form_state->getErrors()) > 0) {
        if ($results = $this->importer->getResults()) {
          $election_date = $this->importer->setElectionDate($results->election['create']);
          $current_election = $results->election["name"];
          if ($election_term = \Drupal::entityTypeManager()
            ->getStorage("taxonomy_term")
            ->loadByProperties([
              "vid"=> "elections",
              "field_election_date" => $election_date
            ])) {
            $election_term = reset($election_term);
            $current_election = $election_term->id();
          }
          if (!empty($form_state->getErrors()["upload"])) {
            $comment = (string) $form_state->getErrors()["upload"];
          }
          elseif (!empty($form_state->getErrors()["election_type"])) {
            $comment = (string) $form_state->getErrors()["election_type"];
          }
          elseif (!empty($form_state->getErrors()["result_type"])) {
            $comment = (string) $form_state->getErrors()["result_type"];
          }
          else {
            $comment = "Unknown";
          }
          $this->importer->writeHistory([
            "generate_date" => strtotime($results->election['create']),
            "upload_date" => strtotime("now"),
            "file" => $file_id[0],
            "result" => "Failed",
            "election" => $current_election,
            "revision" => "",
            "result_comment" => $comment,
          ]);
        }
      }
    }
  }

  /**
   * Implements submitForm().
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

     // Just verify that we have an authenticated user in case the form is
     // somehow compromised.  Routing should handle this ... this is just a
     // double check!
     $account = $this->currentUser();
     if (empty($account)
       || $account->isAnonymous()
       || !$account->hasPermission("edit any election_report content")
     ) {
       throw new NotFoundHttpException();
     }

    $action = $form_state->getTriggeringElement()["#value"]->getUntranslatedString();

    if ($action == "Save Configuration") {
      $config = \Drupal::configFactory()->getEditable("node_elections.settings");
      $values = $form_state->getValues();
      $config
        ->set("corrected_fullname", self::jsonifyReplacements($values["corrected_fullname"]))
        ->set("corrected_parts", self::jsonifyReplacements($values["corrected_parts"]))
        ->save();
      return;
    }

    else {
      // Check that the file has been read and the contents have passed an
      // initial validation in validateForm().
      if (!$this->importer->hasResults()) {
        $msg = Markup::create("The File processing has failed.<br><b>Contact Digital Team</b><br><i>Error 9101</i>.");
        $this->messenger()->addError($msg);
        return FALSE;
      }

      // This will process the uploaded file into the database.
      return $this->importer->import($form_state);

    }

  }

  /* ADDITIONAL BUTTONS */
  public function deleteHistory(array &$form, FormStateInterface $form_state) {
    $storage = \Drupal::entityTypeManager()
      ->getStorage("paragraph");
    $paras = [
      "election_candidate_results",
      "election_contest_results",
      "election_area_results",
      "election_card",
    ];
    foreach ($paras as $para_type) {
      foreach ($storage->loadByProperties(["type" => $para_type]) as $para) {
        $para->delete();
      }
    }

    $storage = \Drupal::entityTypeManager()->getStorage("node");
    $config = $this->config('node_elections.settings');
    if (!$directory = $config->get('upload_directory')) {
      $directory = 'public://election_results';
    }
    foreach ($storage->loadByProperties(["type" => "election_report"]) as $node) {
      $file = $node->field_source_file->getValue()[0]["uri"];
      if ($file && file_exists($directory . '/' . basename($file))) {
        if ($file_entity = \Drupal::entityTypeManager()
          ->getStorage("file")
          ->loadByProperties(["filename" => basename($file)])) {
          foreach ($file_entity as $fe) {
            $fe->delete();
          }
        }
        unlink( $directory . '/' . basename($file));
      }
      $node->delete();
    }

    $storage = \Drupal::entityTypeManager()
      ->getStorage("taxonomy_term");
    $terms = [
      "election_candidates",
      "election_contests",
      "election_areas",
      "elections",
      "elector_groups",
    ];
    foreach ($terms as $vocab_name) {
      foreach ($storage->loadByProperties(["vid" => $vocab_name]) as $term) {
        $term->delete();
      }
    }

    $config = \Drupal::service('config.factory')
      ->getEditable("node_elections.settings");
    $config->set("history", []);
    $config->set("last-run", "")
      ->save();

    if (!isset($this->importer)) {
      $this->importer = new ElectionFileUploader();
    }
    $form_state->clearErrors();
    \Drupal::messenger()->deleteByType("error");
    \Drupal::messenger()->addStatus("All elections have been deleted from the website.");

    $this->importer->writeHistory([
      "generate_date" => NULL,
      "upload_date" => strtotime("now"),
      "file" => 0,
      "result" => "Success",
      "election" => ">>> All Data Reset",
      "revision" => NULL,
      "result_comment" => "All Data manually deleted by " . $this->currentUser()->getDisplayName(),
    ]);

    $history = $this->parseHistory($config);
    $form['node_elections']['history_wrapper']['history']['#markup'] = $history;
    return $form['node_elections']['history_wrapper']['history'];
  }

  public function clearHistory(array &$form, FormStateInterface $form_state) {

    $config = \Drupal::service('config.factory')
      ->getEditable("node_elections.settings");
    $config->set("history", []);
    $config->set("last-run", "")
      ->save();

    if (!isset($this->importer)) {
      $this->importer = new ElectionFileUploader();
    }
    $form_state->clearErrors();
    \Drupal::messenger()->deleteByType("error");
    \Drupal::messenger()->addStatus("History was cleared");

    $this->importer->writeHistory([
      "generate_date" => NULL,
      "upload_date" => strtotime("now"),
      "file" => 0,
      "result" => "Success",
      "election" => ">>> History Reset",
      "revision" => NULL,
      "result_comment" => "History manually reset by " . $this->currentUser()->getDisplayName(),
    ]);

    $history = $this->parseHistory($config);
    $form['node_elections']['history_wrapper']['history']['#markup'] = $history;
    return $form['node_elections']['history_wrapper']['history'];
  }

  /* HELPERS */

  /**
   * Reads this upload history from the node_election config settings.
   *
   * @param \Drupal\Core\Config\ImmutableConfig|\Drupal\Core\Config\Config $config
   *
   * @return string a table with a list of historic uploads.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function parseHistory($config) {
    $history = "<tr><td colspan='5'>No uploads yet.</td></tr>";
    if (!empty($config->get("history"))) {
      $history = '<tr><th>' . $this->t("Election") . '</th><th>' . $this->t("Report Timestamp") . '</th><th>' . $this->t("File Loaded") . '</th><th>' . $this->t("Upload Timestamp"). '</th><th>' . $this->t("Result") . '</th></tr>';
      foreach ($config->get("history") ?: [] as $hist) {
        $rdate = "";
        if ($hist['generate_date']) {
          $rdate = date("d M Y <b>h:i A</b>", $hist['generate_date']);
        }
        $idate = date("d M Y <b>h:i A</b>", $hist['upload_date']);
        if (isset($hist["election"])) {
          if (is_numeric($hist["election"])) {
            if ($elec_term = Term::load($hist["election"])) {
              $elec_term_name = $elec_term->getName();
            }
            else {
              $elec_term_name = "<b>!Deleted Election.</b>";
            }
          }
          else {
            $elec_term_name = $hist["election"];
          }
        }

        if (isset($hist["file"])
          && $hist["file"] !== 0
          && $file = File::load($hist["file"])) {
          $file = "<a href='" . \Drupal::service('file_url_generator')
              ->generate($file->uri->getString())
              ->getUri() . "' target='_blank'>" . $file->getFilename() . "</a>";
        }
        elseif ($hist["file"] === 0) {
          $file = "";
        }
        else {
          $file = "Missing file. ({$hist["file"]})";
        }

        $revision = "";
        if (isset($hist["revision"])) {
          if ($node = \Drupal::entityTypeManager()
            ->getStorage("node")
            ->loadRevision($hist["revision"])) {
            $node_id = $node->id();
            $revision_link = "/node/{$node_id}/revisions/{$hist["revision"]}/view";
            $revision = " (<a href='{$revision_link}' target='_blank'>{$hist["revision"]}</a>)";
          }
          else {
            $revision = "";
          }
        }
        $title = !empty($hist["result_comment"]) ? strip_tags($hist["result_comment"]) : "";
        $class = "result " . strtolower($hist["result"]);
        $history .= "<tr><td>{$elec_term_name}{$revision}</td><td>{$rdate}</td><td>{$file}</td><td>{$idate}</td><td class='{$class}' title='{$title}'>{$hist["result"]}</td></tr>";
      }
    }
    return "<p><i>Most recent upload attempt is at the bottom of the list</i></p><table>{$history}</table>";
  }

  /**
   * Gathers the currently defined "allowed values" filter for election types
   * from the taxonomy field "field_election_type", (in taxonomy "elections")
   * and returns as an options array for the Drupal forms API to handle.
   *
   * @return string[] an array containing a list of options for a select field.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function electionTypeOptions() {
    // Fetch the option list for file types from the list provided in the
    // taxonomy definition.
    $field = \Drupal::entityTypeManager()
      ->getStorage('field_storage_config')
      ->loadByProperties([
        "entity_type" => "taxonomy_term",
        "field_name" => "field_election_type"
      ]);
    $options = $field["taxonomy_term.field_election_type"]
      ->getSetting("allowed_values");

    return ['' => '-- Select --'] + $options;
  }

  public static function jsonifyReplacements(string $replacements): string|bool {
    $replacement_array = [];
    foreach(explode("\r\n", $replacements) as $replacement) {
      if ($replacement) {
        $parts = explode(":", $replacement);
        if (!empty($parts[0])) {
          // Encode the prompt text to allow punctuation etc.
          $replacement_array[base64_encode(trim($parts[0]))] = base64_encode(trim($parts[1]));
        }
      }
    }
    return json_encode($replacement_array);
  }

  public static function stringifyReplacements(string $json_replacements): string {
    $base = [];
    $replacements = json_decode($json_replacements);
    foreach ($replacements as $replace => $replacement) {
      $base[] = base64_decode($replace) . ":" . base64_decode($replacement);
    }
    return implode("\n", $base);
  }
}
