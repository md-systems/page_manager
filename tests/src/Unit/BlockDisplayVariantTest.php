<?php

/**
 * @file
 * Contains \Drupal\Tests\page_manager\Unit\BlockDisplayVariantTest.
 */

namespace Drupal\Tests\page_manager\Unit;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Form\FormState;
use Drupal\page_manager\PageExecutable;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the block display variant plugin.
 *
 * @coversDefaultClass \Drupal\page_manager\Plugin\DisplayVariant\BlockDisplayVariant
 *
 * @group PageManager
 */
class BlockDisplayVariantTest extends UnitTestCase {

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
      ->setMethods(['getBlockCollection', 'getSelectionConditions'])
      ->getMock();
    $display_variant->setConfiguration(['blocks' => ['foo' => []]]);
    $display_variant->expects($this->once())
      ->method('getSelectionConditions')
      ->will($this->returnValue([]));
    $this->assertSame(TRUE, $display_variant->access());
  }

  /**
   * Tests the build() method.
   *
   * @covers ::build
   */
  public function testBuild() {
    $block1 = $this->getMock('Drupal\Core\Block\BlockPluginInterface');
    $block1->expects($this->once())
      ->method('access')
      ->will($this->returnValue(AccessResult::allowed()));
    $block1->expects($this->once())
      ->method('build')
      ->will($this->returnValue([
        '#markup' => 'block1_build_value',
      ]));
    $block1->expects($this->once())
      ->method('getConfiguration')
      ->will($this->returnValue(['label' => 'Block label']));
    $block1->expects($this->once())
      ->method('getPluginId')
      ->will($this->returnValue('block_plugin_id'));
    $block1->expects($this->once())
      ->method('getBaseId')
      ->will($this->returnValue('block_base_plugin_id'));
    $block1->expects($this->once())
      ->method('getDerivativeId')
      ->will($this->returnValue('block_derivative_plugin_id'));
    $block2 = $this->getMock('Drupal\Tests\page_manager\Unit\TestContextAwareBlockPluginInterface');
    $block2->expects($this->once())
      ->method('access')
      ->will($this->returnValue(AccessResult::forbidden()));
    $block1->expects($this->atLeastOnce())
      ->method('getCacheTags')
      ->willReturn(array('block_plugin:block_plugin_id'));
    $block2->expects($this->never())
      ->method('getCacheTags')
      ->willReturn(array('block_plugin:block_plugin_id'));
    $block2->expects($this->never())
      ->method('build');
    $blocks = [
      'top' => [
        'block1' => $block1,
        'block2' => $block2,
      ],
    ];
    $block_collection = $this->getMockBuilder('Drupal\page_manager\Plugin\BlockPluginCollection')
      ->disableOriginalConstructor()
      ->getMock();
    $block_collection->expects($this->once())
      ->method('getAllByRegion')
      ->will($this->returnValue($blocks));

    $language = $this->getMock('Drupal\Core\Language\LanguageInterface');
    $language->expects($this->atLeastOnce())
      ->method('getId')
      ->willReturn('en');
    $language_manager = $this->getMock('Drupal\Core\Language\LanguageManagerInterface');
    $language_manager->expects($this->atLeastOnce())
      ->method('getCurrentLanguage')
      ->willReturn($language);

    $context_handler = $this->getMock('Drupal\Core\Plugin\Context\ContextHandlerInterface');
    $context_handler->expects($this->once())
      ->method('applyContextMapping')
      ->with($block2, []);
    $account = $this->getMock('Drupal\Core\Session\AccountInterface');
    $uuid_generator = $this->getMock('Drupal\Component\Uuid\UuidInterface');
    $page_title = 'Page title';
    $token = $this->getMockBuilder('Drupal\Core\Utility\Token')
      ->disableOriginalConstructor()
      ->getMock();
    $display_variant = $this->getMockBuilder('Drupal\page_manager\Plugin\DisplayVariant\BlockDisplayVariant')
      ->setConstructorArgs([['page_title' => $page_title], 'test', array(), $context_handler, $account, $uuid_generator, $token, $language_manager])
      ->setMethods(array('getBlockCollection', 'drupalHtmlClass', 'renderPageTitle'))
      ->getMock();

    $page = $this->getMock('\Drupal\page_manager\PageInterface');
    $page->expects($this->atLeastOnce())
      ->method('id')
      ->willReturn('page_id');
    $page->expects($this->atLeastOnce())
      ->method('getCacheTags')
      ->willReturn(array('page:page_id'));
    $page_executable = new PageExecutable($page);
    $display_variant->setExecutable($page_executable);

    $display_variant->expects($this->once())
      ->method('getBlockCollection')
      ->will($this->returnValue($block_collection));
    $display_variant->expects($this->once())
      ->method('renderPageTitle')
      ->with($page_title)
      ->will($this->returnValue($page_title));

    $expected_build = [
      'regions' => [
        'top' => [
          '#prefix' => '<div class="block-region-top">',
          '#suffix' => '</div>',
          'block1' => [
            '#theme' => 'block',
            '#attributes' => [],
            '#weight' => 0,
            '#configuration' => [
              'label' => 'Block label'
            ],
            '#plugin_id' => 'block_plugin_id',
            '#base_plugin_id' => 'block_base_plugin_id',
            '#derivative_plugin_id' => 'block_derivative_plugin_id',
            '#cache' => [
              'tags' => [
                0 => 'page:page_id',
                1 => 'block_plugin:block_plugin_id',
              ],
            ],
            '#contextual_links' => [],
            'content' => [
              '#markup' => 'block1_build_value',
            ],
          ],
        ],
      ],
      '#title' => 'Page title',
    ];
    $this->assertSame($expected_build, $display_variant->build());
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
      ->setMethods(['updateBlock'])
      ->getMock();
    $display_variant->expects($update_block_count)
      ->method('updateBlock');

    $form = [];
    $form_state = (new FormState())->setValues($values);
    $display_variant->submitConfigurationForm($form, $form_state);
    $this->assertSame($values['label'], $display_variant->label());
  }

  /**
   * Provides data for testSubmitConfigurationForm().
   */
  public function providerTestSubmitConfigurationForm() {
    $data = [];
    $data[] = [
      [
        'label' => 'test_label1',
      ],
      $this->never(),
    ];
    $data[] = [
      [
        'label' => 'test_label2',
        'blocks' => ['foo1' => []],
      ],
      $this->once(),
    ];
    $data[] = [
      [
        'label' => 'test_label3',
        'blocks' => ['foo1' => [], 'foo2' => []],
      ],
      $this->exactly(2),
    ];
    return $data;
  }

}

interface TestContextAwareBlockPluginInterface extends ContextAwarePluginInterface, BlockPluginInterface {
}
