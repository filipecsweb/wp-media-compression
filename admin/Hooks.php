<?php


namespace SS\MediaCompression\Admin;


class Hooks {

	public function __construct() {
		if ( ! is_admin() ) {
			return;
		}

		add_filter( 'manage_media_columns', [ 'SS\MediaCompression\Admin\Filters', 'manage_media_columns_callback' ], 20 );
		add_filter( 'manage_upload_sortable_columns', [ 'SS\MediaCompression\Admin\Filters', 'manage_upload_sortable_columns_callback' ] );
		add_action( 'manage_media_custom_column', [ 'SS\MediaCompression\Admin\Actions', 'manage_media_custom_column_callback' ], 20, 2 );
		add_action( 'pre_get_posts', [ 'SS\MediaCompression\Admin\Actions', 'pre_get_posts_callback' ] );
	}

}
