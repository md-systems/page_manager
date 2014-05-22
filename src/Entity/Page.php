<?php

/**
 * @file
 * Contains \Drupal\page_manager\Entity\Page.
 */

namespace Drupal\page_manager\Entity;

use Drupal\page_manager\PageInterface;
use Drupal\page_manager\Event\PageManagerContextEvent;
use Drupal\page_manager\Plugin\ConditionPluginBag;
use Drupal\page_manager\Plugin\PageVariantBag;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Defines a Page entity class.
 *
 * @ConfigEntityType(
 *   id = "page",
 *   label = @Translation("Page"),
 *   controllers = {
 *     "access" = "Drupal\page_manager\Entity\PageAccess",
 *     "list_builder" = "Drupal\page_manager\Entity\PageListBuilder",
 *     "view_builder" = "Drupal\page_manager\Entity\PageViewBuilder",
 *     "form" = {
 *       "add" = "Drupal\page_manager\Form\PageAddForm",
 *       "edit" = "Drupal\page_manager\Form\PageEditForm",
 *       "delete" = "Drupal\page_manager\Form\PageDeleteForm",
 *     }
 *   },
 *   admin_permission = "administer pages",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   links = {
 *     "add-form" = "page_manager.page_add",
 *     "edit-form" = "page_manager.page_edit",
 *     "delete-form" = "page_manager.page_delete",
 *   }
 * )
 */
class Page extends ConfigEntityBase implements PageInterface {

  /**
   * The ID of the page entity.
   *
   * @var string
   */
  protected $id;

  /**
   * The label of the page entity.
   *
   * @var string
   */
  protected $label;

  /**
   * The path of the page entity.
   *
   * @var string
   */
  protected $path;

  /**
   * The configuration of the page variants.
   *
   * @var array
   */
  protected $page_variants = array();

  /**
   * The configuration of access conditions.
   *
   * @var array
   */
  protected $access_conditions = array();

  /**
   * Tracks the logic used to compute access, either 'and' or 'or'.
   *
   * @var string
   */
  protected $access_logic = 'and';

  /**
   * The plugin bag that holds the page variants.
   *
   * @var \Drupal\Component\Plugin\PluginBag
   */
  protected $pageVariantBag;

  /**
   * The plugin bag that holds the access conditions.
   *
   * @var \Drupal\Component\Plugin\PluginBag
   */
  protected $accessConditionBag;

  /**
   * An array of collected contexts.
   *
   * This is only used on runtime, and is not stored.
   *
   * @var \Drupal\Component\Plugin\Context\ContextInterface[]
   */
  protected $contexts = array();

  /**
   * {@inheritdoc}
   */
  public function toArray() {
    $properties = parent::toArray();
    $names = array(
      'id',
      'label',
      'path',
      'page_variants',
      'access_conditions',
      'access_logic',
    );
    foreach ($names as $name) {
      $properties[$name] = $this->get($name);
    }
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function getPath() {
    return $this->path;
  }

  /**
   * {@inheritdoc}
   */
  public function postCreate(EntityStorageInterface $storage) {
    parent::postCreate($storage);
    // Ensure there is at least one page variant.
    if (!$this->getPageVariants()->count()) {
      $this->addPageVariant(array(
        'id' => 'http_status_code',
        'label' => 'Default',
        'weight' => 10,
      ));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);
    $this->routeBuilder()->setRebuildNeeded();
  }

  /**
   * Wraps the route builder.
   *
   * @return \Drupal\Core\Routing\RouteBuilderInterface
   *   An object for state storage.
   */
  protected function routeBuilder() {
    return \Drupal::service('router.builder');
  }

  /**
   * Wraps the event dispatcher.
   *
   * @return \Symfony\Component\EventDispatcher\EventDispatcherInterface
   *   The event dispatcher.
   */
  protected function eventDispatcher() {
    return \Drupal::service('event_dispatcher');
  }

  /**
   * {@inheritdoc}
   */
  public function addPageVariant(array $configuration) {
    $configuration['uuid'] = $this->uuidGenerator()->generate();
    $this->getPageVariants()->addInstanceId($configuration['uuid'], $configuration);
    return $configuration['uuid'];
  }

  /**
   * {@inheritdoc}
   */
  public function getPageVariant($page_variant_id) {
    return $this->getPageVariants()->get($page_variant_id);
  }

  /**
   * {@inheritdoc}
   */
  public function removePageVariant($page_variant_id) {
    $this->getPageVariants()->removeInstanceId($page_variant_id);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPageVariants() {
    if (!$this->pageVariantBag) {
      $this->pageVariantBag = new PageVariantBag(\Drupal::service('plugin.manager.page_variant'), $this->get('page_variants'));
      $this->pageVariantBag->sort();
    }
    return $this->pageVariantBag;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginBags() {
    return array(
      'page_variants' => $this->getPageVariants(),
      'access_conditions' => $this->getAccessConditions(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function selectPageVariant() {
    foreach ($this->getPageVariants() as $page_variant) {
      $page_variant->setContexts($this->getContexts());
      if ($page_variant->access()) {
        return $page_variant;
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessConditions() {
    if (!$this->accessConditionBag) {
      $this->accessConditionBag = new ConditionPluginBag(\Drupal::service('plugin.manager.condition'), $this->get('access_conditions'));
    }
    return $this->accessConditionBag;
  }

  /**
   * {@inheritdoc}
   */
  public function addAccessCondition(array $configuration) {
    $configuration['uuid'] = $this->uuidGenerator()->generate();
    $this->getAccessConditions()->addInstanceId($configuration['uuid'], $configuration);
    return $configuration['uuid'];
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessCondition($condition_id) {
    return $this->getAccessConditions()->get($condition_id);
  }

  /**
   * {@inheritdoc}
   */
  public function removeAccessCondition($condition_id) {
    $this->getAccessConditions()->removeInstanceId($condition_id);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessLogic() {
    return $this->access_logic;
  }

  /**
   * {@inheritdoc}
   */
  public function getContexts() {
    if (!$this->contexts) {
      $this->eventDispatcher()->dispatch('page_manager_context', new PageManagerContextEvent($this));
    }
    return $this->contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function addContext($name, $value) {
    $this->contexts[$name] = $value;
  }

}