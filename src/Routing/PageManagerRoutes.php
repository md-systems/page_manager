<?php

/**
 * @file
 * Contains \Drupal\page_manager\Routing\PageManagerRoutes.
 */

namespace Drupal\page_manager\Routing;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Routing\RouteCompiler;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\page_manager\PageInterface;
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
   * The cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheTagsInvalidator;

  /**
   * Constructs a new PageManagerRoutes.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cache_tags_invalidator
   *   The cache tags invalidator.
   */
  public function __construct(EntityManagerInterface $entity_manager, CacheTagsInvalidatorInterface $cache_tags_invalidator) {
    $this->entityStorage = $entity_manager->getStorage('page');
    $this->cacheTagsInvalidator = $cache_tags_invalidator;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    foreach ($this->entityStorage->loadMultiple() as $entity_id => $entity) {
      /** @var \Drupal\page_manager\PageInterface $entity */

      // If the page is disabled skip making a route for it.
      if (!$entity->status() || $entity->isFallbackPage()) {
        continue;
      }

      // Prepare the values that need to be altered for an existing page.
      $parameters = [
        'page_manager_page' => [
          'type' => 'entity:page',
        ],
      ];

      $arguments = [];

      if ($route_name = $this->findPageRouteName($entity, $collection)) {
        $this->cacheTagsInvalidator->invalidateTags(["page_manager_route_name:$route_name"]);

        $collection_route = $collection->get($route_name);
        $path = $collection_route->getPath();
        $parameters += $collection_route->getOption('parameters') ?: [];

        $collection->remove($route_name);
      }
      else {
        $route_name = "page_manager.page_view_$entity_id";
        $path = $entity->getPath();

        // Replace % placeholders with proper route slugs ("{arg}") and set
        // parameter types.
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
        $path = implode('/', $bits);
        }
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
   * Finds the overridden route name.
   *
   * @param \Drupal\page_manager\PageInterface $entity
   *   The page entity.
   * @param \Symfony\Component\Routing\RouteCollection $collection
   *   The route collection.
   *
   * @return string|null
   *   Either the route name if this is overriding an existing path, or NULL.
   */
  protected function findPageRouteName(PageInterface $entity, RouteCollection $collection) {
    // Get the stored page path.
    $path = $entity->getPath();

    // Loop through all existing routes to see if this is overriding a route.
    foreach ($collection->all() as $name => $collection_route) {
      // Find all paths which match the path of the current display.
      $route_path = $collection_route->getPath();
      $route_path_outline = RouteCompiler::getPatternOutline($route_path);

      // Match either the path or the outline, e.g., '/foo/{foo}' or '/foo/%'.
      if ($path === $route_path || $path === $route_path_outline) {
        // Return the overridden route name.
        return $name;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Run after EntityRouteAlterSubscriber.
    $events[RoutingEvents::ALTER][] = ['onAlterRoutes', -160];
    return $events;
  }

}
