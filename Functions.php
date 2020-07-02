<?php

namespace SS\MediaCompression;

defined( 'ABSPATH' ) || exit;

class Functions {

	/**
	 * @return array
	 */
	public static function get_custom_options() {
		$file = dirname( __FILE__ ) . '/.options';

		if ( file_exists( $file ) ) {
			$options = parse_ini_file( $file );
		}

		return $options ?? [];
	}

	/**
	 * @return array
	 */
	public static function get_default_options() {
		return [
			'max_width'    => 1920,
			'max_height'   => 1080,
			'jpeg_quality' => 80,
		];
	}

	public static function get_option( $name ) {
		$default = self::get_default_options();
		$custom  = self::get_custom_options();

		return $custom[ $name ] ?? $default[ $name ] ?? '';
	}

}
