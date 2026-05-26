<?php
/**
 * Plugin Name: YS CART - PayNow
 * Plugin URI: https://github.com/Ya19880104/ys-cart-paynow
 * Description: Adds PayNow logistics methods to YS CART as an external provider plugin.
 * Version: 1.1.6
 * Author: YangSheep
 * Author URI: https://yangsheep.com.tw
 * Requires PHP: 8.1
 * Requires Plugins: ys-cart
 * Text Domain: ys-cart-paynow
 */

defined( 'ABSPATH' ) || exit;

define( 'YS_CART_PAYNOW_VERSION', '1.1.6' );
define( 'YS_CART_PAYNOW_FILE', __FILE__ );
define( 'YS_CART_PAYNOW_DIR', plugin_dir_path( __FILE__ ) );
define( 'YS_CART_PAYNOW_URL', plugin_dir_url( __FILE__ ) );
define( 'YS_CART_PAYNOW_BASENAME', plugin_basename( __FILE__ ) );

if ( is_readable( YS_CART_PAYNOW_DIR . 'vendor/autoload.php' ) ) {
	require_once YS_CART_PAYNOW_DIR . 'vendor/autoload.php';
}

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'YangSheep\\YSCartPaynow\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$file     = YS_CART_PAYNOW_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		if ( ! class_exists( \YangSheep\Ecommerce\Shipping\YSShippingRegistry::class ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					if ( current_user_can( 'activate_plugins' ) ) {
						echo '<div class="notice notice-error"><p>YS CART - PayNow 需要先啟用 YS CART。</p></div>';
					}
				}
			);
			return;
		}

		if ( class_exists( '\YangSheep\PluginHubClient\YSPluginHubClient' ) ) {
			\YangSheep\PluginHubClient\YSPluginHubClient::register(
				[
					'slug'        => 'ys-cart-paynow',
					'version'     => YS_CART_PAYNOW_VERSION,
					'plugin_file' => __FILE__,
					'name'        => 'YS CART - PayNow',
				]
			);
		}

		\YangSheep\YSCartPaynow\Plugin::instance()->init();
	},
	30
);
