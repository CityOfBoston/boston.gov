<?php

namespace Drupal\bos_search\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Class CustomFiltersExtension
 *
 * This class extends the AbstractExtension and provides custom Twig filters.
 * It includes a filter to check for non-Unicode characters in a string.
 */
class CustomFiltersExtension extends AbstractExtension {

  /**
   * @inheritDoc
   */
  public function getFilters() {
    return [
      new TwigFilter('has_non_english_chars', [$this, 'hasNonEnglishChars']),
    ];
  }

  /**
   * Checks if the given string contains non-English characters.
   *
   * @param string $string The string to be checked.
   *
   * @return bool Returns true if the string contains non-English characters, false otherwise.
   */

  public function hasNonEnglishChars($string) {
    // Check if the string is valid UTF-8 (Unicode)
    return (preg_match('/[^\x00-\x7F]/', $string) > 0);
  }

}
