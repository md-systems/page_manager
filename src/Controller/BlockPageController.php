<?php

/**
 * @file
 * Contains \Drupal\block_page\Controller\BlockPageController.
 */

namespace Drupal\block_page\Controller;

use Drupal\block_page\BlockPageInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;

/**
 * Provides route controllers for block page.
 */
class BlockPageController extends ControllerBase {

  /**
   * Route title callback.
   *
   * @param \Drupal\block_page\BlockPageInterface $block_page
   *   The block page.
   *
   * @return string
   *   The title for the block page edit form.
   */
  public function editBlockPageTitle(BlockPageInterface $block_page) {
    return $this->t('Edit %label block page', array('%label' => $block_page->label()));
  }

  /**
   * Route title callback.
   *
   * @param \Drupal\block_page\BlockPageInterface $block_page
   *   The block page.
   * @param string $page_variant_id
   *   The page variant.
   *
   * @return string
   *   The title for the page variant edit form.
   */
  public function editPageVariantTitle(BlockPageInterface $block_page, $page_variant_id) {
    $page_variant_id = $block_page->getPageVariant($page_variant_id);
    return $this->t('Edit %label page variant', array('%label' => $page_variant_id->label()));
  }

  /**
   * Route title callback.
   *
   * @param \Drupal\block_page\BlockPageInterface $block_page
   *   The block page.
   * @param string $access_condition_id
   *   The access condition ID.
   *
   * @return string
   *   The title for the access condition edit form.
   */
  public function editAccessConditionTitle(BlockPageInterface $block_page, $access_condition_id) {
    $access_condition = $block_page->getAccessCondition($access_condition_id);
    return $this->t('Edit %label access condition', array('%label' => $access_condition->getPluginDefinition()['label']));
  }

  /**
   * Presents a list of access conditions to add to the block page.
   *
   * @param \Drupal\block_page\BlockPageInterface $block_page
   *   The block page.
   *
   * @return array
   *   The access condition selection page.
   */
  public function selectAccessCondition(BlockPageInterface $block_page) {
    $build = array(
      '#theme' => 'links',
      '#links' => array(),
    );
    $condition_manager = \Drupal::service('plugin.manager.condition');
    foreach ($condition_manager->getDefinitions() as $access_id => $access_condition) {
      $build['#links'][$access_id] = array(
        'title' => $access_condition['label'],
        'route_name' => 'block_page.access_condition_add',
        'route_parameters' => array(
          'block_page' => $block_page->id(),
          'access_condition_id' => $access_id,
        ),
        'attributes' => array(
          'class' => array('use-ajax'),
          'data-accepts' => 'application/vnd.drupal-modal',
          'data-dialog-options' => Json::encode(array(
            'width' => 'auto',
          )),
        )
      );
    }
    return $build;
  }

}
