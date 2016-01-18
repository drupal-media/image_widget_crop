/**
 * @file
 * Defines the behaviors needed for cropper integration.
 */

(function ($, Drupal, drupalSettings) {
  'use strict';

  var cropperSelector = '.image-style-crop-thumbnail';
  var cropperValuesSelector = '.crop-preview-wrapper-value';
  var cropWrapperSelector = '.crop-wrapper';
  var cropWrapperSummarySelector = 'summary';
  var verticalTabsSelector = '.vertical-tabs';
  var verticalTabsMenuItemSelector = '.vertical-tabs__menu-item';
  var resetSelector = '.crop-reset';
  var cropperOptions = {
    background: false,
    zoomable: false,
    viewMode: 3,
    autoCropArea: 1,
    cropend: function (e) {
      var $this = $(this);
      var $values = $this.siblings(cropperValuesSelector);
      var data = $this.cropper('getData');
      var delta = $(cropperSelector).data('original-height') / $(cropperSelector).prop('naturalHeight');
      $values.find('.crop-x').val(Math.round(data.x * delta));
      $values.find('.crop-y').val(Math.round(data.y * delta));
      $values.find('.crop-width').val(Math.round(data.width * delta));
      $values.find('.crop-height').val(Math.round(data.height * delta));
      $values.find('.crop-applied').val(1);
      Drupal.imageWidgetCrop.updateCropSummaries($this);
    }
  };

  Drupal.imageWidgetCrop = {};

  /**
   * Initialize cropper on the ImageWidgetCrop widget.
   *
   * @param {Object} context - Element to initialize cropper on.
   */
  Drupal.imageWidgetCrop.initialize = function (context) {
    var $cropWrapper = $(cropWrapperSelector, context);
    var $cropWrapperSummary = $cropWrapper.children(cropWrapperSummarySelector);
    var $verticalTabs = $(verticalTabsSelector, context);
    var $verticalTabsMenuItem = $verticalTabs.find(verticalTabsMenuItemSelector);
    var $reset = $(resetSelector, context);

    // @TODO: This event fires too early. The cropper element is not visible yet. This is why we need the setTimeout() workaround. Additionally it also fires when hiding and on page load
    $cropWrapperSummary.bind('click', function (e) {
      var $element = $(this).parents(cropWrapperSelector);
      setTimeout(function () {
        Drupal.imageWidgetCrop.initializeCropperOnChildren($element);
      }, 10);
      return true;
    });

    // @TODO: This event fires too early. The cropper element is not visible yet. This is why we need the setTimeout() workaround.
    $verticalTabsMenuItem.click(function () {
      var tabId = $(this).find('a').attr('href');
      var $cropper = $(tabId).find(cropperSelector);
      var ratio = Drupal.imageWidgetCrop.getRatio($cropper);
      Drupal.imageWidgetCrop.initializeCropper($cropper, ratio);
    });

    $reset.on('click', function (e) {
      e.preventDefault();
      var $element = $(this).siblings(cropperSelector);
      Drupal.imageWidgetCrop.reset($element);
      return false;
    });
  };

  /**
   * Get ratio data and determine if an available ratio or free crop.
   *
   * @param {Object} $element - Element to initialize cropper on its children.
   */
  Drupal.imageWidgetCrop.getRatio = function ($element) {
    var ratio = $element.data('ratio');
    var regex = /:/;

    if ((regex.exec(ratio)) !== null) {
      var int = ratio.split(":");
      if ($.isArray(int) && ($.isNumeric(int[0]) && $.isNumeric(int[1]))) {
        return int[0] / int[1];
      } else {
        return "NaN";
      }
    } else {
      return ratio;
    }
  };

  /**
   * Initialize cropper on an element.
   *
   * @param {Object} $element - Element to initialize cropper on.
   * @param {number} ratio - The ratio of the image.
   */
  Drupal.imageWidgetCrop.initializeCropper = function ($element, ratio) {
    var data = null;
    var $values = $element.siblings(cropperValuesSelector);
    var options = cropperOptions;
    var delta = $(cropperSelector).data('original-height') / $(cropperSelector).prop('naturalHeight');

    if (parseInt($values.find('.crop-applied').val()) === 1) {
      data = {
        x: Math.round(parseInt($values.find('.crop-x').val()) / delta),
        y: Math.round(parseInt($values.find('.crop-y').val()) / delta),
        width: Math.round(parseInt($values.find('.crop-width').val()) / delta),
        height: Math.round(parseInt($values.find('.crop-height').val()) / delta),
        rotate: 0,
        scaleX: 1,
        scaleY: 1
      };
    }

    options.data = data;
    options.aspectRatio = ratio;

    $element.cropper(options);
  };

  /**
   * Initialize cropper on all children of an element.
   *
   * @param {Object} $element - Element to initialize cropper on its children.
   */
  Drupal.imageWidgetCrop.initializeCropperOnChildren = function ($element) {
    var visibleCropper = $element.find(cropperSelector + ':visible');
    var ratio = Drupal.imageWidgetCrop.getRatio($(visibleCropper));
    Drupal.imageWidgetCrop.initializeCropper($(visibleCropper), ratio);
  };

  /**
   * Update single crop summary of an element.
   *
   * @param {Object} $element - The element cropping on which has been changed.
   */
  Drupal.imageWidgetCrop.updateSingleCropSummary = function ($element) {
    var $values = $element.siblings(cropperValuesSelector);
    var croppingApplied = parseInt($values.find('.crop-applied').val());

    $element.closest('details').drupalSetSummary(function (context) {
      if (croppingApplied) {
        return Drupal.t('Cropping applied');
      }
    });
  };

  /**
   * Update common crop summary of an element.
   *
   * @param {Object} $element - The element cropping on which has been changed.
   */
  Drupal.imageWidgetCrop.updateCommonCropSummary = function ($element) {
    var croppingApplied = parseInt($element.find('.crop-applied[value="1"]').length);
    var wrapperText = Drupal.t('Crop image');
    if (croppingApplied) {
      wrapperText = Drupal.t('Crop image (cropping applied)');
    }
    $element.children('summary').text(wrapperText);
  };

  /**
   * Update crop summaries after cropping cas been set or reset.
   *
   * @param {Object} $element - The element cropping on which has been changed.
   */
  Drupal.imageWidgetCrop.updateCropSummaries = function ($element) {
    var $cropWrapper = $(cropWrapperSelector);
    Drupal.imageWidgetCrop.updateSingleCropSummary($element);
    Drupal.imageWidgetCrop.updateCommonCropSummary($cropWrapper);
  };

  /**
   * Update crop summaries of all elements.
   */
  Drupal.imageWidgetCrop.updateAllCropSummaries = function () {
    var $croppers = $(cropperSelector);
    $croppers.each(function () {
      Drupal.imageWidgetCrop.updateSingleCropSummary($(this));
    });
    var $cropWrappers = $(cropWrapperSelector);
    $cropWrappers.each(function () {
      Drupal.imageWidgetCrop.updateCommonCropSummary($(this));
    });
  };

  /**
   * Reset cropping for an element.
   *
   * @param {Object} $element - The element to reset cropping on.
   */
  Drupal.imageWidgetCrop.reset = function ($element) {
    var $values = $element.siblings(cropperValuesSelector);
    $element.cropper('reset').cropper('options', cropperOptions);
    $values.find('.crop-x').val('');
    $values.find('.crop-y').val('');
    $values.find('.crop-width').val('');
    $values.find('.crop-height').val('');
    $values.find('.crop-applied').val(0);
    Drupal.imageWidgetCrop.updateCropSummaries($element);
  };

  Drupal.behaviors.imageWidgetCrop = {
    attach: function (context) {
      Drupal.imageWidgetCrop.initialize(context);
    }
  };

  Drupal.imageWidgetCrop.updateAllCropSummaries();

}(jQuery, Drupal, drupalSettings));
