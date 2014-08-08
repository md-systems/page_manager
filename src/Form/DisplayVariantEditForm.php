<?php

/**
 * @file
 * Contains \Drupal\page_manager\Form\DisplayVariantEditForm.
 */

namespace Drupal\page_manager\Form;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\page_manager\PageInterface;
use Drupal\Component\Serialization\Json;
use Drupal\page_manager\Plugin\ConditionVariantInterface;
use Drupal\page_manager\Plugin\PageAwareVariantInterface;

/**
 * Provides a form for editing a display variant.
 */
class DisplayVariantEditForm extends DisplayVariantFormBase {

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
      )
    ));

    if ($this->displayVariant instanceof ConditionVariantInterface) {
      if ($selection_conditions = $this->displayVariant->getSelectionConditions()) {
        // Selection conditions.
        $form['selection_section'] = array(
          '#type' => 'details',
          '#title' => $this->t('Selection Conditions'),
          '#open' => TRUE,
        );
        $form['selection_section']['add'] = array(
          '#type' => 'link',
          '#title' => $this->t('Add new selection condition'),
          '#route_name' => 'page_manager.selection_condition_select',
          '#route_parameters' => array(
            'page' => $this->page->id(),
            'display_variant_id' => $this->displayVariant->id(),
          ),
          '#attributes' => $add_button_attributes,
          '#attached' => array(
            'library' => array(
              'core/drupal.ajax',
            ),
          ),
        );
        $form['selection_section']['table'] = array(
          '#type' => 'table',
          '#header' => array(
            $this->t('Label'),
            $this->t('Description'),
            $this->t('Operations'),
          ),
          '#empty' => $this->t('There are no selection conditions.'),
        );

        $form['selection_section']['selection_logic'] = array(
          '#type' => 'radios',
          '#options' => array(
            'and' => $this->t('All conditions must pass'),
            'or' => $this->t('Only one condition must pass'),
          ),
          '#default_value' => $this->displayVariant->getSelectionLogic(),
        );

        $form['selection_section']['selection'] = array(
          '#tree' => TRUE,
        );
        foreach ($selection_conditions as $selection_id => $selection_condition) {
          $row = array();
          $row['label']['#markup'] = $selection_condition->getPluginDefinition()['label'];
          $row['description']['#markup'] = $selection_condition->summary();
          $operations = array();
          $operations['edit'] = array(
            'title' => $this->t('Edit'),
            'route_name' => 'page_manager.selection_condition_edit',
            'route_parameters' => array(
              'page' => $this->page->id(),
              'display_variant_id' => $this->displayVariant->id(),
              'condition_id' => $selection_id,
            ),
            'attributes' => $attributes,
          );
          $operations['delete'] = array(
            'title' => $this->t('Delete'),
            'route_name' => 'page_manager.selection_condition_delete',
            'route_parameters' => array(
              'page' => $this->page->id(),
              'display_variant_id' => $this->displayVariant->id(),
              'condition_id' => $selection_id,
            ),
            'attributes' => $attributes,
          );
          $row['operations'] = array(
            '#type' => 'operations',
            '#links' => $operations,
          );
          $form['selection_section']['table'][$selection_id] = $row;
        }
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    // Save the page entity.
    $this->page->save();
    drupal_set_message($this->t('The %label display variant has been updated.', array('%label' => $this->displayVariant->label())));
    $form_state->setRedirectUrl($this->page->urlInfo('edit-form'));
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareDisplayVariant($display_variant_id) {
    // Load the display variant directly from the page entity.
    $variant = $this->page->getVariant($display_variant_id);
    if ($variant instanceof PageAwareVariantInterface) {
      $variant->setExecutable($this->page->getExecutable());
    }
    return $variant;
  }

}
