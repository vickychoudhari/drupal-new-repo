<?php

namespace Drupal\dn_mail\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\symfony_mailer\EmailFactoryInterface;
use Drupal\symfony_mailer\MailerHelperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Symfony Mailer test email form.
 */
class SendContact extends FormBase {

  

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'symfony_mailer_test_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    
    
    $form['#tree'] = TRUE;

    $form['recipient'] = [
      '#title' => $this->t('Recipient'),
      '#type' => 'textfield',
      '#default_value' => '',
      '#description' => $this->t('Recipient email address. Leave blank to send to yourself.'),
    ];

    

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
 
   
    $emailFactory = \Drupal::service('email_factory');
    
    $to = $form_state->getValue('recipient') ?: $this->currentUser();
    $emailFactory->newModuleEmail('symfony_mailer', 'test')
      ->setTo($to)
      ->send();
    $message = is_object($to) ?
      $this->t('An attempt has been made to send an email to you.') :
      $this->t('An attempt has been made to send an email to @to.', ['@to' => $to]);
    $this->messenger()->addMessage($message);
  }

}
