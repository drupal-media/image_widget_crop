ImageWidgetCrop module
======================

Provides an interface for using the features of the [Crop API]. Module is still 
under heavy development.

[Crop API]: https://github.com/drupal-media/crop

Configuration
-------------

* Create a Crop Type (`admin/structure/crop`)
* Create an ImageStyle 
    * add one ImageWidget Manual crop effect, using your Crop Type (to apply 
      your crop selection).
* Create an Image field.
* In its form display, at `admin/structure/types/manage/page/form-display`:
    * set the widget for your field to Image Widget crop 
    * at select your image style in the Crop settings list. You can configure 
      the widget to create different crops on each ImageStyle. For example, if 
      you have an editorial site, you need to display an image on different 
      places. With this option, you can set an optimal crop zone for each of the
      image styles applied to the image
* Set the display formatter Image and choose your image style.
* Go add an image with your widget and crop your picture.
