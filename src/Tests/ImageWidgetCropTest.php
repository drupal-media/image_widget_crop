<?php

/**
 * @file
 * Tests for Image Widget Crop.
 */

namespace Drupal\image_widget_crop\Tests;

use Drupal\node\Entity\Node;
use Drupal\simpletest\WebTestBase;

/**
 * Minimal test case for the image_widget_crop module.
 *
 * @group image_widget_crop
 *
 * @ingroup media
 */
class ImageWidgetCropTest extends WebTestBase {

  /**
   * User with permissions to create content.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $user;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'node',
    'crop',
    'image',
    'image_widget_crop',
  ];

  /**
   * Prepares environment for the tests.
   */
  protected function setUp() {
    parent::setUp();

    $this->drupalCreateContentType(['name' => 'Crop test', 'type' => 'crop_test']);

    $this->user = $this->createUser([
      'access content overview',
      'administer content types',
      'edit any crop_test content'
    ]);
    $this->drupalLogin($this->user);
  }

  /**
   * Test Image Widget Crop UI.
   */
  public function testCropUi() {
    // Test that when a crop has more than one usage we have a warning.
    $this->createImageField('field_image_crop_test', 'crop_test', [], [], ['crop_list' => ['crop_16_9' => 'crop_16_9']]);
    $this->drupalGetTestFiles('image');

    $this->drupalGet('node/add/crop_test');
    $edit = [
      'title[0][value]' => $this->randomMachineName(),
      'files[field_image_crop_test_0]' => \Drupal::service('file_system')->realpath('public://image-test.jpg'),
    ];
    $this->drupalPostAjaxForm(NULL, $edit, $this->getButtonName('//input[@type="submit" and @value="Upload" and @data-drupal-selector="edit-field-image-crop-test-0-upload-button"]'));

    $node = Node::create([
      'title' => '2nd node using it',
      'type' => 'crop_test',
      'field_image_crop_test' => 1,
      'alt' => $this->randomMachineName(),
    ]);
    $node->save();

    /** @var \Drupal\file\FileUsage\FileUsageInterface $usage */
    $usage = \Drupal::service('file.usage');
    $usage->add(\Drupal::service('entity_type.manager')->getStorage('file')->load(1), 'image_widget_crop', 'node', $node->id());

    $this->drupalGet('node/1/edit');

    $this->assertRaw(t('This crop definition affects more usages of this image'));

  }

  /**
   * Gets IEF button name.
   *
   * @param string $xpath
   *   Xpath of the button.
   *
   * @return string
   *   The name of the button.
   */
  protected function getButtonName($xpath) {
    $retval = '';
    /** @var \SimpleXMLElement[] $elements */
    if ($elements = $this->xpath($xpath)) {
      foreach ($elements[0]->attributes() as $name => $value) {
        if ($name == 'name') {
          $retval = (string) $value;
          break;
        }
      }
    }
    return $retval;
  }

  /**
   * Create a new image field.
   *
   * @param string $name
   *   The name of the new field (all lowercase), exclude the "field_" prefix.
   * @param string $type_name
   *   The node type that this field will be added to.
   * @param array $storage_settings
   *   A list of field storage settings that will be added to the defaults.
   * @param array $field_settings
   *   A list of instance settings that will be added to the instance defaults.
   * @param array $widget_settings
   *   A list of widget settings that will be added to the widget defaults.
   */
  protected function createImageField($name, $type_name, $storage_settings = [], $field_settings = [], $widget_settings = []) {
    \Drupal::entityTypeManager()->getStorage('field_storage_config')->create([
      'field_name' => $name,
      'entity_type' => 'node',
      'type' => 'image',
      'settings' => $storage_settings,
      'cardinality' => !empty($storage_settings['cardinality']) ? $storage_settings['cardinality'] : 1,
    ])->save();

    $field_config = \Drupal::entityTypeManager()->getStorage('field_config')->create([
      'field_name' => $name,
      'label' => $name,
      'entity_type' => 'node',
      'bundle' => $type_name,
      'required' => !empty($field_settings['required']),
      'settings' => $field_settings,
    ]);
    $field_config->save();

    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
    $form_display = \Drupal::entityTypeManager()->getStorage('entity_form_display')->load('node.' . $type_name . '.default');
    $form_display->setComponent($name, [
        'type' => 'image_widget_crop',
        'settings' => $widget_settings,
      ])
      ->save();

    /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $view_display */
    $view_display = \Drupal::entityTypeManager()->getStorage('entity_view_display')->load('node.' . $type_name . '.default');
    $view_display->setComponent($name)
      ->save();

  }

}
