<?php

/**
 * @file
 * Contains \Drupal\page_manager\Routing\PageManagerRoutes.
 */

namespace Drupal\page_manager\Routing;

use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Routing\RouteCompiler;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides routes for page entities.
 *
 * @see \Drupal\page_manager\Entity\PageViewBuilder
 */
class PageManagerRoutes extends RouteSubscriberBase {

  /**
   * The entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityStorage;

  /**
   * Constructs a new PageManagerRoutes.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   */
  public function __construct(EntityManagerInterface $entity_manager) {
    $this->entityStorage = $entity_manager->getStorage('page');
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    foreach ($this->entityStorage->loadMultiple() as $entity_id => $entity) {
      /** @var $entity \Drupal\page_manager\PageInterface */

      // If the page is disabled skip making a route for it.
      if (!$entity->status() || $entity->isFallbackPage()) {
        continue;
      }

      // Prepare a route name to use if this is a custom page.
      $route_name = "page_manager.page_view_$entity_id";

      // Prepare the values that need to be altered for an existing page.
      $path = $entity->getPath();
      $parameters = [
        'page_manager_page' => [
          'type' => 'entity:page',
        ],
      ];

      $altered_route = FALSE;
      // Loop through all existing routes to see if this is overriding a route.
      foreach ($collection->all() as $name => $collection_route) {
        // Find all paths which match the path of the current display.
        $route_path = $collection_route->getPath();
        $route_path = RouteCompiler::getPatternOutline($route_path);

        if ($path == $route_path) {
          // Adjust the path to translate %placeholders to {slugs}.
          $path = $collection_route->getPath();

          // Merge in any route parameter definitions.
          $parameters += $collection_route->getOption('parameters') ?: [];

          $altered_route = TRUE;

          // Update the route name this will be added to.
          $route_name = $name;
          // Remove the existing route.
          $collection->remove($route_name);
          break;
        }
      }

      $arguments = [];
      // Replace % placeholders with proper route slugs ("{arg}") and set
      // parameter types.
      if (!$altered_route) {
        $bits = explode('/', $path);
        $arg_counter = 0;
        foreach ($bits as $pos => $bit) {
          // Allowed placeholder patterns: %, %arg, %arg.type
          if (preg_match('/%(([\w_]+)\.)?([\w_]+)/', $bit, $matches)) {
            list($bit, $argdot, $arg, $type) = $matches;
            $arg = $arg ?: 'arg_' . $arg_counter++;
            $arguments[$arg] = NULL;
            $bits[$pos] = '{' . $arg . '}';
            $parameters[$arg] = [
              'type' => $type,
            ];
          }
        }
        $path = implode('/', $bits);
      }

      // Construct an add a new route.
      $route = new Route(
        $path,
        [
          '_entity_view' => 'page_manager_page',
          'page_manager_page' => $entity_id,
          '_title' => $entity->label(),
        ] + $arguments,
        [
          '_entity_access' => 'page_manager_page.view',
        ],
        [
          'parameters' => $parameters,
          '_admin_route' => $entity->usesAdminTheme(),
        ]
      );
      $collection->add($route_name, $route);
    }
  }

  /**
   * {@inheritdoc}
   */
  static function getSubscribedEvents() {
    // Run after EntityRouteAlterSubscriber.
    $events[RoutingEvents::ALTER][] = ['onAlterRoutes', -160];
    return $events;
  }

}
