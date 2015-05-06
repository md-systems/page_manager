<?php

/**
 * @file
 * Contains \Drupal\page_manager\Form\StaticContextEditForm.
 */

namespace Drupal\page_manager\Form;

use Drupal\Core\Condition\ConditionManager;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for adding a new static context.
 */
class StaticContextEditForm extends StaticContextFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'page_manager_static_context_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function submitButtonText() {
    return $this->t('Update Static Context');
  }

  /**
   * {@inheritdoc}
   */
  protected function submitMessageText() {
    return $this->t('The %label static context has been updated.', ['%label' => $this->staticContext['label']]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $input = $form_state->getValue('selection');
    $entity_type = $form_state->getValue('entity_type');
    $entity = $this->getEntityFromSelection($entity_type, $input);

    $old_name = $this->staticContext['machine_name'];

    $this->staticContext = [
      'machine_name' => $form_state->getValue('machine_name'),
      'label' => $form_state->getValue('label'),
      'type' => 'entity:' . $entity_type,
      'value' => $entity->uuid(),
    ];

    $this->page->updateStaticContext($old_name, $this->staticContext);
    $this->page->save();

    // Set the submission message.
    drupal_set_message($this->submitMessageText());

    $form_state->setRedirectUrl($this->page->urlInfo('edit-form'));
  }

}
