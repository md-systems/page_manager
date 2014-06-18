<?php

/**
 * @file
 * Contains \Drupal\page_manager\Tests\PageManagerAdminTest.
 */

namespace Drupal\page_manager\Tests;

use Drupal\Component\Utility\String;
use Drupal\page_manager\Entity\Page;
use Drupal\simpletest\WebTestBase;

/**
 * Tests the admin UI for page entities.
 */
class PageManagerAdminTest extends WebTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = array('page_manager');

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Page Manager admin test',
      'description' => 'Tests the admin UI for page entities.',
      'group' => 'Page Manager',
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    \Drupal::service('theme_handler')->enable(array('bartik'));
    \Drupal::config('system.theme')->set('admin', 'stark')->save();

    $this->drupalLogin($this->drupalCreateUser(array('administer pages', 'access administration pages', 'view the administration theme')));
  }

  /**
   * Tests the Page Manager admin UI.
   */
  public function testAdmin() {
    $this->doTestAddPage();
    $this->doTestDisablePage();
    $this->doTestAddDisplayVariant();
    $this->doTestAddBlock();
    $this->doTestEditDisplayVariant();
    $this->doTestReorderDisplayVariants();
    $this->doTestAddPageWithDuplicatePath();
    $this->doTestAdminPath();
    $this->doTestRemoveDisplayVariant();
    $this->doTestRemoveBlock();
    $this->doTestExistingPathWithoutParameters();
  }

  /**
   * Tests adding a page.
   */
  protected function doTestAddPage() {
    $this->drupalGet('admin/structure/page_manager');
    $this->assertText('There is no Page yet.');

    // Add a new page.
    $this->clickLink('Add page');
    $edit = array(
      'label' => 'Foo',
      'id' => 'foo',
      'path' => 'admin/foo',
    );
    $this->drupalPostForm(NULL, $edit, 'Save');
    $this->assertRaw(String::format('The %label page has been added.', array('%label' => 'Foo')));

    // Test that it is available immediately.
    $this->drupalGet('admin/foo');
    $this->assertResponse(404);
    $this->drupalGet('admin/structure/page_manager/manage/foo');
    $this->clickLink('Edit');
    $this->drupalPostForm(NULL, array('display_variant[status_code]' => 200), 'Update display variant');
    $this->drupalGet('admin/foo');
    $this->assertResponse(200);
    $this->drupalGet('admin/structure/page_manager/manage/foo');
    $this->clickLink('Edit');
    $this->drupalPostForm(NULL, array('display_variant[status_code]' => 403), 'Update display variant');

    // Assert that a display variant was added by default.
    $this->drupalGet('admin/structure/page_manager/manage/foo');
    $this->assertNoText('There are no display variants.');
  }

  /**
   * Tests disabling a page.
   */
  protected function doTestDisablePage() {
    $this->drupalGet('admin/foo');
    $this->assertResponse(403);

    $this->drupalGet('admin/structure/page_manager');
    $this->clickLink('Disable');
    $this->drupalGet('admin/foo');
    // The page should not be found if the page is enabled.
    $this->assertResponse(404);

    $this->drupalGet('admin/structure/page_manager');
    $this->clickLink('Enable');
    $this->drupalGet('admin/foo');
    // Re-enabling the page should make this path available.
    $this->assertResponse(403);
  }

  /**
   * Tests adding a display variant.
   */
  protected function doTestAddDisplayVariant() {
    $this->drupalGet('admin/structure/page_manager/manage/foo');

    // Add a new display variant.
    $this->clickLink('Add new display variant');
    $this->clickLink('Block page');
    $this->assertFieldByName("display_variant[page_title]", 'Foo', 'Default page title "Foo" was taken from page label.');
    $edit = array(
      'display_variant[label]' => 'First',
      'display_variant[page_title]' => 'Example title',
    );
    $this->drupalPostForm(NULL, $edit, 'Add display variant');
    $this->assertRaw(String::format('The %label display variant has been added.', array('%label' => 'First')));

    // Test that the variant is not used because it has no blocks.
    $this->drupalGet('admin/foo');
    $this->assertResponse(403);
  }

  /**
   * Tests adding a block to a variant.
   */
  protected function doTestAddBlock() {
    $this->drupalGet('admin/structure/page_manager/manage/foo');
    $this->clickLink('Edit');
    // Add a block to the variant.
    $this->clickLink('Add new block');
    $this->clickLink('User account menu');
    $edit = array(
      'region' => 'top',
    );
    $this->drupalPostForm(NULL, $edit, 'Add block');

    // Test that the block is displayed.
    $this->drupalGet('admin/foo');
    $this->assertResponse(200);
    $this->assertTitle('Example title | Drupal');
    $elements = $this->xpath('//div[@class="block-region-top"]/div/ul[@class="menu"]/li/a');
    $expected = array('My account', 'Log out');
    $links = array();
    foreach ($elements as $element) {
      $links[] = (string) $element;
    }
    $this->assertEqual($expected, $links);
  }

  /**
   * Tests editing a display variant.
   */
  protected function doTestEditDisplayVariant() {
    if (!$block = $this->findBlockByLabel('foo', 'First', 'User account menu')) {
      $this->fail('Block not found');
      return;
    }

    $block_config = $block->getConfiguration();
    $this->drupalGet('admin/structure/page_manager/manage/foo');
    $this->clickLink('Edit');
    $this->assertTitle('Edit First display variant | Drupal');

    $this->assertOptionSelected('edit-display-variant-blocks-' . $block_config['uuid'] . '-region', 'top');
    $this->assertOptionSelected('edit-display-variant-blocks-' . $block_config['uuid'] . '-weight', 0);

    $form_name = 'display_variant[blocks][' . $block_config['uuid'] . ']';
    $edit = array(
      $form_name . '[region]' => 'bottom',
      $form_name . '[weight]' => -10,
    );
    $this->drupalPostForm(NULL, $edit, 'Update display variant');
    $this->assertRaw(String::format('The %label display variant has been updated.', array('%label' => 'First')));
    $this->clickLink('Edit');
    $this->assertOptionSelected('edit-display-variant-blocks-' . $block_config['uuid'] . '-region', 'bottom');
    $this->assertOptionSelected('edit-display-variant-blocks-' . $block_config['uuid'] . '-weight', -10);
  }

  /**
   * Tests reordering display variants.
   */
  protected function doTestReorderDisplayVariants() {
    $this->drupalGet('admin/foo');
    $this->assertResponse(200);
    $elements = $this->xpath('//div[@class="block-region-bottom"]/div/ul[@class="menu"]/li/a');
    $expected = array('My account', 'Log out');
    $links = array();
    foreach ($elements as $element) {
      $links[] = (string) $element;
    }
    $this->assertEqual($expected, $links);

    $display_variant = $this->findDisplayVariantByLabel('foo', 'Default');
    $edit = array(
      'display_variants[' . $display_variant->id() . '][weight]' => -10,
    );
    $this->drupalPostForm('admin/structure/page_manager/manage/foo', $edit, 'Save');
    $this->drupalGet('admin/foo');
    $this->assertResponse(403);
  }

  /**
   * Tests adding a page with a duplicate path.
   */
  protected function doTestAddPageWithDuplicatePath() {
    // Try to add a second page with the same path.
    $edit = array(
      'label' => 'Bar',
      'id' => 'bar',
      'path' => 'admin/foo',
    );
    $this->drupalPostForm('admin/structure/page_manager/add', $edit, 'Save');
    $this->assertText('The page path must be unique.');
    $this->drupalGet('admin/structure/page_manager');
    $this->assertNoText('Bar');
  }

  /**
   * Tests changing the admin theme of a page.
   */
  protected function doTestAdminPath() {
    \Drupal::config('system.theme')->set('default', 'bartik')->save();
    $this->drupalGet('admin/foo');
    $this->assertTheme('stark');

    $edit = array(
      'use_admin_theme' => FALSE,
    );
    $this->drupalPostForm('admin/structure/page_manager/manage/foo', $edit, 'Save');
    $this->drupalGet('admin/foo');
    $this->assertTheme('bartik');

    // Reset theme.
    \Drupal::config('system.theme')->set('default', 'stark')->save();
  }

  /**
   * Tests removing a display variant.
   */
  protected function doTestRemoveDisplayVariant() {
    $this->drupalGet('admin/structure/page_manager/manage/foo');
    $this->clickLink('Delete');
    $this->assertRaw(String::format('Are you sure you want to delete the display variant %label?', array('%label' => 'Default')));
    $this->drupalPostForm(NULL, array(), 'Delete');
    $this->assertRaw(String::format('The display variant %label has been removed.', array('%label' => 'Default')));
  }

  /**
   * Tests removing a block.
   */
  protected function doTestRemoveBlock() {
    // Assert that the block is displayed.
    $this->drupalGet('admin/foo');
    $this->assertResponse(200);
    $elements = $this->xpath('//div[@class="block-region-bottom"]/div/ul[@class="menu"]/li/a');
    $expected = array('My account', 'Log out');
    $links = array();
    foreach ($elements as $element) {
      $links[] = (string) $element;
    }
    $this->assertEqual($expected, $links);

    $this->drupalGet('admin/structure/page_manager/manage/foo');
    $this->clickLink('Edit');
    $this->clickLink('Delete');
    $this->assertRaw(String::format('Are you sure you want to delete the block %label?', array('%label' => 'User account menu')));
    $this->drupalPostForm(NULL, array(), 'Delete');
    $this->assertRaw(String::format('The block %label has been removed.', array('%label' => 'User account menu')));

    // Assert that the block is now gone.
    $this->drupalGet('admin/foo');
    $this->assertResponse(200);
    $elements = $this->xpath('//div[@class="block-region-bottom"]/div/ul[@class="menu"]/li/a');
    $this->assertTrue(empty($elements));
  }

  /**
   * Tests adding a page with an existing path with no route parameters.
   */
  protected function doTestExistingPathWithoutParameters() {
    // Test an existing path.
    $this->drupalGet('admin');
    $this->assertResponse(200);

    $this->drupalGet('admin/structure/page_manager');
    // Add a new page with existing path 'admin'.
    $this->clickLink('Add page');
    $edit = array(
      'label' => 'existing',
      'id' => 'existing',
      'path' => 'admin',
    );
    $this->drupalPostForm(NULL, $edit, 'Save');

    // Regular result is displayed.
    $this->assertText('The existing page has been added');

    // Ensure the existing path leads to the new page.
    $this->drupalGet('admin');
    $this->assertResponse(404);
  }

  /**
   * Asserts that a theme was used for the page.
   *
   * @param string $theme_name
   *   The theme name.
   */
  protected function assertTheme($theme_name) {
    $url = url('core/themes/' . $theme_name . '/logo.png', array('absolute' => TRUE));
    $elements = $this->xpath('//img[@src=:url]', array(':url' => $url));
    $this->assertEqual(count($elements), 1, String::format('Page is rendered in @theme', array('@theme' => $theme_name)));
  }

  /**
   * Finds a block based on its page, variant, and block label.
   *
   * @param string $page_id
   *   The ID of the page entity.
   * @param string $display_variant_label
   *   The label of the display variant.
   * @param string $block_label
   *   The label of the block.
   *
   * @return \Drupal\block\BlockPluginInterface|null
   *   Either a block plugin, or NULL.
   */
  protected function findBlockByLabel($page_id, $display_variant_label, $block_label) {
    if ($display_variant = $this->findDisplayVariantByLabel($page_id, $display_variant_label)) {
      foreach ($display_variant->getRegionAssignments() as $blocks) {
        /** @var $blocks \Drupal\block\BlockPluginInterface[] */
        foreach ($blocks as $block) {
          if ($block->label() == $block_label) {
            return $block;
          }
        }
      }
    }
    return NULL;
  }

  /**
   * Finds a display variant based on its page and display variant label.
   *
   * @param string $page_id
   *   The ID of the page entity.
   * @param string $display_variant_label
   *   The label of the display variant.
   *
   * @return \Drupal\page_manager\Plugin\VariantInterface|null
   *   Either a display variant, or NULL.
   */
  protected function findDisplayVariantByLabel($page_id, $display_variant_label) {
    if ($page = Page::load($page_id)) {
      /** @var $page \Drupal\page_manager\PageInterface */
      foreach ($page->getVariants() as $display_variant) {
        if ($display_variant->label() == $display_variant_label) {
          return $display_variant;
        }
      }
    }
    return NULL;
  }

}
