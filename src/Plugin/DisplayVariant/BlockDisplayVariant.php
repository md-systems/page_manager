<?php

/**
 * @file
 * Contains \Drupal\page_manager\Plugin\DisplayVariant\BlockDisplayVariant.
 */

namespace Drupal\page_manager\Plugin\DisplayVariant;

use Drupal\Component\Plugin\ContextAwarePluginInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Display\VariantBase;
use Drupal\Component\Utility\String;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\page_manager\PageExecutable;
use Drupal\page_manager\Plugin\BlockVariantInterface;
use Drupal\page_manager\Plugin\BlockVariantTrait;
use Drupal\page_manager\Plugin\ConditionVariantInterface;
use Drupal\page_manager\Plugin\ConditionVariantTrait;
use Drupal\page_manager\Plugin\ContextAwareVariantInterface;
use Drupal\page_manager\Plugin\ContextAwareVariantTrait;
use Drupal\page_manager\Plugin\PageAwareVariantInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a display variant that simply contains blocks.
 *
 * @DisplayVariant(
 *   id = "block_page",
 *   admin_label = @Translation("Block page")
 * )
 */
class BlockDisplayVariant extends VariantBase implements ContextAwareVariantInterface, ConditionVariantInterface, ContainerFactoryPluginInterface, PageAwareVariantInterface, BlockVariantInterface {

  use BlockVariantTrait;
  use ContextAwareVariantTrait;
  use ConditionVariantTrait;

  /**
   * The context handler.
   *
   * @var \Drupal\Core\Plugin\Context\ContextHandlerInterface
   */
  protected $contextHandler;

  /**
   * The UUID generator.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuidGenerator;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The page executable.
   *
   * @var \Drupal\page_manager\PageExecutable
   */
  protected $executable;

  /**
   * Constructs a new BlockDisplayVariant.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Plugin\Context\ContextHandlerInterface $context_handler
   *   The context handler.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid_generator
   *   The UUID generator.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ContextHandlerInterface $context_handler, AccountInterface $account, UuidInterface $uuid_generator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->contextHandler = $context_handler;
    $this->account = $account;
    $this->uuidGenerator = $uuid_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('context.handler'),
      $container->get('current_user'),
      $container->get('uuid')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = array();

    // Default the max page expire to permanent.
    $max_page_expire = Cache::PERMANENT;

    $page = $this->executable->getPage();

    $contexts = $this->getContexts();
    foreach ($this->getRegionAssignments() as $region => $blocks) {
      if (!$blocks) {
        continue;
      }

      $region_name = $this->drupalHtmlClass("block-region-$region");
      $build[$region]['#prefix'] = '<div class="' . $region_name . '">';
      $build[$region]['#suffix'] = '</div>';

      /** @var $blocks \Drupal\Core\Block\BlockPluginInterface[] */
      $weight = 0;
      foreach ($blocks as $block_id => $block) {
        if ($block instanceof ContextAwarePluginInterface) {
          $this->contextHandler()->applyContextMapping($block, $contexts);
        }
        if (!$block->access($this->account)->isAllowed()) {
          continue;
        }

        $block_render_array = array(
          '#theme' => 'block',
          '#attributes' => array(),
          '#weight' => $weight++,
          '#configuration' => $block->getConfiguration(),
          '#plugin_id' => $block->getPluginId(),
          '#base_plugin_id' => $block->getBaseId(),
          '#derivative_plugin_id' => $block->getDerivativeId(),
          '#block' => $block,
          '#cache' => array(),
          '#contextual_links' => array(),
        );
        $block_render_array['#cache']['tags'] = NestedArray::mergeDeepArray(array(
          $page->getCacheTag(), // Page plugin cache tags.
          $block->getCacheTags(), // Block plugin cache tag.
        ));
        $block_render_array['#configuration']['label'] = String::checkPlain($block_render_array['#configuration']['label']);
        $build[$region][$block_id] = $block_render_array;

        if ($block->isCacheable()) {
          $build[$region][$block_id]['#pre_render'][] = array($this, 'buildBlock');
          // Generic cache keys, with the block plugin's custom keys appended
          // (usually cache context keys like 'cache_context.user.roles').
          $default_cache_keys = array(
            'page_manager_page',
            $page->id(),
            'block',
            $block_id,
            \Drupal::languageManager()->getCurrentLanguage()->getId(),
            // Blocks are always rendered in a "per theme" cache context.
            'cache_context.theme',
          );
          $max_block_page = $block->getCacheMaxAge();
          $build[$region][$block_id]['#cache'] += array(
            'keys' => array_merge($default_cache_keys, $block->getCacheKeys()),
            'bin' => $block->getCacheBin(),
            'expire' => ($max_block_page === Cache::PERMANENT) ? Cache::PERMANENT : REQUEST_TIME + $max_block_page,
          );

          // Maintain the max page expire time if it is not currently NULL
          // (disabled), set it to max block expire if that is not permanent and
          // lower than the current max page expire or that is PERMANENT.
          if ($max_page_expire !== NULL && $max_block_page != Cache::PERMANENT && ($max_page_expire == Cache::PERMANENT || $max_block_page < $max_page_expire)) {
            $max_page_expire = $max_block_page;
          }
        }
        else {
          // Disable render caching of the whole page.
          $max_page_expire = NULL;
          $build[$region][$block_id] = $this->buildBlock($build[$region][$block_id]);
        }
      }
    }

    // Set up render cache on the page level.
    if ($max_page_expire !== NULL) {
      $build['#cache'] = array(
        'keys' => array(
          'page-manager-page',
          $this->executable->getPage()->id(),
          \Drupal::languageManager()->getCurrentLanguage()->getId(),
          // Blocks are always rendered in a "per theme" cache context.
          'cache_context.theme',
        ),
        'tags' => $page->getCacheTag(),
        'expire' => ($max_page_expire === Cache::PERMANENT) ? Cache::PERMANENT : REQUEST_TIME + $max_page_expire,
      );
    }

    return $build;
  }

  /**
   * #pre_render callback for building a block.
   *
   * Renders the content using the provided block plugin, and then:
   * - if there is no content, aborts rendering, and makes sure the block won't
   *   be rendered.
   * - if there is content, moves the contextual links from the block content to
   *   the block itself.
   */
  public function buildBlock($build) {
    $content = $build['#block']->build();
    // Remove the block plugin from the render array.
    unset($build['#block']);
    if (!empty($content)) {
      // Place the $content returned by the block plugin into a 'content' child
      // element, as a way to allow the plugin to have complete control of its
      // properties and rendering (e.g., its own #theme) without conflicting
      // with the properties used above, or alternate ones used by alternate
      // block rendering approaches in contrib (e.g., Panels). However, the use
      // of a child element is an implementation detail of this particular block
      // rendering approach. Semantically, the content returned by the plugin
      // "is the" block, and in particular, #attributes and #contextual_links is
      // information about the *entire* block. Therefore, we must move these
      // properties from $content and merge them into the top-level element.
      foreach (array('#attributes', '#contextual_links') as $property) {
        if (isset($content[$property])) {
          var_dump($property);
          var_dump($build[$property]);
          $build[$property] += $content[$property];
          unset($content[$property]);
        }
      }
      $build['content'] = $content;
    }
    else {
      // Abort rendering: render as the empty string and ensure this block is
      // render cached, so we can avoid the work of having to repeatedly
      // determine whether the block is empty. E.g. modifying or adding entities
      // could cause the block to no longer be empty.
      $build = array(
        '#markup' => '',
        '#cache' => $build['#cache'],
      );
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Do not allow blocks to be added until the display variant has been saved.
    if (!$this->id()) {
      return $form;
    }

    // Determine the page ID, used for links below.
    $page_id = $this->executable->getPage()->id();

    // Set up the attributes used by a modal to prevent duplication later.
    $attributes = array(
      'class' => array('use-ajax'),
      'data-accepts' => 'application/vnd.drupal-modal',
      'data-dialog-options' => Json::encode(array(
        'width' => 'auto',
      )),
    );
    $add_button_attributes = NestedArray::mergeDeep($attributes, array(
      'class' => array(
        'button',
        'button--small',
        'button-action',
      ),
    ));

    if ($block_assignments = $this->getRegionAssignments()) {
      // Build a table of all blocks used by this display variant.
      $form['block_section'] = array(
        '#type' => 'details',
        '#title' => $this->t('Blocks'),
        '#open' => TRUE,
      );
      $form['block_section']['add'] = array(
        '#type' => 'link',
        '#title' => $this->t('Add new block'),
        '#route_name' => 'page_manager.display_variant_select_block',
        '#route_parameters' => array(
          'page' => $page_id,
          'display_variant_id' => $this->id(),
        ),
        '#attributes' => $add_button_attributes,
        '#attached' => array(
          'library' => array(
            'core/drupal.ajax',
          ),
        ),
      );
      $form['block_section']['blocks'] = array(
        '#type' => 'table',
        '#header' => array(
          $this->t('Label'),
          $this->t('Plugin ID'),
          $this->t('Region'),
          $this->t('Weight'),
          $this->t('Operations'),
        ),
        '#empty' => $this->t('There are no regions for blocks.'),
        // @todo This should utilize https://drupal.org/node/2065485.
        '#parents' => array('display_variant', 'blocks'),
      );
      // Loop through the blocks per region.
      foreach ($block_assignments as $region => $blocks) {
        // Add a section for each region and allow blocks to be dragged between
        // them.
        $form['block_section']['blocks']['#tabledrag'][] = array(
          'action' => 'match',
          'relationship' => 'sibling',
          'group' => 'block-region-select',
          'subgroup' => 'block-region-' . $region,
          'hidden' => FALSE,
        );
        $form['block_section']['blocks']['#tabledrag'][] = array(
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'block-weight',
          'subgroup' => 'block-weight-' . $region,
        );
        $form['block_section']['blocks'][$region] = array(
          '#attributes' => array(
            'class' => array('region-title', 'region-title-' . $region),
            'no_striping' => TRUE,
          ),
        );
        $form['block_section']['blocks'][$region]['title'] = array(
          '#markup' => $this->getRegionName($region),
          '#wrapper_attributes' => array(
            'colspan' => 5,
          ),
        );
        $form['block_section']['blocks'][$region . '-message'] = array(
          '#attributes' => array(
            'class' => array(
              'region-message',
              'region-' . $region . '-message',
              empty($blocks) ? 'region-empty' : 'region-populated',
            ),
          ),
        );
        $form['block_section']['blocks'][$region . '-message']['message'] = array(
          '#markup' => '<em>' . t('No blocks in this region') . '</em>',
          '#wrapper_attributes' => array(
            'colspan' => 5,
          ),
        );

        /** @var $blocks \Drupal\Core\Block\BlockPluginInterface[] */
        foreach ($blocks as $block_id => $block) {
          $row = array(
            '#attributes' => array(
              'class' => array('draggable'),
            ),
          );
          $row['label']['#markup'] = $block->label();
          $row['id']['#markup'] = $block->getPluginId();
          // Allow the region to be changed for each block.
          $row['region'] = array(
            '#title' => $this->t('Region'),
            '#title_display' => 'invisible',
            '#type' => 'select',
            '#options' => $this->getRegionNames(),
            '#default_value' => $this->getRegionAssignment($block_id),
            '#attributes' => array(
              'class' => array('block-region-select', 'block-region-' . $region),
            ),
          );
          // Allow the weight to be changed for each block.
          $configuration = $block->getConfiguration();
          $row['weight'] = array(
            '#type' => 'weight',
            '#default_value' => isset($configuration['weight']) ? $configuration['weight'] : 0,
            '#title' => t('Weight for @block block', array('@block' => $block->label())),
            '#title_display' => 'invisible',
            '#attributes' => array(
              'class' => array('block-weight', 'block-weight-' . $region),
            ),
          );
          // Add the operation links.
          $operations = array();
          $operations['edit'] = array(
            'title' => $this->t('Edit'),
            'route_name' => 'page_manager.display_variant_edit_block',
            'route_parameters' => array(
              'page' => $page_id,
              'display_variant_id' => $this->id(),
              'block_id' => $block_id,
            ),
            'attributes' => $attributes,
          );
          $operations['delete'] = array(
            'title' => $this->t('Delete'),
            'route_name' => 'page_manager.display_variant_delete_block',
            'route_parameters' => array(
              'page' => $page_id,
              'display_variant_id' => $this->id(),
              'block_id' => $block_id,
            ),
            'attributes' => $attributes,
          );

          $row['operations'] = array(
            '#type' => 'operations',
            '#links' => $operations,
          );
          $form['block_section']['blocks'][$block_id] = $row;
        }
      }
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    // If the blocks were rearranged, update their values.
    if (!$form_state->isValueEmpty('blocks')) {
      foreach ($form_state->getValue('blocks') as $block_id => $block_values) {
        $this->updateBlock($block_id, $block_values);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account = NULL) {
    // If no blocks are configured for this variant, deny access.
    if (empty($this->configuration['blocks'])) {
      return FALSE;
    }

    // Delegate to the conditions.
    if ($this->determineSelectionAccess($this->getContexts()) === FALSE) {
      return FALSE;
    }

    return parent::access($account);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + array(
      'blocks' => array(),
      'selection_conditions' => array(),
      'selection_logic' => 'and',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    foreach ($this->getBlockBag() as $instance) {
      $this->calculatePluginDependencies($instance);
    }
    foreach ($this->getSelectionConditions() as $instance) {
      $this->calculatePluginDependencies($instance);
    }
    return $this->dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return array(
      'selection_conditions' => $this->getSelectionConditions()->getConfiguration(),
      'blocks' => $this->getBlockBag()->getConfiguration(),
    ) + parent::getConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getSelectionLogic() {
    return $this->configuration['selection_logic'];
  }

  /**
   * Wraps drupal_html_class().
   *
   * @return string
   */
  protected function drupalHtmlClass($class) {
    return drupal_html_class($class);
  }

  /**
   * {@inheritdoc}
   */
  protected function contextHandler() {
    return $this->contextHandler;
  }

  /**
   * {@inheritdoc}
   */
  protected function getSelectionConfiguration() {
    return $this->configuration['selection_conditions'];
  }

  /**
   * {@inheritdoc}
   */
  public function setExecutable(PageExecutable $executable) {
    $this->executable = $executable;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function getBlockConfig() {
    return $this->configuration['blocks'];
  }

  /**
   * {@inheritdoc}
   */
  protected function uuidGenerator() {
    return $this->uuidGenerator;
  }

}
