/**
 * @file
 * Defines the behaviors needed for modal widget integration.
 */

(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.imageWidgetModalCrop = {
    attach: function (context) {

      // Handle click on "Apply" button in dialog.
      $('.image-crop-apply', context).click(function() {
        var $wrapper = $('.crop-preview-wrapper__value', context);

        // Copy crop values from the dialog inside the real form.
        $.each($('input', $wrapper), function(key, input) {
          var name = $(input).attr('name');
          var value = $(input).attr('value');
          $('input[name="' + name + '"]').val(value);
        });

        // Close the modal dialog.
        Drupal.dialog('#drupal-modal').close();
      });

    }
  };

}(jQuery, Drupal));
