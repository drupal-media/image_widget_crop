<?php

/**
 * @file
 * Contains \Drupal\image_widget_crop\Plugin\Field\FieldWidget\ImageCropWidget.
 */

namespace Drupal\image_widget_crop\Plugin\Field\FieldWidget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\image\Plugin\Field\FieldWidget\ImageWidget;
use Drupal\image_widget_crop\ImageWidgetCrop;
use Drupal\Core\Field\FieldItemListInterface;


/**
 * Plugin implementation of the 'image_widget_crop' widget.
 *
 * @FieldWidget(
 *   id = "image_widget_crop",
 *   label = @Translation("ImageWidget crop"),
 *   field_types = {
 *     "image"
 *   }
 * )
 */
class ImageCropWidget extends ImageWidget {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return array(
      'crop_preview_image_style' => 'crop_thumbnail',
      'crop_list' => '',
    ) + parent::defaultSettings();
  }

  /**
   * Form API callback: Processes a crop_image field element.
   *
   * Expands the image_image type to include the alt and title fields.
   *
   * This method is assigned as a #process callback in formElement() method.
   */
  public static function process($element, FormStateInterface $form_state, $form) {
    $item = $element['#value'];
    $item['fids'] = $element['fids']['#value'];
    $edit = FALSE;
    $route_params = \Drupal::requestStack()
      ->getCurrentRequest()->attributes->get('_route_params');

    if (isset($route_params['_entity_form']) && preg_match('/.edit/', $route_params['_entity_form'])) {
      $edit = TRUE;
    }

    $element['#theme'] = 'image_widget';
    $element['#attached']['library'][] = 'image/form';
    $element['#attached']['library'][] = 'image_widget_crop/drupal.image_widget_crop.admin';
    $element['#attached']['library'][] = 'image_widget_crop/drupal.image_widget_crop.upload.admin';

    // Add the image preview.
    if (!empty($element['#files']) && $element['#preview_image_style']) {
      $file = reset($element['#files']);
      $variables = array(
        'style_name' => $element['#preview_image_style'],
        'uri' => $file->getFileUri(),
        'file_id' => $file->id(),
      );

      /** @var \Drupal\image_widget_crop\ImageWidgetCrop $ImageWidgetCrop */
      $ImageWidgetCrop = new ImageWidgetCrop();

      // Determine image dimensions.
      if (isset($element['#value']['width']) && isset($element['#value']['height'])) {
        $variables['width'] = $element['#value']['width'];
        $variables['height'] = $element['#value']['height'];
      }
      else {
        $image = \Drupal::service('image.factory')->get($file->getFileUri());
        if ($image->isValid()) {
          $variables['width'] = $image->getWidth();
          $variables['height'] = $image->getHeight();
        }
        else {
          $variables['width'] = $variables['height'] = NULL;
        }
      }

      $element['crop_preview_wrapper'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['crop-wrapper'],
        ],
        '#weight' => 100,
      ];

      // List ImageStyle container.
      $element['crop_preview_wrapper']['list'] = [
        '#type' => 'crop_sidebar',
        '#attributes' => [
          'class' => ['ratio-list'],
        ],
        '#element_type' => 'ul',
        '#weight' => -10,
      ];

      // Wrap crop elements.
      $element['crop_preview_wrapper']['container'] = [
        '#type' => 'crop_container',
        '#attributes' => [
          'class' => ['preview-wrapper-crop'],
        ],
        '#weight' => 100,
      ];

      $image_styles = \Drupal::service('entity.manager')
        ->getStorage('image_style')
        ->loadByProperties(['status' => TRUE]);
      if ($image_styles) {
        /** @var \Drupal\image\Entity\ImageStyle $image_style */
        foreach ($image_styles as $image_style) {
          $machine_name = $image_style->getName();
          $label = $image_style->label();
          if (in_array($machine_name, $element['#crop_list'])) {
            $ratio = $ImageWidgetCrop->getSizeRatio($image_style);

            $element['crop_preview_wrapper']['list'][$machine_name] = [
              '#type' => 'crop_list_items',
              '#attributes' => [
                'class' => ['crop-preview-wrapper', 'item'],
                'data-ratio' => [$ratio],
                'data-name' => [$machine_name],
              ],
              '#variables' => [
                'anchor' => "#$machine_name",
                'ratio' => $ratio,
                'label' => $label
              ]
            ];

            // Generation of html List with image & crop informations.
            $element['crop_preview_wrapper']['container'][$machine_name] = [
              '#type' => 'crop_image_container',
              '#attributes' => [
                'class' => ['crop-preview-wrapper-list'],
                'id' => [$machine_name],
                'data-ratio' => [$ratio],
              ],
              '#variables' => ['label' => $label, 'ratio' => $ratio],
              '#weight' => -10,
            ];

            $element['crop_preview_wrapper']['container'][$machine_name]['image'] = [
              '#theme' => 'image_style',
              '#style_name' => $element['#crop_preview_image_style'],
              '#attributes' => [
                'data-ratio' => [$ratio],
                'data-name' => [$machine_name],
              ],
              '#uri' => $variables['uri'],
              '#weight' => -10,
            ];

            // GET CROP LIBRARIE VALUES.
            $crop_elements = [
              'x1' => ['label' => t('crop x1'), 'value' => NULL],
              'x2' => ['label' => t('crop x2'), 'value' => NULL],
              'y1' => ['label' => t('crop y1'), 'value' => NULL],
              'y2' => ['label' => t('crop y2'), 'value' => NULL],
              'crop-w' => ['label' => t('crop size width'), 'value' => NULL],
              'crop-h' => ['label' => t('crop size height'), 'value' => NULL],
              'thumb-w' => ['label' => t('Thumbnail Width'), 'value' => NULL],
              'thumb-h' => ['label' => t('Thumbnail Height'), 'value' => NULL]
            ];

            if ($edit) {
              $crop = \Drupal::service('entity.manager')
                ->getStorage('crop')->loadByProperties([
                  'type' => $ImageWidgetCrop->getCropType($image_style),
                  'uri' => $variables['uri'],
                  'image_style' => $machine_name
                ]);

              // Only if the crop already exist pre-populate,
              // all cordinates values.
              if (!empty($crop)) {
                /** @var \Drupal\crop\Entity\Crop $crop_entity */
                foreach ($crop as $crop_id => $crop_entity) {
                  $crop_properties = [
                    'anchor' => $crop_entity->position(),
                    'size' => $crop_entity->size()
                  ];
                }

                // Add "saved" class if the crop already exist (in list & img container element).
                $element['crop_preview_wrapper']['list'][$machine_name]['#attributes']['class'][] = 'saved';
                $element['crop_preview_wrapper']['container'][$machine_name]['#attributes']['class'][] = 'saved';

                // If the current crop have a position & sizes,
                // calculate properties to apply crop selection into preview.
                if (isset($crop_properties)) {
                  $values = static::getThumbnailCropProperties($variables['uri'], $crop_properties);
                }

                if (!empty($values)) {
                  // Populate form crop value with values store into crop API.
                  foreach ($crop_elements as $properties => $value) {
                    $crop_elements[$properties]['value'] = $values[$properties];
                  }
                }
              }
            }

            // Generation of html List with image & crop informations.
            $element['crop_preview_wrapper']['container'][$machine_name]['values'] = [
              '#type' => 'container',
              '#attributes' => [
                'class' => ['crop-preview-wrapper-value'],
              ],
              '#weight' => -9,
            ];

            // Generate all cordinates elements into the form when,
            // process is active.
            foreach ($crop_elements as $crop_elements_name => $crop_elements_value) {
              $element['crop_preview_wrapper']['container'][$machine_name]['values'][$crop_elements_name] = [
                '#type' => 'hidden',
                '#attributes' => ['class' => ["crop-$crop_elements_name"]],
                '#value' => !empty($edit) ? $crop_elements_value['value'] : 0,
              ];
            }

            // Stock Original File Values.
            $element['file-uri'] = [
              '#type' => 'value',
              '#value' => $variables['uri'],
            ];

            $element['file-id'] = [
              '#type' => 'value',
              '#value' => $variables['file_id'],
            ];
          }
        }
      }
    }

    return parent::process($element, $form_state, $form);
  }

  /**
   * Calculate properties of thumbnail preview.
   *
   * @param string $uri
   *   The uri of uploaded image.
   * @param array $original_crop_properties
   *   All properties returned by the crop plugin (js),
   *   and the size of thumbnail image.
   * @param string $preview
   *   An array of values for the contained properties of image_crop widget.
   *
   * @return array
   *   All properties (x1, x2, y1, y2, crop height, crop width,
   *   thumbnail height, thumbnail width), to apply the real crop
   *   into thumbnail preview.
   */
  public static function getThumbnailCropProperties($uri, array $original_crop_properties, $preview = 'crop_thumbnail') {
    $image_styles = \Drupal::service('entity.manager')
      ->getStorage('image_style')
      ->loadByProperties(['status' => TRUE, 'name' => $preview]);

    // Verify the configuration of ImageStyle and get the data width.
    $effect = $image_styles[$preview]->getEffects()->getConfiguration();

    // Get Width of this image.
    $thumbnail_width = $effect[array_keys($effect)[0]]['data']['width'];

    list($width, $height) = getimagesize($uri);

    // Calculate Thumbnail height
    // (Original Height x Thumbnail Width / Original Width = Thumbnail Height).
    $thumbnail_height = round(($height * $thumbnail_width) / $width);

    // Get the delta between Original Height divide by Thumbnail Height.
    $delta = $height / $thumbnail_height;

    // Get the Crop selection Size (into Uploaded image) & calculate selection for Thumbnail.
    $crop_thumbnail_properties['crop-h'] = round($original_crop_properties['size']['height'] / $delta);
    $crop_thumbnail_properties['crop-w'] = round($original_crop_properties['size']['width'] / $delta);

    // Calculate the Top-Left corner for Thumbnail.
    $crop_thumbnail_properties['x1'] = round($original_crop_properties['anchor']['x'] / $delta);
    $crop_thumbnail_properties['y1'] = round($original_crop_properties['anchor']['y'] / $delta);

    // Calculate the Bottom-right position for Thumbnail.
    $crop_thumbnail_properties['x2'] = $crop_thumbnail_properties['x1'] + $crop_thumbnail_properties['crop-w'];
    $crop_thumbnail_properties['y2'] = $crop_thumbnail_properties['y1'] + $crop_thumbnail_properties['crop-h'];

    // Get the real thumbnail sizes.
    $crop_thumbnail_properties['thumb-w'] = $thumbnail_width;
    $crop_thumbnail_properties['thumb-h'] = $thumbnail_height;

    return $crop_thumbnail_properties;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    $element['crop_preview_image_style'] = array(
      '#title' => t('Crop preview image style'),
      '#type' => 'select',
      '#options' => image_style_options(FALSE),
      '#empty_option' => '<' . t('no preview') . '>',
      '#default_value' => $this->getSetting('crop_preview_image_style'),
      '#description' => t('The preview image will be shown while editing the content.'),
      '#weight' => 15,
    );

    $element['crop_list'] = [
      '#title' => t('Image style cropped'),
      '#type' => 'select',
      '#options' => image_style_options(FALSE),
      '#empty_option' => '<' . t('no preview') . '>',
      '#default_value' => $this->getSetting('crop_list'),
      '#multiple' => TRUE,
      '#description' => t('The preview image will be cropped.'),
      '#weight' => 16,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $image_styles = image_style_options(FALSE);
    // Unset possible 'No defined styles' option.
    unset($image_styles['']);
    // Styles could be lost because of enabled/disabled modules that defines
    // their styles in code.
    $image_style_setting = $this->getSetting('preview_image_style');
    $crop_image_style_setting = $this->getSetting('crop_preview_image_style');
    $crop_list = $this->getSetting('crop_list');

    if (isset($crop_list) && !empty($crop_list)) {
      $preview[] = t('Crop image style search: @list', array('@list' => implode(", ", $crop_list)));
    }

    if (isset($image_styles[$image_style_setting]) || isset($image_styles[$crop_image_style_setting])) {
      $preview[] = t('Preview image style: @style', array('@style' => $image_styles[$image_style_setting]));
      $preview[] = t('Crop preview image style: @style', array('@style' => $image_styles[$crop_image_style_setting]));
    }
    else {
      $preview = t('Original image');
    }

    return $preview;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    $field_settings = $this->getFieldSettings();

    // Add properties needed by process() method.
    $element['#crop_list'] = $this->getSetting('crop_list');
    $element['#crop_preview_image_style'] = $this->getSetting('crop_preview_image_style');

    // Set an custom upload_location.
    $element['#upload_location'] = 'public://crop/pictures/';

    return parent::formElement($items, $delta, $element, $form, $form_state);
  }

}
