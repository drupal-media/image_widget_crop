<?php

namespace Drupal\image_widget_crop\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\SortArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\crop\Entity\CropType;
use Drupal\entity_browser\Plugin\Field\FieldWidget\FileBrowserWidget;

/**
 * ImageCrop browser file widget.
 *
 * @FieldWidget(
 *   id = "image_crop_browser",
 *   label = @Translation("ImageCrop browser"),
 *   provider = "image_widget_crop",
 *   multiple_values = TRUE,
 *   field_types = {
 *     "image"
 *   }
 * )
 */
class ImageCropBrowserWidget extends FileBrowserWidget {

  /**
   * {@inheritdoc}
   */
  protected static $deleteDepth = 4;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
        'crop_preview_image_style' => 'crop_thumbnail',
        'crop_list' => NULL,
        'show_crop_area' => FALSE,
        'show_default_crop' => TRUE,
        'show_reset_crop' => TRUE,
        'warn_multiple_usages' => TRUE,
        'modal_title' => t('Crop the image'),
        'modal_width' => 0,
        'modal_height' => 0,
        'modal_min_width' => 200,
        'modal_min_height' => 0,
      ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    $element['crop_preview_image_style'] = [
      '#title' => $this->t('Crop preview image style'),
      '#type' => 'select',
      '#options' => image_style_options(FALSE),
      '#default_value' => $this->getSetting('crop_preview_image_style'),
      '#description' => $this->t('The preview image will be shown while editing the content.'),
      '#weight' => 15,
    ];

    $iwc_manager = \Drupal::service('image_widget_crop.manager');
    $element['crop_list'] = [
      '#title' => $this->t('Crop Type'),
      '#type' => 'select',
      '#options' => $iwc_manager->getAvailableCropType(CropType::getCropTypeNames()),
      '#empty_option' => $this->t('<@no-preview>', ['@no-preview' => $this->t('no preview')]),
      '#default_value' => $this->getSetting('crop_list'),
      '#multiple' => TRUE,
      '#required' => TRUE,
      '#description' => $this->t('The type of crop to apply to your image. If your Crop Type not appear here, set an image style use your Crop Type'),
      '#weight' => 16,
    ];

    $element['show_crop_area'] = [
      '#title' => $this->t('Always expand crop area'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('show_crop_area'),
    ];

    $element['show_default_crop'] = [
      '#title' => $this->t('Show default crop area'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('show_default_crop'),
    ];

    $element['show_reset_crop'] = [
      '#title' => $this->t('Show "Reset crop" button'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('show_reset_crop'),
    ];

    $element['warn_multiple_usages'] = [
      '#title' => $this->t('Warn the user if the crop is used more than once.'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('warn_multiple_usages'),
    ];

    $element['modal_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Modal dialog title'),
      '#default_value' => $this->getSetting('modal_title'),
    ];

    $element['modal_width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Modal dialog width, px'),
      '#description' => t('Leave 0 or blank to calculate width automatically.'),
      '#size' => 4,
      '#element_validate' => ['element_validate_integer_positive'],
      '#default_value' => $this->getSetting('modal_width'),
    ];

    $element['modal_height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Modal dialog height, px'),
      '#description' => t('Leave 0 or blank to calculate height automatically.'),
      '#size' => 4,
      '#element_validate' => ['element_validate_integer_positive'],
      '#default_value' => $this->getSetting('modal_height'),
    ];

    $element['modal_min_width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Modal dialog min width, px'),
      '#description' => t('Leave 0 or blank to calculate min width automatically.'),
      '#size' => 4,
      '#element_validate' => ['element_validate_integer_positive'],
      '#default_value' => $this->getSetting('modal_min_width'),
    ];

    $element['modal_min_height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Modal dialog min height, px'),
      '#description' => t('Leave 0 or blank to calculate min height automatically.'),
      '#size' => 4,
      '#element_validate' => ['element_validate_integer_positive'],
      '#default_value' => $this->getSetting('modal_min_height'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $preview = parent::settingsSummary();

    $image_styles = image_style_options(FALSE);
    // Unset possible 'No defined styles' option.
    unset($image_styles['']);

    // Styles could be lost because of enabled/disabled modules that defines
    // their styles in code.
    $image_style_setting = $this->getSetting('preview_image_style');
    $crop_preview = $image_styles[$this->getSetting('crop_preview_image_style')];
    $crop_list = $this->getSetting('crop_list');
    $crop_show_button = $this->getSetting('show_crop_area');
    $show_default_crop = $this->getSetting('show_default_crop');
    $show_reset_crop = $this->getSetting('show_reset_crop');
    $warn_multiple_usages = $this->getSetting('warn_multiple_usages');

    $preview[] = $this->t('Always expand crop area: @bool', ['@bool' => ($crop_show_button) ? 'Yes' : 'No']);
    $preview[] = $this->t('Show default crop area: @bool', ['@bool' => ($show_default_crop) ? 'Yes' : 'No']);
    $preview[] = $this->t('Show crop reset button: @bool', ['@bool' => ($show_reset_crop) ? 'Yes' : 'No']);
    $preview[] = $this->t('Warn the user if the crop is used more than once: @bool', ['@bool' => ($warn_multiple_usages) ? 'Yes' : 'No']);

    if (isset($image_styles[$image_style_setting])) {
      $preview[] = $this->t('Preview image style: @style', ['@style' => $image_style_setting]);
    }
    else {
      $preview[] = $this->t('No preview image style');
    }

    if (isset($crop_preview)) {
      $preview[] = $this->t('Preview crop zone image style: @style', ['@style' => $crop_preview]);
    }

    if (!empty($crop_list)) {
      $preview[] = $this->t('Crop Type used: @list', ['@list' => is_array($crop_list) ? implode(", ", $crop_list) : $crop_list]);
    }

    // Get modal widget settings.
    $modal_title = $this->getSetting('modal_title');
    $modal_width = $this->getSetting('modal_width');
    $modal_height = $this->getSetting('modal_height');
    $modal_min_width = $this->getSetting('modal_min_width');
    $modal_min_height = $this->getSetting('modal_min_height');

    // Format messages for each setting.
    $preview[] = $this->t('Modal title: @title', ['@title' => $modal_title]);
    $preview[] = $this->t('Modal width: @width', ['@width' => $modal_width ? $modal_width . 'px' : t('Auto')]);
    $preview[] = $this->t('Modal height: @height', ['@height' => $modal_height ? $modal_height . 'px' : t('Auto')]);
    $preview[] = $this->t('Modal min width: @width', ['@width' => $modal_min_width ? $modal_min_width . 'px' : t('Auto')]);
    $preview[] = $this->t('Modal min height: @height', ['@height' => $modal_min_height ? $modal_min_height . 'px' : t('Auto')]);

    return $preview;
  }

  /**
   * {@inheritdoc}
   */
  protected function displayCurrentSelection($details_id, $field_parents, $entities) {
    $current = parent::displayCurrentSelection($details_id, $field_parents, $entities);

    unset($current['#header']);

    $extras = ['meta', 'edit_button', 'remove_button'];
    foreach (Element::children($current) as $key) {
      $row = &$current[$key];

      $row['widget'] = [
        '#type' => 'container',
      ];

      foreach ($extras as $extra) {
        $row['widget'][$extra] = $row[$extra];
        unset($row[$extra]);
      }

      $row['widget']['crop_button'] = [
        '#type' => 'image_crop_modal',
        '#file' => $row['display']['#file'],
        '#crop_type_list' => $this->getSetting('crop_list'),
        '#crop_preview_image_style' => 'crop_thumbnail',
        '#show_default_crop' => $this->getSetting('show_default_crop'),
        '#show_reset_crop' => $this->getSetting('show_reset_crop'),
        '#show_crop_area' => $this->getSetting('show_crop_area'),
        '#warn_mupltiple_usages' => $this->getSetting('warn_multiple_usages'),
        '#modal_title' => $this->getSetting('modal_title'),
        '#modal_width' => $this->getSetting('modal_width'),
        '#modal_height' => $this->getSetting('modal_height'),
        '#modal_min_width' => $this->getSetting('modal_min_width'),
        '#modal_min_height' => $this->getSetting('modal_min_height'),
        '#weight' => 10,
      ];

      $row['widget']['edit_button']['#weight'] = 9;
      $row['widget']['remove_button']['#weight'] = 11;
    }

    return $current;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $ids = empty($values['target_id']) ? [] : explode(' ', trim($values['target_id']));
    $return = [];
    foreach ($ids as $id) {
      $id = explode(':', $id)[1];
      if (is_array($values['current']) && isset($values['current'][$id])) {
        $item_values = [
          'target_id' => $id,
          '_weight' => $values['current'][$id]['_weight'],
        ];
        if ($this->fieldDefinition->getType() == 'file') {
          if (isset($values['current'][$id]['meta']['description'])) {
            $item_values['description'] = $values['current'][$id]['widget']['meta']['description'];
          }
          if ($this->fieldDefinition->getSetting('display_field') && isset($values['current'][$id]['widget']['meta']['display_field'])) {
            $item_values['display'] = $values['current'][$id]['widget']['meta']['display_field'];
          }
        }
        if ($this->fieldDefinition->getType() == 'image') {
          if (isset($values['current'][$id]['widget']['meta']['alt'])) {
            $item_values['alt'] = $values['current'][$id]['widget']['meta']['alt'];
          }
          if (isset($values['current'][$id]['widget']['meta']['title'])) {
            $item_values['title'] = $values['current'][$id]['widget']['meta']['title'];
          }
          if (isset($values['current'][$id]['widget']['crop_button'])) {
            $item_values['image_crop'] = $values['current'][$id]['widget']['crop_button'];
          }
        }
        $return[] = $item_values;
      }
    }

    // Return ourself as the structure doesn't match the default.
    usort($return, function ($a, $b) {
      return SortArray::sortByKeyInt($a, $b, '_weight');
    });

    return array_values($return);
  }

}
