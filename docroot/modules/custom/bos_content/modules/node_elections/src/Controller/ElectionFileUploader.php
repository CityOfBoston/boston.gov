<?php

namespace Drupal\node_elections\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\node\Entity\Node;
use Drupal\node_elections\ElectionUtilities;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Term;

class ElectionFileUploader extends ControllerBase {

  /**
   * @var ElectionResults The parsed contents of the uploaded file.
   */
  private ElectionResults $results;

  /**
   * @var string Stores the actual path (uri) to the uploaded file.
   */
  public $file_path;

  /**
   * Detect if the $this->results variable has been set yet
   *   (i.e. has the file been validated yet).
   *
   * @return bool
   */
  public function hasResults(): bool {
    return !empty($this->results);
  }

  /**
   * If results exist then return them, else return FALSE.
   *
   * @return \Drupal\node_elections\Controller\ElectionResults|false
   */
  public function getResults() {
    return $this->hasResults() ? $this->results : FALSE;
  }

  /**
   * Controls the reading and validating of the uploaded file.
   *
   * @param FormStateInterface $form_state The submitted form.
   * @param string $file_path The path to the uploaded file in a public folder.
   *
   * @return boolean Import success flag.
   */
  public function readElectionResults(FormStateInterface &$form_state, string $file_path) {

    $submitted = $form_state->getValues();

    // At the moment there is only one xml file and therefore only one reader.
    // Later we may find that different elections have different file formats.
    //  Since the ultimate entity structure within Drupal is the same, it is
    //  likely that the best solution will be to try to massage the incoming
    //  file data into the expected format in the $this->results object.
    switch ($submitted['election_type']) {
      case "state primary":
      case "state general":
      case "municipal primary":
      case "municipal general":
      case "presidential primary":
      case "special primary":
      case "special":
      case "other":
        if (!$this->readDefaultElectionFormat($form_state, $file_path)) {
          return FALSE;
        }
    }

    // No matter the file format or contents format we should now have the data
    // loaded into a known ElectionResults class.

    // Do a cursory validation the results class.

    // Check the basic fields exist.
    if (empty($this->results->choices)
      || empty($this->results->contests)
      || empty($this->results->conteststats)
      || empty($this->results->electorgroups)
      || empty($this->results->pollprogress)
      || empty($this->results->results)
      || empty($this->results->election)
      || empty($this->results->settings)
    ) {
      // Before throwing an error, we need to verify that this is not an
      // "initiating" file, one sent with no results just election metadata.
      // An initiating file has no results except for pollprogress which has
      // a reported value of "0" for all areaids.
      if (!empty($this->results->pollprogress)
        && empty($this->results->conteststats)
        && $this->results->pollprogress[0]["reported"] === "0") {
          // Make set of default results.
          $this->createDefaultResults();
      }
      else {
        $msg = Markup::create("The selected file does not have all the required fields.<br><b>The file cannot be processed.</b><br><i>Error 9004</i>.");
        $form_state->setErrorByName('upload', $msg);
        return FALSE;
      }

    }

    // Check logic.
    if (empty($this->results->conteststats)) {
      $msg = Markup::create("There are no contest/race results in this file.");
      $form_state->setErrorByName("upload", $msg);
      return FALSE;
    }
    if (count($this->results->contests) != count($this->results->conteststats)) {
      // Not fatal.
      $msg = Markup::create("There are fewer contest results than contests in the file.<br><b><i>Please check the webpage after processing finishes.</i></b>");
      $this->messenger()->addWarning($msg);
    }

    if (empty($this->results->results)) {
      $msg = Markup::create("There are no choice/candidate results in this file.");
      $form_state->setErrorByName("upload", $msg);
      return FALSE;
    }
    if (count($this->results->choices) != count($this->results->results)) {
      // Not Fatal.
      $msg = Markup::create("There are fewer candidate results than candidates in the file.<br><b><i>Please check the webpage after processing finishes.</i></b>");
      $this->messenger()->addWarning($msg);
    }

    // Check the Unofficial/offical flag in the file matches the sected value
    //  on the form.
    if (intval($this->results->settings[0]['officialresults']) != intval($submitted['result_type'])) {
      $type = intval($this->results->settings[0]['officialresults']) ? "OFFICIAL" : "UNOFFICIAL";
      $msg = Markup::create("The selected file contains ${type} results.<br><b>The file will not be processed.</b><br><i>Error 9005</i>.");
      $form_state->setErrorByName('result_type', $msg);
      return FALSE;
    }

    // Check to see that the election type input on the form is mentioned in the
    // title of the report.
    $election_date = $this->setElectionDate($this->results->election['create']);
    $current_election = \Drupal::entityTypeManager()
      ->getStorage("taxonomy_term")
      ->loadByProperties([
        "vid"=> "elections",
        "field_election_date" => $election_date
      ]);
    if (!empty($current_election)) {
      // If an election already exists for this date then check it is of
      // the same type.
      $current_election = reset($current_election);
      if ($current_election->field_election_type->value !== $submitted["election_type"]) {
        $msg = Markup::create("You have selected that this file contains a <b>{$submitted['election_type']}</b> type election.<br>There is already a <b>{$current_election->field_election_type->value}</b> election registered for the date in this file ({$election_date}).<br><b>There can only be one election on any given day.</b><br>The file will not be processed.<br><i>Error 9006</i>.");
        $form_state->setErrorByName('election_type', $msg);
        return FALSE;
      }

      //  Check if this file has already been imported.
      if ($election_report_para = \Drupal::entityTypeManager()
        ->getStorage("node")
        ->loadByProperties([
          "type" => "election_report",
          "field_election" => $current_election->id(),
        ])) {
        $election_report_para = reset($election_report_para);
        if (strtotime($this->results->election["progcreate"]) == strtotime($election_report_para->field_updated_date->value)) {
          $msg = Markup::create("An election with this timestamp <b>({$this->results->election['progcreate']})</b> has already been processed.<br>This file will not be processed.<br><i> Error 9026</i>.");
          $form_state->setErrorByName('upload', $msg);
          return FALSE;
        }
        elseif (strtotime($this->results->election["progcreate"]) < strtotime($election_report_para->field_updated_date->value)) {
          $msg = Markup::create("<b>This file is out of sequence.</b><br>This file has a timestamp <b>{$this->results->election['progcreate']}</b> but the last file uploaded for this election had a timestamp <b>{$election_report_para->field_updated_date->value}</b>.<br>This file will not be processed.<br><i>Error 9027</i>.");
          $form_state->setErrorByName('upload', $msg);
          return FALSE;

        }
      }
    }

  }

  /**
   * Create a set of default results for candidate/choices and contests.
   * This is used when an initiation file is imported which has no values for
   * these arrays.
   *
   * @return void
   */
  private function createDefaultResults() {
    foreach($this->results->contests as $key => $contest) {
      $this->results->addField("conteststats", [
        "name" => $key,
        "value" => [
          "contestid" => $contest["contestid"],
          "ballots" => "0",
          "overvotes" => "0",
          "undervotes" => "0",
          "numvoters" => "0",
          "pushcontests" => "0"
        ],
      ]);
    }
    foreach($this->results->choices as $key => $candidate) {
      $this->results->addField("results", [
        "name" => $key,
        "value" => [
          "contid" => $candidate["conid"],
          "chid" => $candidate["chid"],
          "wrind" => "0",
          "prtid"=> "0",
          "vot" => "0",
        ],
      ]);
    }
  }

  /**
   * Default reader to ingest an xml file into an xml class object.
   *   In the future, there may be additional variants for different file
   *   types (e.g. json) or data structures for the contents.
   * This variant imports the standard xml file exported from the elections
   * system.
   *
   * @param FormStateInterface $form_state The submitted form.
   * @param string $file_path The path to the uploaded file in a public folder.
   *
   * @return bool Read success flag.
   */
  public function readDefaultElectionFormat(FormStateInterface &$form_state, string $file_path) {

    unset($this->xml_results);

    try {
      // Load XML into object
      if (file_exists($file_path)) {
        $this->file_path = $file_path;
        if ($import = file_get_contents($file_path)) {
          $import = str_replace(["\r\n", "\r\n "], "", $import);
          $import = new \SimpleXMLElement($import);

          if (isset($import->Worksheet)) {
            $msg = Markup::create("<b>BAD FILE</b><br/>This file is an Excel export file.  Please generate the correct file and try again.<br><i>Error 9000.</i>");
            $form_state->setErrorByName('upload', $msg);
            return FALSE;
          }

          // We convert the xml into a ElectionFormat class here so that the
          // actual import processes which work on this ElectionFormat object
          // can always be used.  If and when we encounter new file formats we
          // can clone and call a modified variation of this function
          // (readXXXElectionFormat), or, if we are lucky, just clone and call
          // a variation of the following xmltoobject function.
          $this->results = $this->xmltoobject($import);

          // Some contests generate multiple "write-in" results, we need to
          // aggregate these to a single figure here.
          $this->results->aggregateCandidateResults();

          // Need to lock in the sort order for Areas, Contests and Candidates.
          // This is important because the paragraphs will be created in the
          // order we specify here, and then will be attached and displayed on
          // nodes etc in this same order.
          $this->results->extractAreas();
          $this->results->reorder();
        }
        else {
          $msg = Markup::create("Could not read file.<br>Regenerate the file and try again,<br><i>Error 9001.</i>");
          $form_state->setErrorByName('upload', $msg);
          return FALSE;
        }
      }
      else {
        $msg =Markup::create("Error uploading, could not find this file.<br>Try again or contact Digital Team.<br><i>Error 9002.</i>");
        $form_state->setErrorByName('upload', $msg);
        return FALSE;
      }
    }
    catch (\Exception $e) {
      $msg ="<b>BAD FILE</b><br>{$e->getMessage()}.<br>File is probably not a valid xml file. Regenerate the file and try again, or <b>contact Digital Team.</b><i>Error 9003</i>.";
      $form_state->setErrorByName('upload', $msg);
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Manager for importing the file contents into the Drupal entites.
   *
   * @param FormState $form_state The submitted form.
   *
   * @return boolean Import success flag.
   */
  public function import(FormStateInterface $form_state) {

    $submitted = $form_state->getValues();

    // Create an array holding all the process data for this election.
    $election = [
      "file" => [
        "data" => $this->results,
        "election_type" => $submitted['election_type'],
        "election_date" => $this->setElectionDate($this->results->election['create']),
        "path" => $this->file_path,
        "fid" => (int) $submitted['upload'][0],
        "areas" => [],
        "is_official" => $submitted['result_type'],
        "new_election" => FALSE,
        "outcome" => "Success",
      ],
      "mapping" => [],
      "taxonomies" => [
        "elections" => [],
        "election_areas" => [],
        "election_contests" => [],
        "elector_groups" => [],
        "election_candidates" => [],
      ],
      "nodes" =>[
        "election_report" => [],
      ],
      "paragraphs" => [
        "election_area_results" => [],
        "election_contest_results" => [],
        "election_candidate_results" => [],
      ],
    ];

    // Evaluate the list of areas defined within the contests.
    $election["file"]["areas"] = $this->results->extractAreas();

    // Load any previous entites
    $this->fetchExistingElection($election, $election["file"]["election_date"]);

    // If this is a new election, then create the basic elements now.
    if ($election["file"]["new_election"]) {
      if ($this->createElection($election)) {
        $msg = Markup::create("<b>A new Election has been created for this file.</b>");
        $this->messenger()->addStatus($msg);
      }
      else {
        return FALSE;
      }
    }

    // Upsert the election results.
    $this->upsertElectionEntities($election);

    // Update the history array in the settings file.
    $comment = "Processed OK";
    if ($election["file"]["outcome"] != "Success") {
      $comment = $this->messenger()->all()["error"][0];
    }
    $this->writeHistory([
      "generate_date" => strtotime($this->results->election['create']),
      "upload_date" => strtotime("now"),
      "file" => $election["file"]["fid"],
      "result" => $election["file"]["outcome"],
      "election" => $election["taxonomies"]["elections"]->id(),
      "revision" => $election["nodes"]["election_report"]->getRevisionId(),
      "result_comment" => $comment,
    ]);

    // Finally, set the status message for the screen
    if ($election["file"]["outcome"] == "Success") {
      $msg = Markup::create("<b>Success!</b> The Election Results File has been processed and the results updated on the website.");
      $this->messenger()->addStatus($msg);
      return TRUE;
    }

    return FALSE;

  }

  /**
   * Fetch an existing election, including the creation of areas, contests and
   *   candidates etc.
   *
   * @param $election array Array containing the report contents.
   *
   * @return boolean
   */
  protected function fetchExistingElection(array &$election, $election_date) {

    $term_storage = \Drupal::entityTypeManager()
      ->getStorage("taxonomy_term");

    // First search out the election for this date.
    $current_election = $term_storage
      ->loadByProperties([
        "vid"=> "elections",
        "field_election_date" => $election_date
      ]);

    if ($current_election) {
      $election["taxonomies"]["elections"] = reset($current_election);
      $election["file"]["new_election"] = FALSE;
    }
    else {
      $election["file"]["new_election"] = TRUE;
      return FALSE;
    }

    $election_id = $election["taxonomies"]["elections"]->id();

    // Load the taxonomies linked to this Election.
    // This captures election_areas, elections_contests and elector_groups.
    $terms = $term_storage->loadByProperties([
      "field_election" => $election_id,
    ]);
    foreach($terms as $id => $term) {
      if ($term->bundle() != "election_contests") {
        $election["taxonomies"][$term->bundle()][$id] = $term;
        $election["mapping"][$term->bundle()][$term->field_original_id[0]->value] = $id;
      }
    }
    // Load election_contests from election_areas.
    foreach ($election["taxonomies"]["election_areas"] as $area_id => $area_term) {
      $cont_terms = $term_storage->loadByProperties([
        "field_area" => $area_id,
        "vid" => "election_contests",
      ]);
      foreach($cont_terms as $cont_id => $cont_term) {
        $election["taxonomies"][$cont_term->bundle()][$cont_id] = $cont_term;
        $election["mapping"][$cont_term->bundle()][$cont_term->field_original_id[0]->value] =  $cont_id;

        // Load election_candidates from election_contests.
        $cand_terms = $term_storage->loadByProperties([
          "field_contest" => $cont_id,
          "vid" => "election_candidates",
        ]);
        foreach($cand_terms as $cand_id => $cand_term) {
          $election["taxonomies"][$cand_term->bundle()][$cand_id] = $cand_term;
          $election["mapping"][$cand_term->bundle()][$cand_term->field_original_id[0]->value] =  $cand_id;
        }
      }
    }

    // load contestgroups from import.
    foreach ($election["file"]["data"]->contestgroups as $cg) {
      // create a mapping for the contestgroups too.
      $eg_term_id = $election["mapping"]["elector_groups"][$cg["egid"]];
      $election["mapping"]["contest_groups"][$cg["conid"]] = $eg_term_id;
    }

    // Load the current revision of the election report (node)
    $node = \Drupal::entityTypeManager()
      ->getStorage("node")
      ->loadByProperties([
        "field_election" => $election_id,
        "revision_default" => 1,
        "type" => "election_report",
      ]);

    if (!$node) {
      // If we cannot get the node, then there has been no sucessful import for
      // this elections term.
      // Remove the existing term, and then mark this as a new import.
      $election["taxonomies"]["elections"]->delete();
      $election["taxonomies"]["elections"] = [];
      $election["file"]["new_election"] = TRUE;
      return FALSE;
    }

    $node = reset($node);
    $election["nodes"]["election_report"] = $node;

    // Now build out the paragraphs which contain the actual results.
    $term_storage = \Drupal::entityTypeManager()->getStorage('paragraph');
    /**
     * @var $node Node
     */
    if (!empty($node->get("field_area_results"))) {
      foreach ($node->get("field_area_results") as $area_key => $area_target) {
        $para_id = $area_target->target_id;
        $para = $term_storage->load($para_id);
        $election["paragraphs"]["election_area_results"][$para_id] = $para;
        $election["mapping"]["election_area_results"][$para->field_election_area[0]->target_id] = $para_id;

        foreach ($para->get("field_election_contest_results") as $contest_key => $contest_target) {
          $para_id = $contest_target->target_id;
          $para = $term_storage->load($para_id);
          $election["paragraphs"]["election_contest_results"][$para_id] = $para;
          $election["mapping"]["election_contest_results"][$para->field_election_contest[0]->target_id] = $para_id;

          foreach ($para->get("field_candidate_results") as $cand_key => $cand_target) {
            $para_id = $cand_target->target_id;
            $para = $term_storage->load($para_id);
            $election["paragraphs"]["election_candidate_results"][$para_id] = $para;
            $election["mapping"]["election_candidate_results"][$para->field_election_candidate[0]->target_id] = $para_id;
          }

        }

      }
    }

    return TRUE;
  }

  /**
   * Create a new election, essentially this means creating the node for the
   *   election results, and a new entry in the taxonomy for the election
   *   itself.
   *
   * @param $election array Array containing the report contents.
   *
   * @return boolean
   */
  protected function createElection(&$election) {

    $data = $election["file"]["data"];

    $term_storage = \Drupal::entityTypeManager()
      ->getStorage("taxonomy_term");

    // === CREATE THE BASE TAXONOMY for this election ====
    $election["taxonomies"]["elections"] = $term_storage
      ->create([
        "name" => $data->election["name"],
        "vid" => "elections",
        "description" => "Results are {$data->election["unofficial"]}",
        "field_display_title" => $data->election["name"],
        "field_election_subtitle" => $data->election["report"],
        "field_election_date" => $election["file"]["election_date"],
        "field_election_type" => $election["file"]["election_type"],
      ]);
    $election["taxonomies"]["elections"]->save();

    // - Create a node (election_results) with the filename and dates
    /**
     * @var Node $node
     */
    $node_storage = \Drupal::entityTypeManager()
      ->getStorage("node");
    $el = ucwords($election["file"]["election_type"]);
    $election["nodes"]["election_report"] = $node_storage->create([
      "type" => "election_report",
      "title" => $data->election["name"],
      "field_election" => [
        "target_id" => $election["taxonomies"]["elections"]->id(),
      ],
      "field_election_isofficial" => $election["file"]["is_official"],
      "field_updated_date" => $data->election["progcreate"],
      "field_source_file" => [
        'uri' => \Drupal::service('file_url_generator')->generate($election["file"]["path"])->getUri(),
        'title' => $el . " election on " . $election["file"]["election_date"]
      ],
      "uid" => $this->currentUser()->id(),
      ]);
    // Ensure a new revision is created.
    $election["nodes"]["election_report"]->set("revision_log", "Data for {$data->election["create"]} updated via upload form.");
    try {
      if ($election["nodes"]["election_report"]->save() != SAVED_NEW) {
        throw new EntityStorageException("New 'election_report' node updated when a create was expected.");
      }
    }
    catch (EntityStorageException $e) {
      $this->messenger()->addError(Markup::create("Error updating data in 'election_report' (node). <br> {$e->getMessage()}. Error 9103."));
      $election["file"]["outcome"] = "Failed";
      // rollback if possible.
      if (!empty($election["taxonomies"]["elections"])) {
        try {
          $election["taxonomies"]["elections"]->delete();
          $election["taxonomies"]["elections"] = NULL;
        }
        catch (\Exception $e) {}
      }
      if (!empty($election["nodes"]["election_report"])) {
        try {
          $election["nodes"]["election_report"]->delete();
          $election["nodes"]["election_report"] = NULL;
        }
        catch (\Exception $e) {}
      }
      return FALSE;
    }

    return TRUE;
  }

  /**
   * This updates an existing election with new results.
   *
   * @param array $election
   *
   * @return bool
   */
  protected function upsertElectionEntities(array &$election) {

    $data = $election["file"]["data"];
    $election_id = $election["nodes"]["election_report"]->field_election[0]->target_id;

    // Update the subtitle for the election in the elections taxonomy.
    /** @var \Drupal\taxonomy\Entity\Term $tax */
    $tax = $election["taxonomies"]["elections"];
    if ($tax->field_election_subtitle[0]->value != $data->election["report"]) {
      $tax->setNewRevision(FALSE);
      $tax->set("field_election_subtitle", $data->election["report"]);
      try {
        if ($tax->save() != SAVED_UPDATED) {
          throw new EntityStorageException("New 'elections' taxonomy created when update was expected.");
        }
      }
      catch (EntityStorageException $e) {
        $this->messenger()->addError(Markup::create("Error updating data in 'elections' (taxonomy). <br> {$e->getMessage()}. Error 9102."));
        $election["file"]["outcome"] = "Failed";
        return FALSE;
      }
    }

    // Check that we have got enough entries in the taxonomies.
    if (empty($election["taxonomies"]["election_areas"])
      || count($election["taxonomies"]["election_areas"]) != count($election["file"]["areas"])) {
      if (!$this->upsertAreas($election, $election_id)) {
        $election["file"]["outcome"] = "Failed";
        return FALSE;
      }
    }
    if (empty($election["taxonomies"]["elector_groups"])
      || count($election["taxonomies"]["elector_groups"]) != count($data->electorgroups)) {
      if (!$this->upsertGroups($election, $election_id)) {
        $election["file"]["outcome"] = "Failed";
        return FALSE;
      }
    }
    if (empty($election["taxonomies"]["election_contests"])
      || count($election["taxonomies"]["election_contests"]) != count($data->contests)) {
      if (!$this->upsertContests($election, $election_id)) {
        $election["file"]["outcome"] = "Failed";
        return FALSE;
      }

    }
    if (empty($election["taxonomies"]["election_candidates"])
      || count($election["taxonomies"]["election_candidates"]) != count($data->choices)) {
      if (!$this->upsertCandidates($election, $election_id)) {
        $election["file"]["outcome"] = "Failed";
        return FALSE;
      }
    }

    // - Update the node with the filename and dates
    if (!$this->updateElection($election)) {
      $election["file"]["outcome"] = "Failed";
      return FALSE;
    }

    // Take each area in update its paragraph with voting results.
    if (!$this->upsertAreaResults($election)) {
      $election["file"]["outcome"] = "Failed";
      return FALSE;
    }
    if (!$this->upsertContestResults($election)) {
      $election["file"]["outcome"] = "Failed";
      return FALSE;
    }
    if (!$this->upsertCandidateResults($election)) {
      $election["file"]["outcome"] = "Failed";
      return FALSE;
    }

    // Now reorder the Candidate results within each contest
    try {
      $this->sortCandidateResults($election);
    }
    catch (\Exception $e) {}

    return TRUE;
  }

  private function updateElection(array &$election) {
    /**
     * @var Node $node
     */
    $data = $election["file"]["data"];
    $node = $election["nodes"]["election_report"];

    $el = ucwords($election["file"]["election_type"]);

    $node->set("field_updated_date", $data->election["progcreate"]);
    $node->set("field_source_file", [
      'uri' => \Drupal::service('file_url_generator')->generate($election["file"]["path"])->getUri(),
      'title' => $el . " election on " . $election["file"]["election_date"]
    ]);
    $node->set("changed", strtotime("Now"));
    $node->set("uid", $this->currentUser()->id());
    // Ensure a new revision is created.
    $node->setNewRevision(TRUE);
    $node->set("revision_log", "Data for {$data->election["create"]} updated via upload form.");
    try {
      if ($node->save() != SAVED_UPDATED) {
        throw new EntityStorageException("New 'election_report' node created when update was expected.");
      }
    }
    catch (EntityStorageException $e) {
      $this->messenger()->addError(Markup::create("Error updating data in 'election_report' (node). <br> {$e->getMessage()}. Error 9103."));
      return FALSE;
    }
    return TRUE;
  }

  private function upsertGroups(array &$election, int $id) {

    // There is nothing to change in the Group taxonomy after it has been created
    // for a particular election.

    // Ensure we have an array for the election_contest mapping, even if empty.
    if (!isset($election["mapping"]["elector_groups"])) {
      $election["mapping"]["elector_groups"] = [];
    }

    // Check the import array is not empty.
    if (empty($election["file"]["data"]->electorgroups)) {
      $this->messenger()->addError("Error: No election groups found in import file. Error 9204");
      return FALSE;
    }

    foreach ($election["file"]["data"]->electorgroups as $group) {

      // Taxonomy is not found, so we need to create it.
      if (!array_key_exists($group["groupid"], $election["mapping"]["elector_groups"])) {
        $tax = [
          "vid" => "elector_groups",
          "name" => $group["name"],
          "description" => "",
          "field_display_title" => $group["name"],
          "field_original_id" => $group["groupid"],
          "field_is_top" => $group["istop"],
          "field_short_name" => $group["abbreviation"],
          "field_election" => [
            "target_id" => $id,
          ],
        ];

        try {
          $term = Term::create($tax);
          if ($term->save() == SAVED_NEW) {
            $election["taxonomies"]["elector_groups"][] = $term;
            $election["mapping"]["elector_groups"][$group["groupid"]] = $term->id();
          }
          else {
            throw New EntityStorageException("Issue saving new Elector Group term.");
          }
        }
        catch (EntityStorageException $e) {
          $this->messenger()->addError("Error: {$e->getMessage()}. Error 9104");
          return FALSE;
        }

      }

    }

    foreach ($election["file"]["data"]->contestgroups as $cg) {
      // create a mapping for the contestgroups too.
      $eg_term_id = $election["mapping"]["elector_groups"][$cg["egid"]];
      $election["mapping"]["contest_groups"][$cg["conid"]] = $eg_term_id;
    }

    return TRUE;
  }

  private function upsertAreas(array &$election, int $id) {

    // There is nothing to change in the Area taxonomy after it has been created
    // for a particular election.

    // Ensure we have an array for the election_area mapping, even if empty.
    if (!isset($election["mapping"]["election_areas"])) {
      $election["mapping"]["election_areas"] = [];
    }

    // Check the import array is not empty.
    if (empty($election["file"]["areas"])) {
      $this->messenger()->addError("Error: No election areas found in import file. Error 9201");
      return FALSE;
    }

    foreach ($election["file"]["areas"] as $area) {

      if (!array_key_exists($area["areaid"], $election["mapping"]["election_areas"])) {
        // This election_areas term is not found, so we need to create it.
        $tax = [
          "vid" => "election_areas",
          "name" => $area["areaname"],
          "description" => "",
          "field_display_title" => $area["areaname"],
          "field_original_id" => $area["areaid"],
          "field_election" => [
            "target_id" => $id,
          ],
        ];

        try {
          $term = Term::create($tax);
          if ($term->save() == SAVED_NEW) {
            $election["taxonomies"]["election_areas"][$term->id()] = $term;
            $election["mapping"]["election_areas"][$area["areaid"]] =  $term->id();
          }
          else {
            throw New EntityStorageException("Issue saving new Area term.");
          }
        }
        catch (EntityStorageException $e) {
          $this->messenger()->addError("Error: {$e->getMessage()}. Error 9105");
          return FALSE;
        }

      }

    }
    return TRUE;
  }

  private function upsertAreaResults(array &$election) {

    $data = $election["file"]["data"];

    // Check the import array is not empty.
    if (empty($data->pollprogress)) {
      $this->messenger()->addError("Error: No area results found in import file. Error 9206");
      return FALSE;
    }

    if (!isset($election["mapping"]["election_area_results"])) {
      $election["mapping"]["election_area_results"] = [];
    }

    $node = $election["nodes"]["election_report"];

    // Load the candidate results data.
    foreach ($data->pollprogress as $area_result) {
      // Find the result and update.
      if (array_key_exists($area_result["areaid"], $election["mapping"]["election_areas"])
        && $term_id = $election["mapping"]["election_areas"][$area_result["areaid"]]) {
        if (empty($election["mapping"]["election_area_results"][$term_id])) {
          try {
            $para_area_result = Paragraph::create([
              "type" => "election_area_results",
              "field_election_area" => $term_id,
              "field_precincts_total" => $area_result["total"],
              "field_precincts_reported" => $area_result["reported"],
            ]);
            $para_area_result->setParentEntity($node, "field_area_results");
            $para_area_result->save();
            $node_array = $node->get("field_area_results");
            $node_array->appendItem([
              "target_id" => $para_area_result->id(),
              "target_revision_id" => $para_area_result->getRevisionId(),
            ]);
            $node->save();
            $election["paragraphs"]["election_area_results"][$para_area_result->id()] = $para_area_result;
            $election["mapping"]["election_area_results"][$term_id] = $para_area_result->id();

          }
          catch (EntityStorageException $e) {
            $this->messenger()
              ->addError("Error: {$e->getMessage()}. Error 9106");
            return FALSE;
          }
        }

        else {
          $para_id = $election["mapping"]["election_area_results"][$term_id];
          $para_area_result = $election["paragraphs"]["election_area_results"][$para_id];
          $para_area_result->set("field_precincts_reported", $area_result["reported"]);
          $para_area_result->set("field_precincts_total", $area_result["total"]);
          try {
            $para_area_result->save();
          }
          catch (EntityStorageException $e) {
            $this->messenger()
              ->addError("Error: {$e->getMessage()}. Error 9107");
            return FALSE;
          }
        }
      }
      else {

      }

    }

    return TRUE;
  }

  private function upsertContests(array &$election, int $id) {

    // Ensure we have an array for the election_contest mapping, even if empty.
    if (!isset($election["mapping"]["election_contests"])) {
      $election["mapping"]["election_contests"] = [];
    }

    // Check the import array is not empty.
    if (empty($election["file"]["data"]->contests)) {
      $this->messenger()->addError("Error: No election contests found in import file. Error 9202");
      return FALSE;
    }

    foreach ($election["file"]["data"]->contests as $contest) {

      if (empty($election["mapping"]["election_candidates"])
        || !array_key_exists($contest["contestid"], $election["mapping"]["election_contests"])) {
        // This election_contests term does not exist, so we need to create it.
        $orig_area_id = $election["file"]["areas"][$contest["areaid"]]["areaid"];
        $area_term_id = $election["mapping"]["election_areas"][$orig_area_id];
        $eg_term_id = $election["mapping"]["contest_groups"][$contest["contestid"]];
        $contest["name"] = preg_replace_callback("/(\w*)(\(dem\)|\(rep\))(.*)/", function($m){return $m[1] . strtoupper($m[2]);}, $contest["name"]);
        $tax = [
          "vid" => "election_contests",
          "name" => $contest["name"],
          "description" => "",
          "field_display_title" => $contest["name"],
          "field_original_id" => $contest["contestid"],
          "field_contest_eligible" => $contest["eligible"],
          "field_contest_isacclaimed" => $contest["isacclaimed"],
          "field_contest_isdisabled" => $contest["isdisabled"],
          "field_contest_ismajor" => $contest["ismajor"],
          "field_contest_pos" => $contest["pos"],
          "field_contest_sortorder" => $contest["sortorder"],
          "field_has_writeins" => $contest["writeins"],
          "field_elector_group" => [
            "target_id" => $eg_term_id,
          ],
          "field_election" => [
            "target_id" => $id,
          ],
          "field_area" => [
            "target_id" => $area_term_id,
          ],
        ];

        try {
          $term = Term::create($tax);
          if ($term->save() == SAVED_NEW) {
            $election["taxonomies"]["election_contests"][$term->id()] = $term;
            $election["mapping"]["election_contests"][$contest["contestid"]] = $term->id();
          }
          else {
            throw New EntityStorageException("Issue saving new Contest term.");
          }
        }
        catch (EntityStorageException $e) {
          $this->messenger()->addError("Error: {$e->getMessage()}. Error 9108");
          return FALSE;
        }

      }

    }

    return TRUE;
  }

  private function upsertContestResults(array &$election) {

    $data = $election["file"]["data"];

    // Check the import array is not empty.
    if (empty($data->contests)) {
      $this->messenger()->addError("Error: No contest results found in import file. Error 9207");
      return FALSE;
    }

    if (!isset($election["mapping"]["election_contest_results"])) {
      $election["mapping"]["election_contest_results"] = [];
    }

    // Retrieve the precinct reporting info from pollprogress.
    $area_progress = [];
    foreach ($data->contests as $contest) {
      foreach ($data->pollprogress as $progress) {
        if ($contest["areaid"] == $progress["areaid"]) {
          $area_progress[$contest["contestid"]] = [
            "reported" => $progress["reported"],
            "total" => $progress["total"],
            "weight" => $contest["sortorder"],
          ];
          break;
        }
      }
    }
    foreach ($data->conteststats as $contest_result) {
      // Find the result and update.
      $contest_term_id = $election["mapping"]["election_contests"][$contest_result["contestid"]];
      if (!array_key_exists($contest_term_id, $election["mapping"]["election_contest_results"])) {
        try {
          $contest_result_para = Paragraph::create([
            "type" => "election_contest_results",
            "field_election_contest" => ["target_id" => $contest_term_id],
            "field_contest_ballots" => $contest_result["ballots"],
            "field_contest_numvoters" => $contest_result["numvoters"],
            "field_contest_overvotes" => $contest_result["overvotes"],
            "field_contest_undervotes" => $contest_result["undervotes"],
            "field_pushcontests" => $contest_result["pushcontests"],
            "field_calc_total_votes" => 0,
            "field_precinct_reported" => $area_progress[$contest_result["contestid"]]["reported"],
            "field_precinct_total" => $area_progress[$contest_result["contestid"]]["total"],
          ]);
          // Need to work our way up the tree to find the Parent entity (which
          // is a paragraph type "election_area_results").
          $contest_term = $election["taxonomies"]["election_contests"][$contest_term_id];
          $area_term_id = $contest_term->field_area[0]->target_id;        // area taxonomy id
          $area_results_id = $election["mapping"]["election_area_results"][$area_term_id];  // area_results paragraph id
          $area_results_para = $election["paragraphs"]["election_area_results"][$area_results_id];  // area_results paragraph entity

          // Step 1: On the new "election_contest_result" paragraph, set the
          // parent entity to be the "election_area_results" paragraph entity.
          $contest_result_para->setParentEntity($area_results_para, "field_election_contest_results");
          $contest_result_para->save();

          // Step 2: On the existing parent ("election_area_result") paragraph,
          // create a new item in the "field_election_contest_results" field.
          $area_results_para
            ->get("field_election_contest_results")
            ->appendItem([
              "target_id" => $contest_result_para->id(),
              "target_revision_id" => $contest_result_para->getRevisionId(),
            ]);
          $area_results_para->save();

          // Update the $elections object with create entity and its map.
          $election["paragraphs"]["election_contest_results"][$contest_result_para->id()] = $contest_result_para;
          $election["mapping"]["election_contest_results"][$contest_term_id] =  $contest_result_para->id();

        }
        catch (EntityStorageException $e) {
          $this->messenger()->addError("Error: {$e->getMessage()}. Error 9109");
          return FALSE;
        }
      }

      else {
        $para_id = $election["mapping"]["election_contest_results"][$contest_term_id];
        $contest_result_para = $election["paragraphs"]["election_contest_results"][$para_id];
        $contest_result_para->set("field_contest_ballots", $contest_result["ballots"]);
        $contest_result_para->set("field_contest_numvoters", $contest_result["numvoters"]);
        $contest_result_para->set("field_contest_overvotes", $contest_result["overvotes"]);
        $contest_result_para->set("field_contest_undervotes", $contest_result["undervotes"]);
        $contest_result_para->set("field_calc_total_votes", 0);
        $contest_result_para->set("field_contest_numvoters", $contest_result["pushcontests"]);
        $contest_result_para->set("field_precinct_reported", $area_progress[$contest_result["contestid"]]["reported"]);
        $contest_result_para->set("field_precinct_total", $area_progress[$contest_result["contestid"]]["total"]);
        try {
          $contest_result_para->save();
        }
        catch (EntityStorageException $e) {
          $this->messenger()->addError("Error: {$e->getMessage()}. Error 9110");
          return FALSE;
        }
      }

    }

    return TRUE;
  }

  private function upsertCandidates(array &$election, int $id) {
    // TODO: is the anything to change in the Candidate taxonomy after it has
    //   been created for a particular election.

    // Ensure we have an array for the election_candidate mapping, even if empty.
    if (!isset($election["mapping"]["election_candidates"])) {
      $election["mapping"]["election_candidates"] = [];
    }

    // Check the import array is not empty.
    if (empty($election["file"]["data"]->choices)) {
      $this->messenger()->addError("Error: No election candidates/choices found in import file. Error 9203");
      return FALSE;
    }

    foreach ($election["file"]["data"]->choices as $choice) {

      // Find this area in the taxonomy (or don't).
      if (empty($election["mapping"]["election_candidates"])
        || !array_key_exists($choice["chid"], $election["mapping"]["election_candidates"])) {
        // Taxonomy is not found, so we need to create it.
        $contest = $election["mapping"]["election_contests"][$choice["conid"]];
        $tax = [
          "vid" => "election_candidates",
          "name" => $choice["name"],
          "description" => "",
          "field_display_title" => $choice["name"],
          "field_original_id" => $choice["chid"],

          "field_candidate_dis" => $choice["dis"],
          "field_candidate_showvotes" => $choice["showvotes"],
          "field_candidate_wri" => $choice["wri"],
          "field_candidate_wrind" => $choice["wrind"],

          "field_contest" => [
            "target_id" => $contest,
          ],
        ];

        try {
          $term = Term::create($tax);
          if ($term->save() == SAVED_NEW) {
            $election["taxonomies"]["election_candidates"][$term->id()] = $term;
            $election["mapping"]["election_candidates"][$choice["chid"]] = $term->id();
          }
          else {
            throw New EntityStorageException("Issue saving new Candidate term.");
          }
        }
        catch (EntityStorageException $e) {
          $this->messenger()->addError("Error: {$e->getMessage()}. Error 9111");
          return FALSE;
        }
      }

    }

    return TRUE;
  }

  private function upsertCandidateResults(array &$election) {

    $data = $election["file"]["data"];

    // Check the import array is not empty.
    if (empty($data->results)) {
      $this->messenger()->addError("Error: No candidate/choice results found in import file. Error 9208");
      return FALSE;
    }

    if (!isset($election["mapping"]["election_candidate_results"])) {
      $election["mapping"]["election_candidate_results"] = [];
    }

    // Calculate and save the total votes counted per contest.
    $contest_count = [];
    foreach ($data->results as $candidate_result) {
      if (!array_key_exists($candidate_result["contid"], $contest_count)) {
        $contest_count[$candidate_result["contid"]] = intval($candidate_result["vot"]);
      }
      else {
        $contest_count[$candidate_result["contid"]] += intval($candidate_result["vot"]);
      }
    }

    // Process candidate/choice results.
    foreach ($data->results as $candidate_result) {
      // Work out the percentage for this candidate/choice.
      if (intval($candidate_result["vot"]) > 0
        && intval($contest_count[$candidate_result["contid"]]) > 0) {
        $pct = round(intval($candidate_result["vot"]) / intval($contest_count[$candidate_result["contid"]]), 4);
      }
      else {
        $pct = 0;
      }
      // Find the result and update.
      $cand_term = $election["mapping"]["election_candidates"][$candidate_result["chid"]];
      if (empty($candidate_result["chid"])) {
        // DIG-4111: We have a candidate in the results that is not defined in
        // the candidates list.
        $this->messenger()->addWarning("Could not find Candidate ID (chid) {$candidate_result["chid"]}. Error 9114");
        continue;
      }
      elseif (is_numeric($cand_term)) {
        $cand_term_id = $cand_term;
      }
      else {
        $cand_term_id = $cand_term->id();
      }

      if (empty($election["mapping"]["election_candidate_results"][$cand_term_id])) {
        try {
          $candidate_result_para = Paragraph::create([
            "type" => "election_candidate_results",
            "field_election_candidate" => ["target_id" => $cand_term_id],
            "field_candidate_prtid" => $candidate_result["prtid"],
            "field_candidate_vot" => $candidate_result["vot"],
            "field_candidate_wrind" => $candidate_result["wrind"],
            "field_calc_percent" => $pct,
          ]);
          // Need to work our way up the tree to find the Parent entity (which
          // is a paragraph type "election_contest_results").
          $contest_term_id = $election["mapping"]["election_contests"][$candidate_result["contid"]];
          $contest_results_id = $election["mapping"]["election_contest_results"][$contest_term_id];
          $contest_results_para = $election["paragraphs"]["election_contest_results"][$contest_results_id];

          // Step 1: On the new "election_candidate_result" paragraph, set the
          // parent entity to be the "election_contest_results" paragraph entity.
          $candidate_result_para->setParentEntity($contest_results_para, "field_candidate_results");
          $candidate_result_para->save();

          // Step 2: On the existing parent ("election_contest_result") paragraph,
          // create a new item in the "field_election_contest_results" field.
          $vot = intval($contest_results_para->field_calc_total_votes->value ?: 0);
          $contest_results_para
            ->get("field_candidate_results")
            ->appendItem([
              "target_id" => $candidate_result_para->id(),
              "target_revision_id" => $candidate_result_para->getRevisionId()
            ]);
          $contest_results_para
            ->set("field_calc_total_votes", $vot + intval($candidate_result["vot"]))
            ->save();

          // Update the $elections object with create entity and its map.
          $election["paragraphs"]["election_candidate_results"][$candidate_result_para->id()] = $candidate_result_para;
          $election["mapping"]["election_candidate_results"][$cand_term_id] =  $candidate_result_para->id();

        }
        catch (EntityStorageException $e) {
          $this->messenger()->addError("Error: {$e->getMessage()}. Error 9112");
          return FALSE;
        }
      }

      else {
        $para_id = $election["mapping"]["election_candidate_results"][$cand_term_id];
        $candidate_result_para = $election["paragraphs"]["election_candidate_results"][$para_id];
        $candidate_result_para->set("field_candidate_prtid", $candidate_result["prtid"]);
        $candidate_result_para->set("field_candidate_vot", $candidate_result["vot"]);
        $candidate_result_para->set("field_candidate_wrind", $candidate_result["wrind"]);
        $candidate_result_para->set("field_calc_percent", $pct);

        $contest_term_id = $election["mapping"]["election_contests"][$candidate_result["contid"]];
        $contest_results_id = $election["mapping"]["election_contest_results"][$contest_term_id];
        $contest_results_para = $election["paragraphs"]["election_contest_results"][$contest_results_id];
        $vot = intval($contest_results_para->field_calc_total_votes->value ?: 0);
        $contest_results_para
          ->set("field_calc_total_votes", $vot + intval($candidate_result["vot"]))
          ->save();

        try {
          $candidate_result_para->save();
        }
        catch (EntityStorageException $e) {
          $this->messenger()->addError("Error: {$e->getMessage()}. Error 9113");
          return FALSE;
        }
      }

    }

    return TRUE;

  }

  /**
   * This sorts the candidate results so that the candidate/choice with the
   * most votes counted will appear at the top.
   * In the case of ties, Candates will appear alphabetically.
   * Write-ins should always appear the the bottom of the list.
   *
   * @param array $election
   *
   * @return void
   */
  private function sortCandidateResults(array &$election) {
    foreach($election["paragraphs"]["election_contest_results"] as $contest) {
      $candidate_results_list = $contest->field_candidate_results;
      $sort = [];
      foreach ($candidate_results_list as $candidate_results_item) {
        $candidate_para = $election["paragraphs"]["election_candidate_results"][$candidate_results_item->target_id];
        $candidate_term = $election["taxonomies"]["election_candidates"][$candidate_para->field_election_candidate->target_id];
        $name = ElectionResults::getSortableNamePart($candidate_term->name->value, $candidate_term->field_original_id->value);
        $sort[intval($candidate_para->field_candidate_vot->value)][$name] = $candidate_results_item;
        $candidate_results_list->removeItem(0);
      }
      krsort($sort, SORT_NUMERIC);
      foreach($sort as $reorder) {
        ksort($reorder, SORT_STRING);
        foreach ($reorder as $cand) {
          $candidate_results_list->appendItem([
            "target_id" => $cand->target_id,
            "target_revision_id" => $cand->target_revision_id,
          ]);
        }
      }
      $contest->save();
    }

  }

  /**
   * Calculate the Election date from the report creation date.
   *
   * @param string|int $date
   *
   * @return int
   */
  public function setElectionDate(string|int $date) {

    // Convert to a timestamp if not already
    if (is_string($date)) {
      $election_date = strtotime($date);
    }

    // Subtract 12 hours so that we can generate reports up to 12 hours into the
    // day after the election.
    $election_date = strtotime("-12 hours", $election_date);

    // Just want a date, set to midday on election day.
    return date("Y-m-d", $election_date);
  }

  /**
   * Converts the default xml file format for elections reports from XML into
   * an object.
   * This function could be cloned and adapted for other file formats or
   * protocols e.g. json or csv.  The caller
   *
   * @param \SimpleXMLElement $xml XML object to convert.
   *
   * @return ElectionResults Converted object.
   */
  private function xmltoobject(\SimpleXMLElement $xml) {

    $output = new ElectionResults();

    $map = [
      "choices" => "choices",
      "contests" => "contests",
      "conteststats" => "conteststats",
      "electorgroups" => "electorgroups",
      "contestgroups" => "contestgroups",
      "parties" => "parties",
      "pollprogress" => "pollprogress",
      "results" => "results",
      "settings" => "settings",
    ];

    foreach($xml->Report_Info->attributes() as $key => $value) {
      $output->addField("election", [
        "name" => (string) strtolower($key),
        "value" => ucwords(strtolower((string) $value))
      ]);
    }
    $prog_date = strtotime($output->election["create"]);
    $prog_date = date("Y-m-d\TH:i:s", $prog_date);
    $output->addField("election", [
      "name" => "progcreate" ,
      "value" => $prog_date
    ]);
    foreach($xml->Terminology->attributes() as $key => $value) {
      $output->addField("terminology", [
        "name" => (string) strtolower($key),
        "value" => ucwords(strtolower((string) $value))
      ]);
    }

    foreach ($xml as $base_element_name => $base_element) {
      if (in_array($base_element_name, $map)) {
        $id = 0;
        foreach ($base_element as $sub_element) {
          $sub = [];

          foreach ($sub_element->attributes() as $key => $value) {
            $sub[strtolower((string) $key)] = ucwords(strtolower((string) $value));
          }

          $output->addField($map[$base_element_name], [
            "name" => $id,
            "value" => $sub,
          ]);
          $id += 1;
        }
      }
    }

    return $output;

  }

  public function writeHistory(array $record) {
    $config = \Drupal::service('config.factory')->getEditable("node_elections.settings");
    $history = $config->get("history");
    $history[] = $record;
    // DIG-4111 increase history
    if (count($history) > 20) {
      unset($history[0]);
      $history = array_values($history);  //reindex so first element is [0].
    }
    $config->set("history", $history);
    $config->set("last-run", end($history)["upload_date"])
      ->save();
  }

}

class ElectionResults {

  public $areas = [];

  public $election;

  public function create() {
    $election = new \stdClass();
    $election->election = [];
    $election->choices = new \stdClass();
    $election->contests = new \stdClass();
    $election->conteststats = new \stdClass();
    $election->electorgroups = new \stdClass();
    $election->parties = new \stdClass();
    $election->pollprogress = new \stdClass();
    $election->results = new \stdClass();
    $election->settings = new \stdClass();
    $election->terminology = new \stdClass();
    $this->election = $election;
  }

  /**
   * Add field to this object.
   *
   * @param string $fieldType The type of field being added
   * @param array $field The field (an array with a name field and value field)
   *
   * @return void
   */
  public function addField(string $fieldType, array $field) {
    $this->{$fieldType}[strtolower($field["name"])] = $field["value"];
  }

  /**
   * Evaluate the list of areas defined within the contests.
   *
   * @param array|NULL $contests An array of contests (optional).
   *
   * @return array The array of areas.
   */
  public function extractAreas(array $contests = NULL) {
    // Use the class contests if an array is not passed.
    if (!$contests) {
      $contests = $this->contests;
    }
    // Find the area field in the contest and build new array.
    foreach ($contests as $contest) {
      $this->areas[$contest["areaid"]] = [
        "areaid" => $contest["areaid"],
        "areaname" => $contest["areaname"],
      ];
    }

    return $this->areas;
  }

  /**
   * Aggregate votes for duplicated candidates in a single contest.
   * This is commonly used to aggregate write-ins where multiple result fields
   * appear. (usually when more than one position is being contested in a
   * contest (e.g. counsellor-at-large in municipal general elections).
   *
   * @return void
   */
  public function aggregateCandidateResults() {
    if (!empty($this->results)) {
      // Move through and find duplicate contid:chid attributes and add them up.
      $output = [];
      foreach ($this->results as $result) {
        $contest = $result["contid"];
        $choice = $result["chid"];

        if (!array_key_exists($contest, $output)) {
          $output[$contest] = [];
        }

        if (array_key_exists($result["chid"], $output[$contest])) {
          $output[$contest][$choice]["vot"] += $result["vot"];
        }
        else {
          $output[$contest][$choice] = $result;
        }
      }

      // Sort this array so that its in numerical order of the contest.
      ksort($output);

      $this->results = [];

      foreach ($output as $contest) {
        foreach ($contest as $candidate) {
          $this->results[] = $candidate;
        }
      }
    }

    if (!empty($this->choices)) {
      // Repeat for the choices, but don't aggregate anything.
      $output = [];
      foreach ($this->choices as $ch) {
        $contest = $ch["conid"];
        $choice = $ch["chid"];

        if (!array_key_exists($contest, $output)) {
          $output[$contest] = [];
        }

        if (!array_key_exists($ch["chid"], $output[$contest])) {
          $output[$contest][$choice] = $ch;
        }
      }
    }

    // Sort this array so that its in numerical order of the contest.
    ksort($output);

    $this->choices = [];

    foreach ($output as $contest) {
      foreach ($contest as $candidate) {
        $this->choices[] = $candidate;
      }
    }
  }

  /**
   * Reorder the contest and choice array collections in the initial sort order
   * for the election.
   * Contests appear grouped in their areas, but using the osrt order specified
   * from the import file.
   * Candidates are ordered with those having the most votes at the top. In the
   * event of ties, then sort alphabetically, write-ins always at the bottom.
   *
   * @return void
   */
  public function reorder() {
    if (!empty($this->contests)) {
      $output = [];
      foreach ($this->contests as $contest) {
        $output[$contest["sortorder"]] = $contest;
        $map[$contest["contestid"]] = $contest["sortorder"];
      }
      ksort($output);
      $this->contests = array_values($output);
    }

    if (!empty($this->conteststats)) {
      $output = [];
      foreach ($this->conteststats as $conteststat) {
        $sort = $map[$conteststat["contestid"]];
        $output[$sort] = $conteststat;
      }
      ksort($output);
      $this->conteststats = array_values($output);
    }

    if (!empty($this->choices)) {
      $output = [];
      $map = [];
      $util = new ElectionUtilities();
      foreach ($this->choices as $choice) {
        $sort_name = $this->getSortableNamePart($choice["name"], $choice["chid"]);
        $choice["name"] = $util->capitalizeName($choice["name"]);
        $output[$sort_name][$choice["conid"]] = $choice;
        $map[$choice["chid"]] = $sort_name;
      }
      ksort($output);
      $this->choices = [];
      foreach ($output as $choice) {
        foreach ($choice as $row) {
          $this->choices[] = $row;
        }
      }
    }

    if (!empty($this->results)) {
      $output = [];
      foreach ($this->results as $result) {
        $sort = $map[$result["chid"]];
        $output[$sort][$result["contid"]] = $result;
      }
      ksort($output);
      $this->results = [];
      foreach ($output as $choice) {
        foreach ($choice as $row) {
          $this->results[] = $row;
        }
      }
    }

    if (!empty($this->parties)) {
      $output = [];
      foreach ($this->parties as $party) {
        $output[$party["partyid"]] = $party;
      }
      ksort($output);
      $this->parties = array_values($output);
    }
  }

  /**
   * Makes a best guess at the sortable part of the full name for a candidate,
   * of the answer to a choice question.
   *
   * @param $fullname The candidate/choice's fullname with spaces.
   * @param $chid The candidate/choice's id to ensure uniqueness DIG-4111.
   *
   * @return string The best-guess as to the part of the name to sort on.
   */
  public static function getSortableNamePart($fullname, $chid) {
    // Handle special candidates.
    if (in_array(strtolower(trim($fullname)), [
      "write-in",
      "writein",
      "write in",
      "yes",
      "no",
    ])) {
      // Add the zzz's to ensure these will sort last (and appear at the bottom
      // of the list).
      // Because yes and no are the only choices to questions, prepending the
      // zzz will make no difference to the order ...
      return strtolower("zzz" . $fullname);
    }

    $eligible = "";
    $fullname = str_replace([".", ",", '"'], '', $fullname);
    $name_parts = explode(" ", strtolower($fullname));

    // Remove the firstname and reverse the order of the "words" in the name.
    if (count($name_parts) > 1) {
      $name_parts = array_reverse($name_parts);
      $firstname = array_pop($name_parts);
    }

    // Find the first part which is more than 1 char and not a common suffix.
    foreach ($name_parts as $check_part) {
      if (strlen($check_part) > 1
        && !in_array($check_part, [
          "snr",
          "jnr",
          "sr",
          "jr",
          "ii",
          "iii",
          "iv",
          "v"
        ])
      ) {
        $eligible = $check_part;
        break;
      }
    }

    // If we cannot resolve anything, then use the firstname.
    // DIG-4111 append chid to ensure is unique.
    if ($eligible == "") {
      $eligible = ($firstname ?? $fullname) . $chid;
    }

    // 2024 DIG-4111 Need to ensure the sortname is unique, just using the
    // lastname is not enough for big elections with lots of candidates.
    // Start with the determined lastname (eligible) append the firstname and
    // then for good measure, append the chid.
    return $eligible . ($firstname ?? "") . $chid;
  }

}

