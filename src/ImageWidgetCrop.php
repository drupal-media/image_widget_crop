<?php

/**
 * @file
 * Contains of \Drupal\image_widget_crop\ImageWidgetCrop.
 */

namespace Drupal\image_widget_crop;

use Drupal\image\Entity\ImageStyle;

/**
 * ImageWidgetCrop calculation class.
 */
class ImageWidgetCrop {
  /**
   * Gets crop's size.
   *
   * @param \Drupal\image\Entity\ImageStyle $image_style
   *   The image style.
   *
   * @return string|NULL
   *   The ratio to the lowest common denominator.
   */
  public function getSizeRatio(ImageStyle $image_style) {
    // Get the properties of this ImageStyle.
    $properties = $this->getImageStyleSizes($image_style);
    if (isset($properties) && (!empty($properties['width']) || !empty($properties['height']))) {
      $gcd = $this->calculateGcd($properties['width'], $properties['height']);

      if (!empty($gcd) && $gcd != '1') {
        return round($properties['width'] / $gcd) . ':' . round($properties['height'] / $gcd);
      }
      // When you have a non-standard size ratio,
      // is not displayed the lowest common denominator.
      if (!empty($gcd)) {
        return $properties['width'] . ':' . $properties['height'];
      }

      // If you not have an available ratio return an free aspect ratio.
      return '0:0';
    }
  }

  /**
   * Parse the effect into one image style and get the sizes data.
   *
   * @param \Drupal\image\Entity\ImageStyle $image_style
   *   The image style.
   *
   * @return array<integer>|NULL
   *   The data dimensions (width & height) into this ImageStyle.
   */
  public function getImageStyleSizes(ImageStyle $image_style) {
    foreach ($image_style->getEffects() as $effect) {
      /** @var \Drupal\image\ImageEffectInterface $effect */
      if ($effect->getPluginId() != 'image_widget_crop_crop') {
        $data = $effect->getConfiguration()['data'];
        if (isset($data) && (isset($data['width']) && isset($data['height']))) {
          $sizes = [
            'width' => (int) $data['width'],
            'height' => (int) $data['height']
          ];
          return $sizes;
        }
      }
    }
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
   */
  public function cropByImageStyle(array $properties, $field_value, ImageStyle $image_style, $edit, $crop_type) {

    $crop_properties = $this->getCropOriginalDimension($field_value['height'], $properties);
    $image_style_name = $image_style->getName();

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
              $crop[$crop_id]->set($crop_coordinate, $value, TRUE)
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
   * Get the size and position of the crop.
   *
   * @param int $original_height
   *   The original height of image.
   * @param array $properties
   *   The original height of image.
   *
   * @return array<double>
   *   The data dimensions (width & height) into this ImageStyle.
   */
  public function getCropOriginalDimension($original_height, array $properties) {
    $delta = $original_height / $properties['thumb-h'];

    // Get Center coordinate of crop zone.
    $axis_coordinate = $this->getAxisCoordinates(
      ['x' => $properties['x1'], 'y' => $properties['y1']],
      ['width' => $properties['crop-w'], 'height' => $properties['crop-h']]
    );

    // Calculate coordinates (position & sizes) of crop zone.
    $crop_coordinates = $this->getCoordinates([
      'width' => $properties['crop-w'],
      'height' => $properties['crop-h'],
      'x' => $axis_coordinate['x'],
      'y' => $axis_coordinate['y'],
    ], $delta);

    return $crop_coordinates;
  }

  /**
   * Get center of crop selection.
   *
   * @param int[] $axis
   *   Coordinates of x-axis & y-axis.
   * @param array $crop_selection
   *   Coordinates of crop selection (width & height).
   *
   * @return array<integer>
   *   Coordinates (x-axis & y-axis) of crop selection zone.
   */
  public function getAxisCoordinates(array $axis, array $crop_selection) {
    return [
      'x' => (int) $axis['x'] + ($crop_selection['width'] / 2),
      'y' => (int) $axis['y'] + ($crop_selection['height'] / 2),
    ];
  }

  /**
   * Calculate all coordinates for apply crop into original picture.
   *
   * @param array $properties
   *   All properties returned by the crop plugin (js),
   *   and the size of thumbnail image.
   * @param int $delta
   *   The calculated difference between original height and thumbnail height.
   *
   * @return array<double>
   *   Coordinates (x & y or width & height) of crop.
   */
  public function getCoordinates(array $properties, $delta) {
    $original_coordinates = [];

    foreach ($properties as $key => $coordinate) {
      if (isset($coordinate) && $coordinate >= 0) {
        $original_coordinates[$key] = round($coordinate * $delta);
      }
    }

    return $original_coordinates;
  }

  /**
   * Get the crop type correspond to current image style.
   *
   * @param \Drupal\image\Entity\ImageStyle $image_style
   *   The properties of the crop applied to the original image (dimensions).
   *
   * @return string
   *   The name of Crop type set in current image_style.
   */
  public function getCropType(ImageStyle $image_style) {
    // Confirm that all effects on the image style have settings that match
    // what was saved.
    $uuids = [];

    /* @var  \Drupal\image\ImageEffectInterface $effect */
    foreach ($image_style->getEffects() as $uuid => $effect) {
      // Store the uuid for later use.
      $uuids[$effect->getPluginId()] = $uuid;
    }

    if (isset($uuids['crop_crop'])) {
      return $image_style->getEffect($uuids['crop_crop'])
        ->getConfiguration()['data']['crop_type'];
    }
  }

  /**
   * Save the crop when this crop not exist.
   *
   * @param double[] $crop_properties
   *   The properties of the crop applied to the original image (dimensions).
   * @param array|mixed $field_value
   *   An array of values for the contained properties of image_crop widget.
   * @param \Drupal\image\Entity\ImageStyle $image_style
   *   The machine name of ImageStyle.
   * @param string $crop_type
   *   The name of Crop type.
   */
  public function saveCrop(array $crop_properties, $field_value, ImageStyle $image_style, $crop_type) {
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
      $crop_storage = \Drupal::entityManager()->getStorage('crop');
      $crop_storage->delete($crop);

      // Flush the cache of this ImageStyle.
      $image_style->flush($file_uri);
    }
  }

  /**
   * Calculate the greatest common denominator of two numbers.
   *
   * @param int $a
   *   First number to check.
   * @param int $b
   *   Second number to check.
   *
   * @return int|null
   *   Greatest common denominator of $a and $b.
   */
  private static function calculateGcd($a, $b) {
    if (extension_loaded('gmp_gcd')) {
      $gcd = gmp_intval(gmp_gcd($a, $b));
    }
    else {
      if ($b > $a) {
        $gcd = self::calculateGcd($b, $a);
      }
      else {
        while ($b > 0) {
          $t = $b;
          $b = $a % $b;
          $a = $t;
        }
        $gcd = $a;
      }
    }

    return $gcd;
  }

}
