<?php
/**
 * Stripe feed field maps that can store a typed custom value.
 *
 * @package GF_Stripe_Flexible_Meta
 */

defined( 'ABSPATH' ) || die();

/**
 * Fixed field map used for customer and billing information.
 *
 * A selected form field is still stored as customerInformation_email, billingInformation_address_city,
 * and the other flattened keys Stripe already reads. A custom choice is stored in that same key.
 */
class GFSFM_Field_Map extends \Gravity_Forms\Gravity_Forms\Settings\Fields\Field_Map {

	/**
	 * Field type.
	 *
	 * @var string
	 */
	public $type = 'gfsfm_field_map';

	/**
	 * Rebuild each row from the flattened feed meta Stripe reads.
	 *
	 * @return array
	 */
	public function get_value() {
		$value = array();

		foreach ( $this->key_field['choices'] as $choice ) {
			$name = rgar( $choice, 'name' );
			if ( ! $name ) {
				continue;
			}

			$stored = $this->settings->get_value( sprintf( '%s_%s', $this->name, $name ) );
			$select = is_scalar( $stored ) ? (string) $stored : '';
			$custom = '';

			if ( GF_Stripe_Flexible_Meta::is_custom_value( $select ) ) {
				$custom = GF_Stripe_Flexible_Meta::decode_custom_value( $select );
				$select = 'gf_custom';
			}

			$value[] = array(
				'key'          => $name,
				'custom_key'   => '',
				'value'        => $select,
				'custom_value' => $custom,
			);
		}

		return $value;
	}

	/**
	 * Persist field ids as Stripe expects, and custom text in the same keys.
	 *
	 * @param array             $field_values Posted field values.
	 * @param array|bool|string $field_value  Posted value for this map.
	 *
	 * @return array
	 */
	public function save_field( $field_values, $field_value ) {
		if ( is_callable( $this->save_callback ) ) {
			return parent::save_field( $field_values, $field_value );
		}

		$field_value = GFCommon::maybe_decode_json( $field_value );
		if ( ! is_array( $field_value ) ) {
			return $field_values;
		}

		foreach ( $field_value as $mapping ) {
			if ( ! is_array( $mapping ) || ! rgar( $mapping, 'key' ) ) {
				continue;
			}

			$stored = rgar( $mapping, 'value' );
			if ( 'gf_custom' === $stored ) {
				$stored = GF_Stripe_Flexible_Meta::encode_custom_value( rgar( $mapping, 'custom_value' ) );
			}

			$field_values[ sprintf( '%s_%s', $this->name, $mapping['key'] ) ] = $stored;
		}

		unset( $field_values[ $this->name ] );

		return $field_values;
	}
}

/**
 * Metadata map. Each row keeps its custom key and stores typed text on that row's value.
 */
class GFSFM_Dynamic_Field_Map extends \Gravity_Forms\Gravity_Forms\Settings\Fields\Dynamic_Field_Map {

	/**
	 * Field type.
	 *
	 * @var string
	 */
	public $type = 'gfsfm_dynamic_field_map';

	/**
	 * Show saved custom text in the text field again.
	 *
	 * @return array|bool|string
	 */
	public function get_value() {
		$rows = parent::get_value();
		if ( ! is_array( $rows ) ) {
			return $rows;
		}

		foreach ( $rows as $index => $row ) {
			if ( ! is_array( $row ) || ! GF_Stripe_Flexible_Meta::is_custom_value( rgar( $row, 'value' ) ) ) {
				continue;
			}

			$rows[ $index ]['custom_value'] = GF_Stripe_Flexible_Meta::decode_custom_value( $row['value'] );
			$rows[ $index ]['value']        = 'gf_custom';
		}

		return $rows;
	}

	/**
	 * Store typed text in the value Stripe passes to get_field_value().
	 *
	 * @param array|bool|string $value Posted rows.
	 *
	 * @return array|bool|string
	 */
	public function save( $value ) {
		$value = parent::save( $value );
		if ( ! is_array( $value ) ) {
			return $value;
		}

		foreach ( $value as $index => $row ) {
			if ( ! is_array( $row ) || 'gf_custom' !== rgar( $row, 'value' ) ) {
				continue;
			}

			$value[ $index ]['value'] = GF_Stripe_Flexible_Meta::encode_custom_value( rgar( $row, 'custom_value' ) );
		}

		return $value;
	}
}
