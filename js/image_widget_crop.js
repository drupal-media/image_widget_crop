/**
 * @file
 * Provides JavaScript additions to the managed file field type.
 *
 * This file provides progress bar support (if available), popup windows for
 * file previews, and disabling of other file fields during Ajax uploads (which
 * prevents separate file fields from accidentally uploading files).
 */

(function ($, Drupal) {

  "use strict";

  /**
   * Attach behaviors to links within managed file elements.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.image_widget_crop = {
    attach: function (context, settings) {
      var path = settings.path.currentPath;
      var edit = path.search('edit');
      $('section.ratio-list:not(.crop-processed)').addClass('crop-processed').each(function () {
        $(this).find('ul li').on('click', function (event) {
          event.preventDefault();

          // Get elements.
          var ElementRatio = $(this).data('ratio');
          var ElementName = $(this).data('name');

          // On click delete all active class.
          $('.ratio-list ul li').removeClass('active');

          // Active only this li.
          $(this).addClass('active');

          var $wrapperCropContainer = $(this).closest('.crop-wrapper').find('.preview-wrapper-crop');
          var $wrapperRatioName = $wrapperCropContainer.find('#' + ElementName);
          var $cropX1 = $('.crop-x1');
          var $cropY1 = $('.crop-y1');
          var $cropX2 = $('.crop-x2');
          var $cropY2 = $('.crop-y2');
          var $cropW = $('.crop-crop-w');
          var $cropH = $('.crop-crop-h');
          var $cropThumbW = $('.crop-thumb-w');
          var $cropThumbH = $('.crop-thumb-h');

          // Get coordinates & positions.
          var posx1 = $wrapperCropContainer.find($wrapperRatioName).find($cropX1);
          var posy1 = $wrapperCropContainer.find($wrapperRatioName).find($cropY1);
          var posx2 = $wrapperCropContainer.find($wrapperRatioName).find($cropX2);
          var posy2 = $wrapperCropContainer.find($wrapperRatioName).find($cropY2);
          var cropw = $wrapperCropContainer.find($wrapperRatioName).find($cropW);
          var croph = $wrapperCropContainer.find($wrapperRatioName).find($cropH);

          var image_container = $wrapperCropContainer.find($wrapperRatioName);

          var width = $wrapperCropContainer.find($wrapperRatioName).find($cropThumbW);
          var height = $wrapperCropContainer.find($wrapperRatioName).find($cropThumbH);

          // Get image to crop it.
          var img = $wrapperCropContainer.find('#' + ElementName + ' img');

          $(this).closest('.crop-wrapper').find('section.preview-wrapper-crop div.crop-preview-wrapper-list').hide();

          // Initialize plugin.
          image_container.show();
          image_container.addClass('active');
          if (edit > -1 && $(this).hasClass('saved')) {
            var savedElements = $('div.js-form-managed-file section.preview-wrapper-crop > .crop-preview-wrapper-list');
            savedElements.each(function (i, item) {
              if ($(item).hasClass('saved')) {
                var saved_posx1 = $(item).find($cropX1);
                var saved_posy1 = $(item).find($cropY1);
                var saved_posx2 = $(item).find($cropX2);
                var saved_posy2 = $(item).find($cropY2);
                var saved_cropw = $(item).find($cropW);
                var saved_croph = $(item).find($cropH);
                var saved_img = $(item).find('img');

                var saved_width = $(item).find($cropThumbW);
                var saved_height = $(item).find($cropThumbH);

                var dataRatioName = $(item).data('name');

                $(saved_img).imgAreaSelect({
                  aspectRatio: $(item).data('ratio'),
                  keys: true,
                  handles: true,
                  movable: true,
                  parent: $(this),
                  minWidth: 50,
                  minHeight: 50,
                  onSelectEnd: function (saved_img, selection) {

                    // Calculate X1 / Y1 position of crop zone.
                    $(saved_posx1).val(selection.x1);
                    $(saved_posy1).val(selection.y1);

                    // Calculate X2 / Y2 position of crop zone.
                    $(saved_posx2).val(selection.x2);
                    $(saved_posy2).val(selection.y2);

                    // Calculate width / height size of crop zone.
                    $(saved_cropw).val(selection.width);
                    $(saved_croph).val(selection.height);

                    // Get size of thumbnail in UI.
                    $(saved_width).val(saved_img.width);
                    $(saved_height).val(saved_img.height);

                    $('#' + dataRatioName).find('input.delete-crop').val('0');
                  },
                  x1: saved_posx1.val(),
                  y1: saved_posy1.val(),
                  x2: saved_posx2.val(),
                  y2: saved_posy2.val()
                });
              }
            });
          }
          else {
            // Stick cliked element for add class when user crop picture.
            var listElement = $(this);

            // Create an crop instance.
            var crop = $(img).imgAreaSelect({instance: true, keys: true});

            var dataRatioName = $(this).data('name');

            // Set options.
            crop.setOptions({
              aspectRatio: ElementRatio,
              parent: image_container,
              handles: true,
              movable: true,
              minWidth: 50,
              minHeight: 50,
              onSelectEnd: function (img, selection) {

                // Calculate X1 / Y1 position of crop zone.
                $(posx1).val(selection.x1);
                $(posy1).val(selection.y1);

                // Calculate X2 / Y2 position of crop zone.
                $(posx2).val(selection.x2);
                $(posy2).val(selection.y2);

                // Calculate width / height size of crop zone.
                $(cropw).val(selection.width);
                $(croph).val(selection.height);

                // Get size of thumbnail in UI.
                $(width).val(img.width);
                $(height).val(img.height);
                // If user clic in crop zone not save it.
                if (selection.width > 0 || selection.height > 0) {
                  $('#' + dataRatioName).find('input.delete-crop').val('0');

                  // When user have crop the selection mark saved.
                  $(listElement).addClass('saved');
                }
              }
            });
          }
        });
      });


      $('.delete').on('click', function (event) {
        event.preventDefault();
        event.stopPropagation();

        $(this).parents('li').removeClass('saved active');
        var dataRatioName = $(this).closest('.crop-preview-wrapper').data('name');
        $(this).closest('.crop-wrapper').find('#' + dataRatioName).removeClass('active');
        $(this).closest('.crop-wrapper').find('#' + dataRatioName + ' .crop-preview-wrapper-value input').removeAttr('value');
        $(this).closest('.crop-wrapper').find('#' + dataRatioName + ' input.delete-crop').val('1');
        $(this).closest('.crop-wrapper').find('#' + dataRatioName).hide();
        $(this).closest('.crop-wrapper').find('#crop-help').show();
        // If you have an slide active not show help slide.
        if ($(this).closest('.crop-wrapper').find('.crop-preview-wrapper-list.active').length) {
          $(this).closest('.crop-wrapper').find('#crop-help').hide();
        }
        // Create an crop instance.
        $(this).closest('.crop-wrapper').find('#' + dataRatioName + ' img').imgAreaSelect({hide: true});
      });
    }
  };

})(jQuery, Drupal);
