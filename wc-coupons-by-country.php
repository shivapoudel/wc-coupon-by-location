<?php
/**
 * Plugin Name: WC Coupons by Country
 * Plugin URI: https://github.com/shivapoudel/wc-coupons-by-country
 * Description: WooCommerce Coupons by Country restricts coupons by customer’s billing or shipping country.
 * Version: 1.0.0
 * Author: Shiva Poudel
 * Author URI: http://shivapoudel.com
 * License: GPLv3 or later
 * Text Domain: wc-coupons-by-country
 * Domain Path: /languages/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WC_Coupons_Country' ) ) :

/**
 * Main WC_Coupons_Country Class.
 */
class WC_Coupons_Country {

	/**
	 * Plugin version.
	 * @var string
	 */
	const VERSION = '1.0.0';

	/**
	 * Coupon message code.
	 * @var integer
	 */
	const E_WC_COUPON_INVALID_COUNTRY = 99;

	/**
	 * Instance of this class.
	 * @var object
	 */
	protected static $instance = null;

	/**
	 * Initialize the plugin.
	 */
	private function __construct() {
		// Load plugin text domain.
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		// Checks with WooCommerce is installed.
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.3', '>=' ) ) {

			// Hooks
			add_action( 'woocommerce_coupon_options_usage_restriction', array( $this, 'coupon_options_data' ) );
			add_action( 'woocommerce_coupon_options_save', array( $this, 'coupon_options_save' ) );
			add_action( 'woocommerce_coupon_loaded', array( $this, 'coupon_loaded' ) );
			add_filter( 'woocommerce_coupon_is_valid', array( $this, 'is_valid_for_country' ), 10, 2 );
			add_filter( 'woocommerce_coupon_error', array( $this, 'get_country_coupon_error' ), 10, 3 );

			// Rest API
			add_filter( 'woocommerce_api_coupon_response', array( $this, 'api_coupon_response' ), 10, 2 );
			add_action( 'woocommerce_api_create_coupon', array( $this, 'api_create_coupon' ), 10, 2 );
			add_action( 'woocommerce_api_edit_coupon', array( $this, 'api_edit_coupon' ), 10, 2 );
		} else {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
		}
	}

	/**
	 * Return an instance of this class.
	 * @return object A single instance of this class.
	 */
	public static function get_instance() {
		// If the single instance hasn't been set, set it now.
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Load Localisation files.
	 *
	 * Note: the first-loaded translation file overrides any following ones if the same translation is present.
	 *
	 * Locales found in:
	 *      - WP_LANG_DIR/wc-coupons-by-country/wc-coupons-by-country-LOCALE.mo
	 *      - WP_LANG_DIR/plugins/wc-coupons-by-country-LOCALE.mo
	 */
	public function load_plugin_textdomain() {
		$locale = apply_filters( 'plugin_locale', get_locale(), 'wc-coupons-by-country' );

		load_textdomain( 'wc-coupons-by-country', WP_LANG_DIR . '/wc-coupons-by-country/wc-coupons-by-country-' . $locale . '.mo' );
		load_plugin_textdomain( 'wc-coupons-by-country', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Output coupons meta box data.
	 */
	public function coupon_options_data() {
		global $post;

		echo '<div class="options_group">';

		// Billing Countries
		?>
		<p class="form-field"><label for="billing_countries"><?php _e( 'Billing countries', 'wc-coupons-by-country' ); ?></label>
		<select id="billing_countries" name="billing_countries[]" style="width: 50%;" class="wc-enhanced-select" multiple="multiple" data-placeholder="<?php esc_attr_e( 'Any countries', 'wc-coupons-by-country' ); ?>">
			<?php
				$locations = (array) get_post_meta( $post->ID, 'billing_countries', true );
				$countries = WC()->countries->countries;

				if ( $countries ) foreach ( $countries as $key => $val ) {
					echo '<option value="' . esc_attr( $key ) . '"' . selected( in_array( $key, $locations ), true, false ) . '>' . esc_html( $val ) . '</option>';
				}
			?>
		</select> <?php echo wc_help_tip( __( 'List of allowed countries to check against the customer\'s billing country for the coupon to remain valid.', 'wc-coupons-by-country' ) ); ?></p>
		<?php

		// Shipping Countries
		?>
		<p class="form-field"><label for="shipping_countries"><?php _e( 'Shipping countries', 'wc-coupons-by-country' ); ?></label>
		<select id="shipping_countries" name="shipping_countries[]" style="width: 50%;" class="wc-enhanced-select" multiple="multiple" data-placeholder="<?php esc_attr_e( 'Any countries', 'wc-coupons-by-country' ); ?>">
			<?php
				$locations = (array) get_post_meta( $post->ID, 'shipping_countries', true );
				$countries = WC()->countries->countries;

				if ( $countries ) foreach ( $countries as $key => $val ) {
					echo '<option value="' . esc_attr( $key ) . '"' . selected( in_array( $key, $locations ), true, false ) . '>' . esc_html( $val ) . '</option>';
				}
			?>
		</select> <?php echo wc_help_tip( __( 'List of allowed countries to check against the customer\'s shipping country for the coupon to remain valid.', 'wc-coupons-by-country' ) ); ?></p>
		<?php

		echo '</div>';
	}

	/**
	 * Save coupons meta box data.
	 */
	public function coupon_options_save( $post_id ) {
		$billing_countries  = isset( $_POST['billing_countries'] ) ? wc_clean( $_POST['billing_countries'] ) : array();
		$shipping_countries = isset( $_POST['shipping_countries'] ) ? wc_clean( $_POST['shipping_countries'] ) : array();

		// Save billing and shipping countries.
		update_post_meta( $post_id, 'billing_countries', $billing_countries );
		update_post_meta( $post_id, 'shipping_countries', $shipping_countries );
	}

	/**
	 * Populates an order from the loaded post data.
	 * @param WC_Coupon $coupon
	 */
	public function coupon_loaded( $coupon ) {
		$coupon->billing_countries  = get_post_meta( $coupon->id, 'billing_countries', true );
		$coupon->shipping_countries = get_post_meta( $coupon->id, 'shipping_countries', true );
	}

	/**
	 * Check if coupon is valid for country.
	 * @return bool
	 */
	public function is_valid_for_country( $valid_for_cart, $coupon ) {
		if ( sizeof( $coupon->billing_countries ) > 0 || sizeof( $coupon->shipping_countries ) > 0 ) {
			$valid_for_cart = false;
			if ( ! WC()->cart->is_empty() ) {
				if ( in_array( WC()->customer->country, $coupon->billing_countries ) || in_array( WC()->customer->shipping_country, $coupon->shipping_countries ) ) {
					$valid_for_cart = true;
				}
			}
			if ( ! $valid_for_cart ) {
				throw new Exception( self::E_WC_COUPON_INVALID_COUNTRY );
			}
		}

		return $valid_for_cart;
	}

	/**
	 * Map one of the WC_Coupon error codes to an error string.
	 * @param  string $err Error message.
	 * @param  int $err_code Error code
	 * @return string| Error string
	 */
	public function get_country_coupon_error( $err, $err_code, $coupon ) {
		if ( self::E_WC_COUPON_INVALID_COUNTRY == $err_code ) {
			$err = sprintf( __( 'Sorry, coupon "%s" is not applicable to your country.', 'wc-coupons-by-country' ), $coupon->code );
		}

		return $err;
	}

	/**
	 * Rest API get coupon response.
	 * @param  array  $coupon_data
	 * @param  object $coupon
	 * @return array
	 */
	public function api_coupon_response( $coupon_data, $coupon ) {
		$coupon_data['billing_countries']  = $coupon->billing_countries;
		$coupon_data['shipping_countries'] = $coupon->shipping_countries;
		return $coupon_data;
	}

	/**
	 * Rest API create a coupon.
	 * @param int   $id
	 * @param array $data
	 */
	public function api_create_coupon( $id, $data ) {
		$billing_countries  = isset( $data['billing_countries'] ) ? wc_clean( $data['billing_countries'] ) : array();
		$shipping_countries = isset( $data['shipping_countries'] ) ? wc_clean( $data['shipping_countries'] ) : array();

		// Save billing and shipping countries.
		update_post_meta( $id, 'billing_countries', $billing_countries );
		update_post_meta( $id, 'shipping_countries', $shipping_countries );
	}

	/**
	 * Rest API edit a coupon.
	 * @param int   $id
	 * @param array $data
	 */
	public function api_edit_coupon( $id, $data ) {
		if ( isset( $data['billing_countries'] ) ) {
			update_post_meta( $id, 'billing_countries', wc_clean( $data['billing_countries'] ) );
		}

		if ( isset( $data['shipping_countries'] ) ) {
			update_post_meta( $id, 'shipping_countries', wc_clean( $data['shipping_countries'] ) );
		}
	}

	/**
	 * WooCommerce fallback notice.
	 * @return string
	 */
	public function woocommerce_missing_notice() {
		echo '<div class="error notice is-dismissible"><p>' . sprintf( __( 'WooCommerce Coupons by Location depends on the last version of %s or later to work!', 'wc-coupons-by-country' ), '<a href="http://www.woothemes.com/woocommerce/" target="_blank">' . __( 'WooCommerce 2.3', 'wc-coupons-by-country' ) . '</a>' ) . '</p></div>';
	}
}

add_action( 'plugins_loaded', array( 'WC_Coupons_Country', 'get_instance' ), 0 );

endif;
