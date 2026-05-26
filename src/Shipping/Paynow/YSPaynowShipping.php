<?php

namespace YangSheep\YSCartPaynow\Shipping\Paynow;

defined( 'ABSPATH' ) || exit;

use YangSheep\Ecommerce\Shipping\YSShippingInterface;
use YangSheep\Ecommerce\Utils\YSCrypto;
use YangSheep\Ecommerce\Utils\YSLogger;
use YangSheep\YSCartPaynow\Plugin;

abstract class YSPaynowShipping implements YSShippingInterface {
	protected string $id = '';
	protected string $title = '';
	protected string $type = 'cvs';
	protected string $service_code = '';

	private const API_BASE_URL      = 'https://www.paynow.com.tw/api/logistics';
	private const API_BASE_URL_TEST = 'https://test.paynow.com.tw/api/logistics';

	public function get_id(): string {
		return $this->id;
	}

	public function get_title(): string {
		return $this->title;
	}

	public function get_provider(): string {
		return 'paynow';
	}

	public function get_type(): string {
		return $this->type;
	}

	public function get_service_code(): string {
		return $this->service_code;
	}

	public function is_enabled(): bool {
		if ( class_exists( '\YangSheep\Ecommerce\Core\Provider\YSProviderLifecycleState' ) ) {
			return \YangSheep\Ecommerce\Core\Provider\YSProviderLifecycleState::is_method_enabled( 'shipping', $this->id, Plugin::manifest() );
		}

		return '1' === $this->get_option( 'enabled', '0' );
	}

	public function is_available( array $order_data ): bool {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		if ( empty( $this->get_merchant_id() ) || empty( $this->get_hash_key() ) || empty( $this->get_hash_iv() ) ) {
			return false;
		}

		$max_amount = (float) $this->get_option( 'max_amount', 0 );
		if ( $max_amount > 0 ) {
			$subtotal = $this->calculate_subtotal( $order_data['cart_items'] ?? [] );
			if ( $subtotal > $max_amount ) {
				return false;
			}
		}

		$max_weight = (float) $this->get_option( 'max_weight', 0 );
		if ( $max_weight > 0 ) {
			$total_weight = $this->calculate_total_weight( $order_data['cart_items'] ?? [] );
			if ( $total_weight > $max_weight ) {
				return false;
			}
		}

		return true;
	}

	public function get_free_threshold(): float {
		return (float) $this->get_option( 'free_threshold', 0 );
	}

	public function get_settings_fields(): array {
		return [
			'enabled'        => [
				'type'    => 'checkbox',
				'label'   => '啟用',
				'default' => '0',
			],
			'base_fee'       => [
				'type'    => 'number',
				'label'   => '基本運費',
				'default' => '60',
				'desc'    => '此物流方法的基本運費。',
			],
			'free_threshold' => [
				'type'    => 'number',
				'label'   => '免運門檻',
				'default' => '0',
				'desc'    => '訂單小計達此金額免運，0 表示不啟用。',
			],
			'max_amount'     => [
				'type'    => 'number',
				'label'   => '最高訂單金額',
				'default' => '0',
				'desc'    => '超過此金額不可使用，0 表示不限。',
			],
			'max_weight'     => [
				'type'    => 'number',
				'label'   => '最高重量（kg）',
				'default' => '0',
				'desc'    => '超過此重量不可使用，0 表示不限。',
			],
		];
	}

	public function supports_cod(): bool {
		return 'cvs' === $this->type;
	}

	public function get_supported_countries(): array {
		return [ 'TW' ];
	}

	protected function get_merchant_id(): string {
		return $this->get_paynow_option( 'merchant_id', '' );
	}

	protected function get_hash_key(): string {
		$encrypted = $this->get_paynow_option( 'hash_key', '' );
		return empty( $encrypted ) ? '' : YSCrypto::decrypt_from_storage( $encrypted );
	}

	protected function get_hash_iv(): string {
		$encrypted = $this->get_paynow_option( 'hash_iv', '' );
		return empty( $encrypted ) ? '' : YSCrypto::decrypt_from_storage( $encrypted );
	}

	protected function is_testmode(): bool {
		return '1' === $this->get_paynow_option( 'testmode', '0' );
	}

	protected function get_api_base_url(): string {
		return $this->is_testmode() ? self::API_BASE_URL_TEST : self::API_BASE_URL;
	}

	protected function encrypt_request( array $params ): array {
		$key = $this->get_hash_key();
		$iv  = $this->get_hash_iv();

		if ( empty( $key ) || empty( $iv ) ) {
			YSLogger::error( 'paynow_shipping', '缺少 Hash Key 或 Hash IV，無法建立 PayNow 物流請求。' );
			return [];
		}

		$raw_data     = http_build_query( $params );
		$encrypt_info = YSCrypto::encrypt_aes_gcm( $raw_data, $key, $iv );
		$hash_info    = YSCrypto::generate_hash( $raw_data, $key, $iv );

		return [
			'MerchantID'  => $this->get_merchant_id(),
			'Version'     => '2.0',
			'EncryptData' => $encrypt_info,
			'HashData'    => $hash_info,
		];
	}

	protected function decrypt_response( string $encrypt_data, string $hash_data ): ?array {
		$key = $this->get_hash_key();
		$iv  = $this->get_hash_iv();

		if ( empty( $key ) || empty( $iv ) ) {
			return null;
		}

		$raw_data = YSCrypto::decrypt_aes_gcm( $encrypt_data, $key, $iv );
		if ( empty( $raw_data ) ) {
			YSLogger::error( 'paynow_shipping', 'PayNow 回應解密失敗。' );
			return null;
		}

		$expected_hash = YSCrypto::generate_hash( $raw_data, $key, $iv );
		if ( ! hash_equals( $expected_hash, $hash_data ) ) {
			YSLogger::error( 'paynow_shipping', 'PayNow 回應 Hash 驗證失敗。' );
			return null;
		}

		parse_str( $raw_data, $result );
		return $result;
	}

	protected function get_option( string $key, $default = '' ) {
		global $wpdb;
		$table      = $wpdb->prefix . YS_ECOMMERCE_TABLE_PREFIX . 'settings';
		$option_key = 'shipping_' . $this->id . '_' . $key;

		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM {$table} WHERE setting_key = %s",
				$option_key
			)
		);

		return null !== $value ? $value : $default;
	}

	protected function get_paynow_option( string $key, $default = '' ) {
		global $wpdb;
		$table      = $wpdb->prefix . YS_ECOMMERCE_TABLE_PREFIX . 'settings';
		$option_key = 'shipping_paynow_' . $key;

		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM {$table} WHERE setting_key = %s",
				$option_key
			)
		);

		return null !== $value ? $value : $default;
	}

	protected function calculate_total_weight( array $cart_items ): float {
		$total = 0.0;
		foreach ( $cart_items as $item ) {
			$total += (float) ( $item['weight'] ?? 0 ) * (int) ( $item['qty'] ?? $item['quantity'] ?? 1 );
		}

		return $total;
	}

	protected function calculate_subtotal( array $cart_items ): float {
		$subtotal = 0.0;
		foreach ( $cart_items as $item ) {
			$price    = (float) ( $item['price'] ?? $item['unit_price'] ?? 0 );
			$quantity = (int) ( $item['qty'] ?? $item['quantity'] ?? 1 );
			$subtotal += $price * $quantity;
		}

		return $subtotal;
	}
}
