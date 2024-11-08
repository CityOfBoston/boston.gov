<?php

namespace Drupal\bos_search;

use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;

/**
 * Class AiSearch utility class.
 *
 * Provides methods to handle search presets and themes for the AI search
 * functionality.
 */
class AiSearch {

  /**
   * Retrieves the preset value from the form state, request, or session.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface|null $form_state
   *   The current state of the form.
   * @param \Drupal\node\Entity\Node|null $node
   *   The current node entity.
   *
   * @return string
   *   The preset value.
   */
  public static function getPreset(array $form = [], ?FormStateInterface $form_state = NULL, ?Node $node = NULL):string {

    // If the form State has a value for preset, then return it.
    if ($form_state && $form_state->hasValue("preset") ?: FALSE) {
      return $form_state->getValue("preset");
    }

    // Get the preset from the request object.
    $request = \Drupal::request();
    if ($request->query->has('preset')) {
      return $request->query->get('preset');
    }

    // If node is present.
    if ($node) {
      $preset = $_SESSION['bos_search']['block_preset'][$node->id()] ?: FALSE;
      if ($preset) {
        return $preset;
      }
      else {
        $preset = self::getNodeBlock(['aienabledsearchbutton', 'aienabledsearchform']);
        if(is_string($preset)) {
          $_SESSION['bos_search']['block_preset'][$node->id()] = $preset;
          return $preset;
        }
      }
    }

    // Return the first preset as a default.
    return array_key_first(self::getPresets());

  }

  /**
   * Retrieves preset values from the configuration.
   *
   * @param string $preset_name
   *   The name of the preset to retrieve. If not provided, defaults to the preset
   *   obtained from the method `getPreset()`.
   *
   * @return array
   *   An array of configuration values associated with the given preset name,
   *   or an empty array if the preset name is not found.
   */
  public static function getPresetValues(string $preset_name = ""): array {
    if ($preset_name == "") {
      $preset_name = self::getPreset();
    }
    $config = \Drupal::config("bos_search.settings")->get("presets");
    if (empty($preset_name)) {
      return [];
    }
    else {
      return $config[$preset_name] ?? [];
    }
  }

  /**
   * Retrieves an array of preset configurations.
   *
   * This format is suitable for options in select form objects.
   *
   * @return array
   *   An associative array of presets, where the key is the preset identifier
   *   and the value is the preset name.
   */
  public static function getPresets(): array {
    $config = \Drupal::config("bos_search.settings")->get("presets") ?? [];
    $output = [];
    foreach ($config as $cid => $preset) {
      $output[$cid] = $preset["name"];
    }
    return $output;
  }

  /**
   * Generates a machine-readable name from the given input.
   *
   * The new string can be used as a valid drupal machine id.
   *
   * @param string $name
   *   The input string to be converted into a machine-readable format.
   *
   * @return string
   *   The machine-readable name, which consists of lowercase letters and
   *   underscores.
   */
  public static function machineName(string $name):string {
    return strtolower(preg_replace('/[^a-zA-Z0-9_]+/', '_', $name));
  }

  /**
   * Sanitizes a given string by trimming whitespace.
   *
   * @param string $string
   *   The string to be sanitized.
   *
   * @return string
   *   The sanitized string with leading and trailing whitespace removed.
   */
  public static function sanitize(string $string): string {
    // TODO: Do we want to add profanity filters or other forms of sanitation here?
    return (trim($string));
  }

  /**
   * Retrieves a list of form themes available in the specified directory.
   *
   * @return array
   *   An associative array where the keys are the folder names and the values
   *   are the formatted theme names.
   */
  public static function getFormThemes(): array {
    $folders = glob(\Drupal::service("extension.list.module")->getPath('bos_search') . "/templates/presets/*", GLOB_ONLYDIR);
    $themes = [];
    foreach($folders as $folder) {
      $folder = basename($folder);
      $themes[$folder] = ucwords(str_replace(["_", "-"], " ", $folder));
    }
    return $themes;
  }

  /**
   * Retrieves form templates based on the specified theme.
   *
   * Scans the provided folder's 'presets' subfolder and gets a list of
   * implemented templates to be used for the overall search theme for the
   * main search form.
   *
   * The array has an index with the filename stripped of "html.twig" extension
   * with "-" replacing underscores in the filename.
   * The array values are a generated human-readable name for the filename by
   * replacing all underscores spaces.
   *
   * @param string $theme
   *   The theme for which to retrieve form templates.
   *
   * @return array
   *   An associative array of form template names with their prettified
   *   versions.
   */
  public static function getFormTemplates(string $theme): array {
    $files = glob(\Drupal::service("extension.list.module")->getPath('bos_search') . "/templates/presets/{$theme}/*.html.twig");
    $templates = [];
    foreach($files as $file) {
      $twig = basename($file);
      $template = str_replace(".html.twig", "", $twig);
      $templates[$template] = ucwords(str_replace(["_", "-"], " ", $template));
    }
    return $templates;
  }

  /**
   * Determines if the current route or node needs the bos_search theme.
   *
   * This method checks if the current request route matches specific forms
   * such as the disclaimer form, AISearch form, or the AISearch Config form.
   * Additionally, it checks if the node has a block displayed within it that
   * requires the bos_search theme.
   *
   * @return bool
   *   TRUE if the current route or node needs the bos_search theme, else FALSE.
   */
  public static function isBosSearchThemed(): bool {

    // Is this the disclaimer form?
    if (\Drupal::request()->attributes->get("_route") == "bos_search.open_DisclaimerForm") {
      return TRUE;
    }

    // Is this the AISearch form?
    if (\Drupal::request()->attributes->get("_route") == "bos_search.open_AISearchForm") {
      return TRUE;
    }

    // Is this the AISearch Config form?
    if (\Drupal::request()->attributes->get("_route") == "bos_search.AiSearchConfigForm") {
      return TRUE;
    }

    // If this is a node, check if the node has a block displayed within it.
    if (!empty(\Drupal::request()->attributes->get("node"))) {
      return self::hasNodeBlock(['aienabledsearchbutton', 'aienabledsearchform']);
    }

    // Don't appear to need the bos_search theme, return false.
    return FALSE;
  }

  /**
   * Checks if a node block exists in the target blocks array.
   *
   * Use to report if the current node will display any blocks which have been
   * created and placed based on the supplied $targetblock definitions.
   *
   * @param array $targetblocks
   *   The array of target blocks to search for a node block.
   *
   * @return bool
   *   TRUE if the node block exists, FALSE otherwise.
   */
  public static function hasNodeBlock(array $targetblocks) {
    return !self::getNodeBlock($targetblocks) === FALSE;
  }

  /**
   * Retrieves a node block based on specified target block IDs.
   *
   * Determine if the current node will show any blocks which implement any of
   * the $targetblock defintions.
   *
   * @param array $targetblocks
   *   An array of target block IDs to search for.
   *
   * @return mixed
   *   The configuration preset if found, TRUE if a matching condition is found,
   *   or FALSE if no matching conditions are found or the block is not
   *   configured to display.
   */
  public static function getNodeBlock(array $targetblocks) {

    // First find all the blocks created and placed using the block templates.
    $blocks = \Drupal::entityTypeManager()->getStorage('block')->getQuery()
      ->accessCheck(TRUE);
    $or_group = $blocks->orConditionGroup();
    foreach ($targetblocks as $targetblock) {
      $or_group = $or_group->condition('id', $targetblock, 'CONTAINS');
    }
    $blocks->condition($or_group);
    $blocks = $blocks->execute();

    // Now see if the block is configured to display on this node.
    foreach ($blocks as $blockname) {
      $block = \Drupal::entityTypeManager()
        ->getStorage('block')
        ->load($blockname);
      foreach ($block->getVisibilityConditions() as $condition) {
        if ($condition->evaluate()) {
          // Soon as you find a matching condition return.
          $settings = $block->get("settings") ?: [];
          if (!empty($settings["aisearch_config_preset"])) {
            return $settings["aisearch_config_preset"];
          }
          return TRUE;
        }
      }
    }

    return FALSE;

  }

  /**
   * Sets a custom session cookie.
   *
   * @param string $key
   *   The key of the session cookie to set.
   * @param string|bool|array $value
   *   The value to set for the session cookie. If an array is provided, it
   *   will be serialized.
   *
   * @return void
   *   This method does not return a value.
   */
  public static function setSessionCookie(string $key, string|bool|array $value = TRUE):void {
    // Set a custom session cookie.
    if (session_status() == PHP_SESSION_NONE) {
      @session_start();
    }
    if (is_array($value)) {
      $value = serialize($value);
    }
    $_SESSION[$key] = base64_encode($value);
  }

  /**
   * Retrieves a custom session cookie by key.
   *
   * @param string $key
   *   The key of the session cookie to retrieve.
   *
   * @return string|array
   *   The decoded session cookie (bools converted to int), or FALSE if not set.
   */
  public static function getSessionCookie(string $key): string|array {
    // Set a custom session cookie.
    if (session_status() == PHP_SESSION_NONE) {
      @session_start();
    }
    if (empty($_SESSION['shown_search_disclaimer'])) {
      return FALSE;
    }
    return base64_decode($_SESSION['shown_search_disclaimer']);
  }

}
