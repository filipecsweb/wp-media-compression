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

defined( 'ABSPATH' ) || exit;

add_action( 'plugins_loaded', function () {
	require_once 'vendor/autoload.php';

	SS\MediaCompression\MediaCompression::get_instance();
} );
