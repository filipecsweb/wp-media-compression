<?php


namespace SS\MediaCompression\Admin;


class Filters {

	/**
	 * @param string[] $posts_columns An array of columns displayed in the Media list table.
	 *
	 * @return array
	 */
	public static function manage_media_columns_callback( $posts_columns ) {
		$new_columns = is_array( $posts_columns ) ? $posts_columns : [];

		$new_columns['ssmc_compressed'] = 'Otimização';

		return $new_columns;
	}

	/**
	 * @param array $sortable_columns An array of sortable columns.
	 *
	 * @return  array
	 */
	public static function manage_upload_sortable_columns_callback( $sortable_columns ) {
		// Use the key name also as the value.
		$sortable_columns['ssmc_compressed'] = 'ssmc_compressed';

		return $sortable_columns;
	}

}
