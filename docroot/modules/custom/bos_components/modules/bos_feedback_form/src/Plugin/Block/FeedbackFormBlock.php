<?php

namespace Drupal\bos_feedback_form\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'Feedback Form' block.
 *
 * @Block(
 *   id = "feedback_form",
 *   admin_label = @Translation("Feedback Form"),
 *   category = @Translation("Boston"),
 * )
 */
class FeedbackFormBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
    /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'feedback_button_title' => "Provide Your Feedback",
      "feedback_wrapper_css" => "",
      "feedback_button_css" => ""
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $form['feedback_button_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Button Text'),
      '#description' => $this->t('This is the text for the feedback form button.'),
      '#default_value' => $this->configuration['feedback_button_title'] ?? "",
    ];
    $form['feedback_wrapper_css'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom Wrapper CSS'),
      '#description' => $this->t('Additional CSS (from patterns library) to add to the button wrapper. e.g. <i>ta--c</i> will center button on page.'),
      '#default_value' => $this->configuration['feedback_wrapper_css'] ?? "",
    ];
    $form['feedback_button_css'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom Button CSS'),
      '#description' => $this->t('Additional CSS (from patterns library) to add to the actual button.'),
      '#default_value' => $this->configuration['feedback_button_css'] ?? "",
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $this->configuration['feedback_button_title'] = $form_state->getValue('feedback_button_title');
    $this->configuration['feedback_wrapper_css'] = $form_state->getValue('feedback_wrapper_css');
    $this->configuration['feedback_button_css'] = $form_state->getValue('feedback_button_css');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $params = [
      "title" => $this->configuration["feedback_button_title"],
      "wrapper_css" => $this->configuration["feedback_wrapper_css"],
      "button_css" => $this->configuration["feedback_button_css"],
    ];
    return [
      [
        '#lazy_builder' => ['bos_feedback_form.callbacks:renderForm', $params ],
        '#create_placeholder' => TRUE,
      ],
    ];
  }

}
