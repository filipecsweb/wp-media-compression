<?php


namespace SS\MediaCompression\Admin;


use WP_Query;

class Actions {

	/**
	 * @param string $column_name Name of the custom column.
	 * @param int $post_id Attachment ID.
	 */
	public static function manage_media_custom_column_callback( $column_name, int $post_id ) {
		$html = '';

		if ( $column_name == 'ssmc_compressed' ) {
			$compressed = get_post_meta( $post_id, 'ssmc_compressed', true );

			if ( '1' === $compressed ) {
				$html = "<b style='color: #0f834d;'>&check;</b> Concluída";
			} else {
				$html = "<b style='color: firebrick;'>&times;</b> Pendente";
			}
		}

		echo $html;
	}

	/**
	 * This hook may be commented in hooks-admin.php.
	 *
	 * @param WP_Query $query
	 */
	public static function pre_get_posts_callback( $query ) {
		if ( ! $query->is_main_query() ) {
			return;
		}

		$orderby = $query->get( 'orderby' );

		if ( $orderby == 'ssmc_compressed' ) {
			$query->set( 'meta_key', 'ssmc_compressed' );
			$query->set( 'orderby', 'meta_value' );
		}
	}

}
