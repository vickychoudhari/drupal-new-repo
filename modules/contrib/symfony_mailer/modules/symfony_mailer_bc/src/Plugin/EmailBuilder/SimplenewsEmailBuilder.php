<?php

namespace Drupal\symfony_mailer_bc\Plugin\EmailBuilder;

use Drupal\simplenews\SubscriberInterface;
use Drupal\symfony_mailer\EmailFactoryInterface;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Entity\MailerPolicy;

/**
 * Defines the Email Builder plug-in for simplenews module.
 *
 * @EmailBuilder(
 *   id = "simplenews",
 *   sub_types = {
 *     "subscribe" = @Translation("Subscription confirmation"),
 *     "validate" = @Translation("Validate"),
 *   },
 *   proxy = TRUE,
 *   common_adjusters = {"email_subject", "email_body"},
 *   import = @Translation("Simplenews subscriber settings"),
 *   import_warning = @Translation("This overrides the default HTML messages with imported plain text versions."),
 * )
 */
class SimplenewsEmailBuilder extends SimplenewsEmailBuilderBase {

  /**
   * Saves the parameters for a newly created email.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to modify.
   * @param \Drupal\simplenews\SubscriberInterface $subscriber
   *   The subscriber.
   */
  public function createParams(EmailInterface $email, SubscriberInterface $subscriber = NULL) {
    assert($subscriber != NULL);
    $email->setParam('simplenews_subscriber', $subscriber);
  }

  /**
   * {@inheritdoc}
   */
  public function fromArray(EmailFactoryInterface $factory, array $message) {
    if ($message['key'] == 'node' || $message['key'] == 'test') {
      $mail = $message['params']['simplenews_mail'];
      return $factory->newEntityEmail($mail->getNewsletter(), 'node', $mail->getIssue(), $mail->getSubscriber(), ($mail->getKey() == 'test'));
    }

    $key = ($message['key'] == 'subscribe_combined') ? 'subscribe' : 'validate';
    return $factory->newTypedEmail('simplenews', $key, $message['params']['context']['simplenews_subscriber']);
  }

  /**
   * {@inheritdoc}
   */
  public function import() {
    $subscription = $this->helper()->config()->get('simplenews.settings')->get('subscription');

    $convert = [
      'confirm_combined' => 'subscribe',
      'validate' => 'validate',
    ];

    foreach ($convert as $from => $to) {
      $config = [
        'email_subject' => ['value' => $subscription["{$from}_subject"]],
        'email_body' => ['value' => $subscription["{$from}_body"]],
      ];
      MailerPolicy::import("simplenews.$to", $config);
    }
  }

}
