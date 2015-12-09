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
      $values.find('.crop-x').val(Math.round(data.x));
      $values.find('.crop-y').val(Math.round(data.y));
      $values.find('.crop-width').val(Math.round(data.width));
      $values.find('.crop-height').val(Math.round(data.height));
      $values.find('.crop-applied').val(1);
      Drupal.imageWidgetCrop.updateCropSummaries($this);
    }
  };

  Drupal.imageWidgetCrop = {};

  /**
   * Initialize cropper on the ImageWidgetCrop widget.
   *
   * @param context
   *   Element to initialize cropper on.
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
      Drupal.imageWidgetCrop.initializeCropper($cropper, $cropper.data('ratio'));
    });

    $reset.on('click', function (e) {
      e.preventDefault();
      var $element = $(this).siblings(cropperSelector);
      Drupal.imageWidgetCrop.reset($element);
      return false;
    });
  };

  /**
   * Initialize cropper on an element.
   *
   * @param $element
   *   Element to initialize cropper on.
   * @param ratio
   *   The ratio of the image
   */
  Drupal.imageWidgetCrop.initializeCropper = function ($element, ratio) {
    var data = null;
    var $values = $element.siblings(cropperValuesSelector);
    var options = cropperOptions;

    if (parseInt($values.find('.crop-applied').val()) === 1) {
      data = {
        x: parseInt($values.find('.crop-x').val()),
        y: parseInt($values.find('.crop-y').val()),
        width: parseInt($values.find('.crop-width').val()),
        height: parseInt($values.find('.crop-height').val()),
        rotate: 0,
        scaleX: 1,
        scaleY: 1
      };
    }

    options.data = data;
    // @TODO: eval() is evil.
    options.aspectRatio = eval(ratio);

    $element.cropper(cropperOptions);
  };

  /**
   * Initialize cropper on all children of an element.
   *
   * @param $element
   *   Element to initialize cropper on its children.
   */
  Drupal.imageWidgetCrop.initializeCropperOnChildren = function ($element) {
    var visibleCropper = $element.find(cropperSelector + ':visible');
    Drupal.imageWidgetCrop.initializeCropper($(visibleCropper), $(visibleCropper).data('ratio'));
  };

  /**
   * Update single crop summary of an element.
   *
   * @param $element
   *   The element cropping on which has been changed
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
   * @param $element
   *   The element cropping on which has been changed
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
   * @param $element
   *   The element cropping on which has been changed
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
   * @param $element
   *   The element to reset cropping on.
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
