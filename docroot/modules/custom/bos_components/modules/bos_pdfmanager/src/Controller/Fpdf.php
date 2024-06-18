<?php

namespace Drupal\bos_pdfmanager\Controller;

use Picqer\Barcode\BarcodeGeneratorJPG;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\PdfParserException;
use setasign\Fpdi\PdfReader\PageBoundaries;
use setasign\Fpdi\PdfReader\PdfReaderException;
use \Drupal\bos_pdfmanager\Controller\PdfFilenames as pdfFilenames;
use \Drupal\bos_pdfmanager\Controller\pdfTextElement as pdfTextElement;
use \Drupal\bos_pdfmanager\Controller\pdfBarcodeElement as pdfBarcodeElement;

/**
 * Class: FpdfToolkit
 *
 * Used to provide utilities to connect to the FPDF library.
 *
 * FPDF used to create/manipulate PDF files.
 * @see http://www.fpdf.org/
 *
 * FDPI (extends FPDF) used to import and existing PDF
 * @see https://www.setasign.com/products/fpdi/manual/
 * @see https://www.setasign.com/products/fpdi-pdf-parser/details
 *
 * FPDM is a candidate for form filling, but the FPDF library flattens forms
 * so we opted to use pdftk (in class PdfToolkit) instead. Monitor link for
 * future review:
 * @see https://github.com/codeshell/fpdm
 */
class Fpdf extends Fpdi implements PdfManagerInterface {

  protected string $default_font;
  protected string $default_size;
  protected array $default_color;
  protected string $tmppath;
  /**
   * @var array Array of files that can be deleted at end of processing.
   */
  private array $tmpfiles;
  /**
   * @var string (typically timestamp) a unique id for filename generation.
   */
  private string $unique_id;
  /**
   * @var \Exception Holds any captured errors.
   */
  public \Exception $error;

  /**
   *    * Constructor:
   *
   * Sets default class attributes.
   *
   * @param string $tmp_path
   * @param string $unique_id
   * @param string $default_font
   * @param int $default_size
   * @param array $default_color
   */
  public function __construct(string $tmp_path, string $unique_id = "", string $default_font = "Helvetica", int $default_size = 12, array $default_color = [0,0,0]) {
    parent::__construct();
    $this->default_font = $default_font;
    $this->default_size = $default_size;
    $this->default_color = $default_color;
    $this->tmppath = $tmp_path;
    $this->tmpfiles = [];
    $this->unique_id = $unique_id === "" ? time() : $unique_id;
  }

  /**
   * Destructor:
   *
   * This cleans up any temp files created in-process "owned" by this class.
   */
  public function __destruct() {
    foreach($this->tmpfiles as $key => $file) {
      if (file_exists($file)) {
        unlink($file);
      }
    }
  }

  /**
   * @inheritDoc
   */
  public function SetSourceFile($file) {
    try {
      // Load the PDF.
      $page_count = parent::SetSourceFile($file);
    }
    catch (PdfParserException $e) {
      $this->error = new \Exception("PdfParserException: {$e->getMessage()}", $e->getCode());
      if ($e->getCode() == 267) {
        // This is an encoding error.  We can flatten the file.
        if (class_exists("Drupal\bos_pdfmanager\Controller\PdfToolkit")) {
          $pdftk = new PdfToolkit($this->unique_id);
          $file = new pdfFilenames($file);
          // Decompress the file, use FALSE flag to cache the decompressed file.
          // Note we will have to restart the remote pdf service to refresh the
          // cached decompressed file.
          if ($pdftk->Decompress($file, FALSE)) {
            unlink($file->path);
            rename($pdftk->output_file, $file->path);
            unset($pdftk);
            $this->tmpfiles[] = $file->path;
            try {
              // Load the PDF.
              $page_count = parent::SetSourceFile($file->path);
            }
            catch (PdfParserException $e) {
              // Ugh die, return zero pages found.
              return 0;
            }
          }
          else {
            return 0;
          }
        }
      }
      else {
        // Return zero pages found.
        return 0;
      }
    }
    parent::SetFont($this->default_font);
    parent::SetFontSize($this->default_size);
    parent::SetTextColor($this->default_color[0], $this->default_color[1], $this->default_color[2]);
    return $page_count;
  }

  /**
   * @inheritDoc
   */
  public function ImportPage($pageNumber, $box = PageBoundaries::CROP_BOX, $groupXObject = TRUE, $importExternalLinks = FALSE) {
    try {
      $a = parent::ImportPage($pageNumber);
    }
    catch (PdfReaderException | \Exception $e) {
      return FALSE;
    }
    return $a;
  }

  /**
   * Set the title, author and subject for the loaded PDF document.
   *
   * @param array $meta_data
   *
   * @return bool
   */
  public function SetMetaData(array $meta_data): bool {
    parent::SetAuthor(!empty($meta_data["author"]) ? $meta_data["author"] : "City of Boston");
    parent::SetTitle(!empty($meta_data["title"]) ? $meta_data["title"] : "Official PDF Document");
    parent::SetSubject(!empty($meta_data["subject"]) ? $meta_data["subject"] : "");
    return TRUE;
  }

  /**
   * Insert the text objects on current page (overwrite on the current page).
   *
   * @param array $text_elements An array of pdfTextElement text elements.
   *
   * @return void
   */
  public function insertText(array $text_elements): void {
    if (!empty($text_elements)) {
      /**
       * @var $element pdfTextElement
       */
      foreach ($text_elements as $element) {

        // Override the default text attributes.
        if (isset($element->font)) {
          parent::SetFont($element->font);
        }
        if (isset($element->size)) {
          parent::SetFontSize($element->size);
        }
        if (isset($element->color)) {
          parent::SetTextColor($element->color[0], $element->color[1], $element->color[2]);
        }

        parent::Text($element->x, $element->y, trim($element->text));

        // Reset text attributes to default (if they were set).
        if (isset($element->font)) {
          parent::SetFont($this->default_font);
        }
        if (isset($element->size)) {
          parent::SetFontSize($this->default_size);
        }
        if (isset($element->color)) {
          parent::SetTextColor($this->default_color[0], $this->default_color[1], $this->default_color[2]);
        }

      }
    }
  }

  /**
   * Insert barcode (insert an image into the current page)
   *
   * @param array $barcodes An array of pdfBarcodeElement elements.
   * @param int $pageNo The current page number.
   *
   * @return void
   */
  public function insertBarcode(array $barcodes, int $pageNo): void {
    if (!empty($barcodes)) {
      /**
       * @var $element pdfBarcodeElement
       */
      foreach ($barcodes as $key => $element) {
        $barcode = new BarcodeGeneratorJPG();
        $tmpfilename = "{$this->tmppath}/barcode_{$this->unique_id}_${pageNo}{$key}.jpg";
        $color = empty($element->color) ? $this->default_color : $element->color;
        if (file_put_contents($tmpfilename, $barcode->getBarcode($element->val, $element->encode, 2, 45, $color))) {
          $this->tmpfiles[] = $tmpfilename;
          parent::Image($tmpfilename, $element->x, $element->y);
        }
      }
    }
  }

  /**
   * @inheritDoc
   */
  function Output($dest = '', $name = '', $isUTF8 = FALSE) {
    return parent::Output($dest, $name, $isUTF8);
  }


}
