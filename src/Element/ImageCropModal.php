<?php

namespace Drupal\image_widget_crop\Element;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Render\Element;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form element for crop in modal.
 *
 * @FormElement("image_crop_modal")
 */
class ImageCropModal extends ImageCrop {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $info = parent::getInfo();
    $info['#modal_title'] = t('Crop the image');
    $info['#modal_width'] = 0;
    $info['#modal_height'] = 0;
    $info['#modal_min_width'] = 200;
    $info['#modal_min_height'] = 0;
    $info['#attached']['library'][] = 'image_widget_crop/modal';
    return $info;
  }

  /**
   * Render API callback: Expands the image_crop element type.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   form actions container.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The processed element.
   */
  public static function processCrop(array &$element, FormStateInterface $form_state, array &$complete_form) {
    parent::processCrop($element, $form_state, $complete_form);

    // If parent didn't provide crop wrapper then obviously something went
    // terribly wrong.
    if (empty($element['crop_wrapper'])) {
      return $element;
    }

    // Convert existing "details" form field to container to remove collapsible
    // fieldsets.
    $element['crop_wrapper']['#type'] = 'container';

    // Slightly enhance the UX on forms.
    $element['crop_wrapper']['#weight'] = -1;

    // Don't show the warning in the form, we'll show it later in modal dialog
    // if necessary.
    if (!empty($element['crop_reuse'])) {
      $element['crop_reuse']['#access'] = FALSE;
    }

    // Add a simple button which opens a modal with image crop selection.
    $id_prefix = implode('-', $element['#parents']);
    $element['modal_button'] = [
      '#type' => 'button',
      '#value' => t('Edit crop'),
      '#name' => strtr($id_prefix, '-', '_') . '_modal',
      '#attributes' => [
        'class' => ['edit-crop'],
      ],
      '#ajax' => [
        'callback' => '\Drupal\image_widget_crop\Element\ImageCropModal::ajaxModal',
      ],
    ];

    // Modify the display of the crop area in the default Image Crop Widget.
    // We render only "values" here, because this form item is necessary to
    // submit form values. All other elements will be shown in the modal dialog.
    foreach (Element::children($element['crop_wrapper']) as $crop_type) {
      if (empty($element['crop_wrapper'][$crop_type]['crop_container'])) {
        if ($element['crop_wrapper'][$crop_type]['#type'] == 'vertical_tabs') {
          $element['crop_wrapper'][$crop_type]['#access'] = FALSE;
        }
        continue;
      }
      $crop_item = &$element['crop_wrapper'][$crop_type];

      // Covert tab with every crop type into container to avoid visual
      // rendering of it in the main form.
      $crop_item['#type'] = 'container';


      // That's a small trick to generate (if not exist) & preload image in
      // crop image style ahead of display in popup. It prevents popup from
      // jump on load and users from waiting for image to generate.
      $crop_item['crop_container']['image_preload'] = $crop_item['crop_container']['image'];
      unset($crop_item['crop_container']['image_preload']['#attributes']['data-drupal-iwc']);
      $crop_item['crop_container']['image_preload']['#attributes']['style'] = 'display:none';

      // Hide image with crop selection & reset button from the main form.
      // We'll show those items in the modal dialog.
      $crop_item['crop_container']['image']['#access'] = FALSE;
      if (!empty($crop_item['crop_container']['reset'])) {
        $crop_item['crop_container']['reset']['#access'] = FALSE;
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function ajaxModal(array &$form, FormStateInterface $form_state) {

    // Get parents of triggering elements.
    $parents = $form_state->getTriggeringElement()['#array_parents'];
    array_pop($parents);

    $image_crop = NestedArray::getValue($form, $parents);

    // Restore access to crop_reuse message.
    if (!empty($image_crop['crop_reuse'])) {
      $image_crop['crop_reuse']['#access'] = TRUE;
    }

    // We don't want to show the modal button any more.
    $image_crop['modal_button']['#access'] = FALSE;

    // Remove "display:none" style recently added to hide it on the main form.
    // Now we do need to show it in the modal dialog.
    unset($image_crop['crop_wrapper']['#attributes']['style']);

    // Restore access to image crop and "Reset" button of every crop type.
    foreach (Element::children($image_crop['crop_wrapper']) as $crop_type) {
      if (empty($image_crop['crop_wrapper'][$crop_type]['crop_container'])) {
        continue;
      }
      $crop_item = &$image_crop['crop_wrapper'][$crop_type]['crop_container'];
      $crop_item['image']['#access'] = TRUE;
      if (!empty($crop_item['reset'])) {
        $crop_item['reset']['#access'] = TRUE;
      }
    }

    $image_crop['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Apply'),
      '#prefix' => '<div class="form-actions">',
      '#suffix' => '</div>',
      '#attributes' => [
        'class' => ['button--primary', 'image-crop-apply'],
      ],
    ];

    // Build out dialog.ui settings.
    $settings = [];
    $options = ['width', 'height', 'min_width', 'min_height'];
    foreach ($options as $option) {
      if (!empty($image_crop['#modal_' . $option])) {
        $dialogOption = str_replace(['min_width', 'min_height'], ['minWidth', 'minHeight'], $option);
        $settings[$dialogOption] = $image_crop['#modal_' . $option];
      }
    }

    // Get dialog title.
    $title = $image_crop['#modal_title'];

    $response = new AjaxResponse();
    $response->addCommand(new OpenModalDialogCommand($title, $image_crop, $settings));
    return $response;
  }

}
