<?php

namespace Drupal\bos_core\EventSubscriber;

use Drupal\externalauth\Event\ExternalAuthAuthmapAlterEvent;
use Drupal\externalauth\Event\ExternalAuthEvents;
use Drupal\externalauth\Event\ExternalAuthRegisterEvent;
use Drupal\externalauth\Event\ExternalAuthLoginEvent;
use Drupal\samlauth\Event\SamlauthEvents;
use Drupal\samlauth\Event\SamlauthUserLinkEvent;
use Drupal\samlauth\Event\SamlauthUserSyncEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * class SamlAuthentication - event listener.
 * Listens for samlauth module events, and externalauth module events.
 * @see bos_core.module->bos_core_user_create hook as well.
 */
class SamlAuthentication implements EventSubscriberInterface {

  /**
   * @inheritDoc
   */
  public static function getSubscribedEvents() {
    $events = [
      ExternalAuthEvents::AUTHMAP_ALTER => 'authmapAlter',
      ExternalAuthEvents::REGISTER => 'registerSamlUser',
      ExternalAuthEvents::LOGIN => 'samlLogin',
    ];
    // This is for the first time installation of d10.
    if (class_exists("Drupal\samlauth\Event\SamlauthEvents")) {
      $events[SamlauthEvents::USER_LINK] = 'newSamlLink';
      $events[SamlauthEvents::USER_SYNC] = 'userSync';
    }
    return $events;
  }

  /**
   * A new SAML login for an existing Drupal account has been mapped by the
   * samlauth module.
   *
   * @param \Drupal\samlauth\Event\SamlauthUserLinkEvent $event
   *
   * @return void
   */
  public function newSamlLink(SamlauthUserLinkEvent $event) {
    // TODO: update realname, update first and lastnames
    return;
  }

  /**
   * A new SAML login has been mapped to an existing Drupal Account by a
   * general match on username or password.
   * This event should not happen too often, while we are using the samlauth
   * module, newSamlLink() should catch most new SAML authenticated users that
   * already exist.
   *
   * @param \Drupal\externalauth\Event\ExternalAuthAuthmapAlterEvent $event
   *
   * @return void
   */
  public function authmapAlter(ExternalAuthAuthmapAlterEvent $event) {
    return;
  }

  /**
   * Fires before a new user is created in Drupal as they first log in using
   * SAML.
   *
   * We use this function to ensure the email address and realname are set for
   * the user.
   *
   * The account object is not associated, so we cannot change the email here.
   * We are forced to use the hook_create() hook (from externalauth module API).
   *
   * @param \Drupal\externalauth\Event\ExternalAuthRegisterEvent $event
   *
   * @return void
   */
  public function registerSamlUser(ExternalAuthRegisterEvent $event) {
    return;
  }

  /**
   * User successfully logins in using saml.
   *
   * @param \Drupal\externalauth\Event\ExternalAuthLoginEvent $event
   *
   * @return void
   */
  public function samlLogin (ExternalAuthLoginEvent $event) {
    // Set the realname using the pattern supplied (if any)
    $account = $event->getAccount();

    if (empty($account->realname)) {

      $config = \Drupal::config("samlauth.authentication");

      if (!empty($config->get("realname"))) {
        $name = self::getNameArrayFromEmail($account->getEmail()) ?: ["", ""];
        $params = [
          "uid" => $account->getAccountName(),
          "email" => $account->getEmail(),
          "given_name" => $name[0],
          "sn" => $name[1],
        ];
        $realname = self::getRealnameFromSAML($params, $config->get("realname"));
      }

      if (!empty($realname)) {
        // Save the realname, update the $account object and set in $event.
        realname_extensions_save_realname($account, $realname);
      }

    }

    // Clear any access denied errors b/c they triggered this login.
    $errors = \Drupal::messenger()->messagesByType("error");
    if (!empty($errors)) {
      foreach ($errors as $key => $error) {
        if (str_contains((string) $error, "Access denied")) {
          \Drupal::messenger()->deleteByType("error");
          $errors[$key] = NULL;
        }
      }
      if (empty(\Drupal::messenger()->messagesByType('error'))) {
        foreach ($errors as $error) {
          if (!empty($error)) {
            \Drupal::messenger()->addError((string) $error);
          }
        }
      }
    }

  }

  /**
   * After saml login, if config requires the Drupal user account is syncronized
   * with the information in the saml response from the Idp.
   * Name and email are already syn'd, update of other informatin such as
   * firstname lastname etc. can be handled here.
   *
   * @param \Drupal\samlauth\Event\SamlauthUserSyncEvent $event
   *
   * @return void
   */
  public function userSync(SamlauthUserSyncEvent $event) {

    $account = $event->getAccount();
    $saml_attributes = $event->getAttributes();
    $realname ="";

    $config = \Drupal::config("samlauth.authentication");

    if (!empty($account->id()) && empty($account->realname)) {
      // Set the realname using the pattern supplied (if any)
      if (!empty($config->get("realname"))) {
        $realname = self::getRealnameFromSAML($saml_attributes, $config->get("realname"));
      }
      if (empty($realname)) {
        // Use the generic (but custom) realname encoder.
        $realname = realname_extensions_create_realname($account, $account->getDisplayName());
      }
      if (!empty($realname) && $realname != 'anonymous') {
        // Save the new realname, and update the $account object.
        realname_extensions_save_realname($account, $realname);
        $account->realname = $realname;
      }

      // Save changes back into the $event object
      $event->setAccount($account);
    }

    // Check if there is an email to use.
    if (empty($saml_attributes["email"])) {
      // No email so make a unique one here.
      $saml_attributes["email"] = self::getEmailFromSAML($saml_attributes, $config);
      $event->setAttributes($saml_attributes);
    }
  }

  /**
   * Generates the "best" realname possible from the saml response payload.
   * The $saml_attributes array is expected to be this:
   * [
   *    uid => "",
   *    sn => "",
   *    given_name => "",
   *    email => ""
   * ]
   *
   * @param array $saml_attributes
   *
   * @return string
   */
  public static function getRealnameFromSAML(array $saml_attributes, string $pattern):string {
    $realname = [];
    $template = explode(" ", trim($pattern));
    foreach ($template as $part) {
      if (!empty($saml_attributes[$part])) {
        $realname[] = is_array($saml_attributes[$part]) ? $saml_attributes[$part][0] : $saml_attributes[$part];
      }
    }
    if (!empty($realname)) {
      return implode(" ", $realname);
    }
    return "";
  }

  /**
   * Generates the "best" email possible from the saml response payload.
   * The $saml_attributes array is expected to be this:
   * [
   *    uid => "",
   *    sn => "",
   *    given_name => "",
   *    email => ""
   * ]
   *
   * @param array $saml_attributes
   *
   * @return array
   */
  public static function getEmailFromSAML(array $saml_attributes, $config = []):string {

    if(!empty($saml_attributes["given_name"]) && !empty($saml_attributes["sn"])) {

      $recipient = "{$saml_attributes["given_name"]}.{$saml_attributes["sn"]}";
      $mail = "";

      // Guess the email address from the uid and names.
      $uid = $saml_attributes["uid"];
      if (is_numeric($uid) || preg_match("~^(CON|INT)[0-9]{5,}~", $uid)) {
        // uid is six digit or CON/INT +5
        $mail = "{$recipient}@boston.gov";
      }
      elseif (is_numeric($uid) || preg_match("~^BPHC[0-9]{5,}~", $uid)) {
        // uid contains BPHC
        $mail = "{$recipient}@bphc.org";
      }
      elseif (is_numeric($uid) || preg_match("~^BPS[0-9]{5,}~", $uid)) {
        // uid contains BPHC
        $mail = "{$recipient}@bostonpublicschools.org";
      }
    }

    return $mail ?: "{$saml_attributes["uid"]}@boston.gov";

  }

  /**
   * Takes an email and returns a name array (part before @).
   *
   * @param string $email The email
   *
   * @return array Best guess at the users name.
   */
  public static function getNameArrayFromEmail(string $email = NULL) {
    if (!isset($email)) {
      return NULL;
    }
    $name = self::getNameFromEmail($email);
    if (!empty($name)) {
      return explode(" ", $name);
    }
    else {
      return NULL;
    }
  }

  /**
   * Takes an email and returns a name string (part before "@").
   *
   * @param string $email The email
   *
   * @return string Best guess at the users name.
   */
  public static function getNameFromEmail(string $email = NULL) {
    if (!isset($email)) {
      return "";
    }
    elseif (preg_match("/.*@/", $email, $output)) {
      return ucwords(str_replace([".", "_"], " ", trim($output[0], "@")));
    }
    else {
      return "";
    }
  }

}
