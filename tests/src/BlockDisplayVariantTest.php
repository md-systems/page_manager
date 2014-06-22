<?php

/**
 * @file
 * Contains \Drupal\page_manager\Tests\BlockDisplayVariantTest.
 */

namespace Drupal\page_manager\Tests;

use Drupal\block\BlockPluginInterface;
use Drupal\Component\Plugin\ContextAwarePluginInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the block display variant plugin.
 *
 * @coversDefaultClass \Drupal\page_manager\Plugin\DisplayVariant\BlockDisplayVariant
 *
 * @group Drupal
 * @group PageManager
 */
class BlockDisplayVariantTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Page Manager block display variant',
      'description' => '',
      'group' => 'Page Manager',
    );
  }

  /**
   * Tests the access() method.
   *
   * @covers ::access
   */
  public function testAccess() {
    $display_variant = $this->getMockBuilder('Drupal\page_manager\Plugin\DisplayVariant\BlockDisplayVariant')
      ->disableOriginalConstructor()
      ->setMethods(NULL)
      ->getMock();
    $this->assertSame(FALSE, $display_variant->access());

    $display_variant = $this->getMockBuilder('Drupal\page_manager\Plugin\DisplayVariant\BlockDisplayVariant')
      ->disableOriginalConstructor()
      ->setMethods(array('getBlockBag', 'getSelectionConditions'))
      ->getMock();
    $display_variant->setConfiguration(array('blocks' => array('foo' => array())));
    $display_variant->expects($this->once())
      ->method('getSelectionConditions')
      ->will($this->returnValue(array()));
    $this->assertSame(TRUE, $display_variant->access());
  }

  /**
   * Tests the render() method.
   *
   * @covers ::render
   */
  public function testRender() {
    $block1 = $this->getMock('Drupal\block\BlockPluginInterface');
    $block1->expects($this->once())
      ->method('access')
      ->will($this->returnValue(TRUE));
    $block1->expects($this->once())
      ->method('build')
      ->will($this->returnValue(array(
        '#markup' => 'block1_build_value',
      )));
    $block1->expects($this->once())
      ->method('getConfiguration')
      ->will($this->returnValue(array('label' => 'Block label')));
    $block2 = $this->getMock('Drupal\page_manager\Tests\TestContextAwareBlockPluginInterface');
    $block2->expects($this->once())
      ->method('access')
      ->will($this->returnValue(FALSE));
    $block2->expects($this->never())
      ->method('build');
    $blocks = array(
      'top' => array(
        'block1' => $block1,
        'block2' => $block2,
      ),
    );
    $block_bag = $this->getMockBuilder('Drupal\page_manager\Plugin\BlockPluginBag')
      ->disableOriginalConstructor()
      ->getMock();
    $block_bag->expects($this->once())
      ->method('getAllByRegion')
      ->will($this->returnValue($blocks));

    $context_handler = $this->getMock('Drupal\Core\Plugin\Context\ContextHandlerInterface');
    $context_handler->expects($this->once())
      ->method('applyContextMapping')
      ->with($block2, array());
    $account = $this->getMock('Drupal\Core\Session\AccountInterface');
    $uuid_generator = $this->getMock('Drupal\Component\Uuid\UuidInterface');
    $display_variant = $this->getMockBuilder('Drupal\page_manager\Plugin\DisplayVariant\BlockDisplayVariant')
      ->setConstructorArgs(array(array(), 'test', array(), $context_handler, $account, $uuid_generator))
      ->setMethods(array('getBlockBag', 'drupalHtmlClass'))
      ->getMock();
    $display_variant->expects($this->exactly(1))
      ->method('drupalHtmlClass')
      ->will($this->returnArgument(0));
    $display_variant->expects($this->once())
      ->method('getBlockBag')
      ->will($this->returnValue($block_bag));

    $expected_render = array(
      'top' => array(
        '#prefix' => '<div class="block-region-top">',
        '#suffix' => '</div>',
        'block1' => array(
          '#theme' => 'block',
          '#attributes' => array(),
          '#weight' => 0,
          '#configuration' => array(
            'label' => 'Block label'
          ),
          '#plugin_id' => null,
          'content' => array(
            '#markup' => 'block1_build_value',
          ),
        ),
      ),
    );
    $this->assertSame($expected_render, $display_variant->render());
  }

  /**
   * Tests the submitConfigurationForm() method.
   *
   * @covers ::submitConfigurationForm
   *
   * @dataProvider providerTestSubmitConfigurationForm
   */
  public function testSubmitConfigurationForm($values, $update_block_count) {
    $display_variant = $this->getMockBuilder('Drupal\page_manager\Plugin\DisplayVariant\BlockDisplayVariant')
      ->disableOriginalConstructor()
      ->setMethods(array('updateBlock'))
      ->getMock();
    $display_variant->expects($update_block_count)
      ->method('updateBlock');

    $form = array();
    $form_state['values'] = $values;
    $display_variant->submitConfigurationForm($form, $form_state);
    $this->assertSame($values['label'], $display_variant->label());
  }

  /**
   * Provides data for testSubmitConfigurationForm().
   */
  public function providerTestSubmitConfigurationForm() {
    $data = array();
    $data[] = array(
      array(
        'label' => 'test_label1',
      ),
      $this->never(),
    );
    $data[] = array(
      array(
        'label' => 'test_label2',
        'blocks' => array('foo1' => array()),
      ),
      $this->once(),
    );
    $data[] = array(
      array(
        'label' => 'test_label3',
        'blocks' => array('foo1' => array(), 'foo2' => array()),
      ),
      $this->exactly(2),
    );
    return $data;
  }

}

interface TestContextAwareBlockPluginInterface extends ContextAwarePluginInterface, BlockPluginInterface {
}
