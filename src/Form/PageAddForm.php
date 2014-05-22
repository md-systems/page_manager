<?php

/**
 * @file
 * Contains \Drupal\page_manager\Form\PageAddForm.
 */

namespace Drupal\page_manager\Form;

use Drupal\Core\Url;

/**
 * Provides a form for adding a new page entity.
 */
class PageAddForm extends PageFormBase {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, array &$form_state) {
    parent::save($form, $form_state);
    drupal_set_message($this->t('The %label page has been added.', array('%label' => $this->entity->label())));
    $form_state['redirect_route'] = new Url('page_manager.page_edit', array(
      'page' => $this->entity->id(),
    ));
  }

}