<?php

/**
 * @file
 * Contains \Drupal\page_manager\Context\EntityLazyLoadContext.
 */

namespace Drupal\page_manager\Context;

use Drupal\Component\Plugin\Context\ContextDefinitionInterface;
use Drupal\Core\Plugin\Context\Context;

class EntityLazyLoadContext extends Context {

  /**
   * The entity UUID.
   *
   * @var string
   */
  protected $uuid;

  public function __construct(ContextDefinitionInterface $context_definition, $uuid) {
    parent::__construct($context_definition);
    $this->uuid = $uuid;
  }

  /**
   * {@inheritdoc}
   */
  public function getContextValue() {
    if (!$this->contextValue) {
      $this->contextValue = \Drupal::entityManager()->loadEntityByUuid(substr($this->contextDefinition->getDataType(), 7), $this->uuid);
    }
    return $this->contextValue;
  }


}
