<?php

declare(strict_types=1);

namespace Drupal\decoupled_menus\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Routing event subscriber.
 *
 * Alters this module's routes to enable all authentication providers.
 *
 * @internal
 *   This class's API is internal and it is not intended for extension.
 *
 * @todo remove this service when https://www.drupal.org/project/drupal/issues/3200620 lands.
 */
final class EventSubscriber extends RouteSubscriberBase {

  /**
   * An array of enabled authentication provider IDs.
   *
   * @var string[]
   */
  protected $providerIds;

  /**
   * EventSubscriber constructor.
   *
   * @param string[] $authentication_providers
   *   An array of authentication providers, keyed by ID.
   */
  public function __construct(array $authentication_providers) {
    $this->providerIds = array_keys($authentication_providers);
  }

  /**
   * Alter routes.
   *
   * This dynamically enables all authentication providers on this module's
   * routes since they cannot be known in advance.
   *
   * @param \Symfony\Component\Routing\RouteCollection $collection
   *   A collection of routes.
   */
  public function alterRoutes(RouteCollection $collection) {
    $collection->get('decoupled_menus.menu.linkset')->setOption('_auth', $this->providerIds);
  }

}
