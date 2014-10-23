<?php

/**
 * @file
 * Contains \Drupal\Tests\page_manager\Unit\BlockPluginCollectionTest.
 */

namespace Drupal\Tests\page_manager\Unit;

use Drupal\page_manager\Plugin\BlockPluginCollection;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the block plugin collection.
 *
 * @coversDefaultClass \Drupal\page_manager\Plugin\BlockPluginCollection
 *
 * @group Drupal
 * @group PageManager
 */
class BlockPluginCollectionTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Block plugin collection',
      'description' => '',
      'group' => 'Page Manager',
    );
  }

  /**
   * Tests the getAllByRegion() method.
   *
   * @covers ::getAllByRegion
   */
  public function testGetAllByRegion() {
    $blocks = array(
      'foo' => array(
        'id' => 'foo',
        'label' => 'Foo',
        'plugin' => 'system_powered_by_block',
        'region' => 'bottom',
      ),
      'bar' => array(
        'id' => 'bar',
        'label' => 'Bar',
        'plugin' => 'system_powered_by_block',
        'region' => 'top',
      ),
      'bing' => array(
        'id' => 'bing',
        'label' => 'Bing',
        'plugin' => 'system_powered_by_block',
        'region' => 'bottom',
        'weight' => -10,
      ),
      'baz' => array(
        'id' => 'baz',
        'label' => 'Baz',
        'plugin' => 'system_powered_by_block',
        'region' => 'bottom',
      ),
    );
    $plugins = array();
    $plugin_map = array();
    foreach ($blocks as $block_id => $block) {
      $plugin = $this->getMock('Drupal\Core\Block\BlockPluginInterface');
      $plugin->expects($this->any())
        ->method('label')
        ->will($this->returnValue($block['label']));
      $plugin->expects($this->any())
        ->method('getConfiguration')
        ->will($this->returnValue($block));
      $plugins[$block_id] = $plugin;
      $plugin_map[] = array($block_id, $block, $plugin);
    }
    $block_manager = $this->getMock('Drupal\Core\Block\BlockManagerInterface');
    $block_manager->expects($this->exactly(4))
      ->method('createInstance')
      ->will($this->returnValueMap($plugin_map));

    $block_plugin_collection = new BlockPluginCollection($block_manager, $blocks);
    $expected = array(
      'bottom' => array(
        'bing' => $plugins['bing'],
        'baz' => $plugins['baz'],
        'foo' => $plugins['foo'],
      ),
      'top' => array(
        'bar' => $plugins['bar'],
      ),
    );
    $this->assertSame($expected, $block_plugin_collection->getAllByRegion());
  }

}
