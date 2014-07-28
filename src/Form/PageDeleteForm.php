<?php

/**
 * @file
 * Contains \Drupal\page_manager\Form\PageDeleteForm.
 */

namespace Drupal\page_manager\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Url;

/**
 * Provides a form for deleting a page entity.
 */
class PageDeleteForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the page %name?', array('%name' => $this->entity->label()));
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('page_manager.page_list');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array $form, array &$form_state) {
    $this->entity->delete();
    drupal_set_message($this->t('The page %name has been removed.', array('%name' => $this->entity->label())));
    $form_state['redirect_route'] = $this->getCancelUrl();
  }

}
