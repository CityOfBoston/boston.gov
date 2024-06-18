<?php

namespace Drupal\bos_email\Plugin\QueueWorker;

use Drupal\bos_email\Services\DrupalService;
use Drupal\Core\Annotation\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Exception;

/**
 * Queue to hold unsent emails.
 *
 * @QueueWorker(
 *   id = "email_contactform",
 *   title = @Translation("Holds currently unsent emails."),
 *   cron = {"time" = 60}
 * )
 */
class ContactformProcessItems extends QueueWorkerBase {

  /**
   * Process each record.
   *
   * @param mixed $data
   *   The item stored in the queue.
   */
  public function processItem($data):void {

    try {

      $config = \Drupal::configFactory()->get("bos_email.settings");

      if (!$config->get("q_enabled")) {
        // All queue processing is disabled.
        throw new \Exception("All queues are paused by settings at /admin/config/system/boston/email_services.");
      }
      elseif (!empty($data["server"])
        && !$config->get(strtolower($data["server"]))["q_enabled"]) {
        // Just this queue processing for this EmailProcessor is disabled.
        throw new \Exception("The queue for {$data["server"]} is paused by settings at /admin/config/system/boston/email_services.");
      }

      // Cleanup message before sending.
      if (!empty($data["send_error"])) {
        unset($data["send_error"]);
      }

      // Load the original EmailService.
      if (!empty($data["service"])) {
        try {
          $emailService = new $data["service"];
        }
        catch (Exception $e) {}
      }
      if (empty($emailService)) {
        // Defaults to Drupal so we can always send.
        $emailService = new DrupalService();
      }

      try {
        $emailService->sendEmail($data);
      }
      catch (Exception $e) {
        // Bubble this exception. (the try/catch is not strictly necessary, but
        // we can use it to add info for logging.)
        \Drupal::logger("contactform")->error($e->getMessage());
        throw new \Exception("There was a problem in {$emailService->id()}. {$e->getMessage()}");
      }

    }
    catch (\Exception $e) {
      // Throwing an exception here will cause this email to be recycled back
      // into the queue for immediate resending.
      throw new \Exception($e->getMessage());
    }

  }

}
