<?php
/**
 * Plugin Name: WooCommerce eKomi Integration
 * Plugin URI: https://soportewordpress.net/woocommerce-ekomi-integration
 * Description: A plugin for integrate eKomi API with WooCommerce
 * Author: David Táboas
 * Author URI: http://davidtaboas.es
 * Version: 1.0
 * Text Domain: woocommerce-ekomi
 * Domain Path: /languages
 *
 */
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}


if ( ! class_exists( 'WC_eKomi_Integration' ) ) :

class WC_eKomi_Integration {


  /**
   * Instance of this class.
   *
   * @var object
   */
  protected static $instance = null;


  /**
  * Construct the plugin.
  */
  public function __construct() {


    // Load Translation for default options
    $plugin_path = untrailingslashit( plugin_dir_path( __FILE__ ) );
    $locale = apply_filters( 'plugin_locale', get_locale() );
    $domain = 'woocommerce-ekomi';
    $mofile = $plugin_path . '/languages/'.$domain.'.mo';

    if ( file_exists( $plugin_path . '/languages/'.$domain.'-' . $locale . '.mo' ) )
      $mofile = $plugin_path . '/languages/'.$domain.'-' . $locale . '.mo';

    load_textdomain( 'woocommerce-ekomi', $mofile );


    // Checks if WooCommerce is installed.
    if ( class_exists( 'WC_Integration' ) && defined( 'WOOCOMMERCE_VERSION' ) && version_compare( WOOCOMMERCE_VERSION, '2.2', '>=' ) ) {
      // Include our integration class.
      include_once 'includes/class-wc-integration-ekomi.php';

      // Create base for cron jobs
      $this->create_cron_jobs();

      // Register the integrations
      add_filter( 'woocommerce_integrations', array( $this, 'add_integration' ) );

      // Register emails
      add_filter( 'woocommerce_email_classes', array( $this, 'add_emails' ) );

      // Register templates
      add_filter( 'woocommerce_locate_core_template', array( $this, 'email_templates' ), 0, 3 );
    }
    else {
      add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
    }

  }


  /**
   * Return an instance of this class.
   *
   * @return object A single instance of this class.
   */
  public static function get_instance() {
    // If the single instance hasn't been set, set it now.
    if ( null == self::$instance ) {
      self::$instance = new self;
    }

    return self::$instance;
  }


  /**
   * WooCommerce fallback notice.
   *
   * @return string
   */
  public function woocommerce_missing_notice() {
    echo '<div class="error"><p>' . sprintf( __( 'WooCommerce ekomi depends on the last version of %s to work!', 'woocommerce-ekomi' ), '<a href="http://www.woothemes.com/woocommerce/" target="_blank">' . __( 'WooCommerce', 'woocommerce-ekomi' ) . '</a>' ) . '</p></div>';
  }

  /**
   * Get the plugin path.
   *
   * @return string
   */
  public function plugin_path() {
    return untrailingslashit( plugin_dir_path( __FILE__ ) );
  }


  /**
   * Add Custom Email templates
   *
   * @param array   $mails
   * @return array
   */
  public function add_emails( $mails ) {

    $mails[ 'WC_Email_Customer_Ekomi' ]            = include 'includes/class-wc-ekomi-emails.php';
    return $mails;
  }

  /**
   * Add a new integration to WooCommerce.
   */
  public function add_integration( $integrations ) {
    $integrations[] = 'WC_Integration_eKomi_Integration';
    return $integrations;
  }

  /**
   * Filter Email template to include WooCommerce Germanized template files
   *
   * @param string  $core_file
   * @param string  $template
   * @param string  $template_base
   * @return string
   */
  public function email_templates( $core_file, $template, $template_base ) {
    if ( ! file_exists( $template_base . $template ) && file_exists( $this->plugin_path() . '/templates/' . $template ) )
      $core_file = $this->plugin_path() . '/templates/' . $template;
    return apply_filters( 'woocommerce_ekomi_email_template_hook', $core_file, $template, $template_base );
  }

  /**
   * Create cron jobs (clear them first)
   */
  private function create_cron_jobs() {
    // Cron jobs
    wp_clear_scheduled_hook( 'woocommerce_ekomi' );
    wp_schedule_event( time(), 'daily', 'woocommerce_ekomi' );
  }


}//end



add_action( 'plugins_loaded', array( 'WC_eKomi_Integration', 'get_instance' ), 0 );

endif;
