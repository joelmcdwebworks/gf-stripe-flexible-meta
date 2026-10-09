<?php
/**
 * Plugin Name: GF Stripe Flexible Meta
 * Description: Allows custom values, including merge tags, when mapping Stripe feed fields and metadata.
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: Affirmation
 * License: GPL-2.0+
 * Text Domain: gf-stripe-flexible-meta
 *
 * @package GF_Stripe_Flexible_Meta
 */

defined( 'ABSPATH' ) || die();

define( 'GFSFM_VERSION', '1.0.0' );
define( 'GFSFM_FILE', __FILE__ );
define( 'GFSFM_PATH', plugin_dir_path( __FILE__ ) );

add_action( 'gform_loaded', array( 'GF_Stripe_Flexible_Meta_Bootstrap', 'load' ), 20 );
add_action( 'admin_notices', array( 'GF_Stripe_Flexible_Meta_Bootstrap', 'admin_notice' ) );

/**
 * Boots the plugin after Gravity Forms and the Stripe Add-On are available.
 */
class GF_Stripe_Flexible_Meta_Bootstrap {

	/**
	 * Load the plugin when Gravity Forms and Stripe are both active.
	 *
	 * @return void
	 */
	public static function load() {
		if ( ! class_exists( 'GFForms' ) || ! class_exists( 'GFStripe' ) ) {
			return;
		}

		require_once GFSFM_PATH . 'includes/class-gf-stripe-flexible-meta.php';

		// Field-map classes are only needed while editing a feed. Payment requests still resolve saved values.
		if ( is_admin() ) {
			self::load_settings_fields();

			if ( class_exists( '\Gravity_Forms\Gravity_Forms\Settings\Fields\Field_Map' ) && class_exists( '\Gravity_Forms\Gravity_Forms\Settings\Fields\Dynamic_Field_Map' ) ) {
				require_once GFSFM_PATH . 'includes/class-gfsfm-field-map.php';
			}
		}

		GF_Stripe_Flexible_Meta::get_instance()->init();
	}

	/**
	 * Explain why the plugin is idle when a dependency is missing.
	 *
	 * @return void
	 */
	public static function admin_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		if ( class_exists( 'GFForms' ) && class_exists( 'GFStripe' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'GF Stripe Flexible Meta requires Gravity Forms and the Gravity Forms Stripe Add-On.', 'gf-stripe-flexible-meta' );
		echo '</p></div>';
	}

	/**
	 * The settings field classes are created the first time Gravity Forms builds a Settings screen.
	 * Load them in wp-admin so the custom map types can extend them before a feed is edited.
	 *
	 * @return void
	 */
	private static function load_settings_fields() {
		if ( class_exists( '\Gravity_Forms\Gravity_Forms\Settings\Fields\Field_Map' ) ) {
			return;
		}

		$fields_file = dirname( GFSFM_PATH ) . '/gravityforms/includes/settings/class-fields.php';
		if ( file_exists( $fields_file ) ) {
			require_once $fields_file;
		}
	}
}
