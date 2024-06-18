<?php

namespace Drupal\bos_core\Plugin\Action;

/**
 * Action description.
 *
 * @Action(
 *   id = "bos_core_moderation_bulk_operations",
 *   label = @Translation("Draft -> Publish content"),
 *   type = "",
 *   confirm = TRUE,
 * )
 */

/*   requirements = {
 *     "_permission" = "access content",
 *   },
 *
 */

use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Class ModerationBulkOperations.
 *
 * @package Drupal\bos_core\Plugin\Action
 */
class ModerationBulkOperations extends ViewsBulkOperationsActionBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    // Do some processing..
    // Don't return anything for a default completion message, otherwise
    // return translatable markup.
    return $this->t('Changed Moderation State');
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($object->getEntityType() === 'node') {
      $access = $object->access('update', $account, TRUE)
        ->andIf($object->status->access('edit', $account, TRUE));
      return $return_as_object ? $access : $access->isAllowed();
    }

    // Other entity types may have different
    // access methods and properties.
    return TRUE;
  }

}
