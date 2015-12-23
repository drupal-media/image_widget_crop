<?php

/**
 * @file
 * Contains \Drupal\image_widget_crop\Plugin\Field\FieldWidget\ImageCropWidget.
 */

namespace Drupal\image_widget_crop\Plugin\Field\FieldWidget;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\Image;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\image\Plugin\Field\FieldWidget\ImageWidget;
use Drupal\image_widget_crop\ImageWidgetCrop;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;
use Drupal\image_widget_crop\ImageWidgetCropManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\crop\Entity\CropType;

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
   * Instance of API ImageWidgetCropManager.
   *
   * @var \Drupal\image_widget_crop\ImageWidgetCropManager
   */
  protected $imageWidgetCrop;

  /**
   * The image style storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorage
   */
  protected $imageStyleStorage;

  /**
   * The crop type storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorage
   */
  protected $cropTypeStorage;

  /**
   * The config factory.
   *
   * Subclasses should use the self::config() method, which may be overridden to
   * address specific needs when loading config, rather than this property
   * directly. See \Drupal\Core\Form\ConfigFormBase::config() for an example of
   * this.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, ElementInfoManagerInterface $element_info, ImageWidgetCropManager $image_widget_crop, ConfigEntityStorage $image_style_storage, ConfigEntityStorage $crop_type_storage, ConfigFactoryInterface $config_factory) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings, $element_info);
    $this->imageWidgetCrop = $image_widget_crop;
    $this->imageStyleStorage = $image_style_storage;
    $this->cropTypeStorage = $crop_type_storage;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('element_info'),
      $container->get('image_widget_crop.manager'),
      $container->get('entity.manager')->getStorage('image_style'),
      $container->get('entity.manager')->getStorage('crop_type'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string,string|null|false>
   *   The array of settings.
   */
  public static function defaultSettings() {
    return [
      'crop_preview_image_style' => 'crop_thumbnail',
      'crop_list' => NULL,
      'show_crop_area' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * Form API callback: Processes a crop_image field element.
   *
   * Expands the image_image type to include the alt and title fields.
   *
   * This method is assigned as a #process callback in formElement() method.
   *
   * @return array
   *   The elements with parents fields.
   */
  public static function process($element, FormStateInterface $form_state, $form) {
    $edit = FALSE;
    $crop_types_list = $element['#crop_types_list'];
    $route_params = \Drupal::requestStack()
      ->getCurrentRequest()->attributes->get('_route_params');

    // Display an error message if the local/remote library and CSS are not set.
    // @TODO Find a better solution to display the error message.
    $config = \Drupal::config('image_widget_crop.settings');
    $js_library = $config->get('settings.library_url');
    $css_library = $config->get('settings.css_url');
    if (!\Drupal::moduleHandler()->moduleExists('libraries')) {
      if ((empty($js_library) || empty($css_library)) || (empty($js_library) && empty($css_library))) {
        $element['message'] = array(
          '#type' => 'container',
          '#markup' => t('Either set the library locally (in /libraries/cropper) and enable the libraries module or enter the remote URL on <a href="/admin/config/media/crop-widget">Image Crop Widget settings</a>.'),
          '#attributes' => array(
            'class' => array('messages messages--error'),
          ),
        );
      }
    }

    if (isset($route_params['_entity_form']) && preg_match('/.edit/', $route_params['_entity_form'])) {
      $edit = TRUE;
      /** @var \Drupal\crop\CropStorage $crop_storage */
      $crop_storage = \Drupal::service('entity.manager')->getStorage('crop');
    }

    $element['#theme'] = 'image_widget';
    $element['#attached']['library'][] = 'image/form';
    $element['#attached']['library'][] = 'image_widget_crop/cropper.integration';

    // Add the image preview.
    if (!empty($element['#files']) && $element['#preview_image_style']) {
      $file = reset($element['#files']);
      $variables = ['style_name' => $element['#preview_image_style'], 'uri' => $file->getFileUri(), 'file_id' => $file->id()];
      // Verify if user have uploaded an image.
      self::getFileImageVariables($element, $variables);

      // @TODO: $element['#delta'] is not unique. We need to find something unique.
      $list_id = 'crop_list_' . $element['#field_name'] . '_' . $element['#delta'];
      // Standardize the name of wrapper elements.
      $element_wrapper_name = 'crop_container';

      // We need to wrap all elements to identify the widget elements.
      $element['crop_preview_wrapper'] = [
        '#type' => 'details',
        '#title' => t('Crop image'),
        '#attributes' => ['class' => ['crop-wrapper']],
        '#weight' => 100,
      ];

      // Warn the user if the crop is used more than once.
      $usage_counter = 0;
      $file_usage = \Drupal::service('file.usage')->listUsage($file);
      foreach (new RecursiveIteratorIterator(new RecursiveArrayIterator($file_usage)) as $usage) {
        $usage_counter += (int) $usage;
      }
      if ($usage_counter > 1) {
        $element['crop_reuse'] = [
          '#type' => 'container',
          '#markup' => t('This crop definition affects more usages of this image'),
          '#attributes' => [
            'class' => ['messages messages--warning'],
          ],
          '#weight' => -10,
        ];
      }

      $container = &$element['crop_preview_wrapper'];
      $container[$list_id] = [
        '#type' => 'vertical_tabs',
        '#default_tab' => '',
        '#theme_wrappers' => array('vertical_tabs'),
        '#tree' => TRUE,
        '#parents' => array($list_id),
      ];

      if (!empty($crop_types_list)) {
        foreach ($crop_types_list as $crop_type) {
          /** @var \Drupal\crop\Entity\CropType $crop_type */
          $crop_type_id = $crop_type->id();
          $label = $crop_type->label();
          if (in_array($crop_type_id, $element['#crop_list'])) {
            $thumb_properties = [];
            // Add compatibility to PHP 5.3.
            $has_ratio = $crop_type->getAspectRatio();
            $ratio = !empty($has_ratio) ? $has_ratio : t('NaN');
            $ratio = str_replace(':', '/', $ratio);

            $container[$crop_type_id] = [
              '#type' => 'details',
              '#title' => $label,
              '#group' => $list_id,
            ];

            // Generation of html List with image & crop informations.
            $container[$crop_type_id][$element_wrapper_name] = [
              '#type' => 'container',
              '#attributes' => ['class' => ['crop-preview-wrapper', $list_id], 'id' => [$crop_type_id], 'data-ratio' => [$ratio]],
              '#weight' => -10,
            ];

            $container[$crop_type_id][$element_wrapper_name]['image'] = [
              '#theme' => 'image_style',
              '#style_name' => $element['#crop_preview_image_style'],
              '#attributes' => ['data-ratio' => [$ratio], 'data-name' => [$crop_type_id]],
              '#uri' => $variables['uri'],
              '#weight' => -10,
            ];

            $container[$crop_type_id][$element_wrapper_name]['reset'] = [
              '#type' => 'button',
              '#value' => t('Reset crop'),
              '#attributes' => ['class' => ['crop-reset']],
              '#weight' => -10,
            ];

            // Generation of html List with image & crop informations.
            $container[$crop_type_id][$element_wrapper_name]['values'] = [
              '#type' => 'container',
              '#attributes' => ['class' => ['crop-preview-wrapper-value']],
              '#weight' => -9,
            ];

            // Element to track whether cropping is applied or not.
            $container[$crop_type_id][$element_wrapper_name]['values']['crop_applied'] = [
              '#type' => 'hidden',
              '#attributes' => ['class' => ["crop-applied"]],
              '#value' => 0,
            ];

            if ($edit && !empty($crop_storage)) {
              $crops = $crop_storage->loadByProperties(['type' => $crop_type_id, 'uri' => $variables['uri']]);
              if (!empty($crops)) {
                // Only if the crop already exist pre-populate,
                // all cordinates values.
                $crop_properties = self::getCropProperties($crops);

                /** @var \Drupal\Core\Image\Image $image */
                $image = \Drupal::service('image.factory')->get($file->getFileUri());
                if (!$image->isValid()) {
                  throw new \RuntimeException('This image file is nos valid');
                }

                $thumb_properties = self::getThumbnailCropProperties($image, $crop_properties);

                // Element to track whether cropping is applied or not.
                $container[$crop_type_id][$element_wrapper_name]['values']['crop_applied']['#value'] = 1;
              }
            }

            self::getCropFormElement($element, $element_wrapper_name, $thumb_properties, $edit, $crop_type_id);

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
   * Set All sizes properties of the crops.
   *
   * @return array<string,array>
   *   Set all possible crop zone properties.
   */
  public static function setCoordinatesElement() {
    return [
      'x' => ['label' => t('X coordinate'), 'value' => NULL],
      'y' => ['label' => t('Y coordinate'), 'value' => NULL],
      'width' => ['label' => t('Width'), 'value' => NULL],
      'height' => ['label' => t('Height'), 'value' => NULL],
      'thumb-w' => ['label' => t('Thumbnail Width'), 'value' => NULL],
      'thumb-h' => ['label' => t('Thumbnail Height'), 'value' => NULL],
    ];
  }

  /**
   * Get All sizes properties of the crops for an file.
   *
   * @param array $crops
   *   All crops attached to this file based on URI.
   *
   * @return array<array>
   *   Get all crop zone properties (x, y, height, width),
   */
  public static function getCropProperties(array $crops) {
    $crop_properties = [];
    /** @var \Drupal\crop\Entity\Crop $crop */
    foreach ($crops as $crop) {
      $crop_properties = [
        'anchor' => $crop->anchor(),
        'size' => $crop->size()
      ];
    }

    return $crop_properties;
  }

  /**
   * Update crop elements of crop into the form widget.
   *
   * @param array $thumb_properties
   *   All properties calculate for apply to,
   *   thumbnail image in UI.
   * @param bool $edit
   *   Context of this form.
   *
   * @return array<string,array>
   *   Populate all crop elements into the form.
   */
  public static function getCropFormProperties(array $thumb_properties, $edit) {
    $crop_elements = self::setCoordinatesElement();
    if (!empty($thumb_properties) && $edit) {
      foreach ($crop_elements as $properties => $value) {
        $crop_elements[$properties]['value'] = $thumb_properties[$properties];
      }
    }

    return $crop_elements;
  }

  /**
   * Inject crop elements into the form widget.
   *
   * @param array $element
   *   All form elements of widget.
   * @param string $element_wrapper_name
   *   Name of element contains all crop properties.
   * @param array $thumb_properties
   *   All properties calculate for apply to,
   *   thumbnail image in UI.
   * @param bool $edit
   *   Context of this form.
   * @param string $crop_type_id
   *   The id of the current crop.
   *
   * @return array|NULL
   *   Populate all crop elements into the form.
   */
  public static function getCropFormElement(array &$element, $element_wrapper_name, array $thumb_properties, $edit, $crop_type_id) {
    $crop_properties = self::getCropFormProperties($thumb_properties, $edit);
    // Generate all cordinates elements into the form when,
    // process is active.
    foreach ($crop_properties as $property => $value) {
      $crop_element = &$element['crop_preview_wrapper'][$crop_type_id][$element_wrapper_name]['values'][$property];
      $value_property = self::getCropFormPropertyValue($element, $crop_type_id, $edit, $value['value'], $property);
      $crop_element = [
        '#type' => 'hidden',
        '#attributes' => [
          'class' => ["crop-$property"]
        ],
        '#value' => $value_property,
      ];
    }

    return $element;
  }

  /**
   * Get default value of property elements.
   *
   * @param array $element
   *   All form elements without crop properties.
   * @param string $crop_type
   *   The id of the current crop.
   * @param bool $edit
   *   Context of this form.
   * @param int|NULL $value
   *   The values calculated by getCropFormProperties().
   * @param string $property
   *   Name of current property @see setCoordinatesElement().
   *
   * @return int|NULL
   *   Value of this element.
   */
  public static function getCropFormPropertyValue(array &$element, $crop_type, $edit, $value, $property) {
    // Standard case.
    if (!empty($edit) && !empty($value)) {
      return $value;
    }

    // Populate value when ajax populates values after process.
    if (isset($element['#value']) && isset($element['crop_preview_wrapper'])) {
      $ajax_element = &$element['#value']['crop_preview_wrapper']['container'][$crop_type]['values'];
      return (isset($ajax_element[$property]) && !empty($ajax_element[$property])) ? $ajax_element[$property] : NULL;
    }

    return NULL;
  }

  /**
   * Calculate properties of thumbnail preview.
   *
   * @param Image $image
   *   Image object to represent uploaded image file.
   * @param array $original_crop
   *   All properties returned by the crop plugin (js),
   *   and the size of thumbnail image.
   * @param string $preview
   *   An array of values for the contained properties of image_crop widget.
   *
   * @return array<double>
   *   All properties (x, y, crop height, crop width,
   *   thumbnail height, thumbnail width), to apply the real crop
   *   into thumbnail preview.
   */
  public static function getThumbnailCropProperties(Image $image, array $original_crop, $preview = 'crop_thumbnail') {
    $crop_thumbnail = [];

    $thumbnail_properties = ImageWidgetCropManager::getThumbnailCalculatedProperties($image, $preview);
    if (!isset($thumbnail_properties) || empty($thumbnail_properties)) {
      throw new \RuntimeException('Impossible to retrives thumbnail properties');
    }

    $delta = $thumbnail_properties['delta'];

    // Get the Crop selection Size (into Uploaded image) &,
    // calculate selection for Thumbnail.
    $crop_thumbnail['height'] = round($original_crop['size']['height'] / $delta);
    $crop_thumbnail['width'] = round($original_crop['size']['width'] / $delta);

    // Calculate the Top-Left corner for Thumbnail.
    // Get the real thumbnail sizes.
    $crop_thumbnail['thumb-w'] = $thumbnail_properties['thumbnail_width'];
    $crop_thumbnail['thumb-h'] = $thumbnail_properties['thumbnail_height'];
    $crop_thumbnail['x'] = round($original_crop['anchor']['x'] / $delta);
    $crop_thumbnail['y'] = round($original_crop['anchor']['y'] / $delta);

    return $crop_thumbnail;
  }

  /**
   * Verify if ImageStyle is correctly configured.
   *
   * @param array $styles
   *   The list of available ImageStyle.
   *
   * @return array<integer>
   *   The list of styles filtred.
   */
  public function getAvailableCropImageStyle(array $styles) {
    $available_styles = [];
    foreach ($styles as $style_id => $style_label) {
      $style_loaded = $this->imageStyleStorage->loadByProperties(['name' => $style_id]);
      /** @var \Drupal\image\Entity\ImageStyle $image_style */
      $image_style = $style_loaded[$style_id];
      $effect_data = $this->imageWidgetCrop->getEffectData($image_style, 'width');
      if (!empty($effect_data)) {
        $available_styles[$style_id] = $style_label;
      }
    }

    return $available_styles;
  }

  /**
   * Verify if the crop is used by a ImageStyle.
   *
   * @param array $crop_list
   *   The list of existent Crop Type.
   *
   * @return array<integer>
   *   The list of Crop Type filtred.
   */
  public function getAvailableCropType(array $crop_list) {
    $available_crop = [];
    foreach ($crop_list as $crop_machine_name => $crop_label) {
      $image_styles = $this->imageWidgetCrop->getImageStylesByCrop($crop_machine_name);
      if (!empty($image_styles)) {
        $available_crop[$crop_machine_name] = $crop_label;
      }
    }

    return $available_crop;
  }

  /**
   * Verify if the element have an image file.
   *
   * @param array $element
   *   A form element array containing basic properties for the widget.
   * @param array $variables
   *   An array with all existent variables for render.
   *
   * @return array<string,array>
   *   The variables with width & height image informations.
   */
  public static function getFileImageVariables(array $element, array &$variables) {
    // Determine image dimensions.
    if (isset($element['#value']['width']) && isset($element['#value']['height'])) {
      $variables['width'] = $element['#value']['width'];
      $variables['height'] = $element['#value']['height'];
    }
    else {
      /** @var \Drupal\Core\Image\Image $image */
      $image = \Drupal::service('image.factory')->get($variables['uri']);
      if ($image->isValid()) {
        $variables['width'] = $image->getWidth();
        $variables['height'] = $image->getHeight();
      }
      else {
        $variables['width'] = $variables['height'] = NULL;
      }
    }

    return $variables;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    $element['crop_preview_image_style'] = [
      '#title' => t('Crop preview image style'),
      '#type' => 'select',
      '#options' => $this->getAvailableCropImageStyle(image_style_options(FALSE)),
      '#default_value' => $this->getSetting('crop_preview_image_style'),
      '#description' => t('The preview image will be shown while editing the content.'),
      '#weight' => 15,
    ];

    $element['crop_list'] = [
      '#title' => t('Crop Type'),
      '#type' => 'select',
      '#options' => $this->getAvailableCropType(CropType::getCropTypeNames()),
      '#empty_option' => t('<@no-preview>', ['@no-preview' => t('no preview')]),
      '#default_value' => $this->getSetting('crop_list'),
      '#multiple' => TRUE,
      '#required' => TRUE,
      '#description' => t('The type of crop to apply to your image. If your Crop Type not appear here, set an image style use your Crop Type'),
      '#weight' => 16,
    ];

    $element['show_crop_area'] = [
      '#title' => t('Always expand crop area'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('show_crop_area'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   *
   * @return array<array>
   *   A short summary of the widget settings.
   */
  public function settingsSummary() {
    $preview = [];

    $image_styles = image_style_options(FALSE);
    // Unset possible 'No defined styles' option.
    unset($image_styles['']);

    // Styles could be lost because of enabled/disabled modules that defines
    // their styles in code.
    $image_style_setting = $image_styles[$this->getSetting('preview_image_style')];
    $crop_preview = $image_styles[$this->getSetting('crop_preview_image_style')];
    $crop_list = $this->getSetting('crop_list');
    $crop_show_button = $this->getSetting('show_crop_area');

    $preview[] = t('Always expand crop area: @bool', ['@bool' => ($crop_show_button) ? 'Yes' : 'No']);

    if (isset($image_style_setting)) {
      $preview[] = t('Preview image style: @style', ['@style' => $image_style_setting]);
    }
    else {
      $preview[] = t('No preview image style');
    }

    if (isset($crop_preview)) {
      $preview[] = t('Preview crop zone image style: @style', ['@style' => $crop_preview]);
    }

    if (!empty($crop_list)) {
      $preview[] = t('Crop Type used: @list', ['@list' => implode(", ", $crop_list)]);
    }

    return $preview;
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string,array>
   *   The form elements for a single widget for this field.
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    // Add properties needed by process() method.
    $element['#crop_list'] = $this->getSetting('crop_list');
    $element['#crop_preview_image_style'] = $this->getSetting('crop_preview_image_style');
    $element['#crop_types_list'] = $this->cropTypeStorage->loadMultiple();
    $element['#show_crop_area'] = $this->getSetting('show_crop_area');

    return parent::formElement($items, $delta, $element, $form, $form_state);
  }

}
