<?php
namespace Drupal\node_elections;

use Drupal;
use Drupal\node_elections\Form\ElectionUploaderForm;

class ElectionUtilities {

  // Array used in $this->capitalizeName() to override the default app settings.
  // The key in this array is always uppercase so input from the source file
  // can be checked.
  private array $corrections;

  public function __construct() {

    $this->corrections = [];

    $settings = Drupal::config("node_elections.settings");
    foreach(["corrected_fullname", "corrected_parts"] as $key) {
      $names = ElectionUploaderForm::stringifyReplacements($settings->get($key) ?? "{}");
      foreach (explode("\n", $names) as $name) {
        $parts = explode(":", $name);
        if (!empty($parts[0])) {
          $this->corrections[strtoupper($parts[0])] = $parts[1];
        }
      }
    }

  }

  /**
   * Attempt to properly capitalize a full name regardless of its original
   * capitalization.
   * NOTE: Will make text substitutions based on the replacements from the
   * config form.
   *
   * @param $fullname
   *
   * @return string
   */
  public function capitalizeName($fullname) {
    if ($this->nameHasCorrection($fullname)) {
      // There is an override for this $fullname
      return $this->correctName(strtoupper($fullname));
    }

    $nameparts = [];
    foreach (explode(" ", $fullname) as $elem) {
      foreach (explode("-", $elem) as $key => $namepart) {
        $namepart = strtolower($namepart);
        if (in_array(strtolower($namepart), ["and"])) {
          // This is to flag that we could get "Person AND Person" as the name,
          // but in terms of capitalization, completely ignore and do nothing.
          // Add additional nameparts to ignore to the in_array array.
        }
        elseif ($this->nameHasCorrection($namepart)) {
          // There is an override for this $namepart
          // IMPORTANT - do this before other else-ifs otherwise the override
          // could be stepped over.
          $namepart = $this->correctName($namepart);
        }
        elseif (in_array($namepart, ["ii", "iii", "iv", "v"])) {
          $namepart = strtoupper($namepart);
        }
        elseif (str_starts_with($namepart, "mc")
          || str_starts_with($namepart, "mac")) {
          // DIG-4111 improves this text substitution to handle O'Brien as well
          // as MacDonald etc.
          $namepart = preg_replace_callback("/^(ma?c)(.*)/", function($m) { return ucwords($m[1]) . ucwords($m[2]); }, $namepart);
        }
        elseif (str_contains($namepart, "'")
          || str_contains($namepart, '"')) {
          $namepart = preg_replace_callback("/^(\w*)(['\"])(\w*)/", function($m) { return ucwords($m[1]) . $m[2] . ucwords($m[3]); }, $namepart);
        }
        elseif (str_contains($namepart, ".")) {
          $namepart = strtoupper($namepart);
        }
        else {
          $namepart = ucwords($namepart);
        }
        $nameparts[] = $key > 0 ? "-{$namepart}" : " $namepart";
      }
    }
    return implode("", $nameparts);
  }

  /**
   * TRUE if the fullname has a correction entered on the config form.
   * NOTE: The match is case-insensitive.
   *
   * @param string $fullname Full Name, must be a exact match for replacement.
   *
   * @return bool
   */
  public function nameHasCorrection(string $name): bool {
    return array_key_exists(trim(strtoupper($name)), $this->corrections);
  }

  /**
   * Replaces candidate names with updated name.
   *
   * @param string $fullname
   *
   * @return string
   */
  public function correctName(string $name): string {
    if ($this->nameHasCorrection($name)) {
      return $this->corrections[trim(strtoupper($name))];
    }
    return $name;
  }

}
