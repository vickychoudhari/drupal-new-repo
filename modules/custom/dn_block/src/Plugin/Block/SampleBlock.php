<?php

namespace Drupal\dn_block\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Sample: Simple Block' block.
 *
 * @Block(
 *   id = "sample_simple_block",
 *   admin_label = @Translation("Sample: Simple Block")
 * )
 */
class SampleBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $form = \Drupal::formBuilder()->getForm('Drupal\dn_block\Form\StudentForm');
    return [
      '#markup' => $this->t("Sample Block"),
      'form' => $form
    ];
  }

}
