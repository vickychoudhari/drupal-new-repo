<?php

namespace Drupal\dn_block\Plugin\Block;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Sample: Simple Block' block.
 *
 * @Block(
 *   id = "sample_config_block",
 *   admin_label = @Translation("Sample: Config Block")
 * )
 */
class SampleConfigBlock extends BlockBase {

    /**
     * {@inheritdoc}
     *
     * This method sets the block default configuration. This configuration
     * determines the block's behavior when a block is initially placed in a
     * region. 
     *
     * @see \Drupal\block\BlockBase::__construct()
     */
    public function defaultConfiguration() {
      return [
        'block_sample_string' => $this->t('A default value. This block was created at %time', ['%time' => date('c')]),
      ];
    }
  
    /**
     * {@inheritdoc}
     *
     * This method defines form elements for custom block configuration. 
     * @see \Drupal\block\BlockBase::buildConfigurationForm()
     * @see \Drupal\block\BlockFormController::form()
     */
    public function blockForm($form, FormStateInterface $form_state) {
      $form['block_sample_string_text'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Block contents'),
        '#description' => $this->t('This text will appear in the sample block.'),
        '#default_value' => $this->configuration['block_sample_string'],
      ];
      return $form;
    }
  
    /**
     * {@inheritdoc}
     *
     * This method processes the blockForm() form fields when the block
     * configuration form is submitted.
     * The blockValidate() method can be used to validate the form submission.
     */
    public function blockSubmit($form, FormStateInterface $form_state) {
      $this->configuration['block_sample_string']
        = $form_state->getValue('block_sample_string_text');
    }
  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
        '#markup' => $this->configuration['block_sample_string'],
      ];
  }

}
