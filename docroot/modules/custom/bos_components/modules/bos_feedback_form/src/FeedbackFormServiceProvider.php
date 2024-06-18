<?php

namespace Drupal\bos_feedback_form;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Modifies the webform handler plugin manager service.
 */
class FeedbackFormServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Overrides webform handler plugin manager service to add a YAML to the discovery.
    $definition = $container->getDefinition('plugin.manager.webform.handler');
    $definition->setClass('Drupal\bos_feedback_form\Plugin\ZencityHandlerPluginManager');
    $definition->addArgument(new Reference('module_handler'));
  }

}
