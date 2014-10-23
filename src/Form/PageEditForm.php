<?php

/**
 * @file
 * Contains \Drupal\page_manager\Form\PageEditForm.
 */

namespace Drupal\page_manager\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a form for editing a page entity.
 */
class PageEditForm extends PageFormBase {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['use_admin_theme'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Use admin theme'),
      '#default_value' => $this->entity->usesAdminTheme(),
    );
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

    $form['display_variant_section'] = array(
      '#type' => 'details',
      '#title' => $this->t('Display variants'),
      '#open' => TRUE,
    );
    $form['display_variant_section']['add_new_page'] = array(
      '#type' => 'link',
      '#title' => $this->t('Add new display variant'),
      '#url' => Url::fromRoute('page_manager.display_variant_select', [
        'page' => $this->entity->id(),
      ]),
      '#attributes' => $add_button_attributes,
      '#attached' => array(
        'library' => array(
          'core/drupal.ajax',
        ),
      ),
    );
    $form['display_variant_section']['display_variants'] = array(
      '#type' => 'table',
      '#header' => array(
        $this->t('Label'),
        $this->t('Plugin'),
        $this->t('Weight'),
        $this->t('Operations'),
      ),
      '#empty' => $this->t('There are no display variants.'),
      '#tabledrag' => array(array(
        'action' => 'order',
        'relationship' => 'sibling',
        'group' => 'display-variant-weight',
      )),
    );
    foreach ($this->entity->getVariants() as $display_variant_id => $display_variant) {
      $row = array(
        '#attributes' => array(
          'class' => array('draggable'),
        ),
      );
      $row['label']['#markup'] = $display_variant->label();
      $row['id']['#markup'] = $display_variant->adminLabel();
      $row['weight'] = array(
        '#type' => 'weight',
        '#default_value' => $display_variant->getWeight(),
        '#title' => t('Weight for @display_variant display variant', array('@display_variant' => $display_variant->label())),
        '#title_display' => 'invisible',
        '#attributes' => array(
          'class' => array('display-variant-weight'),
        ),
      );
      $operations = array();
      $operations['edit'] = array(
        'title' => $this->t('Edit'),
        'url' => Url::fromRoute('page_manager.display_variant_edit', [
          'page' => $this->entity->id(),
          'display_variant_id' => $display_variant_id,
        ]),
      );
      $operations['delete'] = array(
        'title' => $this->t('Delete'),
        'url' => Url::fromRoute('page_manager.display_variant_delete', [
          'page' => $this->entity->id(),
          'display_variant_id' => $display_variant_id,
        ]),
      );
      $row['operations'] = array(
        '#type' => 'operations',
        '#links' => $operations,
      );
      $form['display_variant_section']['display_variants'][$display_variant_id] = $row;
    }

    if ($access_conditions = $this->entity->getAccessConditions()) {
      $form['access_section_section'] = array(
        '#type' => 'details',
        '#title' => $this->t('Access Conditions'),
        '#open' => TRUE,
      );
      $form['access_section_section']['add'] = array(
        '#type' => 'link',
        '#title' => $this->t('Add new access condition'),
        '#url' => Url::fromRoute('page_manager.access_condition_select', [
          'page' => $this->entity->id(),
        ]),
        '#attributes' => $add_button_attributes,
        '#attached' => array(
          'library' => array(
            'core/drupal.ajax',
          ),
        ),
      );
      $form['access_section_section']['access_section'] = array(
        '#type' => 'table',
        '#header' => array(
          $this->t('Label'),
          $this->t('Description'),
          $this->t('Operations'),
        ),
        '#empty' => $this->t('There are no access conditions.'),
      );

      $form['access_section_section']['access_logic'] = array(
        '#type' => 'radios',
        '#options' => array(
          'and' => $this->t('All conditions must pass'),
          'or' => $this->t('Only one condition must pass'),
        ),
        '#default_value' => $this->entity->getAccessLogic(),
      );

      $form['access_section_section']['access'] = array(
        '#tree' => TRUE,
      );
      foreach ($access_conditions as $access_id => $access_condition) {
        $row = array();
        $row['label']['#markup'] = $access_condition->getPluginDefinition()['label'];
        $row['description']['#markup'] = $access_condition->summary();
        $operations = array();
        $operations['edit'] = array(
          'title' => $this->t('Edit'),
          'url' => Url::fromRoute('page_manager.access_condition_edit', [
            'page' => $this->entity->id(),
            'condition_id' => $access_id,
          ]),
          'attributes' => $attributes,
        );
        $operations['delete'] = array(
          'title' => $this->t('Delete'),
          'url' => Url::fromRoute('page_manager.access_condition_delete', [
            'page' => $this->entity->id(),
            'condition_id' => $access_id,
          ]),
          'attributes' => $attributes,
        );
        $row['operations'] = array(
          '#type' => 'operations',
          '#links' => $operations,
        );
        $form['access_section_section']['access_section'][$access_id] = $row;
      }
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    if (!$form_state->isValueEmpty('display_variants')) {
      foreach ($form_state->getValue('display_variants') as $display_variant_id => $data) {
        if ($display_variant = $this->entity->getVariant($display_variant_id)) {
          $display_variant->setWeight($data['weight']);
        }
      }
    }
    parent::save($form, $form_state);
    drupal_set_message($this->t('The %label page has been updated.', array('%label' => $this->entity->label())));
    $form_state->setRedirect('page_manager.page_list');
  }

  /**
   * {@inheritdoc}
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    $keys_to_skip = array_keys($this->entity->getPluginCollections());
    foreach ($form_state->getValues() as $key => $value) {
      if (!in_array($key, $keys_to_skip)) {
        $entity->set($key, $value);
      }
    }
  }

}
