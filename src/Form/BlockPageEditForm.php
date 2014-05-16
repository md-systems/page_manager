<?php

/**
 * @file
 * Contains \Drupal\block_page\Form\BlockPageEditForm.
 */

namespace Drupal\block_page\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Provides a form for editing a block page.
 */
class BlockPageEditForm extends BlockPageFormBase {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, array &$form_state) {
    $form = parent::form($form, $form_state);
    $attributes = array(
      'class' => array('use-ajax'),
      'data-accepts' => 'application/vnd.drupal-modal',
      'data-dialog-options' => Json::encode(array(
        'width' => 'auto',
      )),
    );

    $form['page_variants'] = array(
      '#type' => 'details',
      '#title' => $this->t('Page Variants'),
      '#open' => TRUE,
    );
    $form['page_variants']['add_new_block_page'] = array(
      '#type' => 'link',
      '#title' => $this->t('Add new page variant'),
      '#route_name' => 'block_page.page_variant_add',
      '#route_parameters' => array(
        'block_page' => $this->entity->id(),
      ),
      '#attributes' => $attributes,
      '#attached' => array(
        'library' => array(
          'core/drupal.ajax',
        ),
      ),
    );
    $form['page_variants']['table'] = array(
      '#type' => 'table',
      '#header' => array(
        $this->t('Label'),
        $this->t('Plugin ID'),
        $this->t('Regions'),
        $this->t('Number of blocks'),
        $this->t('Weight'),
        $this->t('Operations'),
      ),
      '#empty' => $this->t('There are no page variants.'),
      '#tabledrag' => array(array(
        'action' => 'order',
        'relationship' => 'sibling',
        'group' => 'page-variant-weight',
      )),
    );
    foreach ($this->entity->getPageVariants() as $page_variant_id => $page_variant) {
      $row = array(
        '#attributes' => array(
          'class' => array('draggable'),
        ),
      );
      $row['label']['#markup'] = $page_variant->label();
      $row['id']['#markup'] = $page_variant->getPluginId();
      $row['regions'] = array('data' => array(
        '#theme' => 'item_list',
        '#items' => $page_variant->getRegionNames(),
      ));
      $row['count']['#markup'] = $page_variant->getBlockCount();
      $row['weight'] = array(
        '#type' => 'weight',
        '#default_value' => $page_variant->getWeight(),
        '#title' => t('Weight for @page_variant page variant', array('@page_variant' => $page_variant->label())),
        '#title_display' => 'invisible',
        '#attributes' => array(
          'class' => array('page-variant-weight'),
        ),
      );
      $operations = array();
      $operations['edit'] = array(
        'title' => $this->t('Edit'),
        'route_name' => 'block_page.page_variant_edit',
        'route_parameters' => array(
          'block_page' => $this->entity->id(),
          'page_variant_id' => $page_variant_id,
        ),
      );
      $operations['delete'] = array(
        'title' => $this->t('Delete'),
        'route_name' => 'block_page.page_variant_delete',
        'route_parameters' => array(
          'block_page' => $this->entity->id(),
          'page_variant_id' => $page_variant_id,
        ),
      );
      $row['operations'] = array(
        '#type' => 'operations',
        '#links' => $operations,
      );
      $form['page_variants']['table'][$page_variant_id] = $row;
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, array &$form_state) {
    foreach ($form_state['values']['page_variants'] as $page_variant_id => $data) {
      if ($page_variant = $this->entity->getPageVariant($page_variant_id)) {
        $page_variant->setWeight($data['weight']);
      }
    }
    parent::save($form, $form_state);
    drupal_set_message($this->t('The %label block page has been updated.', array('%label' => $this->entity->label())));
    $form_state['redirect_route'] = new Url('block_page.page_list');
  }

  /**
   * {@inheritdoc}
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, array &$form_state) {
    foreach ($form_state['values'] as $key => $value) {
      // Do not manipulate page variants here.
      if ($key != 'page_variants') {
        $entity->set($key, $value);
      }
    }
  }

}
