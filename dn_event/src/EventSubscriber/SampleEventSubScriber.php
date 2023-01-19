<?php

/**
 * @file
 * Contains \Drupal\dn_event\ExampleEventSubScriber.
 */

namespace Drupal\dn_event\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\dn_event\SampleEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;


/**
 * Class ExampleEventSubScriber.
 *
 * @package Drupal\dn_event
 */
class SampleEventSubScriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    
    $events[SampleEvent::SUBMIT][] = array('doSomeAction', 800);
    return $events;

  }

  /**
   * Subscriber Callback for the event.
   * @param SampleEvent $event
   */
  public function doSomeAction(SampleEvent $event) {
    
    \Drupal::messenger()->addMessage("The Example Event has been subscribed, which has bee dispatched on submit of the form with " . $event->getReferenceID() . " as Reference");
  }

  
}