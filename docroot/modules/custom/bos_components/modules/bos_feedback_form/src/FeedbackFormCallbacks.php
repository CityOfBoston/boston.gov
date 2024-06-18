<?php

namespace Drupal\bos_feedback_form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Masquerade callbacks.
 */
class FeedbackFormCallbacks implements TrustedCallbackInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * MasqueradeCallbacks constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\masquerade\Masquerade $masquerade
   *   The masuerade.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['renderForm'];
  }

  /**
   * Lazy builder callback for switch-back link.
   *
   * @return array|string
   *   Render array or an emty string.
   */
  public function renderForm(string $title, ?string $wrapper_class = NULL, ?string $button_class = NULL) {

    $wrapper_class = "webform-dialog-button-wrapper " . ($wrapper_class ?? "");
    $button_class = "webform-dialog webform-dialog-narrow button webform-dialog-button br--3 " . ($button_class ?? "");
    $query = "?source_entity_type=ENTITY_TYPE&amp;source_entity_id=ENTITY_ID";

    return [
      [
        '#prefix' => "<div class='$wrapper_class'>",
        '#markup' => "<a href='/form/website-feedback-form$query' class='$button_class'>$title</a>",
        '#suffix' => '</div>',
      ],
    ];
  }

}
