<?php

/**
 * @file
 * Contains \Drupal\page_manager\Form\StaticContextAddForm.
 */

namespace Drupal\page_manager\Form;

use Drupal\Core\Condition\ConditionManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for adding a new static context.
 */
class StaticContextAddForm extends StaticContextFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'page_manager_static_context_add_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function submitButtonText() {
    return $this->t('Add Static Context');
  }

  /**
   * {@inheritdoc}
   */
  protected function submitMessageText() {
    return $this->t('The %label static context has been added.', ['%label' => $this->staticContext['label']]);
  }

}
