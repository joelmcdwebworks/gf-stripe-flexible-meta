<?php
/**
 * Turns on custom values for Stripe feed field maps and resolves them at payment time.
 *
 * @package GF_Stripe_Flexible_Meta
 */

defined( 'ABSPATH' ) || die();

/**
 * Plugin runtime.
 */
class GF_Stripe_Flexible_Meta {

	/**
	 * Stored prefix for a typed custom value. The remainder is the text, which may contain merge tags.
	 */
	const CUSTOM_VALUE_PREFIX = 'gf_custom:';

	/**
	 * Singleton.
	 *
	 * @var GF_Stripe_Flexible_Meta|null
	 */
	private static $instance = null;

	/**
	 * @return GF_Stripe_Flexible_Meta
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register field types and hooks.
	 *
	 * @return void
	 */
	public function init() {
		if ( class_exists( '\Gravity_Forms\Gravity_Forms\Settings\Fields' ) && class_exists( 'GFSFM_Field_Map' ) && class_exists( 'GFSFM_Dynamic_Field_Map' ) ) {
			\Gravity_Forms\Gravity_Forms\Settings\Fields::register( 'gfsfm_field_map', 'GFSFM_Field_Map' );
			\Gravity_Forms\Gravity_Forms\Settings\Fields::register( 'gfsfm_dynamic_field_map', 'GFSFM_Dynamic_Field_Map' );
		}

		add_filter( 'gform_gravityformsstripe_feed_settings_fields', array( $this, 'enable_custom_values' ) );
		add_filter( 'gform_stripe_field_value', array( $this, 'resolve_custom_value' ), 10, 5 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Add "Add Custom Value" to every Stripe feed field map.
	 *
	 * @param array $settings Feed settings sections.
	 *
	 * @return array
	 */
	public function enable_custom_values( $settings ) {
		if ( ! is_array( $settings ) || ! class_exists( 'GFSFM_Field_Map' ) || ! class_exists( 'GFSFM_Dynamic_Field_Map' ) ) {
			return $settings;
		}

		return $this->walk_settings( $settings );
	}

	/**
	 * Replace a stored custom value with its merge tags resolved as plain text.
	 *
	 * Stripe reads mapped fields through get_field_value(), which passes the stored field id into this filter.
	 *
	 * @param string $field_value Current field value.
	 * @param array  $form        Form being processed.
	 * @param array  $entry       Entry being processed.
	 * @param string $field_id    Mapped field id, or a custom value prefixed with gf_custom:.
	 * @param string $meta_key    Metadata key when Stripe is building metadata. Unused.
	 *
	 * @return string
	 */
	public function resolve_custom_value( $field_value, $form, $entry, $field_id, $meta_key = '' ) {
		unset( $meta_key );

		if ( ! self::is_custom_value( $field_id ) ) {
			return $field_value;
		}

		$template = self::decode_custom_value( $field_id );
		if ( '' === $template ) {
			return '';
		}

		if ( ! is_array( $form ) ) {
			$form = array();
		}

		if ( ! is_array( $entry ) ) {
			$entry = array();
		}

		return GFCommon::replace_variables( $template, $form, $entry, false, false, false, 'text' );
	}

	/**
	 * Load the merge-tag helper on Stripe feed screens.
	 *
	 * @return void
	 */
	public function enqueue_admin_assets() {
		if ( 'gf_edit_forms' !== rgget( 'page' ) || 'gravityformsstripe' !== rgget( 'subview' ) ) {
			return;
		}

		wp_enqueue_script(
			'gf-stripe-flexible-meta-admin',
			plugins_url( 'assets/admin.js', GFSFM_FILE ),
			array(),
			GFSFM_VERSION,
			true
		);
	}

	/**
	 * @param mixed $value Stored map value.
	 *
	 * @return bool
	 */
	public static function is_custom_value( $value ) {
		return is_string( $value ) && 0 === strpos( $value, self::CUSTOM_VALUE_PREFIX );
	}

	/**
	 * @param string $custom_value Text entered for the custom choice.
	 *
	 * @return string
	 */
	public static function encode_custom_value( $custom_value ) {
		return self::CUSTOM_VALUE_PREFIX . (string) $custom_value;
	}

	/**
	 * @param string $value Stored custom value.
	 *
	 * @return string
	 */
	public static function decode_custom_value( $value ) {
		return substr( (string) $value, strlen( self::CUSTOM_VALUE_PREFIX ) );
	}

	/**
	 * Walk feed setting sections and the fields inside them.
	 *
	 * Stripe instantiates some maps while building the feed screen, before this filter runs.
	 * Those objects have to be replaced; arrays can still be retargeted by type.
	 *
	 * @param array $items Sections or fields.
	 *
	 * @return array
	 */
	private function walk_settings( $items ) {
		foreach ( $items as $index => $item ) {
			if ( is_object( $item ) ) {
				$items[ $index ] = $this->prepare_map_object( $item );
				continue;
			}

			if ( ! is_array( $item ) ) {
				continue;
			}

			foreach ( array( 'fields', 'sections' ) as $key ) {
				if ( isset( $item[ $key ] ) && is_array( $item[ $key ] ) ) {
					$items[ $index ][ $key ] = $this->walk_settings( $item[ $key ] );
				}
			}

			$item = $items[ $index ];
			if ( isset( $item['type'] ) && in_array( $item['type'], array( 'field_map', 'dynamic_field_map' ), true ) ) {
				$items[ $index ] = $this->prepare_map_field( $item );
			}
		}

		return $items;
	}

	/**
	 * Rebuild a map that Stripe already turned into a settings field object.
	 *
	 * @param object $field Settings field.
	 *
	 * @return object
	 */
	private function prepare_map_object( $field ) {
		$type = isset( $field->type ) ? $field->type : '';
		if ( ! in_array( $type, array( 'field_map', 'dynamic_field_map' ), true ) ) {
			return $field;
		}

		if ( $field instanceof GFSFM_Field_Map || $field instanceof GFSFM_Dynamic_Field_Map ) {
			return $field;
		}

		$props = array(
			'name'  => $field->name,
			'label' => $field->label,
			'type'  => $type,
		);

		foreach ( array( 'tooltip', 'dependency', 'required', 'class', 'hidden', 'validation_callback', 'description', 'limit', 'exclude_field_types' ) as $prop ) {
			if ( isset( $field[ $prop ] ) && '' !== $field[ $prop ] && null !== $field[ $prop ] ) {
				$props[ $prop ] = $field[ $prop ];
			}
		}

		if ( 'field_map' === $type && ! empty( $field->key_field['choices'] ) ) {
			$props['field_map'] = $field->key_field['choices'];
		}

		$props   = $this->prepare_map_field( $props );
		$created = \Gravity_Forms\Gravity_Forms\Settings\Fields::create( $props, $field->settings );

		return is_wp_error( $created ) ? $field : $created;
	}

	/**
	 * Point a map at the subclass that keeps custom text, and allow that choice in the UI.
	 *
	 * enable_custom_value is only applied when value_field is also present. The core map
	 * constructors force allow_custom off before the parent reads these props.
	 *
	 * @param array $field Field settings.
	 *
	 * @return array
	 */
	private function prepare_map_field( $field ) {
		$field['enable_custom_value'] = true;

		if ( empty( $field['value_field'] ) || ! is_array( $field['value_field'] ) ) {
			$field['value_field'] = array();
		}

		$field['value_field']['allow_custom'] = true;

		if ( 'field_map' === $field['type'] ) {
			$field['type'] = 'gfsfm_field_map';
		} else {
			$field['type'] = 'gfsfm_dynamic_field_map';
		}

		return $field;
	}
}
