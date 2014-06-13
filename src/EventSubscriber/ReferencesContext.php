<?php

/**
 * @file
 * Contains \Drupal\page_manager\EventSubscriber\ReferencesContext.
 */

namespace Drupal\page_manager\EventSubscriber;

use Drupal\page_manager\Event\PageManagerContextEvent;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\page_manager\Event\PageManagerEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds referenced context.
 */
class ReferencesContext implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Adds in the current user as a context.
   *
   * @param \Drupal\page_manager\Event\PageManagerContextEvent $event
   *   The page entity context event.
   */
  public function onPageContext(PageManagerContextEvent $event) {
    $executable = $event->getPageExecutable();
    $references = $executable->getPage()->get('references');

    foreach ($references as $reference) {
      $parts = explode('.', $reference);
      $type = array_pop($parts);
      $context = new Context(array(
        'type' => $type,
        'label' => 'TODO',
      ));
      $executable->addContext(implode('.', $parts), $context);
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
