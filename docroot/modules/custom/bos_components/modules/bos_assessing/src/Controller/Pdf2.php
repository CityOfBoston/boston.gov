<?php

namespace Drupal\bos_assessing\Controller;

use Drupal\bos_pdfmanager\Controller\PdfManager;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class Pdf2 extends Pdf {

  /**
   * @inheritDoc
   */
  public function generate(string $type, string $year, string $parcel_id): Response {

    global $base_url;

    if (str_contains($base_url, "lndo.site")) {
      $base_url = "https://boston_appserver_1";
    }

//    $path = \Drupal::service('file_system')->realpath("") . "/";
//    $path .= \Drupal::service('extension.list.module')->getPath('bos_assessing');
    $this->id = $parcel_id;
    $this->year = strtoupper($year);
    $type = strtolower($type);

    $pdf_manager = new PdfManager();
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
      $this->data["page"] = $data["fillable"];
      $this->data["document"] = $data["document"];
      if (empty($this->data['document']['output_dest'])) {
        // Ensure there is a delivery method defined.
        $this->data['document']['output_dest'] = "D";
      }
      unset($data);
    }
    if ($form_data = file_get_contents("{$template_name}.fdf")) {

      $this->subData($form_data, $dbdata, $type);
      $publicpath= \Drupal::service('file_system')->realpath("public://tmp/");
      $fdf_filename = "{$publicpath}/{$this->year}_{$template}_{$parcel_id}.fdf";
      file_put_contents($fdf_filename, $form_data);

      if (empty($this->data['document']['output_dest'])) {
        // Ensure there is a delivery method defined.
        $this->data['document']['output_dest'] = "D";
      }
      unset($form_data);
    }

    // Check that the file exists, if not, then throw a 400 error.
    if (!file_exists("{$template_name}.pdf")) {
      return $this->error("Template {$template_name}.pdf not found", 400);
    }

    $path = \Drupal::service('file_system')->realpath("") ;
    $pdf_filename = str_replace($path, $base_url, "{$template_name}.pdf" );

    // Delete the output file if it is already there.
    $outfile = "{$this->year}_{$template}_{$parcel_id}.pdf";
    if (file_exists($outfile)) {
      unlink($outfile);
    }

    // Create the PDF.
    try {
      $document = $pdf_manager
        ->setTemplate("{$pdf_filename}")
        ->setFormData("{$fdf_filename}")
        ->setPageData($this->data["page"])
        ->setOutputFilename($outfile)
        ->setDocumentData($this->data["document"], "pdftk")
        ->generate_fillable();
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
   * @inheritDoc
   */
  protected function subData(string &$source, array $dbdata, string $type):string {
    return parent::subData($source, $dbdata, $type);
  }

  /**
   * @inheritDoc
   */
  protected function fetchDBData($type):array {
    // do more updating into the necessary fmt for fdf
    return parent::fetchDBData($type);
  }

  /**
   * @inheritDoc
   */
  protected function error(string $message, int $code = 400):Response {
    return parent::error($message, $code);
  }
}
