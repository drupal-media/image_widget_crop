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

    $element['modal'] = [
      '#type' => 'details',
      '#title' => t('Modal dialog settings'),
      '#open' => TRUE,
    ];

    $element['modal']['modal_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $this->getSetting('modal_title'),
    ];

    $element['modal']['size'] = [
      '#type' => 'fieldset',
      '#title' => t('Width X Height'),
      '#attributes' => [
        'class' => ['container-inline'],
      ],
    ];

    $element['modal']['size']['modal_width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Width'),
      '#title_display' => 'invisible',
      '#size' => 4,
      '#element_validate' => ['element_validate_integer_positive'],
      '#default_value' => $this->getSetting('modal_width'),
      '#suffix' => ' x ',
    ];

    $element['modal']['size']['modal_height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Height'),
      '#title_display' => 'invisible',
      '#size' => 4,
      '#element_validate' => ['element_validate_integer_positive'],
      '#default_value' => $this->getSetting('modal_height'),
      '#suffix' => 'px',
    ];

    $element['modal']['min_size'] = [
      '#type' => 'fieldset',
      '#title' => t('Min width X Min height'),
      '#attributes' => [
        'class' => ['container-inline'],
      ],
    ];

    $element['modal']['min_size']['modal_min_width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Min width'),
      '#title_display' => 'invisible',
      '#size' => 4,
      '#element_validate' => ['element_validate_integer_positive'],
      '#default_value' => $this->getSetting('modal_min_width'),
      '#suffix' => ' x ',
    ];

    $element['modal']['min_size']['modal_min_height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Min height'),
      '#title_display' => 'invisible',
      '#size' => 4,
      '#element_validate' => ['element_validate_integer_positive'],
      '#default_value' => $this->getSetting('modal_min_height'),
      '#suffix' => 'px',
    ];

    $element['modal']['help'] = [
      '#markup' => t('Leave 0 or blank to scale modal dialog automatically.'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $s = $this->getSettings();

    // Add modal specific settings.
    $options = ['title', 'width', 'height', 'min_width', 'min_height'];
    foreach ($options as $option) {
      $element['#modal_' . $option] = $this->getSetting('modal_' . $option);
    }

    return $element;
  }

}
