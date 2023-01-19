<?php

namespace Drupal\customcode\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Messenger\MessengerTraits;
use Symfony\Component\Httpkernal\KernalEvents;
use Symfony\Component\httpkernal\Event\FilterResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;


/**
 * Class EntityTypeSubscriber.
 *
 * @package Drupal\custom_events\EventSubscriber
 */
class DemoEventsubscriber implements EventSubscriberInterface {
}