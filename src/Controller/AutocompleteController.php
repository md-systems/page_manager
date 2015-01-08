<?php

/**
 * @file
 * Contains \Drupal\page_manager\Controller\AutocompleteController.
 */

namespace Drupal\page_manager\Controller;

use Drupal\Component\Utility\String;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides autocomplete route controllers for Page Manager.
 */
class AutocompleteController extends ControllerBase {

  /**
   * Retrieves suggestions for entity autocompletion.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function autocompleteEntity(Request $request, $entity_type_id) {
    $string = $request->query->get('q');

    $entity_type = $this->entityManager()->getDefinition($entity_type_id);
    $storage = $this->entityManager()->getStorage($entity_type_id);

    $ids = $storage->getQuery()
      ->condition($entity_type->getKey('label'), $string, 'STARTS_WITH')
      ->range(0, 10)
      ->sort($entity_type->getKey('label'))
      ->execute();

    $matches = [];
    foreach ($storage->loadMultiple($ids) as $entity) {
      $matches[] = ['value' => $entity->label() . ' (' . $entity->id() . ')', 'label' => String::checkPlain($entity->label())];
    }

    return new JsonResponse($matches);
  }

}
