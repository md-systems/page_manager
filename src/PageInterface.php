<?php

/**
 * @file
 * Contains \Drupal\page_manager\PageInterface.
 */

namespace Drupal\page_manager;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;
use Drupal\page_manager\Plugin\VariantAwareInterface;

/**
 * Provides an interface for page entities.
 */
interface PageInterface extends EntityInterface, EntityWithPluginCollectionInterface, VariantAwareInterface {

  /**
   * Returns whether the page entity is enabled.
   *
   * @return bool
   *   Whether the page entity is enabled or not.
   */
  public function status();

  /**
   * Returns the executable instance for this page.
   *
   * @return \Drupal\page_manager\PageExecutableInterface
   */
  public function getExecutable();

  /**
   * Returns the path for the page entity.
   *
   * @return string
   *   The path for the page entity.
   */
  public function getPath();

  /**
   * Indicates if this page is an admin page or not.
   *
   * @return bool
   *   TRUE if this is an admin page, FALSE otherwise.
   */
  public function usesAdminTheme();

  /**
   * Returns the conditions used for determining access for this page entity.
   *
   * @return \Drupal\Core\Condition\ConditionInterface[]|\Drupal\Core\Condition\ConditionPluginCollection
   *   An array of configured condition plugins.
   */
  public function getAccessConditions();

  /**
   * Adds a new access condition to the page entity.
   *
   * @param array $configuration
   *   An array of configuration for the new access condition.
   *
   * @return string
   *   The access condition ID.
   */
  public function addAccessCondition(array $configuration);

  /**
   * Retrieves a specific access condition.
   *
   * @param string $condition_id
   *   The access condition ID.
   *
   * @return \Drupal\Core\Condition\ConditionInterface
   *   The access condition object.
   */
  public function getAccessCondition($condition_id);

  /**
   * Removes a specific access condition.
   *
   * @param string $condition_id
   *   The access condition ID.
   *
   * @return $this
   */
  public function removeAccessCondition($condition_id);

  /**
   * Returns the logic used to compute access, either 'and' or 'or'.
   *
   * @return string
   *   The string 'and', or the string 'or'.
   */
  public function getAccessLogic();

  /**
   * Gets the values for all defined contexts.
   *
   * @return \Drupal\Component\Plugin\Context\ContextInterface[]
   *   An array of set context values, keyed by context name.
   */
  public function getContexts();

  /**
   * Returns if this page is the fallback page.
   *
   * The fallback page can never be disabled. It must always be available.
   *
   * @return bool
   *   TRUE if this page is the fallback page, FALSE otherwise.
   */
  public function isFallbackPage();

}
