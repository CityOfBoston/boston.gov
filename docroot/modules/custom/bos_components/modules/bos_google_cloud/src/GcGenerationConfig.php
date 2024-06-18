<?php

namespace Drupal\bos_google_cloud;
/**
  Class GcGenerationConfig
  Creates a gen-ai prompt management tool

  david 02 2024
  @file docroot/modules/custom/bos_components/modules/bos_google_cloud/src/GcGenerationConfig.php
 */
/**
 * Defines an object which can be used by Vertex Gemini for fine-tuning the
 * text generation response from a model.
 *
 * The array returned by $this->config() method has the following fields:
 *
 * **Temperature**: Controls the degree of randomness in token selection.
 *   Lower temperatures are good for prompts that require a more deterministic
 *   and less open-ended or creative response, while higher temperatures can
 *   lead to more diverse or creative results.
 *   A temperature of 0 is deterministic:
 *
 * **TopK**: Token Selection. A top-K of 1 means the next selected token is the
 *    most probable among all tokens in the model's vocabulary (also called
 *    "greedy decoding"), while a top-K of 3 means that the next token is
 *    selected from among the three most probable tokens by using temperature.
 *
 * **TopP**: Token Selection. Tokens are selected from the most (see top-K) to
 *   least probable until the sum of their probabilities equals the top-P value.
 *   For example, if tokens A, B, and C have a probability of 0.3, 0.2, and
 *   0.1 and the top-P value is 0.5, then the model will select either A or B
 *   as the next token by using temperature and excludes C as a candidate.
 *   TIP: Specify a lower value for less random responses and a higher value
 *   for more random responses.
 *
 * **maxOutputTokens**: Maximum number of tokens that can be generated in the
 *   response. A token is approximately four characters. 100 tokens correspond
 *   to roughly 60-80 words. Specify a lower value for shorter responses and
 *   a higher value for potentially longer responses.
 */
class GcGenerationConfig {

  private array $config;

  /**
   * See notes for detailed description of parameters.
   *
   * @param float $temperature Controls the degree of randomness in token selection. Range 0-1
   * @param float $topK Token Selection. Range 0-1
   * @param float $topP Token Selection. Range 0-1
   * @param float $maxOutputTokens Max response size, 1 token = approx 4 chars. Range 0-8192.
   */
  public function __construct(float $temperature = 0.8, float $topK = 32, float $topP = 1.0, float $maxOutputTokens = 1024) {
    $this->config = ["candidateCount" => 1];
    $this->setTemperature($temperature)
      ->setTopK($topK)
      ->setTopP($topP)
      ->setMaxOutputTokens($maxOutputTokens);
  }

  /**
   * Return the generation config as an array.
   *
   * @return array
   */
  public function getConfig(): array {
    return $this->config;
  }

  public function setTemperature(float $value):GcGenerationConfig {
    $this->config["temperature"] = min(max($value,0),1);
    return $this;
  }
  public function setTopP(float $value):GcGenerationConfig {
    $this->config["topP"] = min(max($value,0),1);
    return $this;
  }
  public function setTopK(float $value):GcGenerationConfig {
    $this->config["topK"] = min(max($value,0),40);
    return $this;
  }
  public function setMaxOutputTokens(float $value):GcGenerationConfig {
    $this->config["maxOutputTokens"] = min(max($value,0),8192);
    return $this;
  }

}
