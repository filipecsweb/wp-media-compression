<?php

namespace SS\MediaCompression;

defined( 'ABSPATH' ) || exit;

use Exception;
use Spatie\ImageOptimizer\OptimizerChain;
use Spatie\ImageOptimizer\Optimizers\Cwebp;
use Spatie\ImageOptimizer\Optimizers\Gifsicle;
use Spatie\ImageOptimizer\Optimizers\Jpegoptim;
use Spatie\ImageOptimizer\Optimizers\Optipng;
use Spatie\ImageOptimizer\Optimizers\Pngquant;
use Spatie\ImageOptimizer\Optimizers\Svgo;
use WP_Error;
use SS\MediaCompression\Admin;

class MediaCompression {

	/**
	 * @var MediaCompression $instance
	 */
	private static $instance;

	public function __construct() {
		new Admin\Hooks();

		$this->set_hooks();
	}

	private function set_hooks() {
		add_filter( 'wp_generate_attachment_metadata', [ 'SS\MediaCompression\MediaCompression', 'wp_generate_attachment_metadata_callback' ], 10, 2 );
		add_action( 'wp_ajax_ssmc_maybe_compress_attachment', [ 'SS\MediaCompression\MediaCompression', 'ssmc_maybe_compress_attachment_callback' ] );
		add_action( 'wp_ajax_nopriv_ssmc_maybe_compress_attachment', [ 'SS\MediaCompression\MediaCompression', 'ssmc_maybe_compress_attachment_callback' ] );
		add_action( 'ssmc_update_attachment_metadata', [ 'SS\MediaCompression\MediaCompression', 'ssmc_update_attachment_metadata_callback' ], 10, 2 );
	}

	/**
	 * Hooked into `wp_generate_attachment_metadata` filter hook.
	 *
	 * @param array $metadata An array of attachment meta data.
	 * @param int $attachment_id Current attachment ID.
	 *
	 * @return array
	 */
	public static function wp_generate_attachment_metadata_callback( $metadata, $attachment_id ): array {
		// Keep this in order to make sync tests.
		/*try {
			return self::compress( $attachment_id, $metadata );
		} catch ( Exception $e ) {
			// Log here.
			return $metadata;
		}*/

		$args = [
			'timeout'   => 0.01, // Set to 30 in order to perform tests.
			'blocking'  => false, // Set to TRUE in order to perform tests.
			'body'      => compact( 'metadata', 'attachment_id' ),
			'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
			'cookies'   => isset( $_COOKIE ) && is_array( $_COOKIE ) ? $_COOKIE : array(),
		];

		if ( getenv( 'WORDPRESS_HOST' ) !== false ) {
			$url = add_query_arg( [
				'action' => 'ssmc_maybe_compress_attachment'
			], getenv( 'WORDPRESS_HOST' ) . '/wp-admin/admin-ajax.php' );
		} else {
			$url = add_query_arg( [
				'action' => 'ssmc_maybe_compress_attachment'
			], admin_url( 'admin-ajax.php' ) );
		}

//		$metadata = wp_remote_post( $url, $args )['body'];
//		var_dump( $metadata );
//		exit;

		wp_remote_post( $url, $args );

		return $metadata;
	}

	/**
	 * Hooked into 'wp_ajax_ssmc_maybe_compress_attachment' action hook.
	 * Hooked into 'wp_ajax_nopriv_ssmc_maybe_compress_attachment' action hook.
	 */
	public static function ssmc_maybe_compress_attachment_callback() {
		$args = array_merge( [
			'metadata'      => [],
			'attachment_id' => 0,
		], ( $_POST ?? [] ) );

		$wp_error = new WP_Error();

		try {
			$_attachment = get_post( $args['attachment_id'] );

			if ( 'attachment' !== ( $_attachment->post_type ?? '' ) ) {
				throw new Exception( sprintf( __( "Post %d is not an attachment.", 'ss-media-compression' ), intval( $args['attachment_id'] ) ) );
			}

			$compressed = get_post_meta( $args['attachment_id'], 'ssmc_compressed', true );

			if ( '1' === $compressed ) {
				$response = sprintf( __( "Attachment %d is already compressed", 'ss-media-compression' ), intval( $args['attachment_id'] ) );
			} else {
				if ( '0' !== $compressed ) {
					update_post_meta( $args['attachment_id'], 'ssmc_compressed', '0' );
				}

				$response = self::compress( $args['attachment_id'], $args['metadata'] );
			}

			wp_send_json_success( $response ?? [] );
		} catch ( Exception $e ) {
			// Log here.
			$wp_error->add( $e->getCode(), $e->getMessage() );

			wp_send_json_error( $wp_error );
		}
	}

	/**
	 * Hooked into `ssmc_update_attachment_metadata` action hook.
	 *
	 * @param int $attachment_id
	 * @param array $new_metadata
	 */
	public static function ssmc_update_attachment_metadata_callback( $attachment_id, $new_metadata ) {
		wp_update_attachment_metadata( $attachment_id, $new_metadata );
	}

	/**
	 * @param int $attachment_id
	 * @param array $metadata
	 *
	 * @return array Updated attachment metadata.
	 * @throws Exception
	 */
	private static function compress( $attachment_id, $metadata = [] ) {
		$metadata   = $metadata ? $metadata : wp_get_attachment_metadata( $attachment_id );
		$upload_dir = wp_get_upload_dir();
		$files_dir  = ( $upload_dir['basedir'] ?? '' ) . '/' . dirname( $metadata['file'] ?? '' );

		$original_file = $files_dir . '/' . basename( $metadata['file'] ?? '' );

		if ( ! file_exists( $original_file ) ) {
			throw new Exception( sprintf( __( 'File %s does not exist.', 'ss-media-compression' ), $original_file ) );
		}

		// Resize image.
		$image = wp_get_image_editor( $original_file );

		if ( is_wp_error( $image ) ) {
			throw new Exception( 'Line ' . __LINE__ . ': ' . $image->get_error_message() );
		}

		$mw = (int) Functions::get_option( 'max_width' );
		$mh = (int) Functions::get_option( 'max_height' );

		$dimensions = $image->get_size();

		if ( $dimensions['width'] > $mw || $dimensions['height'] > $mh ) {
			$result = $image->resize( $mw, $mh, false );

			if ( is_wp_error( $result ) ) {
				throw new Exception( 'Line ' . __LINE__ . ': ' . $result->get_error_message() );
			}

			$image->save( $original_file );
		}

		// Optimize original file.
		$optimizer = ( new OptimizerChain() )
			->addOptimizer( new Jpegoptim( [ // @link https://www.kokkonen.net/tjko/src/man/jpegoptim.txt
				'-m' . Functions::get_option( 'jpeg_quality' ),
				'--strip-all',
				'--all-progressive',
				'--quiet',
			] ) )
			->addOptimizer( new Pngquant( [
				'--force',
			] ) )
			->addOptimizer( new Optipng( [
				'-i0',
				'-o2',
				'-quiet',
			] ) )
			->addOptimizer( new Svgo( [
				'--disable={cleanupIDs,removeViewBox}',
			] ) )
			->addOptimizer( new Gifsicle( [
				'-b',
				'-O3',
			] ) )
			->addOptimizer( new Cwebp( [
				'-m 6',
				'-pass 10',
				'-mt',
				'-q 80',
			] ) );

		$optimizer
			->setTimeout( 60 )
			->optimize( $original_file );

		/**
		 * Update media metadata.
		 */
		remove_filter( 'wp_generate_attachment_metadata', [ 'SS\MediaCompression\MediaCompression', 'wp_generate_attachment_metadata_callback' ], 10 );

		$new_metadata = wp_generate_attachment_metadata( $attachment_id, $original_file );

		/**
		 * Optimize file subsizes after metadata generation, because when generating metadata the subsizes are recreated anyway.
		 */
		// Try removing files which are not part of the subsizes anymore.
		$over_sizes = array_diff(
			array_column( $metadata['sizes'], 'file' ),
			array_column( $new_metadata['sizes'], 'file' )
		);

		foreach ( $over_sizes as $size ) {
			$size_file = $files_dir . "/$size";

			@unlink( $size_file );
		}

		// Optimize subsizes.
		foreach ( $new_metadata['sizes'] as $_size ) {
			$size_file = $files_dir . "/{$_size['file']}";

			if ( ! file_exists( $size_file ) ) {
				continue;
			}

			$optimizer
				->setTimeout( 30 )
				->optimize( $size_file );
		}

		update_post_meta( $attachment_id, 'ssmc_compressed', '1' );

		wp_schedule_single_event( time() + 30, "ssmc_update_attachment_metadata", [ $attachment_id, $new_metadata ] );

		return $new_metadata;
	}

	/**
	 * Hooked into `plugins_loaded` action hook.
	 *
	 * @return MediaCompression  Class instance.
	 */
	public static function get_instance() {
		if ( ! ( self::$instance instanceof self ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}
