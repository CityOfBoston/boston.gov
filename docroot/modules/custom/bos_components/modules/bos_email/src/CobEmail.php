<?php

namespace Drupal\bos_email;

use Drupal\Component\Utility\Xss;
use Exception;

class CobEmail {

  private array $data = [
    "To" => "",
    "ReplyTo" => "",
    "Cc" => "",
    "Bcc" => "",
    "From" => "",
    "Subject" => "",
    "TextBody" => "",
    "HtmlBody" => "",
    "Tag" => "",
    "Headers" => "",
    "Metadata" => "",
    "TemplateID" => "",
    "TemplateModel" => [
      "subject" => "",
      "TextBody" => "",
      "HtmlBody" => "",
      "ReplyTo" => "",
    ],
    "endpoint" => "",
    "server" => "",
    "service" => "",
    "senddatetime" => "",
  ];

  private array $fieldTypes = [
    "server" => "string",
    "To" => "email",
    "ReplyTo" => "email",
    "Cc" => "email",
    "Bcc" => "email",
    "From" => "email",
    "Subject" => "string",
    "TextBody" => "string",
    "HtmlBody" => "html",
    "Tag" => "string",
    "Headers" => "anyarray",
    "Metadata" => "anyarray",
    "TemplateID" => "string",
    "TemplateModel" => [
      "subject" => "string",
      "TextBody" => "string",
      "HtmlBody" => "html",
      "ReplyTo" => "email",
    ],
    "service" => "string",
    "endpoint" => "string",
    "senddatetime" => "string",
  ];

  private array $requiredFields = [
    "To",
    "From",
    "endpoint",
    "server",
    "service"
  ];

  public const FIELD_STRING = "string";
  public const FIELD_ARRAY = "array";
  public const FIELD_EMAIL = "email";
  public const FIELD_HTML = "html";
  public const FIELD_NUMBER = "number";

  public const HANDLER_POSTMARK = "postmark";
  public const HANDLER_DRUPALMAIL = "drupalmail";

  public const ENCODE = 0;
  public const DECODE = 1;

  /**
   * @const An array of headers to retain when processing header arrays.
   */
  private const KEEP_HEADERS = [
    "Message-ID",
    "References",
    "In-Reply-To",
    "X-Auto-Response-Suppress",
    "auto-submitted",
    "MIME-Version",
    "X-OriginatorOrg",
  ];

  /**
   * @const string The default domain for encoding and decoding.
   */
  private const OUTBOUND_DOMAIN = "web-inbound.boston.gov";

  /**
   * @var array Array of errors from last validation.
   */
  public array $validation_errors = [];

  /**
   * Initialize the class object.
   *
   * @param array $data [OPTIONAL] An array of fields to add to the object
   *
   * @throws \Exception
   */
  public function __construct(array $data = []) {

    $this->data = array_merge($this->data, $data);

    if (!empty($data)) {
      // Only bring in fields which are pre-defined in $this->emailFields.
      foreach ($data as $field => $value) {
        if ($this->hasField($field)) {
          $this->setField($field, $value);
        }
      }
      // Run an initial validation -but don't fail if valoidation fails (just
      // set the validation_errors field).
      $this->validate($this->data);
    }

    return $this->data;

  }

  /**
   * Validates that each field in $this->emailFields is in the expected format,
   * and that required fields are present and not empty.
   *
   * @param array $data
   *
   * @return bool TRUE if validated, FALSE if not.  If fails, then inspect
   * $this->validation_errors for causes.
   */
  public function validate(array $data = []):bool {

    $this->validation_errors = [];

    if (empty($data)) {
      $data = $this->data;
    }

    $validated = TRUE;

    // check emails
    foreach($this->fieldTypes as $field => $type) {
      if ($type == self::FIELD_EMAIL) {
        $value = $data[$field];
        if (!empty($value)) {
          $emails = explode(",", $value);
          foreach ($emails as $email) {
            $emailparts = explode("<", trim($email));
            $mail = trim(array_pop($emailparts), " >");
            if (!\Drupal::service('email.validator')->isValid($mail)) {
              $this->validation_errors[] = "{$field} email is not valid ({$value})";
              $validated = FALSE;
            }
          }
        }

      }
    }

    // check required fields
    foreach ($this->requiredFields as $field) {
      if (empty($data[$field])) {
        $this->validation_errors[] = "{$field} cannot be empty";
        $validated = FALSE;
      }
    }

    if (empty($data["TemplateID"]) && empty($data["TemplateAlias"])) {
      // No template
      if (empty($data["HtmlBody"]) && empty($data["TextBody"])) {
        $this->validation_errors[] = "Must have Html or Text Body for email";
        $validated = FALSE;
      }
      if (empty($data["Subject"])) {
        $this->validation_errors[] = "Must have Subject for email";
        $validated = FALSE;
      }
    }
    else {
      // Using template.
      if (empty($data["TemplateModel"]["TextBody"])) {
        $this->validation_errors[] = "Must have Text Body for templated email";
        $validated = FALSE;
      }
      if (!empty($data["Subject"]) || !empty($data["TextBody"]) || !empty($data["HtmlBody"])) {
        $this->validation_errors[] = "Templates cannot have TextBody, HtmlBody or Subject fields";
        $validated = FALSE;
      }

    }

    if (isset($data["senddatetime"])) {
      // Quick bit of validation when an item is to be scheduled.
      if ($data["senddatetime"] === 0 || !is_numeric($data["senddatetime"])) {
        // If the scheduled date === 0 then we could not parse a datetime from
        // the string in the payload (in class fn setSendDate()).
        // If it's not numeric then something went wrong somewhere.
        $this->validation_errors[] = "Could not evaluate scheduled date.";
        $validated = FALSE;
      }
      elseif (intval($data["senddatetime"]) < strtotime("now")) {
        // Cannot schedule in the past.
        $this->validation_errors[] = "Scheduled date is in the past.";
        $validated = FALSE;
      }
      elseif (($data["senddatetime"] - strtotime("now")) > (400 * 24 * 60 * 60)) {
        // Only allow emails to be scheduled 400 days in advance.
        // Helps prevent date coding issues.
        // (Still allows for an annual reminder for example).
        $this->validation_errors[] = "Emails can only be scheduled up to 400 days in advance.";
        $validated = FALSE;
      }
    }

    return $validated;
  }

  /**
   * Returns the validation errors - empty array if nothing.
   *
   * @return array Array of errors from last validation.
   */
  public function getValidationErrors(): array {
    return $this->validation_errors;
  }

  /**
   * Returns whether the last validation run had errors.
   *
   * @return bool TRUE if errors else FALSE
   */
  public function hasValidationErrors(): bool {
    return !empty($this->validation_errors);
  }

  /**
   * Sanitize the fields in the passed array, and return the fields.
   *
   * @param array|string|numeric $data An array of data to sanitize
   * (defaults to $this->>emailFields)
   *
   * @return array The sanitized array.
   * @throws \Exception
   */
  private function sanitize($data = []): array {

    if (empty($data)) {
      $data = $this->data;
    }

    foreach ($data as $field => &$value) {
      $value = $this->sanitizeField($field, $value);
    }

    return $data;

  }

  /**
   * Sanitizes a value for a single field.
   * Removes any XSS or other unwanted html tags from $value based on the field
   * type defined in $this->$fieldTypes.
   * NOTE: The field value is not set, just returns sanitized $value.
   *
   * @param string $field The destination field
   * @param array|string|number $value The proposed value to set
   *
   * @return array|mixed|string|string[]|null The sanitized value.
   * @throws \Exception If the field cannot be found, if the field is an object,
   *   if the field is not generally in the expected format.
   */
  private function sanitizeField(string $field, $value) {

    if ($type = $this->fieldTypes[$field]) {

      if (empty($value)) {
        return $value;
      }

      if (is_array($type)) {
        // Todo fix so arrays can be validated
        return $value;
      }

      if (is_object($value)) {
        throw new \Exception("Unexpected object for {$field} ({$type})");
      }

      switch ($type) {
        case "string":
        case "number":
          if (is_array($value)) {
            throw new \Exception("Unexpected array for {$field} ({$type})");
          }
          $value = Xss::filter($value);
          return strip_tags($value);
          break;

        case "email":
          if (is_array($value)) {
            throw new \Exception("Unexpected array for {$field} ({$type})");
          }
          if ($extended = preg_match("/<(.*?)>/", $value)) {
            $value = preg_replace("/<(.*?)>/", "^^$1^^", $value);
          }
          $value = Xss::filter($value);
          $value = strip_tags($value);
          if ($extended) {
            $value = preg_replace("/\^\^(.*?)\^\^/", "<$1>", $value);
          }
          return $value;
          break;

        case "html":
          if (is_array($value)) {
            throw new \Exception("Unexpected array for {$field} ({$type})");
          }
          $taglist = array_merge(["html", "style", "title", "body"], Xss::getAdminTagList());
          return Xss::filter($value, $taglist);
          break;

        case "anyarray":
          if (!empty($value) && !is_array($value)) {
            throw new \Exception("Expected array for {$field}");
          }
          return $value;
          break;

        default:
          throw new \Exception("Unknown field type {$field}({$type})");

      }
    }
    throw new \Exception("Field {$field} not found");
  }

  /**
   * Adds a new field to the object.
   *
   * @param string $field The field to create
   * @param string $type The type (use object constants)
   * @param $value The value to set.
   *
   * @return array The current sanitized but unvalidated fields in the object.
   * @throws \Exception - if the type is not "string|array|email|html|number",
   *  or (bubbles up) if the field cannot be sanitized.
   */
  public function addField(string $field, string $type, $value = ""): array {

    if (!array_key_exists($field, $this->fieldTypes)) {
      if (!in_array($type, ["string", "array", "email", "html", "number"])) {
        throw new \Exception("Unacceptable field type.");
      }
      $this->fieldTypes[$field] = $type;
    }

    $this->setField($field, $value);

    return $this->data;

  }

  /**
   * Sets the value of a field.
   * Sanitizes the field to make sure it is the expected format and it XSS safe.
   *
   * @param string $field the field to set.
   * @param mixed $value The value to set.
   *
   * @return array The current sanitized but unvalidated fields in the object.
   * @throws \Exception (bubbles up) if the field cannot be sanitized.
   */
  public function setField(string $field, $value = ""): array {

    if ($field == "senddatetime") {
      // Force the senddatetime field through its own function which converts
      // the value to a unix timestamp.
      $this->setSendDate($value);
    }
    else {
      $this->data[$field] = $this->sanitizeField($field, $value);
    }

    return $this->data;

  }

  /**
   * Returns the current value of a field, or FALSE if the field does not exist.
   *
   * @param string $field
   *
   * @return false|mixed
   */
  public function getField(string $field) {
    return $this->data[$field] ?? FALSE;
  }

  /**
   * Removes a field from the object.
   * The field is unset and will not be returned in the object->data() function.
   * Will not delete protected/required fields (throws error).
   *
   * @param string $field The field to remove.
   *
   * @return void
   * @throws \Exception If field cannot be removed b/c is required.
   */
  public function delField(string $field): void {
    if ($this->hasField($field)) {

      if (in_array($field, $this->requiredFields)) {
        throw new \Exception("Cannot delete required field {$field}");
      }

      unset($this->data[$field]);
      if (isset($this->fieldTypes[$field])) {
        unset($this->fieldTypes[$field]);
      }
    }
  }

  /**
   * Returns TRUE if a field exists, else FALSE.
   *
   * @param $field string A field to check
   *
   * @return bool
   */
  public function hasField($field): bool {
    return isset($this->data[$field]);
  }

  /**
   * Valodates and returns ALL the fields added to the object as an array.
   *
   * @return array|false The emailFields currently set in the object.
   * @throws \Exception If validation fails.
   */
  public function data(bool $validate = TRUE): array {
    if (!$validate) {
      return $this->data;
    }
    if ($this->validate($this->data)) {
      return $this->data;
    }
    throw new \Exception("Validation errors occurred");
  }

  /**
   * Generate a legitimate FAKE email address comprised of the hash of
   * an actual (original) email address prefixed to a specified domain.
   * This way the original email can be obviscated and/or a sender from a trusted
   * domain can be generated.
   *
   * If the specified domain can receive incoming email, then replies to the
   * FAKE email can be deconstructed (using decodeEmail()) to extract the
   * original email address for additional processing.
   *
   * @param string $email The original email address
   * @param string $domain The trusted domain use for the FAKE email.
   *
   * @return string a new legitimate but FAKE email address.
   */
  public static function encodeFakeEmail(string $email, string $domain = ""): string {

    if (empty($domain)) {
      $domain = self::getDomain();
    }

    $hash = self::hashText($email, self::ENCODE);
    return "{$hash}@{$domain}" ;
  }

  /**
   * Extract an email address from a FAKE email generated by encodeEmail().
   *
   * @param string $email The FAKE email address.
   * @param string $domain The expected domain use for the FAKE email.
   * @param bool $strict Return $email if the domain is not found in $email.
   *
   * @return string a new legitimate but FAKE email address, or the FAKE email
   *    address.
   */
  public static function decodeFakeEmail(string $email, string $domain = "", $strict = TRUE): string {

    if ($strict) {

      if (empty($domain)) {
        $domain = self::getDomain();
      }

      if (!str_contains($email, $domain)) {
        return $email;
      }

    }

    $original_recipient = explode("@", $email)[0];
    $original_recipient = self::hashText($original_recipient, self::DECODE);

    // Verify the original recipient
    if (preg_match('/[^A-Za-z0-9_@!#~$%=\*\^\+\-\.]/', $original_recipient) == 1) {
      // This did not decode well, it has chars we do not expect, so probably
      // was not a base64_encoded string originally.
      $original_recipient = "david.upton@boston.gov";
    }

    return $original_recipient;

  }

  /**
   * Adds an array of headers to the email object, optionally keeping just the
   * elements provided in the $keep array. If $keep = NULL, then will use the
   * default KEEP_HEADERS constant (array).  TIP: Pass $keep = [] to retain all
   * array elements in $headers.
   *
   * @param \Drupal\bos_email\CobEmail $email The email object
   * @param array $headers An array of headers to process
   * @param array $keep An array of header elements to retain when processing.
   *
   * @return void
   * @throws \Exception
   */
  public function processHeaders(array $headers, array $keep = self::KEEP_HEADERS): void {

    if (!empty($keep)) {
      $_headers = [];
      foreach ($headers as $header) {
        if (in_array($header->Name, $keep)) {
          $_headers[] = $header;
        }
      }
      $headers = $_headers;
    }

    if (!empty($headers)) {
      $this->setField("Headers", $headers);
    }
  }

  /**
   * Encodes or decodes (simple base64) a string and returns a text-character-only
   * hash of a supplied string.
   *
   * @param string $text The string to encode/decode.
   * @param int $flag Flag to indicate encoding or decoding.
   *
   * @return false|string The processed string.
   */
  public static function hashText(string $text, int $flag = self::ENCODE) {
    switch ($flag) {
      case self::ENCODE:
        return base64_encode($text);
        break;
      case self::DECODE:
        return base64_decode($text);
        break;
    }
  }

  /**
   * Find the current domain.
   *
   * @return string
   */
  private static function getDomain(): string {
    $domain = array_reverse(explode(".", \Drupal::request()->getHttpHost()));
    array_pop($domain);
    return implode(".", array_reverse($domain));
  }

  /**
   * Removes empty elements from the emailfields array
   *
   * @return void
   * @throws \Exception
   */
  public function removeEmpty() {
    foreach($this->data as $key => $value) {
      if ($value == "" && !in_array($key, $this->requiredFields)) {
        $this->delField($key);
      }
    }
  }

  /**
   * Returns true if the emailfields["senddatetime"] field is not empty.
   *
   * @return bool
   */
  public function is_scheduled():bool {
    return isset($this->data["senddatetime"]);
  }

  /**
   * Adds the scheduled time to the object as a valid UNIX timestamp, or 0
   * if it could not be evaluated.
   *
   * @param int|string $value a unix timestamp, or a valid date string
   *
   * @return void
   */
  public function setSendDate(int|string $value) {
    // Note: Setting 0 as the scheduled time will validate but will raise an
    // error later.
    try {
      if (is_numeric($value)) {
        $this->data["senddatetime"] = $this->sanitizeField("senddatetime", $value);
      }
      else {
        $value = strtotime($value);
        $this->data["senddatetime"] = $this->sanitizeField("senddatetime", $value ?: 0);
      }
    }
    catch (Exception $e) {
      $this->data["senddatetime"] = $this->sanitizeField("senddatetime", 0);
    }
  }

}
