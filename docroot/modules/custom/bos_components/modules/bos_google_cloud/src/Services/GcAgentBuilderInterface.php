<?php

/**
 * Interface to handle AgentBuilder API specific methods.
 * "Extends" the GcServiceInterface.
 */

namespace Drupal\bos_google_cloud\Services;

interface GcAgentBuilderInterface {

  /**
   * Returns the current session info (if any).
   * @return array
   */
  public function getSessionInfo(): array;

  /**
   * Loads metadata into the response object
   *
   * @param array $parameters *
   *
   * @return void
   */
  public function loadMetadata(array $parameters): void;

  /**
   * Provides a list of projects in Google Cloud which have AgenBuilder
   * enabed, or which have an engine.
   *
   * @param string|null $service_account *
   *
   * @return array
   */
  public function availableProjects(?string $service_account): array;

  /**
   * Given a service account, provides a list of Agent Builder datastores in
   * Google Cloud for a given project.
   *
   * @param string|null $service_account Service account for authentication
   * @param string|null $project_id Project to scan
   *
   * @return array  Datastores that the service account can access in the project
   */
  public function availableDatastores(?string $service_account, ?string $project_id): array;

  /**
   * Given a service account, provides a list of Agent Builder datastores in
   * Google Cloud for a given project.
   *
   * @param string|null $service_account Service account for authentication
   * @param string|null $project_id Project to scan
   *
   * @return array  Engines (apps) that the service account can access in the project
 */
  public function availableEngines(?string $service_account, ?string $project_id): array;

  /**
   * Alias for availableEngines.
   */
  public function availableApps(?string $service_account, ?string $project_id): array;

  /**
   * Format datastore as required by API.
   *
   * Generate the "fully qualified" data store name based on the provided
   * project ID and data store name.
   *
   * @param string $projectid
   *   The ID of the project.
   * @param string $dsname
   *   The name of the data store.
   *
   * @return string
   *   The "fully qualified" data store name.
   */
  public function fqDataStorename(string $projectid, string $dsname): string;

}
