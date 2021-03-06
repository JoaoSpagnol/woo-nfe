<?php
/**
 * WooCommerce NFe plugin
 *
 * @author            NFe.io
 * @link              https://github.com/nfe/woo-nfe
 * @since             1.0.8
 * @package           WooCommerce_NFe
 *
 * @wordpress-plugin
 * Plugin Name:       WooCommerce NFe
 * Plugin URI:        https://github.com/nfe/woo-nfe
 * Description:       WooCommerce extension for the NFe API
 * Version:           1.2.8
 * Author:            NFe.io
 * Author URI:        https://nfe.io
 * Developer:         Project contributors
 * Developer URI:     https://github.com/nfe/woo-nfe/graphs/contributors
 * Text Domain:       woo-nfe
 * Domain Path:       /languages
 * Network:           false
 *
 * WC requires at least: 3.3.5
 * WC tested up to: 3.5.1
 *
 * Copyright: © 2018 NFe.io
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WooCommerce_NFe' ) ) :

	/**
	* WooCommerce NFe.io Main Class
	*
	* @since 1.0.0
	*/
	final class WooCommerce_NFe {

		/**
		 * Main instance
		 *
		 * @since 1.0.0
		 *
		 * @return instance
		 */
		public static function instance() {

			// Store the instance locally to avoid private static replication.
			static $instance = null;

			// Only run these methods if they haven't been run previously.
			if ( null === $instance ) {
				$instance = new WooCommerce_NFe();
				$instance->setup_globals();
				$instance->dependencies();
				$instance->includes();
				$instance->setup_hooks();
			}

			// Always return the instance.
			return $instance;
		}

		/**
		 * A dummy constructor to prevent WooCommerce_NFe from being loaded more than once.
		 *
		 * @since 1.0.0
		 *
		 * @see WooCommerce_NFe::instance()
		 */
		private function __construct() { /* Do nothing here */ }

		/**
		 * Sets some globals for the plugin
		 *
		 * @since 1.0.0
		 */
		private function setup_globals() {
			$this->domain        = 'woo-nfe';
			$this->name          = 'WooCommerce NFe';
			$this->file          = __FILE__;
			$this->basename      = plugin_basename( $this->file );
			$this->plugin_dir    = plugin_dir_path( $this->file );
			$this->plugin_url    = plugin_dir_url( $this->file );
			$this->includes_dir  = trailingslashit( $this->plugin_dir . 'includes' );

			// WooCommerce Webhook Callback.
			if ( ! defined( 'WC_API_CALLBACK' ) ) {
				define( 'WC_API_CALLBACK', 'nfe_webhook' );
			}
		}

		/**
		 * Include needed files.
		 *
		 * @since 1.0.0
		 */
		private function includes() {

			// NFe Client-PHP API.
			require( $this->plugin_dir . 'li/client-php/lib/init.php' );

			// Admin.
			require( $this->includes_dir . 'nfe-functions.php' );
			require( $this->includes_dir . 'admin/class-settings.php' );
			require( $this->includes_dir . 'admin/class-ajax.php' );
			require( $this->includes_dir . 'admin/class-admin.php' );
			require( $this->includes_dir . 'admin/class-api.php' );
			require( $this->includes_dir . 'admin/class-emails.php' );
			require( $this->includes_dir . 'admin/class-webhook.php' );

			// Front-end.
			require( $this->includes_dir . 'frontend/class-frontend.php' );
		}

		/**
		 * Class dependencies.
		 *
		 * @return void
		 */
		private function dependencies() {
			// Check for SOAP.
			if ( ! class_exists( 'SoapClient' ) ) {
				add_action( 'admin_notices', [ $this, 'soap_missing_notice' ] );
				return;
			}

			// Checks if WooCommerce is installed and with the proper version.
			if ( ! $this->version_check() ) {
				add_action( 'admin_notices', [ $this, 'woocommerce_missing_notice' ] );
				return;
			}
		}

		/**
		 * Set hooks.
		 *
		 * @since 1.0.0
		 */
		private function setup_hooks() {

			// Set up localisation.
			$this->load_plugin_textdomain();

			$settings_url = admin_url( 'admin.php?page=woocommerce_settings&tab=integration&section=woo-nfe' );
			if ( $this->version_check( '2.1' ) ) {
				$settings_url = admin_url( 'admin.php?page=wc-settings&tab=integration&section=woo-nfe' );
			}

			if ( ! defined( 'WOOCOMMERCE_NFE_SETTINGS_URL' ) ) {
				define( 'WOOCOMMERCE_NFE_SETTINGS_URL', $settings_url );
			}

			if ( ! defined( 'WOOCOMMERCE_NFE_PATH' ) ) {
				define( 'WOOCOMMERCE_NFE_PATH', plugin_dir_path( $this->file ) );
			}

			// Filters.
			add_filter( 'woocommerce_integrations', array( $this, 'nfe_integration' ) );
			add_filter( 'plugin_action_links_' . $this->basename, [ $this, 'plugin_action_links' ] );
		}

		/**
		 * Load Localisation files.
		 *
		 * Note: the first-loaded translation file overrides any following ones if the same translation is present.
		 *
		 * Locales found in:
		 *      - WP_LANG_DIR/woo-nfe/woo-nfe-LOCALE.mo
		 *      - WP_LANG_DIR/plugins/woo-nfe-LOCALE.mo
		 */
		public function load_plugin_textdomain() {
			$locale = is_admin() && function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
			$locale = apply_filters( 'plugin_locale', $locale, 'woo-nfe' );

			unload_textdomain( 'woo-nfe' );
			load_textdomain( 'woo-nfe', WP_LANG_DIR . '/woo-nfe/woo-nfe-' . $locale . '.mo' );
			load_plugin_textdomain( 'woo-nfe', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		}

		/**
		 * Adds our custom WC_NFe_Integration integration to WooCommerce.
		 *
		 * @since 1.0.0
		 *
		 * @param array $integrations WooCommerce Integrations.
		 *
		 * @return array
		 */
		public function nfe_integration( $integrations ) {
			$integrations[] = 'WC_NFe_Integration';

			return $integrations;
		}

		/**
		 * SOAPClient missing notice.
		 *
		 * @since 1.0.0
		 */
		public function soap_missing_notice() {
			include $this->includes_dir . 'admin/views/html-notice-missing-soap-client.php';
		}

		/**
		 * WooCommerce missing notice.
		 *
		 * @since 1.0.0
		 */
		public function woocommerce_missing_notice() {
			include $this->includes_dir . 'admin/views/html-notice-missing-woocommerce.php';
		}

		/**
		 * Action links.
		 *
		 * @since 1.0.0
		 *
		 * @param array $links Links.
		 * @return array
		 */
		public function plugin_action_links( $links ) {
			return array_merge(  array(
				'<a href="' . esc_url( WOOCOMMERCE_NFE_SETTINGS_URL ) . '">' . __( 'Settings', 'woo-nfe' ) . '</a>',
			), $links );
		}

		/**
		 * Version check.
		 *
		 * @param  string $version Version to check against.
		 * @return bool
		 */
		protected function version_check( $version = '3.5.1' ) {
			if ( class_exists( 'WooCommerce' ) ) {
				global $woocommerce;
				if ( version_compare( $woocommerce->version, $version, '>=' ) ) {
					return true;
				}
			}

			return false;
		}
	}

	/**
	 * The main function responsible for returning the one true WooCommerce_NFe Instance.
	 *
	 * @since 1.0.0
	 *
	 * @return WooCommerce_NFe
	 */
	function woo_nfe() {
		return WooCommerce_NFe::instance();
	}
	add_action( 'plugins_loaded', 'woo_nfe' );

endif;
