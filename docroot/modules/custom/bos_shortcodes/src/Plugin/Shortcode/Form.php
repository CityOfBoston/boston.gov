<?php

namespace Drupal\bos_shortcodes\Plugin\Shortcode;

use Drupal\Core\Language\Language;
use Drupal\shortcode\Plugin\ShortcodeBase;

/**
 * Replace the given text with a form linking to the relevant form builder.
 *
 * @Shortcode(
 *   id = "form",
 *   title = @Translation("Form"),
 *   description = @Translation("Replace with Tow Form lookup.")
 * )
 */
class Form extends ShortcodeBase {

  /**
   * Caches content to avoid unecessary querying.
   *
   * @var array
   */
  private $classCache = [];

  /**
   * {@inheritdoc}
   */
  public function process(array $attributes, $text, $langcode = Language::LANGCODE_NOT_SPECIFIED) {
    // Merge with default attributes. WTF - Crazy ??!!
    $attributes = $this->getAttributes([
      'id' => '',
    ],
      $attributes
    );
    // If there is no id we can proceed. Return a string of some sort.
    if ($attributes['id'] == '') {
      return implode(',', $attributes);
    }
    // Cleanup id so that we can find the class.
    $id = $attributes['id'];
    // Translate D7 id's to D8.
    if (strpos($id, "bos_") !== FALSE) {
      // Old style (d7) shortcode id has format "bos_some_thing".  In d8 we
      // use the shortcode id to find the class which needs to be camel-case.
      // New style (d8) shortcode id should be the class name in camel case.
      // E.g. d7 "bos_my_shortcode_form" == d8 "MyShortcodeForm".
      $id = str_replace("bos_", "", strtolower($attributes['id']));
      $id = ucwords(str_replace("_", " ", $id));
      $id = str_replace(" ", "", $id);
    }
    // For this to work, all shortcode form classes need to be in
    // \Drupal\bos_shortcodes\Forms namespace and in the correct folder for
    // discovery.
    $id = "Drupal\\bos_shortcodes\\Forms\\" . $id;
    // Get the form from its definition class.
    $form = \Drupal::formBuilder()->getForm($id);
    // Render and return the form as a string string so it can replace the
    // shortcode pattern found in the original text field.
    return $this->render($form);
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
    $this->cacheClasses();
    $output = [];
    $output[] = '<p><strong>' . t('[form id="FormName" /]') . '</strong> ';
    if ($long) {
      $output[] = t('Outputs a custom form.') . '</p>';
    }
    else {
      $output[] = t('Outputs a custom form.') . '</p>';
    }
    if (!empty($this->classCache)) {
      foreach ($this->classCache as $validId) {
        $cls[] = "<i>[form id=$validId /]</i>";
      }
      $output[] = "<p>" . implode("<br>", $cls) . "</p>";
    }
    return implode(' ', $output);
  }

  /**
   * Caches classes that we expect this class to reference later on.
   *
   * @return array
   *   Array of classes reporting this class(Form) as their shortcodePluginId.
   */
  private function cacheClasses() {
    if (empty($this->classCache)) {
      $this->classCache = [];
      foreach (get_declared_classes() as $class) {
        if (stripos($class, "Drupal\\bos_shortcodes\\Forms") !== FALSE) {
          if (method_exists($class, "shortcodePlugin") && $class::$shortcodePluginId == "Form") {
            $this->classCache[] = end(explode('\\', $class));
          }
        }
      }
    }
    return $this->classCache;
  }

}
