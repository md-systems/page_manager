<?php

/**
 * @file
 * Contains \Drupal\Tests\page_manager\Unit\VariantAwareTraitTest.
 */

namespace Drupal\Tests\page_manager\Unit;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\page_manager\Plugin\VariantAwareTrait;
use Drupal\page_manager\Plugin\VariantCollection;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the methods of a variant-aware class.
 *
 * @coversDefaultClass \Drupal\page_manager\Plugin\VariantAwareTrait
 *
 * @group Drupal
 * @group PageManager
 */
class VariantAwareTraitTest extends UnitTestCase {

  /**
   * @var \Drupal\Component\Plugin\PluginManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $manager;

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Tests the methods of a variant-aware class',
      'description' => '',
      'group' => 'Page Manager',
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $container = new ContainerBuilder();
    $this->manager = $this->getMock('Drupal\Component\Plugin\PluginManagerInterface');
    $container->set('plugin.manager.display_variant', $this->manager);
    \Drupal::setContainer($container);
  }

  /**
   * @covers ::getVariants
   */
  public function testGetVariantsEmpty() {
    $trait_object = new TestVariantAwareTrait();
    $this->manager->expects($this->never())
      ->method('createInstance');

    $variants = $trait_object->getVariants();
    $this->assertInstanceOf('Drupal\page_manager\Plugin\VariantCollection', $variants);
    $this->assertSame(0, $variants->count());
  }

  /**
   * @covers ::getVariants
   */
  public function testGetVariants() {
    $trait_object = new TestVariantAwareTrait();
    $config = array(
      'foo' => array('id' => 'foo_plugin'),
      'bar' => array('id' => 'bar_plugin'),
    );
    $plugin = $this->getMock('Drupal\Core\Display\VariantInterface');
    $map = array();
    foreach ($config as $value) {
      $map[] = array($value['id'], $value, $plugin);
    }
    $this->manager->expects($this->exactly(2))
      ->method('createInstance')
      ->will($this->returnValueMap($map));
    $trait_object->setVariantConfig($config);

    $variants = $trait_object->getVariants();
    $this->assertInstanceOf('Drupal\page_manager\Plugin\VariantCollection', $variants);
    $this->assertSame(2, $variants->count());
    return $variants;
  }

  /**
   * @covers ::getVariants
   *
   * @depends testGetVariants
   */
  public function testGetVariantsSort(VariantCollection $variants) {
    $this->assertSame(array('bar' => 'bar', 'foo' => 'foo'), $variants->getInstanceIds());
  }

  /**
   * @covers ::addVariant
   */
  public function testAddVariant() {
    $config = array('id' => 'foo');
    $uuid = 'test-uuid';
    $expected_config = $config + array('uuid' => $uuid);

    $uuid_generator = $this->getMock('Drupal\Component\Uuid\UuidInterface');
    $uuid_generator->expects($this->once())
      ->method('generate')
      ->will($this->returnValue($uuid));
    $trait_object = new TestVariantAwareTrait();
    $trait_object->setUuidGenerator($uuid_generator);

    $plugin = $this->getMock('Drupal\Core\Display\VariantInterface');
    $plugin->expects($this->once())
      ->method('getConfiguration')
      ->will($this->returnValue($expected_config));
    $this->manager->expects($this->any())
      ->method('createInstance')
      ->with('foo', $expected_config)
      ->will($this->returnValue($plugin));

    $resulting_uuid = $trait_object->addVariant($config);
    $this->assertSame($uuid, $resulting_uuid);

    $variants = $trait_object->getVariants();
    $this->assertSame(array($uuid => $uuid), $variants->getInstanceIds());
    $this->assertSame(array($uuid => $expected_config), $variants->getConfiguration());
    $this->assertSame($plugin, $variants->get($uuid));
    return array($trait_object, $uuid, $plugin);
  }

  /**
   * @covers ::getVariant
   *
   * @depends testAddVariant
   */
  public function testGetVariant($data) {
    list($trait_object, $uuid, $plugin) = $data;
    $this->manager->expects($this->never())
      ->method('createInstance');

    $this->assertSame($plugin, $trait_object->getVariant($uuid));
    return array($trait_object, $uuid);
  }

  /**
   * @covers ::removeVariant
   *
   * @depends testGetVariant
   */
  public function testRemoveVariant($data) {
    list($trait_object, $uuid) = $data;

    $this->assertSame($trait_object, $trait_object->removeVariant($uuid));
    $this->assertFalse($trait_object->getVariants()->has($uuid));
    return array($trait_object, $uuid);
  }

  /**
   * @covers ::getVariant
   *
   * @depends testRemoveVariant
   *
   * @expectedException \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @expectedExceptionMessage Plugin ID 'test-uuid' was not found.
   */
  public function testGetVariantException($data) {
    list($trait_object, $uuid) = $data;
    // Attempt to retrieve a variant that has been removed.
    $this->assertNull($trait_object->getVariant($uuid));
  }

}

class TestVariantAwareTrait {
  use VariantAwareTrait;

  /**
   * @var array
   */
  protected $variantConfig = array();

  /**
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuidGenerator;

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
   * Sets the variant configuration.
   *
   * @param array $config
   *   The variant configuration.
   *
   * @return $this
   */
  public function setVariantConfig(array $config) {
    $this->variantConfig = $config;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function getVariantConfig() {
    return $this->variantConfig;
  }

}
