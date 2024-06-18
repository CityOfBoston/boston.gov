<?php

namespace Drupal\bos_core\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountSetEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Session\AccountEvents;

/**
 * Class AccountSubscriber.
 *
 * @package Drupal\bos_core\EventSubscriber
 */

class AccountSubscriber implements EventSubscriberInterface {

  /**
   * The config.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $currentUser;

  /**
   * The account being set.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  private $account;

  /**
   * AccountSubscriber constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(AccountInterface $current_user, ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
    $this->currentUser = $current_user;
  }

  /**
   * @inheritDoc
   */
  public static function getSubscribedEvents() {
    return [
      AccountEvents::SET_USER => 'setuser',
    ];
  }

  /**
   * This is the main code for the event.
   *
   * @param \Drupal\Core\Session\AccountSetEvent $user
   *
   * @return void
   */
  public function setuser(AccountSetEvent $user) {
    if (!$user->getAccount()->isAnonymous()) {
      // If the user has not defined a preferred back-end language, then set
      // to english.
//      $this->setPreferredAdminLangcode($user, "en");
    }
  }

  /**
   * Set the Preferred Admin Langcode for the user being loaded
   *
   * @param \Drupal\Core\Session\AccountSetEvent $user
   *
   * @return void
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function setPreferredAdminLangcode(AccountSetEvent $user, string $langcode = "en") {
//  TODO: DU
//    Setting preferred_admin_langcode causes the user not to be able to edit
//    translated content, the user just sees the preferred language version of
//    the node being editted.
//    This was an attempt to ensure the language of the backend interface (i.e.
//    not content) is always english regardless of the language of the content
//    being editted.
    if (empty($user->getAccount()->getPreferredAdminLangcode(FALSE))) {

      if (empty($this->account)) {
          $this->account = \Drupal::entityTypeManager()
            ->getStorage('user')
            ->load($user->getAccount()->id());
        }

        if (!empty($this->account)) {
          $this->account->set("preferred_admin_langcode", $langcode);
          $this->account->save();
        }

    }

  }

}
