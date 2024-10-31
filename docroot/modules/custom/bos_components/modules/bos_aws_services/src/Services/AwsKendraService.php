<?php

namespace Drupal\bos_aws_services\Services;

use Drupal\bos_core\Controllers\Curl\BosCurlControllerBase;
use Drupal\bos_google_cloud\Services\GcServiceInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactory;

class AwsKendraService extends BosCurlControllerBase implements GcServiceInterface {


  public function __construct(LoggerChannelFactory $logger, ConfigFactory $config) {

    // Load the service-supplied variables.
    $this->log = $logger->get('bos_aws_services');
    $this->config = $config->get("bos_aws_service.settings") ?? [];

    // Do the CuRL initialization in BosCurlControllerBase.
    parent::__construct();

  }
  /**
   * @inheritDoc
   */
  public static function id(): string {
    return "kendra";
  }

  /**
   * @inheritDoc
   */
  public function execute(array $parameters = []): string {
    // TODO: Implement execute() method.
    return "OK";
  }

  /**
   * @inheritDoc
   */
  public function buildForm(array &$form): void {
    // TODO: Implement buildForm() method.
  }

  /**
   * @inheritDoc
   */
  public function submitForm(array $form, FormStateInterface $form_state): void {
    // TODO: Implement submitForm() method.
  }

  /**
   * @inheritDoc
   */
  public function validateForm(array $form, FormStateInterface &$form_state): void {
    // TODO: Implement validateForm() method.
  }

  /**
   * @inheritDoc
   */
  public function setServiceAccount(string $service_account): AwsKendraService {
    // TODO: Implement setServiceAccount() method.
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function hasFollowup(): bool {
    // TODO check if this is set true from config form.
    return TRUE;
  }

}
