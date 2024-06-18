<?php

namespace Drupal\bos_core;

use Drupal\Core\Render\Markup;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\responsive_image\Entity\ResponsiveImageStyle;

/**
 * Class BackgroundResponsiveImage.
 *
 * @package Drupal\bos_core
 */
class BackgroundResponsiveImage extends ResponsiveImageStyle {

  /**
   * Creates inline css to make a background image responsive (uses css @media).
   *
   * Needs to be called from a theme_preprocess_hook or else the ['#attached']
   * array field does not set and the style is not applied to the page.
   *
   * @param array $background_image
   *   Background image render array.
   * @param string $anchorClass
   *   The class name for css generated - the background image main class tag.
   * @param array $options
   *   Options to pass to the function.
   *
   * @return string|bool
   *   False if failed, otherwise an inline css string that could be injected.
   *
   * @throws \Exception
   */
  public static function createBackgroundCss(array $background_image, $anchorClass = "hro", array $options = []) {

    if ($background_image['#formatter'] != 'responsive_image' && substr($background_image[0]["#view_mode"], 0, 10) != "responsive") {
      throw new \Exception("Image is not a responsive style.");
    }

    if (substr($background_image[0]["#view_mode"], 0, 10) == "responsive") {
      $responsiveStyle_group = $background_image[0]["default_responsive_image_style_id"];
      // Extract the responsive style group for this image.
      if ($config = \Drupal::configFactory()->get("core.entity_view_display.media.image." . $background_image[0]["#view_mode"])) {
        $responsiveStyle_group = $config->get("content")["image"]["settings"]["responsive_image_style"] ?? $responsiveStyle_group;
      }
      $media = $background_image[0]["#media"];
      $file = File::load($media->image->target_id);
      $uri = $file->getFileUri();
    }

    elseif (!empty($background_image["#items"]->entity)) {
      $responsiveStyle_group = $background_image[0]["#responsive_image_style_id"];
      $uri = $background_image["#items"]->entity->getFileUri();
    }

    if (isset($uri)) {
      return self::buildMediaQueries($uri, $responsiveStyle_group, $anchorClass, $options);
    }
    return FALSE;
  }

  /**
   * Renders the HTML framework to implement a responsive background image.
   *
   * @param array $nestedFramework
   *   Structured array of nested HTML elements.
   *   First element encloses second etc.
   *
   * @throws \Exception
   *
   * @return \Drupal\Core\Render\Markup
   *   Markup to be applied to a drupal render array.
   */
  public static function createBackgroundFramework(array $nestedFramework) {
    if (!is_array($nestedFramework)) {
      throw new \Exception("Require array input to function.");
    }
    foreach ($nestedFramework as $key => $classDef) {
      $ky = key($classDef);
      $class = $classDef[$ky];
      $html[] = "<$ky class=\"$class\">";
      $htmlClose[] = "</$ky>";
    }
    $html = implode("", $html) . implode("", $htmlClose);
    return Markup::create($html);
  }

  /**
   * Separates functionality to get the breakpoints and responsive style object.
   *
   * @param array $breakpoints
   *   Array to contain the breakspoints for this responsive style group.
   * @param string $responsiveStyle_group
   *   The responsive style group to build.
   *
   * @return \Drupal\responsive_image\Entity\ResponsiveImageStyle|null
   *   The responsiveImageStyle object.
   *
   * @throws \Exception
   *   If the responsive group is not found.
   */
  private static function getStyleElements(array &$breakpoints, string $responsiveStyle_group) {
    // Work out the responsive group id and the breakpoint set being used.
    if (NULL == $responsiveStyle = ResponsiveImageStyle::load($responsiveStyle_group)) {
      throw new \Exception("Unknown responsive style set.");
    }
    $breakpoint_group = $responsiveStyle->get("breakpoint_group");

    // Get the breakpoints for specified group (defined in theme.info).
    $breakpoints = \Drupal::service('breakpoint.manager')
      ->getBreakpointsByGroup($breakpoint_group);

    return $responsiveStyle;
  }

  /**
   * Builds the media queries (css) as specified by responsive style module.
   *
   * @param string $uri
   *   The image Uri.
   * @param string $responsiveStyle_group
   *   The responsive group to use.
   * @param string $anchorClass
   *   The css anchor element to use.
   * @param array $options
   *   Options to pass to the function.
   *
   * @return string
   *   A string of valid css3.
   *
   * @throws \Exception
   *   Error.
   */
  public static function buildMediaQueries(string $uri, string $responsiveStyle_group, string $anchorClass, array $options = []) {
    // Work out the responsive group id and the breakpoint set being used.
    $breakpoints = [];
    $css = [];
    $responsiveStyle = self::getStyleElements($breakpoints, $responsiveStyle_group);

    // Create the default style.
    $fallback_style = $responsiveStyle->getFallbackImageStyle();
    $url = ImageStyle::load($fallback_style)->buildUrl($uri);
    $base_css = "background-image: url(" . $url . ");\n    background-size: cover !important;";
    if (isset($options['base-css'])) {
      $base_css .= "\n    " . $options['base-css'];
    }
    $base_css = "$anchorClass {" . $base_css . "}";
    $css[] = $base_css;
    // Create an array with URL's for each responsive style.
    $styles = $responsiveStyle->getKeyedImageStyleMappings();

    foreach ($styles as $key => $style) {
      foreach ($style as $multiplier => $breakpoint) {
        if (empty($breakpoint['multiplier']) || $breakpoint['multiplier'] == "1x") {
          $multiplier = "";
        }
        else {
          $multiplier = str_replace('x', '', $breakpoint['multiplier']);
          $multiplier = "and (-webkit-min-device-pixel-ratio: $multiplier) ";
        }
        if (isset($breakpoints[$breakpoint['breakpoint_id']])) {
          $url = ImageStyle::load($breakpoint['image_mapping'])
            ->buildUrl($uri);
          $breakpoint = $breakpoints[$breakpoint['breakpoint_id']]->getMediaQuery();
          $css[] = "@media screen and $breakpoint $multiplier{\n    $anchorClass { background-image: url($url); } }";
        }
      }
    }

    return implode("\n", $css);

  }

  /**
   * Builds the picture element sources as specified by responsive style module.
   *
   * @param string $uri
   *   The image Uri.
   * @param string $responsiveStyle_group
   *   The responsive group to use.
   *
   * @return string
   *   A string of valid css3.
   *
   * @throws \Exception
   *   Error.
   */
  public static function buildMediaSources(string $uri, string $responsiveStyle_group) {
    // Work out the responsive group id and the breakpoint set being used.
    $breakpoints = [];
    $responsiveStyle = self::getStyleElements($breakpoints, $responsiveStyle_group);

    // Create the default style.
    $fallback_style = $responsiveStyle->getFallbackImageStyle();
    $uri = explode("?", $uri);
    $url = ImageStyle::load($fallback_style)->buildUrl($uri[0]);
    $sources[] = [
      'srcset' => $url,
      "media" => "",
      "type" => mime_content_type($url),
    ];

    // Create an array with URL's for each responsive style.
    $styles = $responsiveStyle->getKeyedImageStyleMappings();

    foreach ($styles as $key => $style) {
      foreach ($style as $multiplier => $breakpoint) {
        if (empty($breakpoint['multiplier']) || $breakpoint['multiplier'] == "1x") {
          $multiplier = "";
        }
        else {
          $multiplier = str_replace('x', '', $breakpoint['multiplier']);
          $multiplier = "and (-webkit-min-device-pixel-ratio: $multiplier) ";
        }
        $url = ImageStyle::load($breakpoint['image_mapping'])
          ->buildUrl($uri);
        $breakpoint = $breakpoints[$breakpoint['breakpoint_id']]->getMediaQuery();
        $sources[] = [
          'srcset' => $url,
          "media" => "@media screen and $breakpoint $multiplier",
          "type" => "image/jpeg",
        ];
      }
    }

    return $sources;
  }

}
