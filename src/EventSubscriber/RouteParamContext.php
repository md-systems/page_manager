<?php

/**
 * @file
 * Contains \Drupal\page_manager\EventSubscriber\RouteParamContext.
 */

namespace Drupal\page_manager\EventSubscriber;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\page_manager\Event\PageManagerContextEvent;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\page_manager\Event\PageManagerEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Sets values from the route parameters as a context.
 */
class RouteParamContext implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a new CurrentUserContext.
   *
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   The route provider.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(RouteProviderInterface $route_provider, RequestStack $request_stack) {
    $this->routeProvider = $route_provider;
    $this->requestStack = $request_stack;
  }

  /**
   * Adds in the current user as a context.
   *
   * @param \Drupal\page_manager\Event\PageManagerContextEvent $event
   *   The page entity context event.
   */
  public function onPageContext(PageManagerContextEvent $event) {
    $request = $this->requestStack->getCurrentRequest();
    $executable = $event->getPageExecutable();
    $routes = $this->routeProvider->getRoutesByPattern($executable->getPage()->getPath())->all();

    if (empty($routes)) {
      drupal_set_message($this->t('The route for this page might not yet have been built. Try refreshing the page to ensure any route parameter context is included in the list.'), 'warning');
      return;
    }

    $route = reset($routes);

    if ($route_contexts = $route->getOption('parameters')) {
      foreach ($route_contexts as $route_context_name => $route_context) {
        // Skip this parameter.
        if ($route_context_name == 'page_manager_page') {
          continue;
        }

        $context_name = $this->t('{@name} from route', ['@name' => $route_context_name]);
        $context = new Context(new ContextDefinition($route_context['type'], $context_name, FALSE));
        if ($request->attributes->has($route_context_name)) {
          $context->setContextValue($request->attributes->get($route_context_name));
          $cacheability = new CacheableMetadata();
          $cacheability->setCacheContexts(['route']);
          $context->addCacheableDependency($cacheability);
        }
        else {
          // @todo Find a way to add in a fake value for configuration.
        }
        $executable->addContext($route_context_name, $context);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[PageManagerEvents::PAGE_CONTEXT][] = 'onPageContext';
    return $events;
  }

}
