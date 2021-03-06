<?php
/**
 * @package FacebookCommerce
 */

if (!class_exists('FacebookWordPress_Config')) :

class FacebookWordPress_Config {
  const MENU_SLUG = 'facebook_pixel_options';
  const OPTION_GROUP = 'facebook_option_group';
  const SECTION_ID = 'facebook_settings_section';
  const IGNORE_PIXEL_ID_NOTICE = 'ignore_pixel_id_notice';
  const DISMISS_PIXEL_ID_NOTICE = 'dismiss_pixel_id_notice';

  private $options;
  private $options_page;

  public function __construct($plugin_name) {
    add_action('admin_menu', array($this, 'add_menu'));
    add_action('admin_init', array($this, 'register_settings'));
    add_action('current_screen', array($this, 'register_notices'));
    add_action('admin_init', array($this, 'dismiss_notices'));
    add_action('admin_enqueue_scripts', array($this, 'register_plugin_styles'));
    add_filter(
      'plugin_action_links_'.$plugin_name,
      array($this, 'add_settings_link'));
  }

  public function add_menu() {
    $this->options_page = add_options_page(
      'Facebook Pixel Settings',
      'Facebook Pixel',
      'manage_options',
      self::MENU_SLUG,
      array($this, 'create_menu_page'));
  }

  public function create_menu_page() {
    if (!current_user_can('manage_options')) {
      wp_die(__('You do not have sufficient permissions to access this page'));
    }
    // Update class field
    $this->options = get_option(WC_Facebookcommerce_Pixel::SETTINGS_KEY);

    ?>
    <div class="wrap">
      <h2>Facebook Pixel Settings</h2>
      <form action="options.php" method="POST">
        <?php
        settings_fields(self::OPTION_GROUP);
        do_settings_sections(self::MENU_SLUG);
        submit_button();
        ?>
      </form>
    </div>
    <?php
  }

  public function register_settings() {
    register_setting(
      self::OPTION_GROUP,
      WC_Facebookcommerce_Pixel::SETTINGS_KEY,
      array($this, 'sanitize_input'));
    add_settings_section(
      self::SECTION_ID,
      null,
      null,
      self::MENU_SLUG);
    add_settings_field(
      WC_Facebookcommerce_Pixel::PIXEL_ID_KEY,
      'Pixel ID',
      array($this, 'pixel_id_form_field'),
      self::MENU_SLUG,
      self::SECTION_ID);
    add_settings_field(
      WC_Facebookcommerce_Pixel::USE_PII_KEY,
      'Use Advanced Matching on pixel?',
      array($this, 'use_pii_form_field'),
      self::MENU_SLUG,
      self::SECTION_ID);
  }

  public function sanitize_input($input) {
    $input[WC_Facebookcommerce_Pixel::USE_PII_KEY] =
      isset($input[WC_Facebookcommerce_Pixel::USE_PII_KEY]) &&
        $input[WC_Facebookcommerce_Pixel::USE_PII_KEY] == 1
        ? '1'
        : '0';

    return $input;
  }

  public function pixel_id_form_field() {
    printf(
      '
<input name="%s" id="%s" value="%s" />
<p class="description">The unique identifier for your Facebook pixel.</p>
      ',
      WC_Facebookcommerce_Pixel::SETTINGS_KEY . '[' . WC_Facebookcommerce_Pixel::PIXEL_ID_KEY . ']',
      WC_Facebookcommerce_Pixel::PIXEL_ID_KEY,
      isset($this->options[WC_Facebookcommerce_Pixel::PIXEL_ID_KEY])
        ? esc_attr($this->options[WC_Facebookcommerce_Pixel::PIXEL_ID_KEY])
        : '');
  }

  public function use_pii_form_field() {
    ?>
    <label for="<?= WC_Facebookcommerce_Pixel::USE_PII_KEY ?>">
      <input
        type="checkbox"
        name="<?= WC_Facebookcommerce_Pixel::SETTINGS_KEY . '[' . WC_Facebookcommerce_Pixel::USE_PII_KEY . ']' ?>"
        id="<?= WC_Facebookcommerce_Pixel::USE_PII_KEY ?>"
        value="1"
        <?php checked(1, $this->options[WC_Facebookcommerce_Pixel::USE_PII_KEY]) ?>
      />
      Enabling Advanced Matching improves audience building.
    </label>
    <p class="description">
      For businesses that operate in the European Union, you may need to take
      additional action. Read the
      <a href="https://developers.facebook.com/docs/privacy/">
        Cookie Consent Guide for Sites and Apps
      </a> for suggestions on complying with EU privacy requirements.
    </p>
    <?php
  }

  public function register_notices() {
    // Update class field
    $this->options = get_option(WC_Facebookcommerce_Pixel::SETTINGS_KEY);
    $pixel_id = $this->options[WC_Facebookcommerce_Pixel::PIXEL_ID_KEY];
    $current_screen_id = get_current_screen()->id;
    if (
      !WC_Facebookcommerce_Utils::is_valid_id($pixel_id)
        && current_user_can('manage_options')
        && in_array(
          $current_screen_id,
          array('dashboard', 'plugins', $this->options_page),
          true)
        && !get_user_meta(
             get_current_user_id(),
             self::IGNORE_PIXEL_ID_NOTICE,
             true)) {
      add_action('admin_notices', array($this, 'pixel_id_not_set_notice'));
    }
  }

  public function dismiss_notices() {
    $user_id = get_current_user_id();
    if (isset($_GET[self::DISMISS_PIXEL_ID_NOTICE])) {
      update_user_meta($user_id, self::IGNORE_PIXEL_ID_NOTICE, true);
    }
  }

  public function pixel_id_not_set_notice() {
    ?>
      <div class="notice notice-warning is-dismissible hide-last-button">
        <p>
          The Facebook Pixel plugin requires a Pixel ID.
          Click
          <a href="<?
            echo admin_url('options-general.php?page='.self::MENU_SLUG);
          ?>">here</a>
          to configure the plugin.
        </p>
        <button
          type="button"
          class="notice-dismiss"
          onClick="location.href='?<? echo self::DISMISS_PIXEL_ID_NOTICE ?>'">
          <span class="screen-reader-text">Dismiss this notice.</span>
        </button>
      </div>
    <?php
  }

  public function register_plugin_styles() {
    wp_register_style('facebook-pixel', plugins_url('css/admin.css', __FILE__));
    wp_enqueue_style('facebook-pixel');
  }

  public function add_settings_link($links) {
    $settings = array(
      'settings' => sprintf(
        '<a href="%s">%s</a>',
        admin_url('options-general.php?page='.self::MENU_SLUG),
        'Settings')
      );
    return array_merge($settings, $links);
  }
}

endif;
