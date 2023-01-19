<?php

declare(strict_types=1);

namespace Drupal\decoupled_menus\Controller;

use Drupal\Component\Assertion\Inspector;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\system\MenuInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Linkset controller.
 *
 * Provides a menu endpoint.
 *
 * @internal
 *   This class's API is internal and it is not intended for extension.
 */
final class Linkset extends ControllerBase {

  /**
   * Linkset constructor.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menu_tree_loader
   *   The menu tree loader service. This is used to load a menu's link
   *   elements so that they can be serialized into a linkset response.
   */
  public function __construct(MenuLinkTreeInterface $menu_tree_loader) {
    $this->menuTree = $menu_tree_loader;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('menu.link_tree'));
  }

  /**
   * Serve linkset requests.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   An HTTP request.
   * @param \Drupal\system\MenuInterface $menu
   *   A menu for which to produce a linkset.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   A linkset response.
   */
  public function process(Request $request, MenuInterface $menu) {
    // Load the given menu's tree of elements.
    $tree = $this->loadMenuTree($menu);
    // Get the incoming request URI and parse it so the linkset can use a
    // relative URL for the linkset anchor.
    ['path' => $path, 'query' => $query] = parse_url($request->getUri()) + ['query' => FALSE];
    // Construct a relative URL.
    $anchor = $path . (!empty($query) ? '?' . $query : '');
    $cacheability = CacheableMetadata::createFromObject($menu);
    // Encode the menu tree as links in the application/linkset+json media type
    // and add the machine name of the menu to which they belong.
    $menu_id = $menu->id();
    $links = $this->toLinkTargetObjects($tree, $cacheability);
    foreach ($links as $rel => $target_objects) {
      $links[$rel] = array_map(function (array $target) use ($menu_id) {
        // According to the Linkset specification, this member must be an array
        // since the "drupal-menu-machine-name" target attribute is non-standard.
        // See https://tools.ietf.org/html/draft-ietf-httpapi-linkset-00#section-4.2.4.3
        return $target + ['drupal-menu-machine-name' => [$menu_id]];
      }, $target_objects);
    }
    $linkset = !empty($tree)
      ? [['anchor' => $anchor] + $links]
      : [];
    $data = ['linkset' => $linkset];
    // Set the response content-type header.
    $headers = ['content-type' => 'application/linkset+json'];
    $response = CacheableJsonResponse::create($data, 200, $headers);
    // Attach cacheability metadata to the response.
    $response->addCacheableDependency($cacheability);
    return $response;
  }

  /**
   * Encode a menu tree as link items and capture any cacheability metadata.
   *
   * This method recursively traverses the given menu tree to produce a flat
   * array of link items encoded according the the application/linkset+json
   * media type.
   *
   * To preserve hierarchical information, the `drupal-menu` target attribute,
   * contains a `hierarchy` member. Its value is a lexicographically sortable
   * string which can be used to reconstruct a hierarchical data structure.
   *
   * The reason that a `hierarchy` member is used instead of a `parent` or
   * `children` member is because it is more compact, more suited to the linkset
   * media type, and because it simplifies many menu operations. Specifically:
   *
   * 1. Creating a `parent` member would require each link to have an `id`
   *    in order to have something referenceable by the `parent` member. Reusing
   *    the link plugin IDs would not be viable because it would leak
   *    information about which modules are installed on the site. Therefore,
   *    this ID would have to be invented and would probably end up looking a
   *    lot like the `hierarchy` value. Finally, link IDs would encourage
   *    clients to hardcode the ID instead of using link relation types
   *    appropriately.
   * 2. The linkset media type is not itself hierarchical. This means that
   *    `children` is infeasible without inventing our own Drupal-specific media
   *    type.
   * 3. By using simple string comparisons, the `hierarchy` member can be used
   *    to efficiently perform tree operations that would otherwise be more
   *    complicated to implement. For example, using a "starts with" comparison,
   *    you can find any subtree without writing recursive logic or complicated
   *    loops. Visit the URL below for more examples.
   *
   * The structure of a `hierarchy` value is defined below.
   *
   * A link which is a child of another link will always be prefixed by the
   * exact value of their parent's hierarchy member. For example, if a link /bar
   * is a child of a link /foo and /foo has a hierarchy member with the value
   * ".001", then the the link /bar might have a hierarchy member with the value
   * ".001.000". The link /foo can be said to have depth 1, while the link
   * /bar can be said to have depth 2. Links of the same depth will always have
   * a hierarchy value of the same character length.
   *
   * Links which have the same parent (or no parent) have their relative order
   * preserved in the final component of the hierarchy value. Applications can
   * reconstruct this order by sorting that value in an ascending direction.
   *
   * Applications must not assume that the numerical value between dots (".")
   * will always be less than 1000. This number may be increased in the future.
   *
   * However, applications may rely on the length of the hierarchy value to be
   * uniform across all items by increasing the number of left-padded
   * characters.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement[] $tree
   *   A tree of menu elements.
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface $cacheability
   *   An object to capture any cacheability metadata.
   * @param string $hierarchy_prefix
   *   (Internal use only) The hierarchy string value of the the parent element
   *   if $tree is a subtree. Do not pass this value.
   *
   * @return array
   *   An array which can be JSON-encoded to represent the given link tree.
   *
   * @see https://www.drupal.org/project/decoupled_menus/issues/3196342#comment-14016222
   */
  protected function toLinkTargetObjects(array $tree, RefinableCacheableDependencyInterface $cacheability, $hierarchy_prefix = ''): array {
    $links = [];
    // Calling array_values() discards any key names so that $index will be
    // numerical.
    foreach (array_values($tree) as $index => $element) {
      // Extract and preserve the access cacheability metadata.
      $element_access = $element->access;
      assert($element_access instanceof AccessResultInterface);
      $cacheability->addCacheableDependency($element_access);
      // If an element is not accessible, it should not be encoded. Its
      // cacheability should be preserved regardless, which is why that is done
      // outside of this conditional.
      if ($element_access->isAllowed()) {
        // Get and generate the URL of the link's target. This can create
        // cacheability metadata also.
        $url = $element->link->getUrlObject();
        $generated_url = $url->toString(TRUE);
        $cacheability = $cacheability->addCacheableDependency($generated_url);
        // Create the hierarchy value for the current element and prefix it
        // with the link element parent's hierarchy value. See this method's
        // docblock for more context on why this value is the way it is.
        $current_component = str_pad("{$index}", 3, "0", STR_PAD_LEFT);
        $hierarchy = sprintf('%s.%s', $hierarchy_prefix, $current_component);
        $link_options = $element->link->getOptions();
        $link_attributes = ($link_options['attributes'] ?? []);
        $link_rel = $link_attributes['rel'] ?? 'item';
        // Encode the link.
        $links[$link_rel][] = [
          'href' => $generated_url->getGeneratedUrl(),
          // @todo should this use the "title*" key if it is internationalized?
          'title' => $element->link->getTitle(),
          // According to the Linkset specification, this member must be an
          // array since the "drupal-menu-hierarchy" target attribute is
          // non-standard.
          // See https://tools.ietf.org/html/draft-ietf-httpapi-linkset-00#section-4.2.4.3
          'drupal-menu-hierarchy' => [$hierarchy],
        ];
        // Recurse into the element's subtree.
        if (!empty($element->subtree)) {
          // Recursion!
          $links = array_merge_recursive($links, $this->toLinkTargetObjects($element->subtree, $cacheability, $hierarchy));
        }
      }
    }

    return $links;
  }

  /**
   * Loads a menu tree.
   *
   * @param \Drupal\system\MenuInterface $menu
   *   A menu for which a tree should be loaded.
   *
   * @return \Drupal\Core\Menu\MenuLinkTreeElement[]
   *   A menu link tree.
   */
  protected function loadMenuTree(MenuInterface $menu) : array {
    $parameters = new MenuTreeParameters();
    $parameters->onlyEnabledLinks();
    $parameters->setMinDepth(0);
    $tree = $this->menuTree->load($menu->id(), $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $this->menuTree->transform($tree, $manipulators);
    return $tree;
  }

}
