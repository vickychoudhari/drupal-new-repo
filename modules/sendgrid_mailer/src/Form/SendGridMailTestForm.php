<?php

namespace Drupal\sendgrid_mailer\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SendGridMailTestForm.
 *
 * @package Drupal\sendgrid_mailer\Form
 */
class SendGridMailTestForm extends FormBase {

  /**
   * The mail manager service.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * SendGridTestForm constructor.
   *
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   *   The mail manager service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(MailManagerInterface $mailManager, LanguageManagerInterface $languageManager, MessengerInterface $messenger) {
    $this->mailManager = $mailManager;
    $this->languageManager = $languageManager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.mail'),
      $container->get('language_manager'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sendgrid_mailer_test';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['from_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('From name'),
      '#maxlength' => 128,
    ];
    $form['to'] = [
      '#type' => 'textfield',
      '#title' => $this->t('To'),
      '#maxlength' => 128,
      '#required' => TRUE,
    ];
    $form['to_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('To Name'),
      '#maxlength' => 128,
    ];
    $form['cc'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CC'),
      '#maxlength' => 128,
    ];
    $form['bcc'] = [
        '#type' => 'textfield',
      '#title' => $this->t('BCC'),
      '#maxlength' => 128,
    ];
    $form['reply_to'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Reply-To'),
      '#maxlength' => 128,
    ];
    $form['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject'),
      '#maxlength' => 128,
      '#required' => TRUE,
    ];
    $form['include_attachment'] = [
      '#title' => $this->t('Include attachment'),
      '#type' => 'checkbox',
      '#description' => t('If checked, the Drupal icon will be included as an attachment with the test email.'),
      '#default_value' => TRUE,
    ];
    $form['body'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Body'),
      '#rows' => 20,
      '#required' => TRUE,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send test message'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $body = $form_state->getValue('body');
    $params['include_test_attachment'] = $form_state->getValue('include_attachment');
    $params['body'] = check_markup($body['value'], $body['format']);
    $params['subject'] = $form_state->getValue('subject');
    $params['reply_to'] = $form_state->getValue('reply_to');
    $params['to'] = $form_state->getValue('to');
    $params['cc'] = $form_state->getValue('cc');
    $params['bcc'] = $form_state->getValue('bcc');
    $params['from'] = $form_state->getValue('from_name');

    $site_settings = $this->config('system.site');
    // Attempt to send the email and post a message if it was successful.
    if ($form_state->getValue('from_name')) {
      $from = $form_state->getValue('from_name') . ' <' . $site_settings->get('mail') . '>';
    }
    else {
      $from = $site_settings->get('mail');
    }
    $result = $this->mailManager->mail('sendgrid_mailer', 'sengrid_mailer_troubleshooting_test', $form_state->getValue('to'), $this->languageManager->getDefaultLanguage()
      ->getId(), $params, $from);
    if (isset($result['result']) && $result['result'] == TRUE) {
      $this->messenger->addMessage($this->t('SendGrid test email sent from %from to %to.', [
        '%from' => $from,
        '%to' => $form_state->getValue('to'),
      ]));
    }
  }

}
