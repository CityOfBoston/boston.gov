<?php

namespace Drupal\bos_pdfmanager\Controller;

/**
 * Class: PdfManager - main utility to control generation of PDFs.
 *
 * Class: PdfTextElement - defines text to add ONTO a pdf.
 *
 * Class: PdfBarcodeElement - defines barcode to add ONTO a pdf.
 *
 * Class: PdfFilenames - describes a file with local and remote aliases.
 *
 */

use Picqer\Barcode\BarcodeGeneratorJPG;
use Drupal\bos_pdfmanager\Controller\Fpdf as Fpdf_cob;
use \FPDF as Fpdf_fpdf;

/**
 * Class: PdfManager - General purpose PDF Manager Class.
 *
 * This class can
 *  - overlay text and barcodes on a flat PDF
 *  - insert text to a fillable form, and add barcodes and fixed text to it too.
 *
 * Barcode types supported are listed at:
 *  https://github.com/picqer/php-barcode-generator/blob/main/src/BarcodeGenerator.php
 *  - Simply substitute the text (or const) value into the "encode" field of the
 * class.
 *
 * @package Drupal\bos_assessing\Controller
 */
class PdfManager {

  /**
   * @var \Drupal\bos_pdfmanager\Controller\PdfManagerInterface The object managing the PDF.
   */
  protected PdfManagerInterface $pdf;
  protected array $text;
  protected string $template;
  protected string $formdata;
  protected array $barcode;
  protected PdfFilenames $tmppath;
  protected string $outputname;
  protected PdfFilenames $metadata_file;
  /**
   * @var array Array of files that can be deleted at end of processing.
   */
  private array $tmpfiles;
  protected $meta_data;
  protected string $default_font;
  protected string $default_size;
  protected array $default_color;

  protected string $template_path;

  /**
   * @var string (typically timestamp) a unique id for filename generation.
   */
  private string $unique_id;

  /**
   * Constructor:
   *
   * Sets default class attributes.
   *
   * @param string $default_font The default font family to use.
   * @param int $default_size The default font-size to use.
   * @param array $default_color The default color for text on the page.
   * @throws \Exception
   */
  public function __construct(string $default_font = "Helvetica", int $default_size = 12, array $default_color = [0,0,0]) {
    $this->tmpfiles = [];
    $this->default_font = $default_font;
    $this->default_size = $default_size;
    $this->default_color = $default_color;

    try {
      $this->tmppath = new PdfFilenames(\Drupal::service('file_system')
        ->realpath("public://tmp"));
      if (!$this->tmppath->exists) {
        mkdir($this->tmppath->path);
      }
    }
    catch (\Exception $e) {
      throw new \Exception("Issue with temp folder: {$e->getMessage()}", $e->getCode());
    }

    // Make sure this instance has a unique ID.
    $this->unique_id = (string) time();
    $lock_file = "{$this->tmppath->path}/{$this->unique_id}.lock";
    while (file_exists($lock_file)) {
      $this->unique_id .= time();
    }
    touch($lock_file);
    $this->tmpfiles[] = $lock_file;
  }

  /**
   * Destructor:
   *
   * This cleans up any temp files "owned" by this class created in-process.
   */
  public function __destruct() {
    foreach($this->tmpfiles as $key => $file) {
      if (file_exists($file)) {
        unlink($file);
      }
    }
  }

  /**
   * Sets the template to be used for building the final document.
   *
   * @param string $filename The absolute path on the server to the template.
   *
   * @return $this This class.
   */
  public function setTemplate(string $filename): PdfManager {
    $this->template = $filename;
    return $this;
  }

  /**
   * Sets the form data file to be used for building the final document.
   *
   * @param string $filename The absolute path on the server to the form data.
   *
   * @return $this This class.
   */
  public function setFormData(string $filename): PdfManager {
    $this->formdata = $filename;
    return $this;
  }

  /**
   * Set the output filename - this is the name of the completed PDF that is
   * delivered to the caller.
   *
   * @param string $filename The filename that the caller will see/receive.
   *
   * @return $this This class.
   */
  public function setOutputFilename(string $filename): PdfManager {
    $this->outputname = $filename;
    return $this;
  }

  /**
   * Sets an array of data that will be used to complete the PDF document.
   *
   * The input $page_data is an array with each element containing an array of
   * arrays defining content to be added to the page.
   *
   * The structure is:
   *     [
   *       [
   *         [content],
   *         [content]
   *       ],
   *       [ .. page 2 etc .. ]
   *     ]
   *
   *  where content is an array for text or a barcode:
   *      ["type"=>"text", "note"=>"", "x"=>0, "y"=>0, "txt"=>"", "size"=>"", "font"=>"" , "color"=>[]]
   *      ["type"=>"barcode", "note"=>"", "x"=>0, "y"=>0, "val"=>"", "encode"=>"C128", "color"=>[]]
   *    - type = 'barcode' or 'text'
   *    - x = insertion distance from left margin
   *    - y = insertion distance from top margin
   *    - color = (optional) RGB array (e.g. black = [0,0,0]) color (overrides default)
   *    - font = (text only) (optional) Name of the font (overrides default
   *    - size = (text only) (optional) size in points for the text (overrides default)
   *    - txt = (text only) the text to insert
   *    - val = (barcode only) string to be encoded in barcode (usually a number as a string)
   *    - encode = (barcode only) barcode encoding (see class pdfBarcodeElement)
   *    - note = (optional) descriptive text for management
   *
   * @param array $page_data The raw array
   *
   * @return $this This class
   */
  public function setPageData(array $page_data) {
    if (!empty($page_data)) {
      foreach ($page_data as $page_number => $page) {
        foreach ($page as $page_element) {
          switch (strtolower($page_element["type"])) {
            case "text":
              if (!isset($this->text[$page_number + 1])) {
                $this->text[$page_number + 1] = [];
              }
              $this->text[$page_number + 1][] = new pdfTextElement(
                $page_element["x"],
                $page_element["y"],
                $page_element["txt"],
                $page_element["size"] ?? $this->default_size,
                $page_element["font"] ?? $this->default_font,
                $page_element["color"] ?? $this->default_color,
              );
              break;

            case "barcode":
              // @see https://github.com/picqer/php-barcode-generator
              if (!isset($this->barcode[$page_number + 1])) {
                $this->barcode[$page_number + 1] = [];
              }

              $this->barcode[$page_number + 1][] = new pdfBarcodeElement(
                $page_element["x"],
                $page_element["y"],
                $page_element["val"],
                $page_element["encode"] ?? "Code128",
                $page_element["color"] ?? $this->default_color
              );
              break;
          }
        }
      }
    }
    return $this;
  }

  /**
   * Sets an array of data which relates to the documents properties.
   *
   * Key value pairs for:
   *     "output_dest": I (in-browser), D (download), F (file) or S (text),
   *     "title": Title for the output pdf
   *     "author": Author for the output pdf
   *     "subject": Subject for the output pdf
   *
   * @param array $document_data
   *
   * @return $this This class
   */
  public function setDocumentData(array $document_data, string $mode = "v1") {

    if (!empty($document_data)) {
      $this->meta_data = $document_data;
    }

    // Create a file which the pdftk update_info function can ingest.
    if ($mode == "pdftk") {
      $filename = "{$this->tmppath->path}/metadata_{$this->unique_id}.dat";
      $file = fopen($filename,"w");
      foreach ($this->meta_data as $property => $value) {
        if (in_array(strtolower($property), [
          "title",
          "subject",
          "creator",
          "author",
          "producer",
        ])) {
          fwrite($file, "InfoBegin\n");
          fwrite($file, "InfoKey: " . ucwords($property) . "\n");
          fwrite($file, "InfoValue: " . ucwords($value) . "\n");
        }
      }
      fclose($file);
      $this->metadata_file = new PdfFilenames($filename);
      $this->tmpfiles[] = $this->metadata_file->path;
    }

    return $this;
  }

  /**
   * Generate a flat PDF.
   *
   * @return \Drupal\bos_pdfmanager\Controller\PdfFilenames|false|string
   *
   */
  public function generate_flat() {

    $this->pdf = new Fpdf_cob($this->tmppath->path, $this->unique_id, "Helvetica", 12, [0,0,0]);

    // Load the source PDF.
    // Note using the FPDF library, the generated PDF will not be fillable.
    //  (even if the template was fillable)
    if (!$pageCount = $this->pdf->setSourceFile($this->template)) {
      return FALSE;
    }

    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {

      // Open each page in turn, and use as a template.
      if (!$templateId = $this->pdf->importPage($pageNo)) {
        return FALSE;
      }

      // Add a new page to the document, and then copy in the template page.
      $size = $this->pdf->getTemplateSize($templateId);
      $this->pdf->AddPage($size['orientation'], $size);
      $this->pdf->useTemplate($templateId);

      // Apply/overlay text and images (barcodes) as needed.
      $this->pdf->insertText($this->text[$pageNo] ?? []);
      $this->pdf->insertBarcode($this->barcode[$pageNo] ?? [], $pageNo);
    }

    $this->pdf->setMetaData($this->meta_data);

    // Determine the output filename and path for the new PDF doc
    $returnfile = "{$this->tmppath->path}/{$this->outputname}";
    if (file_exists($returnfile)) {
      unlink($returnfile);
    }

    // Output the new PDF doc
    $this->pdf->Output("F", $returnfile);

    // If the destination was "F" (file) then get a URL to the file.
    if ($this->meta_data['output_dest'] == "F") {
      // Save this in a public place on the site.
      $returnfile = new PdfFilenames($returnfile);
    }

    return $returnfile;

  }

  /**
   * Generate a fillable PDF.
   *
   * @return false|string The PDF filename if successful, or false if failed.
   * @throws \Exception
   */
  public function generate_fillable() {
    $this->pdf = new PdfToolkit($this->unique_id);
    $this->pdf
      ->SetPdfFilename($this->template, FALSE)
      ->SetDataFilename($this->formdata, TRUE);

    // Update the PDF with the Data from the datafile.
    if ($this->pdf->FillForm()) {

      if (!empty($this->barcode) || !empty($this->text)) {
        // Add flat elements if required.
        $flat_image = new PdfFilenames($this->outputname, FALSE);
        if ($this->makeBarcodePdf($flat_image, $this->barcode)) {
          $this->makeOverlayPdf($flat_image, $this->text);
          $flat_image = new PdfFilenames($flat_image->path);
          $this->pdf->SetOverlayFilename($flat_image, TRUE);
          if (!$this->pdf->AddOverlayToPdf()) {
            return FALSE;
          }
        }
      }

      $this->pdf->SetDocumentProperties($this->metadata_file->url);
      // Retrieve the document from the PdfToolkit endpoint and save locally.
      $this->pdf->DownloadFile();

      return $this->pdf->output_file;
    }
    return FALSE;
  }

  /**
   * Returns the template file location, creates folder if it does not exist.
   *
   * @return string
   */
  /**
   * Returns the template file location, creates folder if it does not exist.
   *
   * @return string
   */
  public function getTemplatePath(): string {
    if (empty($this->template_path)) {

      $this->template_path = \Drupal::service('file_system')
        ->realpath("public://pdf_templates");

      if (!file_exists($this->template_path)) {
        mkdir($this->template_path);
      }

    }

    return $this->template_path ?? "";
  }

  /**
   * Create a PDF with just a barcode in the desired place on each page.
   *
   * Typically, this is used for multistamp, adding a barcode to a fillable pdf
   * using pdftk library.
   *
   * @param PdfFilenames $outputfile The filename and path to create the pdf.
   * @param array $elements An array of pdfTextElement text definitions.
   * @param string $pagesize a string for the pagesize
   *
   * @return bool If the process succeeded
   */
  private function makeBarcodePdf(PdfFilenames &$outputfile, array $elements, string $pagesize = "letter"): bool {
    $tmpfiles = [];
    $pdf = new Fpdf_fpdf();
    if (!empty($this->barcode)) {
      for ($pageNo = 1; $pageNo <= array_key_last($this->barcode); $pageNo++) {
        $pdf->AddPage("P", $pagesize);
        if (isset($elements[$pageNo])) {
          $page = $elements[$pageNo];
          foreach ($page as $key => $element) {
            $barcode = new BarcodeGeneratorJPG();
            $tmpfilename = str_ireplace(".pdf", "_" . $this->unique_id . "_{$pageNo}{$key}.jpg", "{$this->tmppath->path}/{$outputfile->filename}");
            $color = $element->color ?? $this->default_color;
            if (file_put_contents($tmpfilename, $barcode->getBarcode($element->val, $element->encode, 2, 45, $color))) {
              $tmpfiles[] = $tmpfilename;
              $pdf->Image($tmpfilename, $element->x, $element->y);
            }
          }
        }
      }
    }
    else {
      // Add a page, so we can make a blank pdf as the output file.
      $pdf->AddPage("P", $pagesize);
    }

    // Write out a file, even if there is nothing there
    $output = str_ireplace(".pdf", "_" . $this->unique_id . ".pdf", "{$this->tmppath->path}/{$outputfile->filename}");
    $pdf->Output("F", $output);
    $outputfile->setPath($output);

    if ($outputfile->exists) {
      foreach ($tmpfiles as $file) {
        if (file_exists($file)) {
          unlink($file);
        }
      }
      $this->tmpfiles[] = $outputfile->path;
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Overlay text on a template pdf file.
   *
   * Typically, this is used for multistamp, adding uneditable text to a
   * fillable pdf using pdftk library.
   *
   * @param PdfFilenames $template The basefile to use as a template (pdf).
   * @param array $elements An array of pdfTextElement text definitions.
   *
   * @return bool If the file was written successfully.
   */
  private function makeOverlayPdf(PdfFilenames &$template, array $elements): bool {
    $pdf = new Fpdf_cob($this->tmppath->path, $this->unique_id, $this->default_font, $this->default_size, $this->default_color);
    // Load the source PDF.
    // Note using the FPDF library, the generated PDF will not be fillable.
    //  (even if the template was fillable)
    if (!$pageCount = $pdf->setSourceFile($template->path)) {
      return FALSE;
    }

    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {

      // Open each page in turn, and use as a template.
      if (!$templateId = $pdf->importPage($pageNo)) {
        return FALSE;
      }

      // Add a new page to the document, and then copy in the template page.
      $size = $pdf->getTemplateSize($templateId);
      $pdf->AddPage($size['orientation'], $size);
      $pdf->useTemplate($templateId);

      // Apply/overlay text and images (barcodes) as needed.
      $pdf->insertText($elements[$pageNo]??[]);
    }

    // Delete the current base file so we can replace it with this file
    unlink($template->path);

    // Output the new PDF doc
    $pdf->Output("F", $template->path);

    return TRUE;

  }

}

/**
 * Class: pdfTextElement
 *
 * Class used to manage a text object.
 */
class pdfTextElement {
  public int $x;
  public int $y;
  public string $font;
  public int $size;
  public array $color;
  public string $text;
  public function __construct(int $x, int $y, string $text, int $size = NULL, string $font = NULL, array $color = []) {
    $this->x = $x;
    $this->y = $y;
    $this->text = $text;
    if (!empty($font)) {
      $this->font = $font;
    }
    if (!empty($size)) {
      $this->size = $size;
    }
    if (!empty($color)) {
      $this->color = $color;
    }
    return $this;
  }
}

/**
 * Class: pdfBarcodeElement
 *
 * Class to manage a barcode object.
 */
class pdfBarcodeElement {

  const TYPE_CODE_32 = 'C32';
  const TYPE_CODE_39 = 'C39';
  const TYPE_CODE_39_CHECKSUM = 'C39+';
  const TYPE_CODE_39E = 'C39E'; // CODE 39 EXTENDED
  const TYPE_CODE_39E_CHECKSUM = 'C39E+'; // CODE 39 EXTENDED + CHECKSUM
  const TYPE_CODE_93 = 'C93';
  const TYPE_STANDARD_2_5 = 'S25';
  const TYPE_STANDARD_2_5_CHECKSUM = 'S25+';
  const TYPE_INTERLEAVED_2_5 = 'I25';
  const TYPE_INTERLEAVED_2_5_CHECKSUM = 'I25+';
  const TYPE_CODE_128 = 'C128';
  const TYPE_CODE_128_A = 'C128A';
  const TYPE_CODE_128_B = 'C128B';
  const TYPE_CODE_128_C = 'C128C';
  const TYPE_EAN_2 = 'EAN2'; // 2-Digits UPC-Based Extention
  const TYPE_EAN_5 = 'EAN5'; // 5-Digits UPC-Based Extention
  const TYPE_EAN_8 = 'EAN8';
  const TYPE_EAN_13 = 'EAN13';
  const TYPE_UPC_A = 'UPCA';
  const TYPE_UPC_E = 'UPCE';
  const TYPE_MSI = 'MSI'; // MSI (Variation of Plessey code)
  const TYPE_MSI_CHECKSUM = 'MSI+'; // MSI + CHECKSUM (modulo 11)
  const TYPE_POSTNET = 'POSTNET';
  const TYPE_PLANET = 'PLANET';
  const TYPE_RMS4CC = 'RMS4CC'; // RMS4CC (Royal Mail 4-state Customer Code) - CBC (Customer Bar Code)
  const TYPE_KIX = 'KIX'; // KIX (Klant index - Customer index)
  const TYPE_IMB = 'IMB'; // IMB - Intelligent Mail Barcode - Onecode - USPS-B-3200
  const TYPE_CODABAR = 'CODABAR';
  const TYPE_CODE_11 = 'CODE11';
  const TYPE_PHARMA_CODE = 'PHARMA';
  const TYPE_PHARMA_CODE_TWO_TRACKS = 'PHARMA2T';

  public int $x;
  public int $y;
  public string $val;
  public string $encode; // a constant from this class eg ::Code128 ('C128')
  public array $color;
  public function __construct(int $x, int $y, string $val, string $encode, array $color = []) {
    $this->x = $x;
    $this->y = $y;
    $this->val = $val;
    $this->encode = $encode;
    if (!empty($color)) {
      $this->color = $color;
    }
    return $this;
  }
}

/**
 * Class: PdfFilenames
 *
 * Class used to control the object structure for a full filename.
 * The class stores and calculates absolute paths, URL and Drupal route maps.
 */
class PdfFilenames {

  /**
   * @var string The public URL for the file (if any)
   */
  public string $url;

  /**
   * @var string The Drupal route for the file (if any)
   */
  public string $route;

  /**
   * @var string The absolute server path for the file.
   */
  public string $path;

  /**
   * @var string The actual filename for the file.
   */
  public string $filename;

  /**
   * @var string A checksum for the file (right now uses the modified date)
   */
  public string $checksum;

  /**
   * @var bool Does this file exist (if $path is provided)
   */
  public bool $exists;

  /**
   * @var bool Should this file be deleted during cleanup
   */
  public bool $delete;

  /**
   * Initializes the object. Takes the provided filename and tries to work out
   * if it is a URL, an absolute path or a Drupal route, and updates the object
   * accordingly.
   *
   * @param string $raw The raw file+path. Can be a url, a path or a Drupal
   * route.
   *
   * @throws \Exception
   */
  public function __construct(string $raw, bool $check_exists = TRUE) {

    global $base_url;

    $public_base = \Drupal::service('file_system')->realpath("public://");
    $this->delete = FALSE;

    // get the actual filename.
    $file = explode("/", $raw);
    $this->filename = array_pop($file);

    if (str_starts_with($raw, "//")) {
      // make this into a url.
      $raw = "https:{$raw}";
    }

    if (str_starts_with($raw, "public://")
      || str_starts_with($raw, "private://")) {
      if (str_starts_with($raw, "private://")) {
        $path = \Drupal::service('file_system')->realpath(str_replace($this->filename, '', $raw));
        copy($path, "{$public_base}/tmp/{$this->filename}");
        $this->delete = TRUE;
        $raw = "public://tmp/{$this->filename}";
      }
      $this->route = $raw;
      $this->setPath(str_replace('public://', '$public_base', $raw));
    }

    elseif (str_starts_with($raw, "http")) {
      // This looks like a url, so try to download the file
      $this->url = $raw;
      if (str_starts_with($raw, $base_url)) {
        // so this is one of ours
        $base_path = \Drupal::service('file_system')->realpath("");
        $this->setPath(str_replace($base_url, $base_path, $raw));
      }
    }

    else {
      // This looks like a (local server) file path.
      if (file_exists($raw)) {
        if (str_starts_with($raw, $public_base)) {
          // is in the path for public:// so just create url etc.
          $this->setPath($raw);
          $this->route = "public:/" . substr($raw, strlen($public_base));
        }
        else {
          // Copy the file to public://tmp and then create url etc.
          copy($raw, "{$public_base}/tmp/{$this->filename}");
          $this->delete = TRUE;
          $this->setPath("{$public_base}/tmp/{$this->filename}");
          $this->route = "public://tmp/{$this->filename}";
        }
      }
      else {
        $this->exists = FALSE;
        if ($check_exists) {
          throw new \Exception("File not found " . $raw);
        }
      }

    }

    if (isset($this->route)) {
      $this->url = \Drupal::service('file_url_generator')
        ->generateAbsoluteString($this->route);
    }

  }

  /**
   * Set the path, query the file properties and save in object.
   *
   * @param string $path an absolute path.
   *
   * @return void
   */
  public function setPath(string $path = "") {
    if (!empty($path)) {
      $this->path = $path;
      $this->exists = file_exists($path);
      $file = explode("/", $path);
      $this->filename = array_pop($file);
      $this->checksum = filemtime($path);
    }
  }

}
