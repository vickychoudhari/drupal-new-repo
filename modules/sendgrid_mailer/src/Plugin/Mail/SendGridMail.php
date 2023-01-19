<?php

namespace Drupal\sendgrid_mailer\Plugin\Mail;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Mail\MailFormatHelper;
use Drupal\Core\Mail\Plugin\Mail\PhpMail;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\sendgrid_api\SendGrid;
use SendGrid\Mail\Mail;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the default Drupal mail backend, using SendGrid API.
 *
 * @Mail(
 *   id = "sendgrid_mail",
 *   label = @Translation("SendGrid mailer"),
 *   description = @Translation("Sends emails using SendGrid API.")
 * )
 */
class SendGridMail extends PhpMail implements ContainerFactoryPluginInterface {

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The SendGrid client.
   *
   * @var \Drupal\sendgrid_api\SendGrid
   */
  protected SendGrid $sendGrid;

  /**
   * The request body object to use in /mail/send SendGrid API call.
   *
   * @var \SendGrid\Mail\Mail
   */
  protected Mail $mail;

  /**
   * MimeMail plugin constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory service.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\sendgrid_api\SendGrid $send_grid
   *   The SendGrid client.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, FileSystemInterface $fileSystem, RendererInterface $renderer, SendGrid $send_grid) {
    // Bypass parent constructor because the parent statically initializes
    // $this->configFactory (defined in the parent) instead of injecting it.
    $this->configFactory = $config_factory;
    $this->fileSystem = $fileSystem;
    $this->renderer = $renderer;
    $this->sendGrid = $send_grid;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('file_system'),
      $container->get('renderer'),
      $container->get('sendgrid_api.client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function format(array $message) {
    if (is_array($message['body'])) {
      $message['body'] = implode("\n\n", $message['body']);
    }

    $body = [
      '#theme' => 'sendgrid_mailer_wrapper',
      '#message' => $message,
      '#module' => $message['module'],
      '#key' => $message['key'],
    ];

    $message['body'] = (string) $this->renderer->renderRoot($body);

    return $message;
  }

  /**
   * {@inheritdoc}
   */
  public function mail(array $message) {
    try {
      $this->mail = new Mail();

      $this->setFrom($message);

      $this->addHeaders($message);

      $this->addRecipients($message);

      $this->mail->setSubject($message['subject']);

      $this->addContent($message);

      $this->addAttachments($message);

      $response = $this->sendGrid->client->mail()->send()->post($this->mail);
      if ($response->statusCode() !== 202) {
        watchdog_exception('sendgrid_mailer', new \Exception('SendGrid Error'), $response->body());
      }

      return $response->statusCode() === 202;
    }
    catch (\Exception $e) {
      watchdog_exception('sendgrid_mailer', $e, $e->getMessage());
    }

    return FALSE;
  }

  /**
   * Sets the sender email address to the Mail object.
   *
   * @param $message
   *   A message array, as described in hook_mail_alter().
   *
   * @throws \SendGrid\Mail\TypeException
   */
  protected function setFrom($message) {
    if (!empty($message['from'])) {
      $address_from = $this->parseAddress($message['from']);
    }
    else {
      $address_from = [
        'email' => $this->configFactory->get('system.site')->get('mail'),
        'name' => $this->configFactory->get('system.site')->get('name'),
      ];
    }

    $this->mail->setFrom($address_from['email'], $address_from['name']);
  }

  /**
   * Adds email recipients to a Mail object.
   *
   * @param $message
   *   A message array, as described in hook_mail_alter().
   * @param $recipient_type
   *   Recipient type name: To, Cc or Bcc. Empty to extract all the types.
   *
   * @throws \SendGrid\Mail\TypeException
   */
  protected function addRecipients($message, $recipient_type = NULL) {
    if (empty($recipient_type)) {
      foreach (['To', 'Cc', 'Bcc'] as $recipient_type) {
        $this->addRecipients($message, $recipient_type);
      }
    }
    else {
      $recipient_type_lower = strtolower($recipient_type);
      $recipient_type = ucfirst($recipient_type_lower);
      $add = "add$recipient_type";
      if ($recipient_type !== 'To') {
        $addresses = !empty($message['headers'][$recipient_type])
        ? $message['headers'][$recipient_type] :
        (!empty($message['headers'][$recipient_type_lower]) ? $message['headers'][$recipient_type_lower] : NULL);
      }
      else {
        $addresses = !empty($message[$recipient_type])
        ? $message[$recipient_type] :
        (!empty($message[$recipient_type_lower]) ? $message[$recipient_type_lower] : NULL);
      }

      if (!empty($addresses)) {
        if (is_string($addresses)) {
          $addresses = explode(',', $addresses);
        }
        foreach ($addresses as $address) {
          $address = $this->parseAddress($address);
          $this->mail->$add($address['email'], $address['name']);
        }
      }
    }
  }

  /**
   * Adds headers to the Mail object.
   *
   * @param $message
   *   A message array, as described in hook_mail_alter().
   *
   * @throws \SendGrid\Mail\TypeException
   */
  protected function addHeaders($message) {
    $excluded_headers = [
      'Content-Type',
      'Content-Transfer-Encoding',
      'From',
      'cc',
      'Cc',
      'bcc',
      'Bcc',
    ];
    foreach ($message['headers'] as $key => $value) {
      if (in_array($key, $excluded_headers)) {
        continue;
      }
      if ($key == 'Reply-to') {
        $address = $this->parseAddress($value);
        $this->mail->setReplyTo($address['email'], $address['name']);
      }
      else {
        $this->mail->addHeader($key, $value);
      }
    }
  }

  /**
   * Adds content plain and html to the Mail object.
   *
   * @param $message
   *   A message array, as described in hook_mail_alter().
   *
   * @throws \SendGrid\Mail\TypeException
   */
  protected function addContent($message) {
    if (!empty($message['plaintext'])) {
      $plaintext = $message['plaintext'];
    }
    else {
      $plaintext = $this->htmlToText($message['body']);
    }

    $this->mail->addContent('text/plain', $plaintext);

    $message['plain'] = $message['plain'] ?? FALSE;
    if ($message['plain'] !== TRUE) {
      $this->mail->addContent('text/html', $message['body']);
    }
  }

  /**
   * Adds attachments to the Mail object.
   *
   * @param $message
   *   A message array, as described in hook_mail_alter().
   *
   * @throws \SendGrid\Mail\TypeException
   */
  protected function addAttachments($message) {
    $attachments = $message['params']['attachments'] ?? ($message['attachments'] ?? []);
    foreach ($attachments as $attachment) {
      $path = $this->fileSystem->realpath($attachment['uri']);
      if ($path) {
        $file_content = file_get_contents($path);
        $this->mail->addAttachment(base64_encode($file_content), $attachment['filemime'], $attachment['filename'], 'attachment');
      }
    }
  }

  /**
   * Transforms an HTML string into plain text, preserving its structure.
   *
   * @param $string
   *   The string to be transformed.
   *
   * @return string
   *   The transformed string.
   */
  protected function htmlToText($string): string {
    if (preg_match('/<body.*?>(.*?)<\/body>/mis', $string, $matches) === 1) {
      $string = $matches[1];
    }
    return MailFormatHelper::htmlToText($string);
  }

  /**
   * Parses the given address to extract name and email separately.
   *
   * @param $address
   *   The address to parse.
   *
   * @return array
   *   Array containing `name` and `email` extracted from the given string.
   */
  protected function parseAddress($address): array {
    $address = trim($address);
    preg_match_all('/(.*?)<(.*?)>/', $address, $matches);
    // Extract string before '<'.
    $name = !empty($matches[1][0]) ? trim($matches[1][0]) : NULL;
    // Extract string between '<' and '>'.
    $email = !empty($matches[2][0]) ? trim($matches[2][0]) : $address;
    return [
      'name' => $name,
      'email' => $email,
    ];
  }

}
