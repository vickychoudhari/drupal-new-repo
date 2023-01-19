<?php

declare(strict_types=1);

namespace Drupal\Tests\decoupled_menus\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Url;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\UserInterface;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the behavior of the linkset controller.
 *
 * The purpose of this test is to validate that the a typical menu can be
 * correctly serialized as using the application/linkset+json media type.
 *
 * @group decoupled_menus
 *
 * @see https://tools.ietf.org/html/draft-ietf-httpapi-linkset-00
 */
final class LinksetControllerTest extends BrowserTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected $profile = 'minimal';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'basic_auth',
    'link',
    'path_alias',
    'path',
    'user',
    'menu_link_content',
    'node',
    'decoupled_menus',
    'page_cache',
    'dynamic_page_cache',
  ];

  /**
   * An HTTP kernel.
   *
   * Used to send a test request to the controller under test and validate its
   * response.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * A user account to author test content.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $authorAccount;

  /**
   * Test set up.
   *
   * Installs necessary database schemas, then creates test content and menu
   * items. The concept of this set up is to replicate a typical site's menus.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function setUp(): void {
    parent::setUp();

    $permissions = ['view own unpublished content'];
    $this->authorAccount = $this->setUpCurrentUser([
      'name' => 'author',
      'pass' => 'authorPass',
    ], $permissions);

    NodeType::create([
      'type' => 'page',
    ])->save();

    $home_page_link = $this->createMenuItem([
      'title' => 'Home',
      'description' => 'Links to the home page.',
      'link' => 'internal:/<front>',
      'weight' => 0,
      'menu_name' => 'main',
    ]);

    $about_us_page = $this->createNode([
      'nid' => 1,
      'title' => 'About us',
      'type' => 'page',
      'path' => '/about',
    ]);
    $about_us_link = $this->createMenuItem([
      'title' => 'About us',
      'description' => 'Links to the about us page.',
      'link' => 'entity:node/' . (int) $about_us_page->id(),
      'weight' => $home_page_link->getWeight() + 1,
      'menu_name' => 'main',
    ]);

    $our_name_page = $this->createNode([
      'nid' => 2,
      'title' => 'Our name',
      'type' => 'page',
      'path' => '/about/name',
    ]);
    $this->createMenuItem([
      'title' => 'Our name',
      'description' => 'Links to the page which describes the origin of the organization name.',
      'link' => 'entity:node/' . (int) $our_name_page->id(),
      'menu_name' => 'main',
      'parent' => $about_us_link->getPluginId(),
    ]);

    $this->httpKernel = $this->container->get('http_kernel');
  }

  /**
   * Test core functions of the linkset endpoint.
   *
   * Not intended to test every feature of the endpoint, only the most basic
   * functionality. E.g. is it 200 OK and does it use the right content type?
   *
   * The expected linkset also ensures that path aliasing is working properly.
   *
   * @throws \Exception
   */
  public function testBasicFunctions() {
    $expected_linkset = Json::decode(file_get_contents(__DIR__ . '/linkset-menu-main.json'));
    $response = $this->doRequest(Request::create('/system/menu/main/linkset'));
    $this->assertSame('application/linkset+json', $response->getHeaderLine('content-type'));
    $this->assertSame($expected_linkset, Json::decode((string) $response->getBody()));
    $this->doRequest(Request::create('/system/menu/missing/linkset'), 404);
  }

  /**
   * Test the cacheability of the linkset endpoint.
   *
   * This test's purpose is to ensure that the menu linkset response is properly
   * cached. It does this by sending a request and validating it has a cache
   * miss and the correct cacheability meta, then by sending the same request to
   * assert a cache hit. Finally, a new menu item is created to ensure that the
   * cached response is properly invalidated.
   */
  public function testCacheability() {
    $expected_cacheability = new CacheableMetadata();
    $expected_cacheability->addCacheContexts([
      'user.permissions',
    ]);
    $expected_cacheability->addCacheTags([
      'config:user.role.anonymous',
      'config:system.menu.main',
      'http_response',
      'node:1',
      'node:2',
    ]);
    $response = $this->doRequest(Request::create('/system/menu/main/linkset'));
    $this->assertDrupalResponseCacheability('MISS', $expected_cacheability, $response);
    $response = $this->doRequest(Request::create('/system/menu/main/linkset'));
    $this->assertDrupalResponseCacheability('HIT', $expected_cacheability, $response);
    // Create a new menu item to invalidate the cache.
    $duplicate_title = 'About us (duplicate)';
    $this->createMenuItem([
      'title' => $duplicate_title,
      'description' => 'Links to the about us page again.',
      'link' => 'entity:node/1',
      'menu_name' => 'main',
    ]);
    // Redo the request.
    $response = $this->doRequest(Request::create('/system/menu/main/linkset'));
    // Assert that the cache has been invalidated.
    $this->assertDrupalResponseCacheability('MISS', $expected_cacheability, $response);
    // Then ensure that the new menu link is in the response.
    $link_items = Json::decode((string) $response->getBody())['linkset'][0]['item'];
    $titles = array_column($link_items, 'title');
    $this->assertContains($duplicate_title, $titles);
  }

  /**
   * Test the access control functionality of the linkset endpoint.
   *
   * By testing with different current users (Anonymous included) against the
   * user account menu, this test ensures that the menu endpoint respects route
   * access controls. E.g. it does not output links to which the current user
   * does not have access (if it can be determined).
   */
  public function testAccess() {
    $expected_cacheability = new CacheableMetadata();
    $expected_cacheability->addCacheContexts(['user.permissions']);
    $expected_cacheability->addCacheTags([
      'node:1',
      'node:2',
      'config:user.role.anonymous',
      'config:system.menu.main',
      'http_response',
    ]);
    // Warm the cache, then get a response and ensure it was warmed.
    $this->doRequest(Request::create('/system/menu/main/linkset'));
    $response = $this->doRequest(Request::create('/system/menu/main/linkset'));
    $this->assertDrupalResponseCacheability('HIT', $expected_cacheability, $response);
    // Ensure the "Our name" menu link is visible.
    $link_items = Json::decode((string) $response->getBody())['linkset'][0]['item'];
    $titles = array_column($link_items, 'title');
    $this->assertContains('Our name', $titles);
    // Now, unpublish the target node.
    $our_name_page = Node::load(2);
    assert($our_name_page instanceof NodeInterface);
    $our_name_page->setUnpublished()->save();
    // Redo the request.
    $response = $this->doRequest(Request::create('/system/menu/main/linkset'));
    // Assert that the cache was invalidated.
    $this->assertDrupalResponseCacheability('MISS', $expected_cacheability, $response);
    // Ensure the "Our name" menu link is no longer visible.
    $link_items = Json::decode((string) $response->getBody())['linkset'][0]['item'];
    $titles = array_column($link_items, 'title');
    $this->assertNotContains('Our name', $titles);
    // Redo the request, but authenticate as the unpublished page's author.
    $response = $this->doRequest(Request::create('/system/menu/main/linkset'), 200, $this->authorAccount);
    $expected_cacheability = new CacheableMetadata();
    $expected_cacheability->addCacheContexts(['user']);
    $expected_cacheability->addCacheTags([
      'config:system.menu.main',
      'http_response',
      'node:1',
      'node:2',
    ]);
    $this->assertDrupalResponseCacheability(FALSE, $expected_cacheability, $response);
    // Ensure the "Our name" menu link is no longer visible.
    $link_items = Json::decode((string) $response->getBody())['linkset'][0]['item'];
    $titles = array_column($link_items, 'title');
    $this->assertContains('Our name', $titles);
  }

  /**
   * Tests that the user account menu behaves as it should.
   *
   * The account menu is a good test case because it provides a restricted,
   * YAML-defined link ("My account") and a dynamic code-defined link
   * ("Log in/out")
   */
  public function testUserAccountMenu() {
    $expected_cacheability = new CacheableMetadata();
    $expected_cacheability->addCacheContexts([
      'user.permissions',
      'user.roles:authenticated',
    ]);
    $expected_cacheability->addCacheTags([
      'config:user.role.anonymous',
      'config:system.menu.account',
      'http_response',
    ]);
    $response = $this->doRequest(Request::create('/system/menu/account/linkset'));
    $this->assertDrupalResponseCacheability('MISS', $expected_cacheability, $response);
    $link_items = Json::decode((string) $response->getBody())['linkset'][0]['item'];
    $titles = array_column($link_items, 'title');
    $this->assertContains('Log in', $titles);
    $this->assertNotContains('Log out', $titles);
    $this->assertNotContains('My account', $titles);
    // Redo the request, but with an authenticated user.
    $response = $this->doRequest(Request::create('/system/menu/account/linkset'), 200, $this->authorAccount);
    // The expected cache tags must be updated.
    $expected_cacheability->setCacheTags([
      'config:system.menu.account',
      'http_response',
    ]);
    // Authenticated requests do not use the page cache, so a "HIT" or "MISS"
    // isn't expected either.
    $this->assertDrupalResponseCacheability(FALSE, $expected_cacheability, $response);
    $link_items = Json::decode((string) $response->getBody())['linkset'][0]['item'];
    $titles = array_column($link_items, 'title');
    $this->assertContains('Log out', $titles);
    $this->assertContains('My account', $titles);
    $this->assertNotContains('Log in', $titles);
  }

  /**
   * Tests that menu items can use a custom link relation.
   */
  public function testCustomLinkRelation() {
    $this->assertTrue($this->container->get('module_installer')->install(['decoupled_menus_test'], TRUE), 'Installed modules.');
    $response = $this->doRequest(Request::create('/system/menu/account/linkset'), 200, $this->authorAccount);
    $link_context_object = Json::decode((string) $response->getBody())['linkset'][0];
    $this->assertContains('authenticated-as', array_keys($link_context_object));
    $my_account_link = $link_context_object['authenticated-as'][0];
  }

  /**
   * Sends a request to the kernel and makes basic response assertions.
   *
   * Only to be used when the expected response is a linkset response.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request to send.
   * @param int $expected_status
   *   The expected status code.
   * @param \Drupal\user\UserInterface $account
   *   A user account whose credentials should be used to authenticate the
   *   request.
   *
   * @return \GuzzleHttp\Psr7\Response
   *   The response object.
   */
  protected function doRequest(Request $request, $expected_status = 200, UserInterface $account = NULL): Response {
    $this->refreshVariables();
    $request_options[RequestOptions::HTTP_ERRORS] = FALSE;
    $request_options[RequestOptions::ALLOW_REDIRECTS] = FALSE;
    if (!is_null($account)) {
      $credentials = $account->name->value . ':' . $account->passRaw;
      $request_options[RequestOptions::HEADERS] = [
        'Authorization' => 'Basic ' . base64_encode($credentials),
      ];
    }
    $client = $this->getSession()->getDriver()->getClient()->getClient();
    $response = $client->request($request->getMethod(), Url::fromUri($request->getUri())->setAbsolute(TRUE)->toString(), $request_options);
    $this->assertSame($expected_status, $response->getStatusCode(), (string) $response->getBody());
    return $response;
  }

  /**
   * Helper to assert a cacheable value matches an expectation.
   *
   * @param string|false $expect_cache
   *   'HIT', 'MISS', or FALSE. Asserts the value of the X-Drupal-Cache header.
   *   FALSE if the page cache is not applicable.
   * @param \Drupal\Core\Cache\CacheableDependencyInterface $expected_metadata
   *   The expected cacheability metadata.
   * @param \GuzzleHttp\Psr7\Response $response
   *   The response on which to assert cacheability.
   */
  protected function assertDrupalResponseCacheability($expect_cache, CacheableDependencyInterface $expected_metadata, Response $response) {
    assert(in_array($expect_cache, ['HIT', 'MISS', FALSE], TRUE));
    $this->assertSame($expected_metadata->getCacheContexts(), explode(' ', $response->getHeaderLine('X-Drupal-Cache-Contexts')));
    $this->assertSame($expected_metadata->getCacheTags(), explode(' ', $response->getHeaderLine('X-Drupal-Cache-Tags')));
    $max_age_message = $expected_metadata->getCacheMaxAge();
    if ($max_age_message === 0) {
      $max_age_message = '0 (Uncacheable)';
    }
    elseif ($max_age_message === -1) {
      $max_age_message = '-1 (Permanent)';
    }
    $this->assertSame($max_age_message, $response->getHeaderLine('X-Drupal-Cache-Max-Age'));
    if ($expect_cache) {
      $this->assertSame($expect_cache, $response->getHeaderLine('X-Drupal-Cache'));
    }
  }

  /**
   * Creates, saves, and returns a new menu link content entity.
   *
   * @param array $values
   *   Menu field values.
   *
   * @return \Drupal\menu_link_content\MenuLinkContentInterface
   *   The newly created menu link content entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *
   * @see \Drupal\menu_link_content\MenuLinkContentInterface::create()
   */
  protected function createMenuItem(array $values): MenuLinkContentInterface {
    $link_content = MenuLinkContent::create($values);
    assert($link_content instanceof MenuLinkContentInterface);
    $link_content->save();
    return $link_content;
  }

}
