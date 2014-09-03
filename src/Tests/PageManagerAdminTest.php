<?php

/**
 * @file
 * Contains \Drupal\page_manager\Tests\PageManagerAdminTest.
 */

namespace Drupal\page_manager\Tests;

use Drupal\Component\Utility\String;
use Drupal\Core\Url;
use Drupal\page_manager\Entity\Page;
use Drupal\simpletest\WebTestBase;

/**
 * Tests the admin UI for page entities.
 */
class PageManagerAdminTest extends WebTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['page_manager'];

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return [
      'name' => 'Page Manager admin test',
      'description' => 'Tests the admin UI for page entities.',
      'group' => 'Page Manager',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    \Drupal::service('theme_handler')->install(['bartik']);
    \Drupal::config('system.theme')->set('admin', 'stark')->save();

    $this->drupalLogin($this->drupalCreateUser(['administer pages', 'access administration pages', 'view the administration theme']));
  }

  /**
   * Tests the Page Manager admin UI.
   */
  public function testAdmin() {
    $this->doTestAddPage();
    $this->doTestDisablePage();
    $this->doTestAddDisplayVariant();
    $this->doTestAddBlock();
    $this->doTestEditBlock();
    $this->doTestEditDisplayVariant();
    $this->doTestReorderDisplayVariants();
    $this->doTestAddPageWithDuplicatePath();
    $this->doTestAdminPath();
    $this->doTestRemoveDisplayVariant();
    $this->doTestRemoveBlock();
    $this->doTestExistingPathWithoutParameters();
    $this->doTestDeletePage();
  }

  /**
   * Tests adding a page.
   */
  protected function doTestAddPage() {
    $this->drupalGet('admin/structure');
    $this->clickLink('Pages');
    $this->assertText('There is no Page yet.');

    // Add a new page.
    $this->clickLink('Add page');
    $edit = [
      'label' => 'Foo',
      'id' => 'foo',
      'path' => 'admin/foo',
    ];
    $this->drupalPostForm(NULL, $edit, 'Save');
    $this->assertRaw(String::format('The %label page has been added.', ['%label' => 'Foo']));

    // Test that it is available immediately.
    $this->drupalGet('admin/foo');
    $this->assertResponse(404);
    $this->drupalGet('admin/structure/page_manager/manage/foo');
    $this->clickLink('Edit');
    $this->drupalPostForm(NULL, ['display_variant[status_code]' => 200], 'Update display variant');
    $this->drupalGet('admin/foo');
    $this->assertResponse(200);
    $this->drupalGet('admin/structure/page_manager/manage/foo');
    $this->clickLink('Edit');
    $this->drupalPostForm(NULL, ['display_variant[status_code]' => 403], 'Update display variant');

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
    $edit = [
      'display_variant[label]' => 'First',
      'display_variant[page_title]' => 'Example title',
    ];
    $this->drupalPostForm(NULL, $edit, 'Add display variant');
    $this->assertRaw(String::format('The %label display variant has been added.', ['%label' => 'First']));

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
    $edit = [
      'region' => 'top',
    ];
    $this->drupalPostForm(NULL, $edit, 'Add block');

    // Test that the block is displayed.
    $this->drupalGet('admin/foo');
    $this->assertResponse(200);
    $elements = $this->xpath('//div[@class="block-region-top"]/nav/ul[@class="menu"]/li/a');
    $this->assertTitle('Example title | Drupal');
    $expected = ['My account', 'Log out'];
    $links = [];
    foreach ($elements as $element) {
      $links[] = (string) $element;
    }
    $this->assertEqual($expected, $links);
    // @todo Restore the <h2> check once the follow-up to
    //   https://www.drupal.org/node/1869476 is in.
    //$this->assertRaw('<h2>User account menu</h2>');
    // Check the block label.
    $this->assertRaw('User account menu');
  }

  /**
   * Tests editing a block.
   */
  protected function doTestEditBlock() {
    $this->drupalGet('admin/structure/page_manager/manage/foo');
    $this->clickLink('Edit');
    $this->clickLink('Edit');
    $edit = [
      'settings[label]' => 'Updated block label',
    ];
    $this->drupalPostForm(NULL, $edit, 'Update block');
    // Test that the block is displayed.
    $this->drupalGet('admin/foo');
    $this->assertResponse(200);
    // Check the block label.
    // @todo Restore the <h2> check once the follow-up to
    //   https://www.drupal.org/node/1869476 is in.
    //$this->assertRaw('<h2>' . $edit['settings[label]'] . '</h2>');
    $this->assertRaw($edit['settings[label]']);
  }

  /**
   * Tests editing a display variant.
   */
  protected function doTestEditDisplayVariant() {
    if (!$block = $this->findBlockByLabel('foo', 'First', 'Updated block label')) {
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
    $edit = [
      $form_name . '[region]' => 'bottom',
      $form_name . '[weight]' => -10,
    ];
    $this->drupalPostForm(NULL, $edit, 'Update display variant');
    $this->assertRaw(String::format('The %label display variant has been updated.', ['%label' => 'First']));
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
    $elements = $this->xpath('//div[@class="block-region-bottom"]/nav/ul[@class="menu"]/li/a');
    $expected = ['My account', 'Log out'];
    $links = [];
    foreach ($elements as $element) {
      $links[] = (string) $element;
    }
    $this->assertEqual($expected, $links);

    $display_variant = $this->findDisplayVariantByLabel('foo', 'Default');
    $edit = [
      'display_variants[' . $display_variant->id() . '][weight]' => -10,
    ];
    $this->drupalPostForm('admin/structure/page_manager/manage/foo', $edit, 'Save');
    $this->drupalGet('admin/foo');
    $this->assertResponse(403);
  }

  /**
   * Tests adding a page with a duplicate path.
   */
  protected function doTestAddPageWithDuplicatePath() {
    // Try to add a second page with the same path.
    $edit = [
      'label' => 'Bar',
      'id' => 'bar',
      'path' => 'admin/foo',
    ];
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

    $edit = [
      'use_admin_theme' => FALSE,
    ];
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
    $this->assertRaw(String::format('Are you sure you want to delete the display variant %label?', ['%label' => 'Default']));
    $this->drupalPostForm(NULL, [], 'Delete');
    $this->assertRaw(String::format('The display variant %label has been removed.', ['%label' => 'Default']));
  }

  /**
   * Tests removing a block.
   */
  protected function doTestRemoveBlock() {
    // Assert that the block is displayed.
    $this->drupalGet('admin/foo');
    $this->assertResponse(200);
    $elements = $this->xpath('//div[@class="block-region-bottom"]/nav/ul[@class="menu"]/li/a');
    $expected = ['My account', 'Log out'];
    $links = [];
    foreach ($elements as $element) {
      $links[] = (string) $element;
    }
    $this->assertEqual($expected, $links);

    $this->drupalGet('admin/structure/page_manager/manage/foo');
    $this->clickLink('Edit');
    $this->clickLink('Delete');
    $this->assertRaw(String::format('Are you sure you want to delete the block %label?', ['%label' => 'Updated block label']));
    $this->drupalPostForm(NULL, [], 'Delete');
    $this->assertRaw(String::format('The block %label has been removed.', ['%label' => 'Updated block label']));

    // Assert that the block is now gone.
    $this->drupalGet('admin/foo');
    $this->assertResponse(200);
    $elements = $this->xpath('//div[@class="block-region-bottom"]/nav/ul[@class="menu"]/li/a');
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
    $edit = [
      'label' => 'existing',
      'id' => 'existing',
      'path' => 'admin',
    ];
    $this->drupalPostForm(NULL, $edit, 'Save');

    // Regular result is displayed.
    $this->assertText('The existing page has been added');

    // Ensure the existing path leads to the new page.
    $this->drupalGet('admin');
    $this->assertResponse(404);
  }

  /**
   * Tests deleting a page.
   */
  protected function doTestDeletePage() {
    $this->drupalGet('admin/structure/page_manager');
    $this->clickLink('Delete');
    $this->drupalPostForm(NULL, [], 'Delete');
    $this->assertRaw(String::format('The page %name has been removed.', ['%name' => 'existing']));
    $this->drupalGet('admin');
    // The overridden page is back to its default.
    $this->assertResponse(200);

    $this->drupalGet('admin/structure/page_manager');
    $this->clickLink('Delete');
    $this->drupalPostForm(NULL, [], 'Delete');
    $this->assertRaw(String::format('The page %name has been removed.', ['%name' => 'Foo']));
    $this->drupalGet('admin/foo');
    // The custom page is no longer found.
    $this->assertResponse(404);
  }

  /**
   * Asserts that a theme was used for the page.
   *
   * @param string $theme_name
   *   The theme name.
   */
  protected function assertTheme($theme_name) {
    $url = Url::fromUri('base://core/themes/' . $theme_name . '/logo.png', ['absolute' => TRUE])->toString();
    $elements = $this->xpath('//img[@src=:url]', [':url' => $url]);
    $this->assertEqual(count($elements), 1, String::format('Page is rendered in @theme', ['@theme' => $theme_name]));
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
   * @return \Drupal\Core\Block\BlockPluginInterface|null
   *   Either a block plugin, or NULL.
   */
  protected function findBlockByLabel($page_id, $display_variant_label, $block_label) {
    if ($display_variant = $this->findDisplayVariantByLabel($page_id, $display_variant_label)) {
      /** @var $display_variant \Drupal\page_manager\Plugin\BlockVariantInterface */
      foreach ($display_variant->getRegionAssignments() as $blocks) {
        /** @var $blocks \Drupal\Core\Block\BlockPluginInterface[] */
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
   * @return \Drupal\Core\Display\VariantInterface|null
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
