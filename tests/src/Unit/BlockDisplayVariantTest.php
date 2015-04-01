<?php

/**
 * @file
 * Contains \Drupal\Tests\page_manager\Unit\BlockDisplayVariantTest.
 */

namespace Drupal\Tests\page_manager\Unit;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Form\FormState;
use Drupal\page_manager\PageExecutable;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

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
      ->setMethods(['determineSelectionAccess'])
      ->getMock();
    $display_variant->expects($this->once())
      ->method('determineSelectionAccess')
      ->willReturn(FALSE);
    $this->assertSame(FALSE, $display_variant->access());

    $display_variant = $this->getMockBuilder('Drupal\page_manager\Plugin\DisplayVariant\BlockDisplayVariant')
      ->disableOriginalConstructor()
      ->setMethods(['determineSelectionAccess'])
      ->getMock();
    $display_variant->expects($this->once())
      ->method('determineSelectionAccess')
      ->willReturn(TRUE);
    $this->assertSame(TRUE, $display_variant->access());
  }

  /**
   * Tests the build() method.
   *
   * @covers ::build
   */
  public function testBuildNoCache() {
    $container = new ContainerBuilder();
    $cache_contexts = $this
      ->getMockBuilder('Drupal\Core\Cache\CacheContexts')
      ->disableOriginalConstructor()
      ->getMock();
    $container->set('cache_contexts', $cache_contexts);
    \Drupal::setContainer($container);

    $block1 = $this->getMock('Drupal\Core\Block\BlockPluginInterface');
    $block1->expects($this->once())
      ->method('access')
      ->will($this->returnValue(TRUE));
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
      ->will($this->returnValue(FALSE));
    $block1->expects($this->atLeastOnce())
      ->method('getCacheTags')
      ->willReturn(array('block_plugin:block_plugin_id'));
    $block2->expects($this->never())
      ->method('getCacheTags')
      ->willReturn(array('block_plugin:block_plugin_id'));
    $block1->expects($this->once())
      ->method('getCacheMaxAge')
      ->willReturn(3600);
    $block1->expects($this->once())
      ->method('getCacheContexts')
      ->willReturn(['url']);
    $block2->expects($this->never())
      ->method('getCacheContexts');
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
      ->setConstructorArgs([['page_title' => $page_title], 'test', array(), $context_handler, $account, $uuid_generator, $token])
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
                0 => 'block_plugin:block_plugin_id',
                1 => 'page:page_id',
              ],
              'contexts' => [
                0 => 'url',
              ],
              'max-age' => 3600,
            ],
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
   * Tests the build() method when blocks can be cached.
   *
   * @covers ::build
   */
  public function testBuildCache() {
    $container = new ContainerBuilder();
    $cache_contexts = $this
      ->getMockBuilder('Drupal\Core\Cache\CacheContexts')
      ->disableOriginalConstructor()
      ->getMock();
    $container->set('cache_contexts', $cache_contexts);
    \Drupal::setContainer($container);

    $block1 = $this->getMock('Drupal\Core\Block\BlockPluginInterface');
    $block1->expects($this->once())
      ->method('access')
      ->will($this->returnValue(TRUE));
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
      ->will($this->returnValue(TRUE));
    $block1->expects($this->once())
      ->method('isCacheable')
      ->willReturn(TRUE);
    $block2->expects($this->once())
      ->method('isCacheable')
      ->willReturn(TRUE);
    $block1->expects($this->once())
      ->method('getCacheKeys')
      ->willReturn([]);
    $block2->expects($this->once())
      ->method('getCacheKeys')
      ->willReturn([]);
    $block1->expects($this->once())
      ->method('getCacheContexts')
      ->willReturn(['url']);
    $block2->expects($this->once())
      ->method('getCacheContexts')
      ->willReturn([]);
    $block1->expects($this->once())
      ->method('getCacheMaxAge')
      ->willReturn(3600);
    $block2->expects($this->once())
      ->method('getCacheMaxAge')
      ->willReturn(Cache::PERMANENT);
    $block1->expects($this->atLeastOnce())
      ->method('getCacheTags')
      ->willReturn(array('block_plugin1:block_plugin_id'));
    $block2->expects($this->atLeastOnce())
      ->method('getCacheTags')
      ->willReturn(array('block_plugin2:block_plugin_id'));
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
      ->setConstructorArgs([['page_title' => $page_title, 'uuid' => 'UUID'], 'test', [], $context_handler, $account, $uuid_generator, $token])
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

    $expected_cache_block1 = [
      'tags' => ['block_plugin1:block_plugin_id', 'page:page_id'],
      'contexts' => ['url'],
      'max-age' => 3600,
      'keys' => ['page_manager_page', 'page_id', 'block', 'block1'],
    ];
    $expected_cache_block2 = [
      'tags' => ['block_plugin2:block_plugin_id', 'page:page_id'],
      'contexts' => [],
      'max-age' => Cache::PERMANENT,
      'keys' => ['page_manager_page', 'page_id', 'block', 'block2'],
    ];

    $expected_cache_page = [
      'keys' => ['page_manager_page', 'page_id', 'UUID', 'block1', 'block2'],
      'contexts' => ['url'],
      'max-age' => 3600,
    ];

    $build = $display_variant->build();
    $this->assertSame([$display_variant, 'buildBlock'], $build['regions']['top']['block1']['#pre_render'][0]);
    $this->assertSame([$display_variant, 'buildBlock'], $build['regions']['top']['block2']['#pre_render'][0]);
    $this->assertSame($expected_cache_block1, $build['regions']['top']['block1']['#cache']);
    $this->assertSame($expected_cache_block2, $build['regions']['top']['block2']['#cache']);
    $this->assertSame($expected_cache_page, $build['regions']['#cache']);

    $block1->expects($this->once())
      ->method('build')
      ->will($this->returnValue([
        '#markup' => 'block1_build_value',
      ]));

    $block1_build = $display_variant->buildBlock($build['regions']['top']['block1']);
    $block2_build = $display_variant->buildBlock($build['regions']['top']['block2']);
    $this->assertSame(['#markup' => 'block1_build_value'], $block1_build['content']);
    $this->assertSame(['#markup' => '', '#cache' => $expected_cache_block2], $block2_build);
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
