<?php

namespace Drupal\bos_google_cloud\Plugin\WebformHandler;


use Drupal\bos_google_cloud\Services\GcAuthenticator;
use Drupal\bos_google_cloud\Services\GcBigQuery;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Annotation\WebformHandler;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Exception;
use GeoIp2\WebService\Client;

// @see Drupal\bos_google_cloud\Services\GcBigQuery

/**
 * Form submission handler.
 *
 * @WebformHandler(
 *   id = "bigquery_form_handler",
 *   label = @Translation("Post to BigQuery"),
 *   category = @Translation("DataWarehouse"),
 *   description = @Translation("Send submission to BigQuery."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 * )
 */
class GcBigQueryHandler extends WebformHandlerBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'service_account' => '',
      'project' => '',
      'table' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritDoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $service_accounts = GcAuthenticator::SVS_ACCOUNT_LIST;

    $form['bigquery_form_handler'] = [
      '#type' => 'details',
      '#title' => $this->t('Google Cloud: Big Query Submission.'),
      '#open' => TRUE,
    ];
    $form['bigquery_form_handler']['service_account'] = [
      '#type' => 'select',
      '#title' => $this->t('Goocle Cloud Service Account'),
      '#options' => $service_accounts,
      '#default_value' => $this->configuration['service_account'],
      '#description' => $this->t('Select the service account to use.'),
    ];
    $form['bigquery_form_handler']['project'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Google Cloud Platform Project to use.'),
      '#default_value' => $this->configuration['project'],
      '#description' => $this->t('Enter the name or ID of the Big Query-enabled Project.'),
    ];
    $form['bigquery_form_handler']['dataset'] = [
      '#type' => 'textfield',
      '#title' => $this->t('The dataset to use.'),
      '#default_value' => $this->configuration['dataset'],
      '#description' => $this->t('Enter the name of the Big Query dataset.'),
    ];
    $form['bigquery_form_handler']['table'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Table'),
      '#default_value' => $this->configuration['table'],
      '#description' => $this->t('Enter the table into which the submissions are to be posted.'),
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc} 738313172788
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration = $form_state->getUserInput()["settings"]['bigquery_form_handler'];
  }

  /**
   * {@InheritDoc}
   * Require that something was entered on the form.
   */
  public function validateForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    $anything = FALSE;
    foreach ($webform_submission->getRawData() as $question => $answer) {
      $anything = $anything || !empty($answer);
    }
    if (!$anything) {
      $form_state->setErrorByName("how_easy_was_it_to_find_the_information_you_were_looking_for", "Please enter some information!");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE): void {
    // The form is being submitted. Handle it here.
    $api = new GcBigQuery($this->configuration['service_account'], $this->configuration['project'], $this->configuration['dataset']);
    try {
      $record = $api->getGa4EventBody();
      $this->updateRecordData($record, $webform_submission->getData(), $webform_submission->getWebform()->getElementsDecodedAndFlattened());
      $api->insertRow($this->configuration['table'], $record);
    }
    catch (Exception $e) {
      $this->messenger()
        ->addError($this->t('Error: @message', ['@message' => $api->error() ?? $e->getMessage()]));
    }
  }

  /**
   * Processes and formats the provided record data for insertion into BQ.
   *
   * @param array $record
   *   The record to be inserted into BigQuery.
   * @param array $submission
   *   The raw data from websubmission to be added to record.
   * @param array $elements
   *   List of form elements (fields) to be scanned.
   *
   * @return void
   *   Nothing return, the $record is altered byRef.
   */
  private function updateRecordData(array &$record, array $submission, array $elements): void {

    $record["event_name"] = "webform.{$this->getWebform()->id()}";
    $record["device"]["language"] = $this->webformSubmission->getLangcode();
    $record["event_params"][] = [
      "key" => "source",
      "value" => ["string_value" => "GcBigQueryHander.php"],
    ];

    // Add in data for the event, in this case a webform submission.
    foreach ($elements as $field_name => $field_definition) {
      switch ($field_definition["#type"]) {
        case "checkbox":
          $response = $submission[$field_name] ?? 0;
          $record["event_params"][] = [
            "key" => $field_name,
            "value" => ["int_value" => $response],
          ];
          break;

        case "checkboxes":
          $flipped_record = array_flip($submission[$field_name]);
          foreach ($field_definition["#options"] as $option_name => $option_value) {
            $response = (array_key_exists($option_name, $flipped_record) ? 1 : 0);
            $record["event_params"][] = [
              "key" => "{$field_name}.{$option_name}",
              "value" => ["int_value" => $response],
            ];
          }
          break;

        case "textfield":
        case "hidden":
        case "textarea":
          $response = $submission[$field_name] ?? "";
          $record["event_params"][] = [
            "key" => $field_name,
            "value" => ["string_value" => $response],
          ];
          break;
      }
    }

  }

}
