<?php

/**
 * @file
 * Contains \Drupal\Tests\page_manager\Unit\PageManagerRoutesTest.
 */

namespace Drupal\Tests\page_manager\Unit;

use Drupal\Core\Routing\RouteBuildEvent;
use Drupal\page_manager\Routing\PageManagerRoutes;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Tests the page manager route subscriber.
 *
 * @coversDefaultClass \Drupal\page_manager\Routing\PageManagerRoutes
 *
 * @group Drupal
 * @group PageManager
 */
class PageManagerRoutesTest extends UnitTestCase {

  /**
   * The mocked entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $entityManager;

  /**
   * The mocked page storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $pageStorage;

  /**
   * The tested page route subscriber.
   *
   * @var \Drupal\page_manager\Routing\PageManagerRoutes
   */
  protected $routeSubscriber;

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Page Manager route subscriber',
      'description' => '',
      'group' => 'Page Manager',
    );
  }

  /**
   * {@inheritdoc}
   *
   * @covers ::__construct
   */
  protected function setUp() {
    $this->entityManager = $this->getMock('Drupal\Core\Entity\EntityManagerInterface');
    $this->pageStorage = $this->getMock('Drupal\Core\Config\Entity\ConfigEntityStorageInterface');
    $this->entityManager->expects($this->any())
      ->method('getStorage')
      ->with('page')
      ->will($this->returnValue($this->pageStorage));
    $this->routeSubscriber = new PageManagerRoutes($this->entityManager);
  }

  /**
   * Tests adding a route for the fallback page.
   *
   * @covers ::alterRoutes
   */
  public function testAlterRoutesWithFallback() {
    // Set up the fallback page.
    $page = $this->getMock('Drupal\page_manager\PageInterface');
    $page->expects($this->once())
      ->method('status')
      ->will($this->returnValue(TRUE));
    $page->expects($this->never())
      ->method('getPath');
    $page->expects($this->once())
      ->method('isFallbackPage')
      ->will($this->returnValue(TRUE));
    $pages['page1'] = $page;

    $this->pageStorage->expects($this->once())
      ->method('loadMultiple')
      ->will($this->returnValue($pages));

    $collection = new RouteCollection();
    $route_event = new RouteBuildEvent($collection);
    $this->routeSubscriber->onAlterRoutes($route_event);

    // The collection should be empty.
    $this->assertSame(0, $collection->count());
  }

  /**
   * Tests adding routes for enabled and disabled pages.
   *
   * @covers ::alterRoutes
   */
  public function testAlterRoutesWithStatus() {
    // Set up a valid page.
    $page1 = $this->getMock('Drupal\page_manager\PageInterface');
    $page1->expects($this->once())
      ->method('status')
      ->will($this->returnValue(TRUE));
    $page1->expects($this->once())
      ->method('getPath')
      ->will($this->returnValue('/page1'));
    $page1->expects($this->once())
      ->method('usesAdminTheme')
      ->will($this->returnValue(TRUE));
    $pages['page1'] = $page1;

    // Set up a disabled page.
    $page2 = $this->getMock('Drupal\page_manager\PageInterface');
    $page2->expects($this->once())
      ->method('status')
      ->will($this->returnValue(FALSE));
    $pages['page2'] = $page2;

    $this->pageStorage->expects($this->once())
      ->method('loadMultiple')
      ->will($this->returnValue($pages));

    $collection = new RouteCollection();
    $route_event = new RouteBuildEvent($collection);
    $this->routeSubscriber->onAlterRoutes($route_event);

    // Only the valid page should be in the collection.
    $this->assertSame(1, $collection->count());
    $route = $collection->get('page_manager.page_view_page1');
    $expected_defaults = array(
      '_entity_view' => 'page_manager_page',
      'page_manager_page' => 'page1',
    );
    $expected_requirements = array(
      '_entity_access' => 'page_manager_page.view',
    );
    $expected_options = array(
      'compiler_class' => 'Symfony\Component\Routing\RouteCompiler',
      'parameters' => array(
        'page_manager_page' => array(
          'type' => 'entity:page',
        ),
      ),
      '_admin_route' => TRUE,
    );
    $this->assertMatchingRoute($route, '/page1', $expected_defaults, $expected_requirements, $expected_options);
  }

  /**
   * Tests overriding an existing route.
   *
   * @covers ::alterRoutes
   */
  public function testAlterRoutesOverrideExisting() {
    // Set up a page with the same path as an existing route.
    $page = $this->getMock('Drupal\page_manager\PageInterface');
    $page->expects($this->once())
      ->method('status')
      ->will($this->returnValue(TRUE));
    $page->expects($this->once())
      ->method('getPath')
      ->will($this->returnValue('/test_route'));

    $this->pageStorage->expects($this->once())
      ->method('loadMultiple')
      ->will($this->returnValue(array('page1' => $page)));

    $collection = new RouteCollection();
    $collection->add('test_route', new Route('test_route', array(), array(), array('parameters' => array('foo' => 'bar'))));
    $route_event = new RouteBuildEvent($collection);
    $this->routeSubscriber->onAlterRoutes($route_event);

    // The normal route name is not used, the existing route name is instead.
    $this->assertSame(1, $collection->count());
    $this->assertNull($collection->get('page_manager.page_view_page1'));

    $route = $collection->get('test_route');
    $expected_defaults = array(
      '_entity_view' => 'page_manager_page',
      'page_manager_page' => 'page1',
    );
    $expected_requirements = array(
      '_entity_access' => 'page_manager_page.view',
    );
    $expected_options = array(
      'compiler_class' => 'Symfony\Component\Routing\RouteCompiler',
      'parameters' => array(
        'page_manager_page' => array(
          'type' => 'entity:page',
        ),
        'foo' => 'bar',
      ),
      '_admin_route' => NULL,
    );
    $this->assertMatchingRoute($route, '/test_route', $expected_defaults, $expected_requirements, $expected_options);
  }

  /**
   * Asserts that a route object has the expected properties.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to test.
   * @param string $expected_path
   *   The expected path for the route.
   * @param array $expected_defaults
   *   The expected defaults for the route.
   * @param array $expected_requirements
   *   The expected requirements for the route.
   * @param array $expected_options
   *   The expected options for the route.
   */
  protected function assertMatchingRoute(Route $route, $expected_path, $expected_defaults, $expected_requirements, $expected_options) {
    $this->assertSame($expected_path, $route->getPath());
    $this->assertSame($expected_defaults, $route->getDefaults());
    $this->assertSame($expected_requirements, $route->getRequirements());
    $this->assertSame($expected_options, $route->getOptions());
  }

}
