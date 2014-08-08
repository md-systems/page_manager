<?php

/**
 * @file
 * Contains \Drupal\page_manager\Tests\PageConfigSchemaTest.
 */

namespace Drupal\page_manager\Tests;

use Drupal\config\Tests\SchemaCheckTestTrait;
use Drupal\page_manager\Entity\Page;
use Drupal\simpletest\KernelTestBase;

/**
 * Ensures that page entities have valid config schema.
 */
class PageConfigSchemaTest extends KernelTestBase {

  use SchemaCheckTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = array('page_manager', 'block', 'node');

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Page config schema',
      'description' => 'Ensures that page entities have valid config schema.',
      'group' => 'Page Manager',
    );
  }

  /**
   * Tests whether the page entity config schema is valid.
   */
  function testValidPageConfigSchema() {
    $id = strtolower($this->randomMachineName());
    /** @var $page \Drupal\page_manager\PageInterface */
    $page = Page::create(array(
      'id' => $id,
      'label' => $this->randomMachineName(),
      'path' => '/node/{node}',
    ));

    // Add an access condition.
    $page->addAccessCondition(array(
      'id' => 'node_type',
      'bundles' => array(
        'article' => 'article',
      ),
      'negate' => TRUE,
      'context_mapping' => array(
        'node' => 'node',
      ),
    ));

    // Add a block display variant.
    $display_variant_id = $page->addVariant(array(
      'id' => 'block_page',
      'label' => 'Block page',
    ));
    /** @var $display_variant \Drupal\page_manager\Plugin\DisplayVariant\BlockDisplayVariant */
    $display_variant = $page->getVariant($display_variant_id);

    // Add a selection condition.
    $display_variant->addSelectionCondition(array(
      'id' => 'node_type',
      'bundles' => array(
        'page' => 'page',
      ),
      'context_mapping' => array(
        'node' => 'node',
      ),
    ));

    // Add a block.
    $display_variant->addBlock(array(
      'id' => 'entity_view:node',
      'label' => 'View the node',
      'provider' => 'page_manager',
      'label_display' => 'visible',
      'view_mode' => 'default',
    ));
    $page->save();

    $config = \Drupal::config("page_manager.page.$id");
    $this->assertEqual($config->get('id'), $id);
    $this->assertConfigSchema(\Drupal::service('config.typed'), $config->getName(), $config->get());
  }

}
