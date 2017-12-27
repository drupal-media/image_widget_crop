/**
 * @file
 * Defines the behaviors needed for modal widget integration.
 */

(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.imageWidgetModalCrop = {
    attach: function (context) {

      $('.image-crop-apply', context).click(function() {
        var $wrapper = $('.crop-preview-wrapper__value', context);
        $.each($('input', $wrapper), function(key, input) {
          var name = $(input).attr('name');
          var value = $(input).attr('value');
          $('input[name="' + name + '"]').val(value);
        });

        Drupal.dialog('#drupal-modal').close();

      });

    }
  };

}(jQuery, Drupal));
