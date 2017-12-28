<?php

namespace Drupal\image_widget_crop\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'image_widget_modal_crop' widget.
 *
 * @FieldWidget(
 *   id = "image_widget_modal_crop",
 *   label = @Translation("ImageWidget Modal crop"),
 *   field_types = {
 *     "image"
 *   }
 * )
 */
class ImageCropModalWidget extends ImageCropWidget {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
        'modal_title' => t('Crop the image'),
        'modal_width' => 0,
        'modal_height' => 0,
        'modal_min_width' => 200,
        'modal_min_height' => 0,
      ] + parent::defaultSettings();
  }

  /**
   * Form API callback: Processes a image_crop_modal field element.
   *
   * Expands the image_image type to include the alt and title fields.
   *
   * This method is assigned as a #process callback in formElement() method.
   *
   * @return array
   *   The elements with parents fields.
   */
  public static function process($element, FormStateInterface $form_state, $form) {
    $element = parent::process($element, $form_state, $form);

    // Add modal specific settings.
    if (!empty($element['image_crop'])) {

      // Change the form item type to modal crop.
      $element['image_crop']['#type'] = 'image_crop_modal';

      $element['image_crop'] += [
        '#modal_title' => $element['#modal_title'],
        '#modal_width' => $element['#modal_width'],
        '#modal_height' => $element['#modal_height'],
        '#modal_min_width' => $element['#modal_min_width'],
        '#modal_min_height' => $element['#modal_min_height'],
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

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

    // Currently we limit display of crop types to 1 in the modal dialog.
    /// If someone will need more - it's a good place to start from.
    $element['crop_list']['#multiple'] = FALSE;

    return $element;
  }

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   A short summary of the widget settings.
   */
  public function settingsSummary() {
    $preview = parent::settingsSummary();

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
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    // Add modal specific settings.
    $options = ['title', 'width', 'height', 'min_width', 'min_height'];
    foreach ($options as $option) {
      $element['#modal_' . $option] = $this->getSetting('modal_' . $option);
    }

    return $element;
  }

}
