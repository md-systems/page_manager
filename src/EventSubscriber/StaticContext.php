<?php

/**
 * @file
 * Contains \Drupal\page_manager\EventSubscriber\StaticContext.
 */

namespace Drupal\page_manager\EventSubscriber;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\page_manager\Context\EntityLazyLoadContext;
use Drupal\page_manager\Event\PageManagerContextEvent;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\page_manager\Event\PageManagerEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds static context.
 */
class StaticContext implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Adds in the current user as a context.
   *
   * @param \Drupal\page_manager\Event\PageManagerContextEvent $event
   *   The page entity context event.
   */
  public function onPageContext(PageManagerContextEvent $event) {
    $executable = $event->getPageExecutable();
    $static_contexts = $executable->getPage()->get('static_context');

    foreach ($static_contexts as $static_context) {
      $context = new EntityLazyLoadContext(new ContextDefinition($static_context['type'], $static_context['label']), $static_context['value']);
      $executable->addContext($static_context['machine_name'], $context);
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
