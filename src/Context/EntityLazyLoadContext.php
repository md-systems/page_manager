<?php

/**
 * @file
 * Contains \Drupal\page_manager\Context\EntityLazyLoadContext.
 */

namespace Drupal\page_manager\Context;

use Drupal\Core\Plugin\Context\Context;

class EntityLazyLoadContext extends Context {

  /**
   * {@inheritdoc}
   */
  public function getContextValue() {
    if (!$this->contextValue) {
      $this->contextValue = \Drupal::entityManager()->loadEntityByUuid(substr($this->contextDefinition['type'], 7), $this->contextDefinition['value']);
    }
    return $this->contextValue;
  }


}
