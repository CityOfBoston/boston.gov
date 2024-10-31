<?php

namespace Drupal\bos_search;

use Drupal\bos_search\Model\AiSearchRequest;
use Drupal\bos_search\Model\AiSearchResponse;
use Drupal\Component\Plugin\PluginInspectionInterface;

/**
* Defines an interface for Search plugins.
 *
 * Any Search plugin must implement this interface in order to function with
 * the bos_search module.
*/
interface AiSearchInterface extends PluginInspectionInterface {

/**
* Return the ServiceId.
*
* @return string The service Type ID
*
*/
  public function getServiceId();

  /**
   * Return the Service Object.
   *
   * @return object The service Type ID
   *
   */
  public function getService();

  /**
   * Flag whether the service supports an ongoing conversation.
   *
   * @return bool TRUE is conversation supported.
   */
  public function hasFollowUp(): bool;

  /**
   * Perform a search using the selected Service.
   *
   * @param \Drupal\bos_search\Model\AiSearchRequest $request Request object
   * @param bool $fake For testing - provides a canned response without actually
   *                   requesting the AI.
   *
   * @return \Drupal\bos_search\Model\AiSearchResponse Standardized output.
   */
  public function search(AiSearchRequest $request, bool $fake = FALSE): AiSearchResponse ;

  /**
   * Returns a list of prompts which can be used by this AI model.
   *
   * @return array
   */
  public function availablePrompts(): array;

}
