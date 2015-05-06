<?php

/**
 * @file
 * Contains \Drupal\page_manager\Form\StaticContextDeleteForm.
 */

namespace Drupal\page_manager\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\page_manager\PageInterface;
use Drupal\Core\Form\ConfirmFormBase;

/**
 * Provides a form for deleting an access condition.
 */
class StaticContextDeleteForm extends ConfirmFormBase {

  /**
   * The page entity this selection condition belongs to.
   *
   * @var \Drupal\page_manager\PageInterface
   */
  protected $page;

  /**
   * The static context settings array.
   *
   * @var array
   */
  protected $staticContext;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'page_manager_static_context_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the static context %name?', ['%name' => $this->staticContext['label']]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->page->urlInfo('edit-form');
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
  public function buildForm(array $form, FormStateInterface $form_state, PageInterface $page = NULL, $name = NULL) {
    $this->page = $page;
    $this->staticContext = $page->getStaticContext($name);
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->page->removeStaticContext($this->staticContext['machine_name']);
    $this->page->save();
    drupal_set_message($this->t('The static context %name has been removed.', ['%name' => $this->staticContext['label']]));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
