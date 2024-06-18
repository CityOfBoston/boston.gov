<?php

namespace Drupal\bos_pdfmanager\Controller;

use \Drupal\bos_pdfmanager\Controller\PdfFilenames as pdfFilenames;

/**
 * Class: PdfToolkit
 *
 * Used to provide utilities to connect to the pdftk API wrapper.
 *
 * @see https://www.pdflabs.com/docs/pdftk-man-page
 * @see https://www.pdflabs.com/docs/pdftk-cli-examples/
 */
class PdfToolkit implements PdfManagerInterface {

  /**
   * @var pdfFilenames a base PDF file/template
   */
  private pdfFilenames $pdf_file;

  /**
   * @var pdfFilenames an FDF file
   */
  private pdfFilenames $data_file;

  /**
   * @var pdfFilenames A flat pdf to be overlaid (stamped) onto base pdf.
   */
  private pdfFilenames $overlay_file;

  /**
   * @var string The output file from last method called.
   */
  public string $output_file;

  /**
   * @var string The remote pdftk API endpoint
   */
  private string $host;

  /**
   * @var \CurlHandle The curl object
   */
  private \CurlHandle $ch;

  /**
   * @var array Array of files that can be deleted at end of processing.
   */
  protected array $tmpfiles;

  /**
   * @var CurlResponse Processwed response from last api call.
   */
  public CurlResponse $response;

  /**
   * @var string (typically timestamp) a unique id for filename generation.
   */
  private string $unique_id;

  /**
   * @var \Exception Holds any captured errors.
   */
  public \Exception $error;

  /**
   * Constants used to define the API endpoint protocol
   */
  public const POST = "POST";
  public const GET = "GET";
  public const DELETE = "DELETE";
  public const PATCH = "PATCH";

  /**
   * Constructor:
   *
   * Sets default class attributes.
   */
  public function __construct(string $unique_id = "") {
    $this->host = \Drupal::config("bos_pdfmanager.settings")->get("host");
    $this->tmpfiles = [];
    $this->unique_id = $unique_id === "" ? time() : $unique_id;
  }

  /**
   * Destructor:
   *
   * This cleans up any temp files created in-process "owned" by this class.
   */
  public function __destruct() {

    if (isset($this->ch)) {
      // Close any curl sessions using the handle.
      curl_close($this->ch);
    }

    // Remove any temporary files which are marked as deletable.
    foreach($this->tmpfiles as $key => $file) {
      if (file_exists($file)) {
        unlink($file);
      }
    }
  }

  /**
   * Set the PDF Form/Template filename.
   *
   * $filename is saved in $pdf_file.
   *
   * @param string $filename public:// route for the pdf.
   * @param bool $temp Mark this file for deletion after processing.
   *
   * @return \Drupal\bos_pdfmanager\Controller\PdfToolkit
   */
  public function SetPdfFilename (string $filename, bool $temp = TRUE): PdfToolkit {
    try {
      $this->pdf_file = new pdfFilenames($filename);
      $this->pdf_file->delete = $temp;
      if ($this->pdf_file->delete) {
        $this->tmpfiles[] = $this->pdf_file->path;
      }
    }
    catch (\Exception $e) {}
    finally {
      return $this;
    }
  }

  /**
   * Set the PDF Form data (fdf) filename.
   *
   * $filename is saved in $data_file.
   *
   * @param string $filename public:// route for the data file
   * @param bool $temp Mark this file for deletion after processing.
   *
   * @return \Drupal\bos_pdfmanager\Controller\PdfToolkit
   */
  public function SetDataFilename (string $filename, bool $temp = TRUE): PdfToolkit {
    try {
      $this->data_file = new pdfFilenames($filename);
      $this->data_file->delete = $temp;
      if ($this->data_file->delete) {
        $this->tmpfiles[] = $this->data_file->path;
      }
    }
    catch (\Exception $e){}
    finally {
      return $this;
    }
  }

  /**
   * Set the PDF barcode filename.
   *
   * $filename is saved in $barcode_file.
   *
   * @param string $filename public:// route for the data file
   * @param bool $temp Mark this file for deletion after processing.
   *
   * @return \Drupal\bos_pdfmanager\Controller\PdfToolkit
   */
  public function SetOverlayFilename(pdfFilenames $filename, bool $temp = TRUE): PdfToolkit {
    try {
      $this->overlay_file = $filename;
      $this->overlay_file->delete = $temp;
      if ($this->overlay_file->delete) {
        $this->tmpfiles[] = $this->overlay_file->path;
      }
    }
    catch (\Exception $e){}
    finally {
      return $this;
    }
  }

  /**
   * Fill out the PDF ($pdf_file) with the fdf data ($data_file) and save.
   *
   * The completed form is saved in the remote location $output_file.
   *
   * @return bool
   */
  public function FillForm (): bool {
    $endpoint = "{$this->host}/fill";
    $payload = [
      "formfile" => $this->pdf_file->url,
      "datafile" => $this->data_file->url,
      "checksum" => $this->pdf_file->checksum
    ];
    if (!$this->CreateCurlRequest($endpoint, self::POST, $payload, [])) {
      return FALSE;
    }
    if (!$this->ExecuteCurlRequest()) {
      return FALSE;
    }

    if ($this->response->httpcode != 200 || !isset($this->response->payload["output"])) {
      return FALSE;
    }

    $this->output_file = $this->response->payload["output"];
    return TRUE;
  }

  /**
   * Merge a fillable pdf ($pdf_file) with a barcode in pdf format ($barcode_file)
   *
   * The merged pdf with barcode is saved in the remote location $output_file.
   *
   * @return bool
   */
  public function AddOverlayToPdf(): bool {
    $endpoint = "{$this->host}/overlay";
    $payload = [
      "basefile" => $this->output_file,
      "overlayfile" => $this->overlay_file->url
    ];
    if (!$this->CreateCurlRequest($endpoint, self::POST, $payload, [])) {
      return FALSE;
    }
    if (!$this->ExecuteCurlRequest()) {
      return FALSE;
    }

    if ($this->response->httpcode != 200 || !isset($this->response->payload["output"])) {
      return FALSE;
    }

    $this->output_file = $this->response->payload["output"];
    return TRUE;
  }

  /**
   * @param pdfFilenames $filename filename to decompress
   * @param bool $delete whether the compressed file should be deleted on the
   *    remote server
   *
   *       Note: If $delete is FALSE, then the only way to have the file
   * re-decompressed is to restart the pdf endpoint (dbconnector), since the
   * compressed file is essentially being cached.
   * @return bool
   *
   */
  public function Decompress(pdfFilenames $filename, bool $delete = TRUE): bool {
    $endpoint = "{$this->host}/decompress";
    $payload = [
      "pdf_file" => $filename->url,
      "del" => $delete ? "true" : "false"
    ];
    if (!$this->CreateCurlRequest($endpoint, self::GET, $payload, [])) {
      return FALSE;
    }
    if (!$this->ExecuteCurlRequest()) {
      return FALSE;
    }

    if ($this->response->httpcode != 200 || !isset($this->response->payload["output"])) {
      return FALSE;
    }

    $this->output_file = $this->response->payload["output"];
    $this->tmpfiles[] = $this->output_file;
    return TRUE;
  }

  /**
   * Downloads a file from the remote endpoint, and saves locally.
   *
   * The filename and path for downloaded file is saved in $output_file.
   *
   * @return bool
   */
  public function DownloadFile(): bool {

    $endpoint = "{$this->host}/fetch";
    $payload = [
      "file" => $this->output_file,
      "del" => "true",
      "display" => "D"
    ];
    if (!$this->CreateCurlRequest($endpoint, self::GET, $payload, [])) {
      return FALSE;
    }
    if (!$this->ExecuteCurlRequest()) {
      return FALSE;
    }

    if ($this->response->httpcode != 200 || !isset($this->response->payload["output"])) {
      return FALSE;
    }

    $this->output_file = $this->response->payload["output"];
    $this->tmpfiles[] = $this->output_file;
    return TRUE;
  }

  /**
   * Downloads a file from the remote endpoint, and saves locally.
   *
   * The filename and path for downloaded file is saved in $output_file.
   *
   * @return bool
   */
  public function SetDocumentProperties(string $metadata_file): bool {

    $endpoint = "{$this->host}/metadata";
    $payload = [
      "pdf_file" => $this->output_file,
      "meta_data" => $metadata_file,
    ];
    if (!$this->CreateCurlRequest($endpoint, self::POST, $payload, [])) {
      return FALSE;
    }
    if (!$this->ExecuteCurlRequest()) {
      return FALSE;
    }

    if ($this->response->httpcode != 200 || !isset($this->response->payload["output"])) {
      return FALSE;
    }

    $this->output_file = $this->response->payload["output"];
    $this->tmpfiles[] = $this->output_file;
    return TRUE;
  }

  /**
   * Creates a curl object and loads the default request settings.
   *
   * @param string $ep The remote endpoint (a URL)
   * @param string $type GET/POST etc - can use class constant
   * @param array $payload An array of fields to pass to remote enspoint
   * @param array $headers Any custom headers (merged with defaults)
   *
   * @return \CurlHandle reference to curl object
   */
  protected function CreateCurlRequest(string $ep, string $type, array $payload, array $headers = []) {

    // The dbconnector is load balanced, we want to try to hold a connection to
    // a single instance, so we recycle the CuRL handle.

    if (!isset($this->ch)) {
      $this->ch = curl_init();
    }
    else {
      curl_reset($this->ch);
    }

    $curl_opts = [
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_SSL_VERIFYPEER => FALSE,
      CURLOPT_SSL_VERIFYHOST => FALSE,
      CURLOPT_TCP_KEEPALIVE => 1
    ];


    if (in_array(strtoupper($type), [self::POST, self::PATCH, self::DELETE])) {
      $curl_opts[CURLOPT_URL] = $ep;
      $curl_opts[CURLOPT_CUSTOMREQUEST] = "POST";
      $curl_opts[CURLOPT_POST] = 1;
      $curl_opts[CURLOPT_POSTFIELDS] = json_encode($payload);
      $curl_opts[CURLOPT_HTTPHEADER] = array_merge([
        "cache-control: no-cache",
        "Content-Type: application/json",
      ], $headers);
    }

    elseif (in_array(strtoupper($type), [self::GET])) {
      if (!empty($payload)) {
        if (is_array($payload)) {
          // Change array into a query string
          $query = "?" . $this->unique_id;
          foreach ($payload as $key => $value) {
            $value = rawurlencode($value);
            $query .= "&{$key}={$value}";
          }
          $ep = "{$ep}?{$query}";
        }
        elseif (is_string($payload)) {
          // assume payload is a query string - remove leading ? if exists
          $payload = trim($payload, "?\t\n\r\0\x0B");
          $ep = "{$ep}?{$payload}";
        }
      }
      $curl_opts[CURLOPT_URL] = $ep;
    }

    curl_setopt_array($this->ch, $curl_opts);

    return $this->ch;
  }

  /**
   * Executes the curl command and saves the results to the $results variable.
   *
   * @return bool
   */
  protected function ExecuteCurlRequest() {
    $headers = [];
    $this->response = new CurlResponse();

    curl_setopt($this->ch, CURLOPT_HEADERFUNCTION,
      // this function is called by curl for each header received
      function($curl, $header) use (&$headers) {
        $len = strlen($header);
        $header = explode(':', $header, 2);
        if (count($header) < 2) { // ignore invalid headers
          return $len;
        }
        $headers[strtolower(trim($header[0]))][] = trim($header[1]);

        return $len;
      }
    );

    try {
      if (!$result = curl_exec($this->ch)) {
        $e = curl_error($this->ch);
        throw new \Exception("CurlExecError: {$e}");
      }
    }
    catch (\Exception $e) {
      $this->response->error = $e;
      $this->error = $e;
      return FALSE;
    }

    if ($headers) {
      // decode the Json into an associative array.
      $this->response->content_type = $headers["content-type"][0] ?? "";
      if (str_contains($this->response->content_type, "application/json")) {
        $this->response->payload = json_decode($result, TRUE);
      }
      elseif (str_contains($this->response->content_type, "application/pdf")) {
        if (str_contains($headers["content-disposition"][0], "attachment")) {
          // save the file locally
          $path= \Drupal::service('file_system')->realpath("private://");
          $filename = "{$path}/" . $this->unique_id . ".pdf";
          foreach (explode(";", $headers["content-disposition"][0]) as $part) {
            if (str_contains($part, "filename")) {
              $part = str_replace("\"", "", $part);
              $filename = "{$path}/" . explode("=", $part)[1];
            }
          };
          file_put_contents($filename, $result);
          $this->response->payload["output"] = $filename;
        }
        else {
          $this->response->payload = $result;
        }
      }
      else {
        $this->response->payload = $result;
      }
    }
    else {
      $this->response->payload = $result;
    }
    $this->response->httpcode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);

    return TRUE;

  }
}

/**
 * Class: CurlResponse
 *
 * Class used to control the object structure for a curl response.
 */
class CurlResponse {

  /**
   * @var int Stores the return code for a Curl request against remote API.
   */
  public int $httpcode;

  /**
   * @var string Stores the content type returned from remote API (from header).
   */
  public string $content_type;

  /**
   * @var mixed Stores the data returned from remote API.
   */
  public $payload;

  /**
   * @var string Stores the error code (if any) from curl_exec.
   */
  public string $error;
}
