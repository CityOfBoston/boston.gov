<?php

namespace Drupal\bos_core\Controllers\Moderation;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\EventSubscriber\RedirectResponseSubscriber;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ContentModeration extends ControllerBase {

  public function setModeration(ContentEntityInterface $entity, string $state, string $language) {
//    $transitions = \Drupal::service("content_moderation.state_transition_validation")->getValidTransitions($entity, \Drupal::currentUser());
    $revision_log = $this->t('Used the Moderation Sidebar to change the state to "@state".', ['@state' => $state]);
    if ($entity->isTranslatable()) {
      if ($language != $entity->language()->getId()) {
        $entity = $entity->getTranslation($language);
      }
      if (!$entity->isLatestTranslationAffectedRevision()) {
        $revision_id = \Drupal::entityTypeManager()
          ->getStorage('node')
          ->getLatestTranslationAffectedRevisionId($entity->id(), $language);
        $entity = \Drupal::entityTypeManager()
          ->getStorage('node')
          ->loadRevision($revision_id)
          ->getTranslation($language);
      }
      $redirect = new RedirectResponse(\Drupal::request()->getBasePath() . "/{$language}/node/{$entity->id()}");
    }
    else {
      if (!$entity->isLatestRevision()) {
        $revision_id = \Drupal::entityTypeManager()
          ->getStorage('node')
          ->getLatestRevisionId($entity->id());
        $entity = \Drupal::entityTypeManager()
          ->getStorage('node')
          ->loadRevision($revision_id);
      }
      $redirect = new RedirectResponse(\Drupal::request()->getBasePath() . "/node/{$entity->id()}");
    }
    $revision = $this->prepareNewRevision($entity, $revision_log);
    $revision->set('moderation_state', $state);
    $revision->save();
    \Drupal::messenger()->addMessage($this->t('The moderation state has been updated.'));

    if ($state === "draft" || $state === "needs_review") {
      $redirect->setTargetUrl($redirect->getTargetUrl() . "/latest");
    }

    return $redirect;
  }
  /**
   * Prepares a new revision of a given entity, if applicable.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   An entity.
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $message
   *   A revision log message to set.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The moderation state for the given entity.
   */
  protected function prepareNewRevision(EntityInterface $entity, string $message) {
    $storage = \Drupal::entityTypeManager()->getStorage($entity->getEntityTypeId());
    if ($storage instanceof ContentEntityStorageInterface) {
      $revision = $storage->createRevision($entity);
      if ($revision instanceof RevisionLogInterface) {
        $revision->setRevisionLogMessage($message);
        $revision->setRevisionCreationTime(\Drupal::time()->getRequestTime());
        $revision->setRevisionUserId(\Drupal::currentUser()->id());
      }
      return $revision;
    }
    return $entity;
  }
}
