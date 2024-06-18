<?php

use Drupal\bos_core\EventSubscriber\SamlAuthentication;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;

/**
 * This block catches accounts which do not have a proper realname assigned.
 * With new SAML login July 2023, this hook/function can safely be decomissioned
 * in late 2023, 6 months after deployment.
 *
 * KEEP
 *
 * Extends HOOK bos_core_preprocess_username() from bos_core.module.
 */
function realname_extension_preprocess_username(&$variables) {

  if (empty($variables["account"])) {
    return;
  }

  $name = $variables["account"]->realname;
  if (empty($name) || is_numeric(substr($name,4)) || str_contains($name, "@")) {
    $config = \Drupal::config("samlauth.authentication");
    $account = $variables["account"];
    $name = SamlAuthentication::getNameArrayFromEmail($account->getEmail()) ?: ["", ""];
    if (!empty($name)) {
      $params = [
        "uid" => $account->getAccountName(),
        "email" => $account->getEmail(),
        "given_name" => $name[0],
        "sn" => isset($name[1]) ? $name[1] : "",
      ];
      $realname = SamlAuthentication::getRealnameFromSAML($params, $config->get("realname"));
      if (!empty($realname)) {
        realname_extensions_save_realname($account, $realname);
        $account->realname = $realname;
        $variables["account"] = $account;
      }
    }

  }

}

/**
 * Writes the realname to the database.
 * NOTE: this SQL is copied directly from realname.module::realname_update()
 *
 * KEEP
 *
 * @param $account
 * @param $realname
 *
 * @return void
 */
function realname_extensions_save_realname($account, $realname) {
  if ($account->id()) {
    \Drupal::database()->merge('realname')
      ->key('uid', $account->id())
      ->fields([
        'realname' => $realname,
        'created' => \Drupal::time()->getRequestTime(),
      ])
      ->execute();
  }
}

/**
 * Adds the Accounts realname to the User Edit form.
 * Config is saved in custom submit callback at
 * realname_extensions_user_form_submit().
 *
 * KEEP
 *
 * Implements hook_form_FORM_ID_alter().
 */
function realname_extensions_form_user_form_alter(&$form, FormStateInterface $form_state, $form_id) {
  $config = \Drupal::config("realname.settings");
  $account = $form_state->getBuildInfo()["callback_object"]->getEntity();
  $form["account"]["realname"] = [
    "#access" => TRUE,
    "#attributes" => ["class" => ["username"]],
    "#default_value" => $account->realname,
    "#maxlength" => 60,
    "#title" => Markup::create("Realname"),
    "#description" => Markup::create("This is the actual displayname for this account."),
    "#type" => "textfield"
  ];
  if (!empty($config->get("pattern"))) {
    $form["account"]["realname"]["#attributes"]["disabled"] = "disabled";
    $form["account"]["realname"]["#description"] = Markup::create("This is the actual displayname for this account.<br><b>- Disabled because there is a default realname pattern set at /admin/config/people/realname</b>");
  }
  $form["#submit"][] = "realname_extensions_user_form_submit";
  $form_state->setRebuild(true);
}

/**
 * Submit Callback:
 * Saves the Account realname if altered on the form before it was submitted.
 *
 * KEEP
 */
function realname_extensions_user_form_submit($form, $form_state) {
  if ($form_state->hasValue("realname")) {

    $account = $form_state->getBuildInfo()["callback_object"]->getEntity();

    $newval = $form_state->getValue("realname");
    $existingval = $account->realname ?: "";

    if ($newval !== $existingval) {
      realname_extensions_save_realname($account, $newval);
    }
  }
}


/**
 * Extracts a custom realname for a user using the $account object properties.
 *
 * @param $account
 *
 * @return string
 */
function realname_extensions_create_realname($account, $realname) {

  if (empty($realname) && !empty($account->realname)) {
    // Realname does exist.
    return $account->realname;
  }

  if ($account->id() == 0) {
    return "anonymous";
  }
  elseif ($account->id() == 1) {
    return "cob-admin";
  }

  $_realname = NULL;
  if ($account->hasField("name")) {
    // Use the display name.
    $_realname = $account->getDisplayName() ?: $account->getAccountName();
    if (is_numeric($_realname) || preg_match("~(CON|INT|BPHC|BPS)[0-9]*~", $_realname)) {
      // We don't want COB ID's to appear as names.
      $_realname = NULL;
    }
    elseif (str_contains($_realname, "@")) {
      // If the displayname is an email for some reason
      $_realname = SamlAuthentication::getNameFromEmail($_realname);
    }
  }

  if (empty($_realname) && $account->hasField("mail")) {
    // try to get the users name from their email.
    $_realname = SamlAuthentication::getNameFromEmail($account->getEmail());
  }

  if (empty($_realname)) {
    // OK we have to use something ...
    $_realname = $account->getDisplayName() ?:
      ($account->getAccountName() ?:
        ($realname ?: "new-user"));
  }

  return $_realname;
}
/**
 * Implements hook_realname_alter().
 */
function bos_core_realname_alter(&$realname, $account) { }

/**
 * Implements hook_realname_update().
 *
 * Programmatically enforces realname regardless of realname module settings.
 * Note: This will override any realname pattern set in the UI.
 */
function bos_core_realname_update($realname, $account) { }
