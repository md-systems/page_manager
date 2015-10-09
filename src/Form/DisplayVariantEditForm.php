<?php

/**
 * @file
 * Contains \Drupal\page_manager\Form\DisplayVariantEditForm.
 */

namespace Drupal\page_manager\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ctools\Form\AjaxFormTrait;
use Drupal\ctools\Plugin\BlockVariantInterface;
use Drupal\ctools\Plugin\ConditionVariantInterface;
use Drupal\page_manager\PageInterface;

/**
 * Provides a form for editing a display variant.
 */
class DisplayVariantEditForm extends DisplayVariantFormBase {

  use AjaxFormTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'page_manager_display_variant_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function submitText() {
    return $this->t('Update display variant');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, PageInterface $page = NULL, $display_variant_id = NULL) {
    $form = parent::buildForm($form, $form_state, $page, $display_variant_id);

    if ($this->displayVariant instanceof BlockVariantInterface) {
      $form['block_section'] = $this->buildBlockForm();
    }
    if ($this->displayVariant instanceof ConditionVariantInterface) {
      $form['selection_section'] = $this->buildSelectionForm();
    }

    return $form;
  }

  /**
   * Builds the block form for a variant.
   *
   * @return array
   */
  protected function buildBlockForm() {
    if (!$this->displayVariant instanceof BlockVariantInterface) {
      return [];
    }

    // Determine the page ID, used for links below.
    $page_id = $this->getPage()->id();

    // Set up the attributes used by a modal to prevent duplication later.
    $attributes = $this->getAjaxAttributes();
    $add_button_attributes = $this->getAjaxButtonAttributes();

    $form = [];
    if ($block_assignments = $this->displayVariant->getRegionAssignments()) {
      // Build a table of all blocks used by this display variant.
      $form = [
        '#type' => 'details',
        '#title' => $this->t('Blocks'),
        '#open' => TRUE,
      ];
      $form['add'] = [
        '#type' => 'link',
        '#title' => $this->t('Add new block'),
        '#url' => Url::fromRoute('page_manager.display_variant_select_block', [
          'page' => $page_id,
          'display_variant_id' => $this->displayVariant->id(),
        ]),
        '#attributes' => $add_button_attributes,
        '#attached' => [
          'library' => [
            'core/drupal.ajax',
          ],
        ],
      ];
      $form['blocks'] = [
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
        $form['blocks']['#tabledrag'][] = [
          'action' => 'match',
          'relationship' => 'sibling',
          'group' => 'block-region-select',
          'subgroup' => 'block-region-' . $region,
          'hidden' => FALSE,
        ];
        $form['blocks']['#tabledrag'][] = [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'block-weight',
          'subgroup' => 'block-weight-' . $region,
        ];
        $form['blocks'][$region] = [
          '#attributes' => [
            'class' => ['region-title', 'region-title-' . $region],
            'no_striping' => TRUE,
          ],
        ];
        $form['blocks'][$region]['title'] = [
          '#markup' => $this->displayVariant->getRegionName($region),
          '#wrapper_attributes' => [
            'colspan' => 5,
          ],
        ];
        $form['blocks'][$region . '-message'] = [
          '#attributes' => [
            'class' => [
              'region-message',
              'region-' . $region . '-message',
              empty($blocks) ? 'region-empty' : 'region-populated',
            ],
          ],
        ];
        $form['blocks'][$region . '-message']['message'] = [
          '#markup' => '<em>' . $this->t('No blocks in this region') . '</em>',
          '#wrapper_attributes' => [
            'colspan' => 5,
          ],
        ];

        /** @var \Drupal\Core\Block\BlockPluginInterface[] $blocks */
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
            '#options' => $this->displayVariant->getRegionNames(),
            '#default_value' => $this->displayVariant->getRegionAssignment($block_id),
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
              'display_variant_id' => $this->displayVariant->id(),
              'block_id' => $block_id,
            ]),
            'attributes' => $attributes,
          ];
          $operations['delete'] = [
            'title' => $this->t('Delete'),
            'url' => Url::fromRoute('page_manager.display_variant_delete_block', [
              'page' => $page_id,
              'display_variant_id' => $this->displayVariant->id(),
              'block_id' => $block_id,
            ]),
            'attributes' => $attributes,
          ];

          $row['operations'] = [
            '#type' => 'operations',
            '#links' => $operations,
          ];
          $form['blocks'][$block_id] = $row;
        }
      }
    }
    return $form;
  }

  /**
   * Builds the selection form for a variant.
   *
   * @return array
   */
  protected function buildSelectionForm() {
    // Set up the attributes used by a modal to prevent duplication later.
    $attributes = $this->getAjaxAttributes();
    $add_button_attributes = $this->getAjaxButtonAttributes();

    $form = [];
    if ($this->displayVariant instanceof ConditionVariantInterface) {
      if ($selection_conditions = $this->displayVariant->getSelectionConditions()) {
        // Selection conditions.
        $form = [
          '#type' => 'details',
          '#title' => $this->t('Selection Conditions'),
          '#open' => TRUE,
        ];
        $form['add'] = [
          '#type' => 'link',
          '#title' => $this->t('Add new selection condition'),
          '#url' => Url::fromRoute('page_manager.selection_condition_select', [
            'page' => $this->page->id(),
            'display_variant_id' => $this->displayVariant->id(),
          ]),
          '#attributes' => $add_button_attributes,
          '#attached' => [
            'library' => [
              'core/drupal.ajax',
            ],
          ],
        ];
        $form['table'] = [
          '#type' => 'table',
          '#header' => [
            $this->t('Label'),
            $this->t('Description'),
            $this->t('Operations'),
          ],
          '#empty' => $this->t('There are no selection conditions.'),
        ];

        $form['selection_logic'] = [
          '#type' => 'radios',
          '#options' => [
            'and' => $this->t('All conditions must pass'),
            'or' => $this->t('Only one condition must pass'),
          ],
          '#default_value' => $this->displayVariant->getSelectionLogic(),
        ];

        $form['selection'] = [
          '#tree' => TRUE,
        ];
        foreach ($selection_conditions as $selection_id => $selection_condition) {
          $row = [];
          $row['label']['#markup'] = $selection_condition->getPluginDefinition()['label'];
          $row['description']['#markup'] = $selection_condition->summary();
          $operations = [];
          $operations['edit'] = [
            'title' => $this->t('Edit'),
            'url' => Url::fromRoute('page_manager.selection_condition_edit', [
              'page' => $this->page->id(),
              'display_variant_id' => $this->displayVariant->id(),
              'condition_id' => $selection_id,
            ]),
            'attributes' => $attributes,
          ];
          $operations['delete'] = [
            'title' => $this->t('Delete'),
            'url' => Url::fromRoute('page_manager.selection_condition_delete', [
              'page' => $this->page->id(),
              'display_variant_id' => $this->displayVariant->id(),
              'condition_id' => $selection_id,
            ]),
            'attributes' => $attributes,
          ];
          $row['operations'] = [
            '#type' => 'operations',
            '#links' => $operations,
          ];
          $form['table'][$selection_id] = $row;
        }
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // @todo This feels very wrong.
    if ($this->displayVariant instanceof BlockVariantInterface) {
      // If the blocks were rearranged, update their values.
      if (!$form_state->isValueEmpty(['display_variant', 'blocks'])) {
        foreach ($form_state->getValue(['display_variant', 'blocks']) as $block_id => $block_values) {
          $this->displayVariant->updateBlock($block_id, $block_values);
        }
      }
    }

    parent::submitForm($form, $form_state);

    // Save the page entity.
    $this->page->save();
    drupal_set_message($this->t('The %label display variant has been updated.', ['%label' => $this->displayVariant->label()]));
    $form_state->setRedirectUrl($this->page->urlInfo('edit-form'));
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareDisplayVariant($display_variant_id) {
    // Load the display variant directly from the page entity.
    return $this->page->getVariant($display_variant_id);
  }

}
