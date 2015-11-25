<?php

/**
 * @file
 * Contains \Drupal\image_widget_crop\Form\CropWidgetForm.
 */

namespace Drupal\image_widget_crop\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfigFormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure ImageWidgetCrop general settings for this site.
 */
class CropWidgetForm extends ConfigFormBase {

  /**
   * The settings of image_widget_crop configuration.
   *
   * @var array
   *
   * @see \Drupal\Core\Config\Config
   */
  protected $settings;

  /**
   * Constructs a CropWidgetForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    parent::__construct($config_factory);
    $this->settings = $this->config('image_widget_crop.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static (
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'image_widget_crop_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['image_widget_crop.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    //kint($this->settings->get('settings.crop_upload_location'));
    $form['crop_upload_location'] = array(
      '#type' => 'textfield',
      '#title' => t('Image upload location path'),
      '#default_value' => $this->settings->get('settings.crop_upload_location'),
      '#maxlength' => 255,
      '#description' => t('A local file system path where croped images files will be stored. Spécify the location of files instead of \'sites/default/files\' folder')
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    foreach (['crop_upload_location'] as $form_element_name) {
      $value = $form_state->getValue($form_element_name);
      $this->settings->set("settings.$form_element_name", $value);
    }
    $this->settings->save();
    parent::submitForm($form, $form_state);
  }

}
