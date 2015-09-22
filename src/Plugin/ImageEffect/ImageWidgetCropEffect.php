<?php

/**
 * @file
 * Contains \Drupal\image_widget_crop\Plugin\ImageEffect\ImageWidgetCropEffect.
 */

namespace Drupal\image_widget_crop\Plugin\ImageEffect;

use Drupal\Core\Image\ImageInterface;
use Drupal\crop\Plugin\ImageEffect\CropEffect;

/**
 * Extend Crops API and provide an image resource cropped by center.
 *
 * @ImageEffect(
 *   id = "image_widget_crop_crop",
 *   label = @Translation("ImageWidget Manual crop"),
 *   description = @Translation("Applies manually provided crop to the image by center.")
 * )
 */
class ImageWidgetCropEffect extends CropEffect {

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    if (empty($this->configuration['crop_type']) || !$this->typeStorage->load($this->configuration['crop_type'])) {
      $this->logger->error('Manual image crop failed due to misconfigured crop type on %path.', ['%path' => $image->getSource()]);
      return FALSE;
    }

    if ($crop = $this->getCrop($image)) {
      // Use position instead of anchor(),
      // ImageWidgetCrop already set coordinates by center
      $anchor = $crop->position();
      $size = $crop->size();

      if (!$image->crop($anchor['x'], $anchor['y'], $size['width'], $size['height'])) {
        $this->logger->error('Manual image crop failed using the %toolkit toolkit on %path (%mimetype, %width x %height)', [
            '%toolkit' => $image->getToolkitId(),
            '%path' => $image->getSource(),
            '%mimetype' => $image->getMimeType(),
            '%width' => $image->getWidth(),
            '%height' => $image->getHeight(),
          ]
        );
        return FALSE;
      }
    }

    return TRUE;
  }

}
