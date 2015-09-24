# ImageWidgetCrop module

Provides an interface for using the features of the [Crop API.](https://github.com/drupal-media/crop). 
Module is still under heavy development.

# Requirements

* Libraries GMP.

# Configuration

* Create a crop Type (admin/structure/crop)
* Create an ImageStyle with one effect ImageWidget Manual crop with your,
 crop type( for apply your crop selection).
* Create an field with type (Image Crop).
* In form display set fieldwidget (ImageWidget crop) for your field and select,
 your image style into Crop list setting (admin/structure/types/manage/page/form-display).
  You can configure the widget to create differents crop by ImageStyle by example if you have,
   an editorial site you need display one image into differents place. With this option,
    you can set an optimal crop zone by imagestyle (by place to display this image).
* Set the display widget Image with imageStyle and choise your image style.
* Go add an image with your widget and crop your picture.

# Technical details

## Installation to GMP Libraries

Linux user

```bash
sudo apt-get install libgmp-dev
sudo apt-get install php5-gmp
sudo service apache2 reload
```
Mac OSX

* Run php --version to check what version of PHP you have.
* Download that version of PHP somewhere on your system.
* phpize & compile it

```bash
cd php-5.5.21/ext/gmp
phpize
./configure
make
make install
```

* Add the following to your php.ini

```bash
 extension="gmp.so"
```

* Restart Apache
