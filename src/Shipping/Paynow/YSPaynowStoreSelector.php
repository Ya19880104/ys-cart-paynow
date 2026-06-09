<?php

namespace YangSheep\YSCartPaynow\Shipping\Paynow;

defined( 'ABSPATH' ) || exit;

use YangSheep\Ecommerce\Utils\YSCrypto;
use YangSheep\Ecommerce\Utils\YSLogger;
use YangSheep\Ecommerce\Services\Setup\YSPageResolver;
use YangSheep\YSCartPaynow\Plugin;

class YSPaynowStoreSelector {
	private const MAP_URL      = 'https://www.paynow.com.tw/logistics/map';
	private const MAP_URL_TEST = 'https://test.paynow.com.tw/logistics/map';

	private const CVS_TYPES = [
		'ys_ec_paynow_ship_711'    => '01',
		'ys_ec_paynow_ship_family' => '03',
		'ys_ec_paynow_ship_hilife' => '05',
	];

	public static function init(): void {
		// REST routes are registered by the provider plugin.
	}

	/**
	 * @return array{map_url:string,temp_id:string}|false
	 */
	public static function build_map_form_data( string $shipping_id, string $cart_scope = 'default', string $return_url = '' ) {
		$shipping_id = sanitize_key( $shipping_id );
		if ( '' === $shipping_id || ! isset( self::CVS_TYPES[ $shipping_id ] ) || ! self::is_method_enabled( $shipping_id ) ) {
			return false;
		}

		$cart_scope = self::sanitize_cart_scope( $cart_scope );
		$return_url = self::sanitize_return_url( $return_url, $cart_scope );

		$service_code = self::CVS_TYPES[ $shipping_id ];
		$temp_id      = wp_generate_uuid4();

		set_transient(
			'ys_ec_paynow_map_' . $temp_id,
			[
				'shipping_id'  => $shipping_id,
				'service_code' => $service_code,
				'user_id'      => get_current_user_id(),
				'cart_scope'  => $cart_scope,
				'return_url'   => $return_url,
				'created_at'   => current_time( 'timestamp' ),
			],
			30 * MINUTE_IN_SECONDS
		);

		$callback_url = rest_url( 'ys-ecommerce/v1/paynow/store-callback' );
		$merchant_id  = self::setting( 'shipping_paynow_merchant_id' );
		$hash_key_raw = self::setting( 'shipping_paynow_hash_key' );
		$hash_iv_raw  = self::setting( 'shipping_paynow_hash_iv' );
		$testmode     = self::setting( 'shipping_paynow_testmode' );

		if ( empty( $merchant_id ) || empty( $hash_key_raw ) || empty( $hash_iv_raw ) ) {
			return false;
		}

		$hash_key = YSCrypto::decrypt_from_storage( $hash_key_raw );
		$hash_iv  = YSCrypto::decrypt_from_storage( $hash_iv_raw );

		if ( empty( $hash_key ) || empty( $hash_iv ) ) {
			return false;
		}

		$params = [
			'MerchantID'     => $merchant_id,
			'ServiceCode'    => $service_code,
			'TempId'         => $temp_id,
			'ServerReplyURL' => $callback_url,
		];

		$raw_data     = http_build_query( $params );
		$encrypt_data = YSCrypto::encrypt_aes_gcm( $raw_data, $hash_key, $hash_iv );
		$hash_data    = YSCrypto::generate_hash( $raw_data, $hash_key, $hash_iv );
		$base_url     = ( '1' === $testmode ) ? self::MAP_URL_TEST : self::MAP_URL;

		$map_url = $base_url . '?' . http_build_query(
			[
				'MerchantID'  => $merchant_id,
				'Version'     => '2.0',
				'EncryptData' => $encrypt_data,
				'HashData'    => $hash_data,
			]
		);

		return [
			'map_url' => $map_url,
			'temp_id' => $temp_id,
		];
	}

	/**
	 * REST-only handler; request must be a WP_REST_Request.
	 */
	public static function handle_store_callback( \WP_REST_Request $request ): void {
		$params = $request->get_params();
		$encrypt_data = sanitize_text_field( wp_unslash( $params['EncryptData'] ?? '' ) );
		$hash_data    = sanitize_text_field( wp_unslash( $params['HashData'] ?? '' ) );

		if ( empty( $encrypt_data ) || empty( $hash_data ) ) {
			YSLogger::error( 'paynow_store', '選店回呼缺少加密資料。' );
			wp_die( '參數錯誤', 'PayNow Store Callback', [ 'response' => 400 ] );
		}

		$hash_key = YSCrypto::decrypt_from_storage( self::setting( 'shipping_paynow_hash_key' ) );
		$hash_iv  = YSCrypto::decrypt_from_storage( self::setting( 'shipping_paynow_hash_iv' ) );
		if ( empty( $hash_key ) || empty( $hash_iv ) ) {
			YSLogger::error( 'paynow_store', 'PayNow 選店回呼缺少 Hash Key 或 Hash IV。' );
			wp_die( '設定錯誤', 'PayNow Store Callback', [ 'response' => 500 ] );
		}

		$raw_data = YSCrypto::decrypt_aes_gcm( $encrypt_data, $hash_key, $hash_iv );

		if ( empty( $raw_data ) ) {
			YSLogger::error( 'paynow_store', 'PayNow 選店回呼解密失敗。' );
			wp_die( '解密失敗', 'PayNow Store Callback', [ 'response' => 400 ] );
		}

		$expected_hash = YSCrypto::generate_hash( $raw_data, $hash_key, $hash_iv );
		if ( ! hash_equals( $expected_hash, $hash_data ) ) {
			YSLogger::error( 'paynow_store', 'PayNow 選店回呼 Hash 驗證失敗。' );
			wp_die( 'Hash 驗證失敗', 'PayNow Store Callback', [ 'response' => 400 ] );
		}

		parse_str( $raw_data, $store_data );
		$temp_id  = sanitize_text_field( $store_data['TempId'] ?? '' );
		$map_data = get_transient( 'ys_ec_paynow_map_' . $temp_id );
		if ( ! is_array( $map_data ) ) {
			YSLogger::error( 'paynow_store', 'PayNow 選店回呼 TempId 不存在。' );
			wp_die( 'TempId 不存在', 'PayNow Store Callback', [ 'response' => 400 ] );
		}

		$shipping_id = sanitize_key( (string) ( $map_data['shipping_id'] ?? '' ) );
		if ( '' === $shipping_id || ! self::is_method_enabled( $shipping_id ) ) {
			YSLogger::warning( 'paynow_store', 'PayNow 選店回呼對應物流方式已停用。', [ 'shipping_id' => $shipping_id ] );
			wp_die( '物流方式已停用', 'PayNow Store Callback', [ 'response' => 403 ] );
		}

		$store_info = [
			'provider'      => 'paynow',
			'store_id'      => sanitize_text_field( $store_data['StoreID'] ?? '' ),
			'store_name'    => sanitize_text_field( $store_data['StoreName'] ?? '' ),
			'store_address' => sanitize_text_field( $store_data['StoreAddr'] ?? '' ),
			'store_phone'   => sanitize_text_field( $store_data['StorePhone'] ?? '' ),
			'service_code'  => sanitize_text_field( (string) ( $map_data['service_code'] ?? '' ) ),
			'shipping_id'   => $shipping_id,
			'cart_scope'    => self::sanitize_cart_scope( (string) ( $map_data['cart_scope'] ?? 'default' ) ),
			'return_url'    => self::sanitize_return_url(
				(string) ( $map_data['return_url'] ?? '' ),
				(string) ( $map_data['cart_scope'] ?? 'default' )
			),
			'selected_at'   => current_time( 'mysql' ),
		];

		set_transient( 'ys_ec_paynow_store_' . $temp_id, $store_info, 30 * MINUTE_IN_SECONDS );
		delete_transient( 'ys_ec_paynow_map_' . $temp_id );

		YSLogger::info( 'paynow_store', 'PayNow 選店完成。', $store_info );
		self::render_callback_page( $store_info );
	}

	private static function render_callback_page( array $store_info ): void {
		$json_data = wp_json_encode( $store_info, JSON_UNESCAPED_UNICODE );
		$checkout_url = esc_url( $store_info['return_url'] ?? self::checkout_url() );
		?>
		<!DOCTYPE html>
		<html>
		<head><meta charset="UTF-8"><title>PayNow 門市已選擇</title></head>
		<body>
		<script>
		(function() {
			var storeData = <?php echo $json_data; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
			storeData._timestamp = Date.now();
			var serialized = JSON.stringify(storeData);
			try {
				localStorage.setItem('ys_ec_selected_store', serialized);
			} catch (e) {
				try { sessionStorage.setItem('ys_ec_selected_store', serialized); } catch (e2) {}
			}
			if (window.opener) {
				window.opener.postMessage({
					type: 'ys_ec_store_selected',
					action: 'ys_ec_store_selected',
					provider: 'paynow',
					data: storeData
				}, '<?php echo esc_js( home_url() ); ?>');
				window.close();
				return;
			}
			window.location.replace(<?php echo wp_json_encode( $checkout_url ); ?>);
		})();
		</script>
		</body>
		</html>
		<?php
		exit;
	}

	private static function sanitize_cart_scope( string $scope ): string {
		$scope = sanitize_key( $scope );
		if ( '' === $scope || ! preg_match( '/^[a-z0-9_]{1,32}$/', $scope ) ) {
			return 'default';
		}

		return $scope;
	}

	private static function sanitize_return_url( string $return_url, string $cart_scope = 'default' ): string {
		$fallback = self::checkout_url();
		if ( 'default' !== $cart_scope ) {
			$fallback = add_query_arg( [ 'cart_scope' => $cart_scope ], $fallback );
		}

		$return_url = trim( $return_url );
		if ( '' === $return_url ) {
			return $fallback;
		}

		$return_url = wp_validate_redirect( esc_url_raw( $return_url ), $fallback );
		if ( 'default' !== $cart_scope ) {
			$return_url = add_query_arg( [ 'cart_scope' => $cart_scope ], $return_url );
		}

		return $return_url ?: $fallback;
	}

	private static function checkout_url(): string {
		if ( class_exists( YSPageResolver::class ) ) {
			return YSPageResolver::checkout_url();
		}

		return home_url( '/checkout/' );
	}

	private static function setting( string $key ): string {
		global $wpdb;
		$table = $wpdb->prefix . YS_ECOMMERCE_TABLE_PREFIX . 'settings';

		return (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM {$table} WHERE setting_key = %s",
				$key
			)
		);
	}

	private static function is_method_enabled( string $shipping_id ): bool {
		if ( class_exists( '\YangSheep\Ecommerce\Core\Provider\YSProviderLifecycleState' ) ) {
			return \YangSheep\Ecommerce\Core\Provider\YSProviderLifecycleState::is_method_enabled( 'shipping', $shipping_id, Plugin::manifest() );
		}

		return '1' === self::setting( 'shipping_' . sanitize_key( $shipping_id ) . '_enabled' );
	}
}
