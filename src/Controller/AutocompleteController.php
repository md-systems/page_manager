<?php

/**
 * @file
 * Contains \Drupal\page_manager\Controller\AutocompleteController.
 */

namespace Drupal\page_manager\Controller;

use Drupal\block\BlockManagerInterface;
use Drupal\page_manager\PageInterface;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\String;
use Drupal\Core\Controller\ControllerBase;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides autocomplete route controllers for Page Manager.
 */
class AutocompleteController extends ControllerBase {


  public function autocompleteEntity(Request $request, $entity_type_id) {
    $string = $request->query->get('q');

    $entity_type = \Drupal::entityManager()->getDefinition($entity_type_id);

    $ids = \Drupal::entityQuery($entity_type_id)
      ->condition($entity_type->getKey('label'), $string, 'STARTS_WITH')
      ->range(0, 10)
      ->sort($entity_type->getKey('label'))
      ->execute();

    $matches = array();
    $storage = \Drupal::entityManager()->getStorage($entity_type_id);
    foreach ($storage->loadMultiple($ids) as $entity) {
      $matches[] = array('value' => $entity->label() . ' (' . $entity->id() . ')', 'label' => String::checkPlain($entity->label()));
    }

    return new JsonResponse($matches);
  }
}
