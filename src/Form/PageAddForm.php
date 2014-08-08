<?php

/**
 * @file
 * Contains \Drupal\page_manager\Form\PageAddForm.
 */

namespace Drupal\page_manager\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a form for adding a new page entity.
 */
class PageAddForm extends PageFormBase {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    parent::save($form, $form_state);
    drupal_set_message($this->t('The %label page has been added.', array('%label' => $this->entity->label())));
    $form_state->setRedirect('page_manager.page_edit', array(
      'page' => $this->entity->id(),
    ));
  }

}
