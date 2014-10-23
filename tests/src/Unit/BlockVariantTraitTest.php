<?php

/**
 * @file
 * Contains \Drupal\Tests\page_manager\Unit\BlockVariantTraitTest.
 */

namespace Drupal\Tests\page_manager\Unit;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\page_manager\Plugin\BlockPluginCollection;
use Drupal\page_manager\Plugin\BlockVariantTrait;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the methods of a block-based variant.
 *
 * @coversDefaultClass \Drupal\page_manager\Plugin\BlockVariantTrait
 *
 * @group Drupal
 * @group PageManager
 */
class BlockVariantTraitTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Tests the methods of a block-based variant',
      'description' => '',
      'group' => 'Page Manager',
    );
  }

  /**
   * Tests the getRegionAssignments() method.
   *
   * @covers ::getRegionAssignments
   *
   * @dataProvider providerTestGetRegionAssignments
   */
  public function testGetRegionAssignments($expected, $blocks = array()) {
    $block_collection = $this->getMockBuilder('Drupal\page_manager\Plugin\BlockPluginCollection')
      ->disableOriginalConstructor()
      ->getMock();
    $block_collection->expects($this->once())
      ->method('getAllByRegion')
      ->will($this->returnValue($blocks));

    $display_variant = new TestBlockVariantTrait();
    $display_variant->setBlockPluginCollection($block_collection);

    $this->assertSame($expected, $display_variant->getRegionAssignments());
  }

  public function providerTestGetRegionAssignments() {
    return array(
      array(
        array(
          'top' => array(),
          'bottom' => array(),
        ),
      ),
      array(
        array(
          'top' => array('foo'),
          'bottom' => array(),
        ),
        array(
          'top' => array('foo'),
        ),
      ),
      array(
        array(
          'top' => array(),
          'bottom' => array(),
        ),
        array(
          'invalid' => array('foo'),
        ),
      ),
      array(
        array(
          'top' => array(),
          'bottom' => array('foo'),
        ),
        array(
          'bottom' => array('foo'),
          'invalid' => array('bar'),
        ),
      ),
    );
  }

}

class TestBlockVariantTrait {
  use BlockVariantTrait;

  /**
   * @var array
   */
  protected $blockConfig = array();

  /**
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuidGenerator;

  /**
   * @param \Drupal\page_manager\Plugin\BlockPluginCollection $block_plugin_collection
   *
   * @return $this
   */
  public function setBlockPluginCollection(BlockPluginCollection $block_plugin_collection) {
    $this->blockPluginCollection = $block_plugin_collection;
    return $this;
  }

  /**
   * @param \Drupal\Component\Uuid\UuidInterface $uuid_generator
   *
   * @return $this
   */
  public function setUuidGenerator(UuidInterface $uuid_generator) {
    $this->uuidGenerator = $uuid_generator;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function uuidGenerator() {
    return $this->uuidGenerator;
  }

  /**
   * Sets the block configuration.
   *
   * @param array $config
   *   The block configuration.
   *
   * @return $this
   */
  public function setBlockConfig(array $config) {
    $this->blockConfig = $config;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function getBlockConfig() {
    return $this->blockConfig;
  }

}
