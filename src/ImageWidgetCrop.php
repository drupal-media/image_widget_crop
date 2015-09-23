<?php

/**
 * @file
 * Contains of \Drupal\image_widget_crop\ImageWidgetCrop.
 */

namespace Drupal\image_widget_crop;

use Drupal\Core\Render\Element;
use Drupal\image\Entity\ImageStyle;

class ImageWidgetCrop {

  /**
   * Gets crop's size.
   *
   * @param \Drupal\image\Entity\ImageStyle $image_style
   *   The image style.
   *
   * @return string
   *   The ratio to the lowest common denominator.
   */
  public function getSizeRatio(ImageStyle $image_style) {
    // Get the properties of this ImageStyle.
    $properties = $this->getImageStyleSizes($image_style);
    if (isset($properties) && (!empty($properties['width']) || !empty($properties['height']))) {
      $gcd_object = gmp_gcd($properties['width'], $properties['height']);
      $gcd = gmp_intval($gcd_object);

      if (!empty($gcd) && $gcd != '1') {
        return round($properties['width'] / $gcd) . ':' . round($properties['height'] / $gcd);
      }
      elseif (!empty($gcd)) {
        return $gcd . ':' . $gcd;
      }
      else {
        return NULL;
      }
    }
  }

  /**
   * Parse the effect into one image style and get the sizes data.
   *
   * @param \Drupal\image\Entity\ImageStyle $image_style
   *   The image style.
   *
   * @return array
   *   The data dimensions (width & height) into this ImageStyle.
   */
  public function getImageStyleSizes(ImageStyle $image_style) {
    foreach ($image_style->getEffects() as $uuid => $effect) {
      /** @var \Drupal\image\ImageEffectInterface $effect */
      if ($effect->getPluginId() != 'image_widget_crop_crop') {
        $data = $effect->getConfiguration()['data'];
        if (isset($data) && (isset($data['width']) && isset($data['height']))) {
          return [
            'width' => $data['width'],
            'height' => $data['height'],
          ];
        }
      }
    }
  }

  /**
   * Get the size and position of the crop since the properties of the thumbnail.
   *
   * @param int $original_height
   *   The original height of image.
   * @param array $properties
   *   The original height of image.
   *
   * @return array
   *   The data dimensions (width & height) into this ImageStyle.
   */
  public function getCropOriginalDimension($original_height, array $properties) {
    $delta = $original_height / $properties['thumb-h'];
    $crop_position = $this->getCalculatePosition($properties, $delta);
    $crop_size = $this->getCalculateCropSize($properties, $delta);

    return array_merge($crop_size, $crop_position);
  }

  /**
   * Get the left-corner position of crop selection.
   *
   * @param array $properties
   *   All properties returned by the crop plugin (js),
   *   and the size of thumbnail image.
   * @param int $delta
   *   The calculated difference between original height and thumbnail height.
   *
   * @return array
   *   Coordinate (x & y) of crop position.
   */
  public function getCalculatePosition(array $properties, $delta) {
    foreach ($properties as $key => $coordinate) {
      if (isset($coordinate) && $coordinate >= 0) {
        $original_coordinates[$key] = $coordinate * $delta;
      }
    }

    return [
      'x' => round($original_coordinates['x1']),
      'y' => round($original_coordinates['y1']),
    ];
  }

  /**
   * Get size of the crop selection.
   *
   * @param array $properties
   *   All properties returned by the crop plugin (js),
   *   and the size of thumbnail image.
   * @param int $delta
   *   The calculated difference between original height and thumbnail height.
   *
   * @return array
   *   Size of crop selection in (width & height).
   */
  public function getCalculateCropSize(array $properties, $delta) {

    // Parse the properties of image_crop element,
    // and calculate original sizes of crop selection.
    foreach ($properties as $key => $coordinate) {
      if (isset($coordinate) && $coordinate >= 0) {
        $original_coordinates[$key] = $coordinate * $delta;
      }
    }
    return [
      'width' => round($original_coordinates['crop-w']),
      'height' => round($original_coordinates['crop-h']),
    ];
  }

  /**
   * Get the crop type correspond to current image style.
   *
   * @param \Drupal\image\Entity\ImageStyle $image_style
   *   The properties of the crop applied to the original image (dimensions).
   *
   * @return string $crop_type
   *   The name of Crop type.
   */
  public function getCropType(ImageStyle $image_style) {
    // Confirm that all effects on the image style have settings that match
    // what was saved.
    $uuids = array();

    /** @var  \Drupal\image\ImageEffectInterface $effect */
    foreach ($image_style->getEffects() as $uuid => $effect) {
      // Store the uuid for later use.
      $uuids[$effect->getPluginId()] = $uuid;
    }

    if (isset($uuids['image_widget_crop_crop'])) {
      $crop_type = $image_style->getEffect($uuids['image_widget_crop_crop'])
        ->getConfiguration()['data']['crop_type'];
    }
    else {
      $crop_type = FALSE;
    }

    return $crop_type;
  }


  /**
   * Get original size of a thumbnail image.
   *
   * @param array $properties
   *   All properties returned by the crop plugin (js),
   *   and the size of thumbnail image.
   * @param array|mixed $field_value
   *   An array of values for the contained properties of image_crop widget.
   * @param \Drupal\image\Entity\ImageStyle $image_style
   *   The machine name of ImageStyle.
   * @param bool $edit
   *   The action form.
   * @param ImageWidgetCrop $image_crop
   *   Instance of ImageWidgetCrop.
   */
  public function cropByImageStyle(array $properties, $field_value, $image_style, $edit) {

    $crop_properties = $this->getCropOriginalDimension($field_value['height'], $properties);
    $image_style_name = $image_style->getName();

    // Get crop type for current ImageStyle.
    $crop_type = $this->getCropType($image_style);

    if (isset($edit)) {
      $crop = \Drupal::service('entity.manager')
        ->getStorage('crop')->loadByProperties([
          'type' => $crop_type,
          'uri' => $field_value['file-uri'],
          'image_style' => $image_style_name
        ]);

      if (!empty($crop)) {
        /** @var \Drupal\crop\Entity\Crop $crop_entity */
        foreach ($crop as $crop_id => $crop_entity) {
          $crop_position = $crop_entity->position();
          $crop_size = $crop_entity->size();
          $old_crop = array_merge($crop_position, $crop_size);
          // Verify if the crop (dimensions / positions) have changed.
          if (($crop_properties['x'] == $old_crop['x'] && $crop_properties['width'] == $old_crop['width']) && ($crop_properties['y'] == $old_crop['y'] && $crop_properties['height'] == $old_crop['height'])) {
            return;
          }
          else {
            // Parse all properties if this crop have changed.
            foreach ($crop_properties as $crop_coordinate => $value) {
              // Edit the crop properties if he have changed.
              $crop[$crop_id]->set($crop_coordinate, $value, $notify = TRUE)
                ->save();
            }

            // Flush the cache of this ImageStyle.
            $image_style->flush($field_value['file-uri']);
          }
        }
      }
      else {
        $this->saveCrop($crop_properties, $field_value, $image_style, $crop_type);
      }
    }
    else {
      $this->saveCrop($crop_properties, $field_value, $image_style, $crop_type);
    }
  }

  /**
   * Save the crop when this crop not exist.
   *
   * @param array $crop_properties
   *   The properties of the crop applied to the original image (dimensions).
   * @param array|mixed $field_value
   *   An array of values for the contained properties of image_crop widget.
   * @param \Drupal\image\Entity\ImageStyle $image_style
   *   The machine name of ImageStyle.
   * @param string $crop_type
   *   The name of Crop type.
   */
  public function saveCrop(array $crop_properties, $field_value, ImageStyle $image_style, $crop_type) {
    if ($crop_type) {
      $values = [
        'type' => $crop_type,
        'entity_id' => $field_value['file-id'],
        'entity_type' => 'file',
        'uri' => $field_value['file-uri'],
        'x' => $crop_properties['x'],
        'y' => $crop_properties['y'],
        'width' => $crop_properties['width'],
        'height' => $crop_properties['height'],
        'image_style' => $image_style->getName(),
      ];

      // Save crop with previous values.
      /** @var \Drupal\crop\CropInterface $crop */
      $crop = \Drupal::entityManager()->getStorage('crop')->create($values);
      $crop->save();

      // Generate the image derivate uri.
      $destination_uri = $image_style->buildUri($field_value['file-uri']);

      // Create a derivate of the original image with a good uri.
      $image_style->createDerivative($field_value['file-uri'], $destination_uri);

      // Flush the cache of this ImageStyle.
      $image_style->flush($field_value['file-uri']);
    }
    else {
      drupal_set_message(t("The type of crop does not exist, please check the configuration of the ImageStyle ('@imageStyle')", ['@imageStyle' => $image_style_name]), 'error');
    }
  }

  /**
   * Delete the crop when user delete it.
   *
   * @param string $file_uri
   *   Uri of image uploaded by user.
   * @param \Drupal\image\Entity\ImageStyle $image_style
   *   The ImageStyle object.
   */
  public function deleteCrop($file_uri, ImageStyle $image_style) {
    // Get crop type for current ImageStyle.
    $crop_type = $this->getCropType($image_style);

    /** @var \Drupal\crop\CropInterface $crop */
    $crop = \Drupal::service('entity.manager')
      ->getStorage('crop')->loadByProperties([
        'type' => $crop_type,
        'uri' => $file_uri,
        'image_style' => $image_style->getName(),
      ]);

    if (isset($crop)) {
      /** @var \Drupal\crop\CropInterface $crop */
      $cropStorage = \Drupal::entityManager()->getStorage('crop');
      $cropStorage->delete($crop);
      
      // Flush the cache of this ImageStyle.
      $image_style->flush($file_uri);
    }
  }

}
