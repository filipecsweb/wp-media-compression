<?php
/**
 * Plugin Name:  SS - Otimização de Imagens
 * Description:  Otimiza e redimensiona imagens da biblioteca de mídia do WP.
 * Author:       Filipe Seabra
 * Author URI:   https://filipecsweb.com.br
 * License:      GPLv3
 * License URI:  https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: ss-media-compression
 * Version: 1.0.0.00
 *
 * @link https://github.com/spatie/image-optimizer
 */

use Spatie\ImageOptimizer\OptimizerChain;
use Spatie\ImageOptimizer\Optimizers\Cwebp;
use Spatie\ImageOptimizer\Optimizers\Gifsicle;
use Spatie\ImageOptimizer\Optimizers\Jpegoptim;
use Spatie\ImageOptimizer\Optimizers\Optipng;
use Spatie\ImageOptimizer\Optimizers\Pngquant;
use Spatie\ImageOptimizer\Optimizers\Svgo;

class SS_Media_Compression {

	/**
	 * @var    SS_Media_Compression $instance Instance of this class.
	 */
	private static $instance;

	private static $max_width = 1920;

	private static $max_height = 1080;

	public function __construct() {
		require_once 'vendor/autoload.php';

		add_filter( 'wp_generate_attachment_metadata', [ 'SS_Media_Compression', 'wp_generate_attachment_metadata_callback' ], 10, 2 );
		add_filter( 'wp_update_attachment_metadata', [ 'SS_Media_Compression', 'wp_update_attachment_metadata_callback' ], 10, 2 );
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
		try {
			return self::compress( $attachment_id, $metadata );
		} catch ( Exception $e ) {
			// Log here.
			return $metadata;
		}
	}

	/**
	 * Hooked into `wp_update_attachment_metadata` filter hook.
	 *
	 * @param array $metadata Array of updated attachment meta data.
	 * @param int $attachment_id Attachment post ID.
	 *
	 * @return array
	 */
	public static function wp_update_attachment_metadata_callback( $metadata, $attachment_id ): array {
		try {
			return self::compress( $attachment_id, $metadata );
		} catch ( Exception $e ) {
			// Log here.
			return $metadata;
		}
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
			throw new Exception( $image->get_error_message() );
		}

		$mw = self::$max_width;
		$mh = self::$max_height;

		$dimensions = $image->get_size();

		if ( $dimensions['width'] > $mw || $dimensions['height'] > $mh ) {
			$result = $image->resize( $mw, $mh, false );

			if ( is_wp_error( $result ) ) {
				throw new Exception( $result->get_error_message() );
			}

			$image->save( $original_file );
		}

		// Optimize original file.
		$optimizer = ( new OptimizerChain() )
			->addOptimizer( new Jpegoptim( [ // @link https://www.kokkonen.net/tjko/src/man/jpegoptim.txt
				'-m75',
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
			->setTimeout( 30 )
			->optimize( $original_file );

		// Update media metadata.
		remove_filter( 'wp_generate_attachment_metadata', [ 'SS_Media_Compression', 'wp_generate_attachment_metadata_callback' ], 10 );
		remove_filter( 'wp_update_attachment_metadata', [ 'SS_Media_Compression', 'wp_update_attachment_metadata_callback' ], 10 );

		$new_metadata = wp_generate_attachment_metadata( $attachment_id, $original_file );

		wp_update_attachment_metadata( $attachment_id, $new_metadata );

		add_filter( 'wp_generate_attachment_metadata', [ 'SS_Media_Compression', 'wp_generate_attachment_metadata_callback' ], 10, 2 );
		add_filter( 'wp_update_attachment_metadata', [ 'SS_Media_Compression', 'wp_update_attachment_metadata_callback' ], 10, 2 );

		/**
		 * Optimize file subsizes after metadata generation, because when generating metadata the subsizes are recreated anyway.
		 */
		$updated_metadata = wp_get_attachment_metadata( $attachment_id );

		if ( ! $updated_metadata ) {
			throw new Exception( sprintf( __( "Couldn't retrieve metadata for attachment %d", 'ss-media-compression' ), intval( $attachment_id ) ) );
		}

		// Try removing files which are not part of the subsizes anymore.
		$over_sizes = array_diff(
			array_column( $metadata['sizes'], 'file' ),
			array_column( $updated_metadata['sizes'], 'file' )
		);

		foreach ( $over_sizes as $size ) {
			$size_file = $files_dir . "/$size";

			@unlink( $size_file );
		}

		// Optimize subsizes.
		foreach ( $updated_metadata['sizes'] as $_size ) {
			$size_file = $files_dir . "/{$_size['file']}";

			if ( ! file_exists( $size_file ) ) {
				continue;
			}

			$optimizer
				->setTimeout( 15 )
				->optimize( $size_file );
		}

		// Return.
		return $updated_metadata;
	}

	/**
	 * Hooked into `plugins_loaded` action hook.
	 *
	 * @return  SS_Media_Compression  Class instance.
	 */
	public static function get_instance() {
		if ( ! ( self::$instance instanceof self ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}

add_action( 'plugins_loaded', [ 'SS_Media_Compression', 'get_instance' ] );
