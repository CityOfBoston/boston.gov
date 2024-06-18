<?php

namespace Drupal\bos_assessing\Controller;

use Drupal\bos_pdfmanager\Controller\PdfManager;
use Drupal\Core\Controller\ControllerBase;
use \Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\bos_sql\Controller\SQL;

/**
 * Class Pdf
 * Used to generate PDF for bos_assessing.
 *
 * @package Drupal\bos_assessing\Controller
 */
class Pdf extends ControllerBase {

  /**
   * @var array Holds data to be inserted into the document.
   */
  protected array $data;

  /**
   * @var string Unique ID (parcel_id) (argument from endpoint).
   */
  protected string $id;

  /**
   * @var string holds the year: fmt=FY20XX (argument from endpoint).
   */
  protected string $year;

  /**
   * Magic function.
   * Catch calls to endpoints that don't exist.
   *
   * @param string $name
   *   Name.
   * @param mixed $arguments
   *   Arguments.
   */
  public function __call($name, $arguments) {
    throw new NotFoundHttpException();
  }

  /**
   * Send the desired document and settings to the PDFManager.
   *
   * @param string $type
   * @param string $year
   * @param string $parcel_id
   *
   * @return Response
   * @throws \Exception
   */
  public function generate(string $type, string $year, string $parcel_id): Response {
//    $path = \Drupal::service('file_system')->realpath("") . "/";
//    $path .= \Drupal::service('extension.list.module')->getPath('bos_assessing');
    $this->id = $parcel_id;
    $this->year = strtoupper($year);
    $type = strtolower($type);

    $pdf_manager = new PdfManager("Helvetica", "12", [0,0,0]);
    $path = $pdf_manager->getTemplatePath();

    $dbdata = $this->fetchDBData($type);

    switch(strtolower($type)) {
      case 'abatement':
        $template = "Abatement_Application_long";
        if (in_array($dbdata["land_use"],["R1", "R2", "R3"])) {
          $template = "Abatement_Application_short";
        }
        break;
      case 'resexempt':
        $template = "Residential_Exemption";
        break;
      case 'persexempt':
        $template = "Personal_Exemption";
        break;
      default:
        return $this->error("Template for {$type} form not found", 404);
    }

    $template_name = "{$path}/pdf/{$this->year}/{$this->year}_{$template}";
    if (!file_exists("{$template_name}.json")) {
      return $this->error("Configuration for {$template_name} not found.", 400);
    }
    if ($data = file_get_contents("{$template_name}.json")) {
        $this->subData($data, $dbdata, $type);
        $data = json_decode($data, TRUE);
        $this->data["page"] = $data["flat"];
        $this->data["document"] = $data["document"];
        if (empty($this->data['document']['output_dest'])) {
          // Ensure there is a delivery method defined.
          $this->data['document']['output_dest'] = "D";
        }
        unset($data);
    }

    // Check that the file exists, if not, then throw a 400 error.
    if (!file_exists("{$template_name}.pdf")) {
      return $this->error("Template {$template_name}.pdf not found", 400);
    }

    // Delete the output file if it is already there.
    $outfile = "{$this->year}_{$template}_{$parcel_id}.pdf";
    if (file_exists($outfile)) {
      unlink($outfile);
    }

    // Create the PDF.
    try {
      $document = $pdf_manager
        ->setTemplate("{$template_name}.pdf")
        ->setPageData($this->data["page"])
        ->setDocumentData($this->data["document"])
        ->setOutputFilename($outfile)
        ->generate_flat();
    }
    catch (\Exception $e) {
      return $this->error($e->getMessage(), 400);
    }

    if (empty($document)) {
      return $this->error("Generation failed.", 400);
    }

    // Decide how to handle the return.
    switch(strtoupper($this->data['document']['output_dest'])) {

      Case "I":
        // Diplay the PDF in the users browser if a viewer exists.
        $response = new BinaryFileResponse($document, 200, [
          'Content-Type' => 'application/pdf',
        ], true);
        break;

      Case "F":
        // Return the file location.
        $response = new Response(json_encode($document), 200, [
          'Content-Type' => 'application/json',
        ]);
        break;

      Case "D":
      Default:
        // Download the PDF in the user's browser. This is the default.
        $response = new BinaryFileResponse($document, 200, [
          'Content-Type' => 'application/pdf',
          'Content-Disposition' => "attachment; filename=\"{$outfile}\""
        ], true);
        break;

    }
    return $response;

  }

  /**
   * Does a pattern substitution, adding data from a DB Query into the supplied
   * string.
   * Searches for %-wrapped strings and substitutes with a value for the
   * matching field in the dbdata array.
   *   e.g.  "Hi %name%" in $template_data expands to "Hi David" if there is
   *   an element $dbdata["name"] = "David".
   *
   * @param string $source A string imported from the json config file.
   * @param array $dbdata
   * @param string $type
   *
   * @return string String with replaced patterns.
   */
  protected function subData(string &$source, array $dbdata, string $type): string {
    foreach ($dbdata as $search => $replace) {
      $source = str_ireplace("%{$search}%", strtoupper($replace), $source);
    }
    return $source;
  }

  /**
   * Runs a query against the current database to extract dsata that can be
   * used when updating the PDF template.
   *
   * @param $type string The PDF Template type
   *
   * @return array An array containing the data from the Query.
   * @throws \Exception
   */
  protected function fetchDBData($type):array {
    $sql = new SQL();
    $count = 0;
    while (!$tokens = $sql->getToken("assessing")) {
      $count++;
      if ($count <= 10) {
        sleep(10);
      }
    }
    $count = 0;
//    $statement = "SELECT * FROM dbo.taxbill WHERE parcel_id = '{$this->id}'";
//    while (!$map = $sql->runQuery($tokens["bearer_token"], $tokens["connection_token"], $statement)) {
    while (!$map = $sql->runSelect($tokens["bearer_token"], $tokens["connection_token"], "taxbill", NULL, [["parcel_id" => $this->id]])) {
      $count++;
      if ($count <= 10) {
        sleep(10);
      }
    }
    $map = json_decode($map->getContent(), TRUE)[0];
    // Make sure the parcel_id is the expected parcel id
    if ($map["parcel_id"] != $this->id) {
      throw new \Exception("Data error - unexpected pacel_id");
    }
    $map["year"] = is_numeric($this->year) ? $this->year : substr($this->year, 2,4);
    // Reformat some data
    $map["total_value"] = number_format($map["total_value"], 0, ".", ",");
    $map["streetno"] = trim($map["street_number"] . (empty($map["street_number_suffix"]) ? "": "-" . $map["street_number_suffix"])) ;
    $map["text_address_nolocale"] = "{$map['streetno']} {$map['street_name']}" . (empty($map["apt_unit"]) ? "": " #{$map['apt_unit']}") ;
    $map["text_address_nozip"] = "{$map['text_address_nolocale']}, {$map['city']}";
    $map["text_address"] = "{$map['text_address_nozip']} {$map['location_zip_code']}";
    $map["streetno"] = "{$map['streetno']}" . (empty($map["apt_unit"]) ? "": ", #{$map['apt_unit']}");
    if ($type == "abatement" || $type == "abatementl") {
      $map["ward"] = preg_replace("~(.)(.)~", "$1 $2 $3 $4 $5", substr($map["parcel_id"], 0, 2));
      $map["parcel-0"] = preg_replace("~(.)(.)(.)(.)(.)~", "$1 $2 $3 $4 $5", substr($map["parcel_id"], 2, 5));
      $map["parcel-1"] = preg_replace("~(.)(.)(.)~", "$1 $2 $3 $4 $5", substr($map["parcel_id"], 7, 3));
      $seqnum = $sql->runSP($tokens["bearer_token"], $tokens["connection_token"], "dbo.sp_get_pdf_data", [
        "parcel_id" => $this->id,
        "form_type" => "overval"
      ]);
      $map["seq_num"] = "{$map["year"]}10000";
      if (isset($seqnum[0][0])) {
        $map["seq_num"] = $seqnum[0][0]->application_number;
      }
    }
    return $map;
  }

  /**
   * Manages the return of an error message to caller.
   *
   * @param string $message a message to return.
   * @param int $code the http response code to pass back.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   */
  protected function error(string $message, int $code = 400): Response {
    if (\Drupal::request()->getMethod() == "GET") {
      // just send a 404
      \Drupal::logger("PDFGenerator")->error("Dispatched 404 because: {$message}");
      throw new NotFoundHttpException($message);
    }
    else {
      \Drupal::logger("PDFGenerator")->error("Returned JSOn error because: {$message}");
      return new Response(json_encode([
        "error" => $message
      ]), $code);
    }
  }

}
