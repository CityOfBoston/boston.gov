<?php

namespace Drupal\bos_email\Plugin\QueueWorker;

use Drupal\bos_email\Services\DrupalService;
use Drupal\Core\Annotation\QueueWorker;
use Drupal\Core\Queue\DelayedRequeueException;
use Drupal\Core\Queue\QueueWorkerBase;
use Exception;

/**
 * Processes scheduled emails.
 *
 * @QueueWorker(
 *   id = "scheduled_email",
 *   title = @Translation("Queues emails for later sending."),
 *   cron = {"time" = 60}
 * )
 */
class ScheduledEmailProcessor extends QueueWorkerBase {

  /**
   * Process each record.
   *
   * @param mixed $data
   *   The item stored in the queue.
   */
  public function processItem($data): void {
    try {

      $config = \Drupal::configFactory()->get("bos_email.settings");

      if (!$config->get("q_enabled")) {
        // All queue processing is disabled.
        throw new Exception("All queues are paused by settings at /admin/config/system/boston/email_services.");
      }
      elseif (!empty($data["server"])
        && !$config->get(strtolower($data["server"]))["q_enabled"]) {
        // Just this queue processing for this EmailProcessor is disabled.
        throw new Exception("The queue for {$data["server"]} is paused by settings at /admin/config/system/boston/email_services.");
      }

      // Check the scheduled send time for this email.
      if (intval($data["senddatetime"]) <= strtotime("Now")) {
        // The scheduled date/time has passed, so this email is now eligible to
        // be sent.

        // Tidy up the email first.
        if (!empty($data["senddatetime"])) {
          unset($data["senddatetime"]);
        }
        if (!empty($data["send_date"])) {
          unset($data["send_date"]);
        }

        // Load the original EmailService.
        if (!empty($data["service"])) {
          try {
            $emailService = new $data["service"];
          }
          catch (Exception $e) {}
        }
        if (empty($emailService)) {
          // Defaults to sending via Drupal so that we can always send an email.
          $emailService = new DrupalService();
        }

        // This will throw an error if the mail does not send, which will cause
        // the selected email to remain in the queue.
        // Failures in sending will recycle the email back into this queue, and
        // make it eligible for immediate resending.
        try {
          $emailService->sendEmail($data);
        }
        catch (Exception $e) {
          // Bubble this exception. (the try/catch is not strictly necessary, but
          // we can use it to add info for logging.)
          \Drupal::logger("contactform")->error($e->getMessage());
          throw new Exception("There was a problem in {$emailService->id()}. {$e->getMessage()}");
        }

      }
      else {
        // This email is not ready to be sent yet because the send time has not
        // been reached. Throw this error to put the email back into this queue
        // and not make it available until its scheduled time.
        throw new DelayedRequeueException(
          intval($data["senddatetime"]) - strtotime("Now"),
          "Email scheduled for the future."
        );
      }

    }
    catch (DelayedRequeueException $e) {
      // This has most likely been thrown in the normal course of events because
      // it is not the scheduled time to send this email yet.
      // Throwing this error will put the email back in this queue and will not
      // allow any worker to select it again until the time it should be sent.
      throw new DelayedRequeueException($e->getDelay(), $e->getMessage());
    }
    catch (Exception $e) {
      // This is a general error, so simply put the email back into this queue
      // and mark it eligible for immediate resending.
      throw new Exception($e->getMessage());
    }

  }

}
