<?php

use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Extends the samlauth configuration form to allow roles and realname to be
 * customised from the UI.
 * The settings are saved in the custom submit handler
 * _bos_core_saml_configure_form_submit() and the configuration is used by
 * samlAuthentication.php to extend basic saml processes from samlauth and
 * externalauth modules.
 *
 * Implements hook_form_FORM_ID_alter().
 */
function bos_core_form_samlauth_configure_form_alter(&$form, $form_state, $form_id) {
  $config = \Drupal::config("samlauth.authentication");
  $form["user_info"]["realname"] = [
    "#default_value" => $config->get("realname") ?: "",
    '#description' => Markup::create("When a user is created, create a realname from these attributes. Linked users do not get additional roles.<br>Example <i>sn lastname</i>"),
    '#title' => Markup::create("User realname attribute"),
    '#attributes' => ["placeholder" => "sn lastname"],
    '#type' => 'textfield',
  ];
  $form["#submit"][] = "samlauth_extensions_form_submit";
  $form["saml_login_logout"]["allow_local_login"] = [
    "#type" => "checkbox",
    '#default_value' => $config->get("allow_local_login") ?: 0,
    '#title' => Markup::create("Allow local logins"),
    '#description' => Markup::create("When selected users can login with a local account at user/login. Deselected means SSO only."),
    '#weight' => -11,
  ];
  $form["saml_login_logout"]["login_menu_item_title"]["#weight"] = -10;
  $form["saml_login_logout"]["login_menu_item_instructions"] = [
    "#default_value" => $config->get("login_menu_item_instructions") ?: "",
    '#description' => Markup::create("Provide some login instructions/narrative"),
    '#title' => Markup::create("Login menu item instructions"),
    '#type' => 'textfield',
    "#weight" => -9,
  ];
  $form["saml_login_logout"]["logout_menu_item_title"]["#weight"] = -8;
  $form["saml_login_logout"]["logout_different_user"]["#weight"] = -7;
  $form_state->setRebuild(true);
}

/**
 * Custom submit handler for the samlauth sconfig form which was extended in
 * bos_core_form_samlauth_configure_form_alter. Saves new fields into the
 * 'samlauth.authentication' configuration for use in samlAuthentication.php.
 *
 * @param $form
 * @param $form_state
 *
 * @return void
 */
function samlauth_extensions_form_submit($form, $form_state) {
  $save = FALSE;
  $config = \Drupal::configFactory()->getEditable("samlauth.authentication");
  if ($form_state->hasValue("realname")) {
    $newval = $form_state->getValue("realname");
    $existingval = $config->get("realname") ?: "";
    if ($newval !== $existingval) {
      $config->set("realname", $newval);
      $save = TRUE;
    }
  }
  if ($form_state->hasValue("login_menu_item_instructions")) {
    $newval = $form_state->getValue("login_menu_item_instructions");
    $existingval = $config->get("login_menu_item_instructions") ?: "";
    if ($newval !== $existingval) {
      $config->set("login_menu_item_instructions", $newval);
      $save = TRUE;
    }
  }
  if ($form_state->hasValue("allow_local_login")) {
    $newval = $form_state->getValue("allow_local_login");
    $existingval = $config->get("allow_local_login") ?: "";
    if ($newval !== $existingval) {
      $config->set("allow_local_login", $newval);
      $save = TRUE;
    }
  }
  if ($save) {
    $config->save();
  }
}

/**
 * Implements hook_user_logout().
 *
 * This effectively redirects the user to the saml logout when a
 * regular drupal logout occurs for an account.
 */
function bos_core_user_logout($account) {

  if (str_contains(\Drupal::request()->getRequestUri(), "masquerade")) {
    // This probably is a maquerade or unmasquerade request.
    return;
  }

  $request = \Drupal::requestStack()->getCurrentRequest();
  if ($request->hasSession() && ($session = $request->getSession())) {
    if ($session->has('masquerading')) {
      // This is a logout action being take on an account while masquerading.
      return;
    }
    // Find the actual user account for this session
    $session_uid = $session->get('uid');
  }
  else {
    $session_uid = \Drupal::currentUser()->id();
  }

  if ($session_uid != $account->id()) {
    // The account in the session is not the account being logged out.
    return;
  }

  $service = \Drupal::service("externalauth.authmap");
  $authmap_id = $service->getAuthData($session_uid, "samlauth");
  if (!$authmap_id) {
    // The account being logged out does not have an authmap entry, so it's not
    // saml-managed.
    return;
  }
  // Finally, redirect to the saml logout.
  $url = Url::fromRoute('samlauth.saml_controller_logout', [], ['absolute' => 'true']);
  $response = new RedirectResponse($url->toString());
  \Drupal::service('bos_core.redirect')->setRedirectResponse($response);
  $response->send();
}

