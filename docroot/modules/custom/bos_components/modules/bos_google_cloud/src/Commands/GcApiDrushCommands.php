<?php

namespace Drupal\bos_google_cloud\Commands;

use Drupal;
use Drupal\bos_geocoder\Utility\BosGeoAddress;
use Drupal\bos_google_cloud\GcGenerationPrompt;
use Drupal\bos_google_cloud\Services\GcGeocoder;
use Drupal\bos_google_cloud\Services\GcServiceInterface;
use Drupal\bos_google_cloud\Services\GcTextRewriter;
use Drupal\bos_google_cloud\Services\GcTextSummarizer;
use Drupal\bos_google_cloud\Services\GcTranslation;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\CommandFailedException;
use Exception;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class GcApiDrushCommands extends DrushCommands {

  /**
   * Summarize some text using Gemini.
   *
   * @validate-module-enabled bos_google_cloud
   *
   * @option service_account [optional] The service account to use. Will default
   *      to the service account set in
   *   \Drupal\bos_google_cloud\Services\GcAuthenticator::SVS_ACCOUNT_LIST[0]
   * @option text [required] The text to be summarized
   * @option prompt The predfined prompt to use (you will be asked if ommitted)
   *
   * @command bosai:summarize
   *
   * @usage drush bosai:summarize --service_account=abc --text="text to summarize" --prompt=default
   *
   * @param array $options
   *
   * @return void
   * @throws \Drush\Exceptions\CommandFailedException
   */
  public function gcSummarize(array $options = ['service_account' => NULL, 'text' => "", "prompt" => NULL]): void {

    if (empty($options["text"])) {
      throw new CommandFailedException("Please provide some text to summarize.");
    }
    $options["text"] = rawurlencode($options["text"]);

    if (empty($options["prompt"])) {

      $title = "Google Cloud Text Summarizer - using Gemini (Vertex):\n Select the prompt to use:";
      $options["prompt"] = $this->getUserInput($title, GcGenerationPrompt::getPrompts(GcTextSummarizer::id()));

      if (!$options["prompt"]) {
        $this->output()->writeln("CANCELLED");
        return;
      }

    }

    $summarizer = Drupal::service("bos_google_cloud.GcTextSummarizer");
    $this->callService($summarizer, $options);

  }

  /**
   * Rewrite the supplied text using Gemini.
   *
   * @validate-module-enabled bos_google_cloud
   *
   * @option service_account [optional] The service account to use. Will default
   *      to the service account set in
   *   \Drupal\bos_google_cloud\Services\GcAuthenticator::SVS_ACCOUNT_LIST[0]
   * @option text [required] The text to be rewritten
   * @option prompt The predfined prompt to use (you will be asked if ommitted)
   *
   * @command bosai:rewrite
   *
   * @usage drush bosai:rewrite --service_account=abc --text="text to rewrite" --prompt=default
   *
   * @param array $options
   *
   * @return void
   * @throws \Drush\Exceptions\CommandFailedException
   */
  public function gcRewrite(array $options = ['service_account' => NULL, 'text' => "", "prompt" => NULL]): void {

    if (empty($options["text"])) {
      throw new CommandFailedException("Please provide some text to rewrite.");
    }
    $options["text"] = rawurlencode($options["text"]);

    if (empty($options["prompt"])) {

      $title = "Google Cloud Text Rewriter - using Gemini (Vertex):\n Select the prompt to use:";
      $options["prompt"] = $this->getUserInput($title, GcGenerationPrompt::getPrompts(GcTextRewriter::id()));

      if (!$options["prompt"]) {
        $this->output()->writeln("CANCELLED");
        return;
      }

    }

    $rewriter = Drupal::service("bos_google_cloud.GcTextRewriter");
    $this->callService($rewriter, $options);

  }

  /**
   * Search the boston.gov site using Vertex Search and Conversation.
   *
   * @validate-module-enabled bos_google_cloud
   *
   * @option service_account [optional] The service account to use. Will default
   *      to the service account set in
   *   \Drupal\bos_google_cloud\Services\GcAuthenticator::SVS_ACCOUNT_LIST[0]
   * @option search [required] The text to be searched
   * @option prompt The predfined prompt to use (you will be asked if ommitted)
   *
   * @command bosai:search
   *
   * @usage drush bosai:search --service_account=abc --search="text to search" --prompt=default
   *
   * @param array $options
   *
   * @return void
   * @throws \Drush\Exceptions\CommandFailedException
   */
  public function gcSearch(array $options = ['service_account' => NULL, 'search' => "", "prompt" => NULL]): void {

    if (empty($options["search"])) {
      throw new CommandFailedException("Please provide a search query.");
    }

    if (empty($options["prompt"])) {

      $title = "Vertex Search and Conversation (Google Cloud):\n Select the prompt (preamble) to use:";
      $options["prompt"] = $this->getUserInput($title, GcGenerationPrompt::getPrompts("search"));

      if (!$options["prompt"]) {
        $this->output()->writeln("CANCELLED");
        return;
      }

    }

    $search = Drupal::service("bos_google_cloud.GcSearch");
    $this->callService($search, $options);

  }

  /**
   * Converse with Cody using the boston.gov site as a base reference - using
   * Vertex Search and Conversation.
   *
   * @validate-module-enabled bos_google_cloud
   *
   * @option service_account [optional] The service account to use. Will default
   *      to the service account set in
   *   \Drupal\bos_google_cloud\Services\GcAuthenticator::SVS_ACCOUNT_LIST[0]
   * @option text [required] The conversation text.
   * @option prompt The predfined prompt to use (you will be asked if ommitted).
   *
   * @command bosai:conversation
   * @aliases bosai:converse
   *
   * @usage drush bosai:conversation --service_account=abc --text="Some text" --prompt=default
   *
   * @param array $options
   *
   * @return void
   *
   * @throws \Drush\Exceptions\CommandFailedException
   */
  public function gcConverse(array $options = ['service_account' => NULL, 'text' => "", "prompt" => NULL]): void {

    if (empty($options["text"])) {
      throw new CommandFailedException("Please provide some text.");
    }
    $options["text"] = rawurlencode($options["text"]);

    if (empty($options["prompt"])) {
      $title = "Vertex Search and Conversation (Google Cloud):\n Select the prompt (preamble) to use:";
      $options["prompt"] = $this->getUserInput($title, GcGenerationPrompt::getPrompts("search"));

      if (!$options["prompt"]) {
        $this->output()->writeln("CANCELLED");
        return;
      }
    }

    $converse = Drupal::service("bos_google_cloud.GcConversation");
    $this->callService($converse, $options);

  }

  /**
   * Translate using Cody with the boston.gov site as a base reference - using
   * Vertex Search and Conversation.
   *
   * @validate-module-enabled bos_google_cloud
   *
   * @option service_account [optional] The service account to use. Will default
   *      to the service account set in
   *   \Drupal\bos_google_cloud\Services\GcAuthenticator::SVS_ACCOUNT_LIST[0]
   * @option text [required] The text to translate
   * @option lang The language to translate to (you will be asked if ommitted)
   * @option prompt The predfined prompt to use (uses 'default' if ommitted)
   *
   * @command bosai:translation
   * @aliases bosai:translate
   *
   * @usage drush bosai:translation --service_account=abc --text="Some text"
   *   --lang=spanish
   *
   * @param array $options
   *
   * @return void
   * @throws \Drush\Exceptions\CommandFailedException
   */
  public function gcTranslate(array $options = ['service_account' => NULL, 'text' => "", "prompt" => NULL, "lang" => NULL]): void {

    if (empty($options["text"])) {
      throw new CommandFailedException("Please provide some text to translate.");
    }
    $options["text"] = rawurlencode($options["text"]);

    if (empty($options["lang"])) {
      $title = "AI Translation:\n Select the language to translate to:";
      $options["lang"] = $this->getUserInput($title, GcGenerationPrompt::getPrompts(GcTranslation::id()));

      if (!$options["lang"]) {
        $this->output()->writeln("CANCELLED");
        return;
      }
    }

    if (empty($options["prompt"])) {
      $options["prompt"] = "default";
    }

    $translator = Drupal::service("bos_google_cloud.GcTranslate");
    $this->callService($translator, $options);

  }

  /**
   * Use Google Geolocator to resolve an address into lat/long coordinates, or
   * reverse geocode lat/long coordinates to a physical address.
   *
   * @validate-module-enabled bos_google_cloud
   *
   * @param string $direction "geocode" or "reversegeocode"
   * @param array $options
   *
   * @return void
   * @throws \Drush\Exceptions\CommandFailedException
   * @option address [optional] The address as a single line, or
   * @option latlong [optional] The lat/long coords, comma separated
   *
   * @command bosai:geocode
   *
   * @usage drush bosai:geocode geocode --address="abc"
   * @usage drush bosai:geocode reversegeocode --latlong="42.1235,-71.2356"
   *
   */
  public function gcGeocode(string $direction, array $options = ["address" => "", "latlong" => ""]): void {

    if ($direction == "geocode" && empty($options["address"])) {
      throw new CommandFailedException("Please a single line address to resolve.");
    }
    elseif ($direction == "reversegeocode" && empty($options["latlong"])) {
      throw new CommandFailedException("Please Lat/Long corodinates to resolve.");
    }

    /**
     * @var $geocoder GcGeocoder
     */
    $geocoder = Drupal::service("bos_google_cloud.GcGeocoder");

    $address = new BosGeoAddress();

    switch ($direction) {

      case "geocode":
        $address->setSingleLineAddress($options["address"]);
        $geocoder->setAddress($address);
        $result = $geocoder->execute(["mode" => $geocoder::GEOCODE_FORWARD]);
        break;

      case "reversegeocode":
        $coords = explode(",", $options["latlong"]);
        if (count($coords) != 2) {
          throw new CommandFailedException("Lat/Long must be a comma separated string of two numbers.");
        }
        $address->setLocation($coords[0], $coords[1]);
        $geocoder->setAddress($address);
        $result = $geocoder->execute(["mode" => $geocoder::GEOCODE_REVERSE]);
        break;

      default:
        throw new CommandFailedException("Unknown direction parameter. Allowed values are 'geocode' or 'reversegeocode'.");
    }

    if ($result) {
      $this->output()
        ->writeln("RESULT: $result");
    }
    else {
      $this->output()
        ->writeln(t("FAILED: @error", [
          '@error' => implode(": ", $geocoder->getWarnings())
        ]));
    }

  }

  /**
   * Invalidate a cache entry in the summarize ai component.
   *
   * @validate-module-enabled bos_google_cloud
   *
   * @param string $prompt The prompt abbreviation
   * @param string $text The text to summarize
   *
   * @return void
   *
   * @command bosai:cache-invalidate
   *
   * @usage drush bosai:cache-invalidate "default" "Some text"
   *
   */
  public function gcCacheInvalidate(string $prompt, string $text): void {

    /**
     * @var $summarizer  GcTextSummarizer
     */
    $summarizer = Drupal::service("bos_google_cloud.GcTextSummarizer");
    $summarizer->invalidateCachedSummary($prompt, $text);

    $this->output()
        ->writeln("RESULT: Removed entry from cache");

  }

  /**
   * Provides a console question and processes the user response.
   *
   * @param string $title Selection questions for the target list.
   * @param array $prompt_list The target list.
   * @param int $default [optional] the default selection (0 is default).
   * @param bool $with_cancel [option] adds "cancel" option in ord position 0.
   *
   * @return string|bool The User Response (if cancelled or not valid then 0).
   */
  private function getUserInput(string $title, array $prompt_list, int $default = 0, bool $with_cancel = TRUE): string|bool {

    $count = 0;

    $opts = "$title\n\n";
    $opts_array = [];

    if ($with_cancel) {
      $opts_array = ["Cancel"];
      $opts .= " [" . $count++ . "]: Cancel\n";
    }

    foreach ($prompt_list as $prompt => $narrative) {
      if (!empty($prompt)) {
        $opts .= " [$count]: $prompt ($narrative)\n";
        $opts_array[$count++] = $prompt;
      }
    }
    $ord = $this->io()->ask($opts, $default);

    if (!array_key_exists($ord, $opts_array)) {
      return 0;
    }

    return ($ord == 0 ? FALSE : $opts_array[$ord]);

  }

  /**
   * Calls the service with the options, and returns the output to the console.
   *
   * @param GcServiceInterface $service The service
   * @param array $options Prompt, Text and other options as needed
   *
   * @return void
   * @throws CommandFailedException
   */
  private function callService(GcServiceInterface $service, array $options): void {

    if (!empty($options["service_account"])) {
      try {
        $service->setServiceAccount($options["service_account"]);
        unset($options["service_account"]);
      }
      catch (Exception $e) {
        throw new CommandFailedException($e->getMessage());
      }
    }

    $result = $service->execute($options);

    if ($result) {
      $this->output()
        ->writeln("RESULT: $result");
    }
    else {
      $this->output()
        ->writeln(t("FAILED: @error", [
          '@error' => $service->error()
        ]));
    }

  }

}
