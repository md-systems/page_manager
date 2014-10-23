<?php

/**
 * @file
 * Contains \Drupal\Tests\page_manager\Unit\PageAccessTest.
 */

namespace Drupal\Tests\page_manager\Unit;

use Drupal\page_manager\Entity\PageAccess;
use Drupal\Tests\UnitTestCase;

/**
 * Tests access for Page entities.
 *
 * @coversDefaultClass \Drupal\page_manager\Entity\PageAccess
 *
 * @group Drupal
 * @group PageManager
 */
class PageAccessTest extends UnitTestCase {

  /**
   * The context handler.
   *
   * @var \Drupal\Core\Plugin\Context\ContextHandlerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $contextHandler;

  /**
   * @var \Drupal\Core\Entity\EntityTypeInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $entityType;

  /**
   * @var \Drupal\Core\Entity\EntityAccessControlHandlerInterface
   */
  protected $pageAccess;

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Tests access for Page entities',
      'description' => '',
      'group' => 'Page Manager',
    );
  }

  /**
   * @covers ::__construct
   */
  public function setUp() {
    parent::setUp();
    $this->contextHandler = $this->getMock('Drupal\Core\Plugin\Context\ContextHandlerInterface');
    $this->entityType = $this->getMock('Drupal\Core\Entity\EntityTypeInterface');

    $module_handler = $this->getMock('Drupal\Core\Extension\ModuleHandlerInterface');
    $module_handler->expects($this->any())
      ->method('invokeAll')
      ->will($this->returnValue(array()));

    $this->pageAccess = new PageAccess($this->entityType, $this->contextHandler);
    $this->pageAccess->setModuleHandler($module_handler);
  }

  /**
   * @covers ::checkAccess
   */
  public function testAccessView() {
    $executable = $this->getMock('Drupal\page_manager\PageExecutableInterface');
    $executable->expects($this->once())
      ->method('getContexts')
      ->will($this->returnValue(array()));

    $page = $this->getMock('Drupal\page_manager\PageInterface');
    $page->expects($this->once())
      ->method('getExecutable')
      ->will($this->returnValue($executable));
    $page->expects($this->once())
      ->method('getAccessConditions')
      ->will($this->returnValue(array()));
    $page->expects($this->once())
      ->method('getAccessLogic')
      ->will($this->returnValue('and'));
    $page->expects($this->once())
      ->method('status')
      ->will($this->returnValue(TRUE));

    $account = $this->getMock('Drupal\Core\Session\AccountInterface');

    $this->assertTrue($this->pageAccess->access($page, 'view', NULL, $account));
  }

  /**
   * @covers ::checkAccess
   */
  public function testAccessViewDisabled() {
    $page = $this->getMock('Drupal\page_manager\PageInterface');
    $page->expects($this->once())
      ->method('status')
      ->will($this->returnValue(FALSE));
    $account = $this->getMock('Drupal\Core\Session\AccountInterface');

    $page->expects($this->once())
      ->method('getCacheTags')
      ->willReturn(['page:1']);

    $this->assertFalse($this->pageAccess->access($page, 'view', NULL, $account));
  }

  /**
   * @covers ::checkAccess
   *
   * @dataProvider providerTestAccessDelete
   */
  public function testAccessDelete($is_new, $is_fallback, $expected) {
    $this->entityType->expects($this->any())
      ->method('getAdminPermission')
      ->will($this->returnValue('test permission'));

    $page = $this->getMock('Drupal\page_manager\PageInterface');
    $page->expects($this->any())
      ->method('isNew')
      ->will($this->returnValue($is_new));
    $page->expects($this->any())
      ->method('isFallbackPage')
      ->will($this->returnValue($is_fallback));

    // Ensure that the cache tag is added for the temporary conditions.
    if ($is_new || $is_fallback) {
      $page->expects($this->once())
        ->method('getCacheTags')
        ->willReturn(['page:1']);
    }

    $account = $this->getMock('Drupal\Core\Session\AccountInterface');
    $account->expects($this->any())
      ->method('hasPermission')
      ->with('test permission')
      ->will($this->returnValue(TRUE));

    $this->assertSame($expected, $this->pageAccess->access($page, 'delete', NULL, $account));
  }

  /**
   * Provides data for testAccessDelete().
   */
  public function providerTestAccessDelete() {
    $data = array();
    $data[] = array(TRUE, FALSE, FALSE);
    $data[] = array(FALSE, TRUE, FALSE);
    $data[] = array(TRUE, TRUE, FALSE);
    $data[] = array(FALSE, FALSE, TRUE);
    return $data;
  }

}
