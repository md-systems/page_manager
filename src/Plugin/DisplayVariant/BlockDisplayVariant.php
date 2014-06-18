<?php

/**
 * @file
 * Contains \Drupal\page_manager\Plugin\DisplayVariant\BlockDisplayVariant.
 */

namespace Drupal\page_manager\Plugin\DisplayVariant;

use Drupal\Component\Plugin\ContextAwarePluginInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\page_manager\Plugin\VariantBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a display variant that simply contains blocks.
 *
 * @DisplayVariant(
 *   id = "block_page",
 *   admin_label = @Translation("Block page")
 * )
 */
class BlockDisplayVariant extends VariantBase implements ContainerFactoryPluginInterface {

  /**
   * The context handler.
   *
   * @var \Drupal\Core\Plugin\Context\ContextHandlerInterface
   */
  protected $contextHandler;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

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
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ContextHandlerInterface $context_handler, AccountInterface $account) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->contextHandler = $context_handler;
    $this->account = $account;
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
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getRegionNames() {
    return array(
      'top' => 'Top',
      'bottom' => 'Bottom',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = array();
    $contexts = $this->getContexts();
    foreach ($this->getRegionAssignments() as $region => $blocks) {
      if (!$blocks) {
        continue;
      }

      $region_name = $this->drupalHtmlClass("block-region-$region");
      $build[$region]['#prefix'] = '<div class="' . $region_name . '">';
      $build[$region]['#suffix'] = '</div>';

      /** @var $blocks \Drupal\block\BlockPluginInterface[] */
      foreach ($blocks as $block_id => $block) {
        if ($block instanceof ContextAwarePluginInterface) {
          $this->contextHandler->applyContextMapping($block, $contexts);
        }
        if ($block->access($this->account)) {
          $row = $block->build();
          $block_name = $this->drupalHtmlClass("block-$block_id");
          $row['#prefix'] = '<div class="' . $block_name . '">';
          $row['#suffix'] = '</div>';

          $build[$region][$block_id] = $row;
        }
      }
    }
    $build['#title'] = $this->renderPageTitle($this->configuration['page_title']);
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $configuration = parent::defaultConfiguration();
    $configuration['page_title'] = '';
    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, array &$form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Allow to configure the page title, even when adding a new display.
    // Default to the page label in that case.
    $form['page_title'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Page title'),
      '#description' => $this->t('Configure the page title that will be used for this display.'),
      '#default_value' => !$this->id() ? $this->executable->getPage()->label() : $this->configuration['page_title'],
    );

    if (\Drupal::moduleHandler()->moduleExists('token')) {
      $form['token_tree'] = array(
        '#theme' => 'token_tree',
        '#token_types' => array_keys($this->getContextAsTokenData()),
      );
    }

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

        /** @var $blocks \Drupal\block\BlockPluginInterface[] */
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
  public function submitConfigurationForm(array &$form, array &$form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!empty($form_state['values']['page_title'])) {
      $this->configuration['page_title'] = $form_state['values']['page_title'];
    }

    // If the blocks were rearranged, update their values.
    if (!empty($form_state['values']['blocks'])) {
      foreach ($form_state['values']['blocks'] as $block_id => $block_values) {
        $this->updateBlock($block_id, $block_values);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access() {
    // If no blocks are configured for this variant, deny access.
    if (!$this->getBlockCount()) {
      return FALSE;
    }

    return parent::access();
  }

  /**
   * Wraps drupal_html_class().
   *
   * @return string
   */
  protected function drupalHtmlClass($class) {
    return drupal_html_class($class);
  }

}
