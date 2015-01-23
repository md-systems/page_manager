<?php

/**
 * @file
 * Contains \Drupal\page_manager\Plugin\DisplayVariant\BlockDisplayVariant.
 */

namespace Drupal\page_manager\Plugin\DisplayVariant;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\String;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Display\VariantBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\Token;
use Drupal\page_manager\PageExecutableInterface;
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
 *   id = "block_display",
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
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

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
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ContextHandlerInterface $context_handler, AccountInterface $account, UuidInterface $uuid_generator, Token $token, LanguageManagerInterface $language_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->contextHandler = $context_handler;
    $this->account = $account;
    $this->uuidGenerator = $uuid_generator;
    $this->token = $token;
    $this->languageManager = $language_manager;
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
      $container->get('uuid'),
      $container->get('token'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    // Default the max page expire to permanent.
    $max_page_expire = Cache::PERMANENT;

    $page = $this->executable->getPage();

    // Set default page cache keys that includes the page and display.
    $page_cache_keys = [
      'page-manager-page',
      // The page ID.
      $page->id(),
      // The UUID of this display.
      // @todo should have an API for this?
      $this->configuration['uuid'],
      $this->languageManager->getCurrentLanguage()->getId(),
    ];

    $contexts = $this->getContexts();
    foreach ($this->getRegionAssignments() as $region => $blocks) {
      if (!$blocks) {
        continue;
      }

      $region_name = Html::getClass("block-region-$region");
      $build['regions'][$region]['#prefix'] = '<div class="' . $region_name . '">';
      $build['regions'][$region]['#suffix'] = '</div>';

      /** @var $blocks \Drupal\Core\Block\BlockPluginInterface[] */
      $weight = 0;
      foreach ($blocks as $block_id => $block) {
        if ($block instanceof ContextAwarePluginInterface) {
          $this->contextHandler()->applyContextMapping($block, $contexts);
        }
        if (!$block->access($this->account)) {
          continue;
        }

        $block_render_array = [
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
        ];
        $block_render_array['#cache']['tags'] = NestedArray::mergeDeepArray(array(
          $page->getCacheTags(), // Page plugin cache tags.
          $block->getCacheTags(), // Block plugin cache tag.
        ));
        if (!empty($block_render_array['#configuration']['label'])) {
          $block_render_array['#configuration']['label'] = String::checkPlain($block_render_array['#configuration']['label']);
        }
        $build['regions'][$region][$block_id] = $block_render_array;

        if ($block->isCacheable()) {
          $build['regions'][$region][$block_id]['#pre_render'][] = array($this, 'buildBlock');
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
          $build['regions'][$region][$block_id]['#cache'] += array(
            'keys' => array_merge($default_cache_keys, $block->getCacheKeys()),
            'expire' => ($max_block_page === Cache::PERMANENT) ? Cache::PERMANENT : REQUEST_TIME + $max_block_page,
          );

          // Also include the block ID and cache keys in the page cache keys.
          $page_cache_keys = array_merge($page_cache_keys, array($block_id), $block->getCacheKeys());

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
          $build['regions'][$region][$block_id] = $this->buildBlock($build['regions'][$region][$block_id]);
        }
      }
    }

    $build['#title'] = $this->renderPageTitle($this->configuration['page_title']);

    // Set up render cache on the page level.
    if ($max_page_expire !== NULL) {
      $build['regions']['#cache'] = array(
        'keys' => array_unique($page_cache_keys),
        'tags' => $page->getCacheTags(),
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

    // Allow to configure the page title, even when adding a new display.
    // Default to the page label in that case.
    $form['page_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Page title'),
      '#description' => $this->t('Configure the page title that will be used for this display.'),
      '#default_value' => !$this->id() ? $this->executable->getPage()->label() : $this->configuration['page_title'],
    ];

    // Do not allow blocks to be added until the display variant has been saved.
    if (!$this->id()) {
      return $form;
    }

    // Determine the page ID, used for links below.
    $page_id = $this->executable->getPage()->id();

    // Set up the attributes used by a modal to prevent duplication later.
    $attributes = [
      'class' => ['use-ajax'],
      'data-accepts' => 'application/vnd.drupal-modal',
      'data-dialog-options' => Json::encode([
        'width' => 'auto',
      ]),
    ];
    $add_button_attributes = NestedArray::mergeDeep($attributes, [
      'class' => [
        'button',
        'button--small',
        'button-action',
      ],
    ]);

    if ($block_assignments = $this->getRegionAssignments()) {
      // Build a table of all blocks used by this display variant.
      $form['block_section'] = [
        '#type' => 'details',
        '#title' => $this->t('Blocks'),
        '#open' => TRUE,
      ];
      $form['block_section']['add'] = [
        '#type' => 'link',
        '#title' => $this->t('Add new block'),
        '#url' => Url::fromRoute('page_manager.display_variant_select_block', [
          'page' => $page_id,
          'display_variant_id' => $this->id(),
        ]),
        '#attributes' => $add_button_attributes,
        '#attached' => [
          'library' => [
            'core/drupal.ajax',
          ],
        ],
      ];
      $form['block_section']['blocks'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Label'),
          $this->t('Plugin ID'),
          $this->t('Region'),
          $this->t('Weight'),
          $this->t('Operations'),
        ],
        '#empty' => $this->t('There are no regions for blocks.'),
        // @todo This should utilize https://drupal.org/node/2065485.
        '#parents' => ['display_variant', 'blocks'],
      ];
      // Loop through the blocks per region.
      foreach ($block_assignments as $region => $blocks) {
        // Add a section for each region and allow blocks to be dragged between
        // them.
        $form['block_section']['blocks']['#tabledrag'][] = [
          'action' => 'match',
          'relationship' => 'sibling',
          'group' => 'block-region-select',
          'subgroup' => 'block-region-' . $region,
          'hidden' => FALSE,
        ];
        $form['block_section']['blocks']['#tabledrag'][] = [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'block-weight',
          'subgroup' => 'block-weight-' . $region,
        ];
        $form['block_section']['blocks'][$region] = [
          '#attributes' => [
            'class' => ['region-title', 'region-title-' . $region],
            'no_striping' => TRUE,
          ],
        ];
        $form['block_section']['blocks'][$region]['title'] = [
          '#markup' => $this->getRegionName($region),
          '#wrapper_attributes' => [
            'colspan' => 5,
          ],
        ];
        $form['block_section']['blocks'][$region . '-message'] = [
          '#attributes' => [
            'class' => [
              'region-message',
              'region-' . $region . '-message',
              empty($blocks) ? 'region-empty' : 'region-populated',
            ],
          ],
        ];
        $form['block_section']['blocks'][$region . '-message']['message'] = [
          '#markup' => '<em>' . $this->t('No blocks in this region') . '</em>',
          '#wrapper_attributes' => [
            'colspan' => 5,
          ],
        ];

        /** @var $blocks \Drupal\Core\Block\BlockPluginInterface[] */
        foreach ($blocks as $block_id => $block) {
          $row = [
            '#attributes' => [
              'class' => ['draggable'],
            ],
          ];
          $row['label']['#markup'] = $block->label();
          $row['id']['#markup'] = $block->getPluginId();
          // Allow the region to be changed for each block.
          $row['region'] = [
            '#title' => $this->t('Region'),
            '#title_display' => 'invisible',
            '#type' => 'select',
            '#options' => $this->getRegionNames(),
            '#default_value' => $this->getRegionAssignment($block_id),
            '#attributes' => [
              'class' => ['block-region-select', 'block-region-' . $region],
            ],
          ];
          // Allow the weight to be changed for each block.
          $configuration = $block->getConfiguration();
          $row['weight'] = [
            '#type' => 'weight',
            '#default_value' => isset($configuration['weight']) ? $configuration['weight'] : 0,
            '#title' => $this->t('Weight for @block block', ['@block' => $block->label()]),
            '#title_display' => 'invisible',
            '#attributes' => [
              'class' => ['block-weight', 'block-weight-' . $region],
            ],
          ];
          // Add the operation links.
          $operations = [];
          $operations['edit'] = [
            'title' => $this->t('Edit'),
            'url' => Url::fromRoute('page_manager.display_variant_edit_block', [
              'page' => $page_id,
              'display_variant_id' => $this->id(),
              'block_id' => $block_id,
            ]),
            'attributes' => $attributes,
          ];
          $operations['delete'] = [
            'title' => $this->t('Delete'),
            'url' => Url::fromRoute('page_manager.display_variant_delete_block', [
              'page' => $page_id,
              'display_variant_id' => $this->id(),
              'block_id' => $block_id,
            ]),
            'attributes' => $attributes,
          ];

          $row['operations'] = [
            '#type' => 'operations',
            '#links' => $operations,
          ];
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

    if ($form_state->hasValue('page_title')) {
      $this->configuration['page_title'] = $form_state->getValue('page_title');
    }

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
    return parent::defaultConfiguration() + [
      'blocks' => [],
      'selection_conditions' => [],
      'selection_logic' => 'and',
      'page_title' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    foreach ($this->getBlockCollection() as $instance) {
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
    return [
      'selection_conditions' => $this->getSelectionConditions()->getConfiguration(),
      'blocks' => $this->getBlockCollection()->getConfiguration(),
    ] + parent::getConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getSelectionLogic() {
    return $this->configuration['selection_logic'];
  }

  /**
   * Renders the page title and replaces tokens.
   *
   * @param string $page_title
   *   The page title that should be rendered.
   *
   * @return string
   *   The page title after replacing any tokens.
   */
  protected function renderPageTitle($page_title) {
    $data = $this->getContextAsTokenData();
    return $this->token->replace($page_title, $data);
  }

  /**
   * Returns available context as token data.
   *
   * @return array
   *   An array with token data values keyed by token type.
   */
  protected function getContextAsTokenData() {
    $data = [];
    foreach ($this->executable->getContexts() as $context) {
      // @todo Simplify this when token and typed data types are unified in
      //   https://drupal.org/node/2163027.
      if (strpos($context->getContextDefinition()->getDataType(), 'entity:') === 0) {
        $token_type = substr($context->getContextDefinition()->getDataType(), 7);
        if ($token_type == 'taxonomy_term') {
          $token_type = 'term';
        }
        $data[$token_type] = $context->getContextValue();
      }
    }
    return $data;
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
  public function setExecutable(PageExecutableInterface $executable) {
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
