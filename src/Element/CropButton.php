<?php

/**
 * @file
 * Contains \Drupal\image_widget_crop\Element\CropButton.
 */

namespace Drupal\image_widget_crop\Element;

use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\Button;

/**
 * Provides an specific button form element.
 *
 * @FormElement("crop_button")
 */
class CropButton extends Button {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return array(
      '#input' => TRUE,
      '#name' => 'op',
      '#is_button' => TRUE,
      '#executes_submit_callback' => FALSE,
      '#limit_validation_errors' => FALSE,
      '#pre_render' => array(
        array($class, 'preRenderButton'),
      ),
      '#theme_wrappers' => array('input__submit'),
    );
  }

  /**
   * Prepares a #type 'button' render element for input.html.twig.
   *
   * @param array $element
   *   An associative array containing the properties of the element.
   *   Properties used: #attributes, #button_type, #name, #value.
   *
   * @return array
   *   The $element with prepared variables ready for input.html.twig.
   */
  public static function preRenderButton(array $element) {
    $element = parent::preRenderButton($element);
    $element['#attributes']['type'] = 'button';
    return $element;
  }

}
