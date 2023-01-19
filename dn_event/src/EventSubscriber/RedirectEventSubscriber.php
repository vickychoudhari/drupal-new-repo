<?php

namespace Drupal\dn_event\EventSubscriber;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;


/**
 * Event subscriber subscribing to KernelEvents::REQUEST.
 */
class RedirectEventSubscriber implements EventSubscriberInterface {

  public function checkAuthStatus(GetResponseEvent $event) {

    global $base_url;

   

      if(\Drupal::routeMatch()->getRouteName() == 'dn_event.add_student' && \Drupal::currentUser()->isAnonymous()){
        $response = new RedirectResponse($base_url . '/user/login', 301);
        $event->setResponse($response);
        $event->stopPropagation();
		  return;
	  }
  
  }

  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = array('checkAuthStatus');
    return $events;
  }
}