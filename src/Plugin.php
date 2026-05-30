<?php

namespace YangSheep\YSCartPaynow;

use YangSheep\Ecommerce\Api\Storefront\YSRequestParser;
use YangSheep\Ecommerce\Api\Storefront\YSRestAuth;
use YangSheep\Ecommerce\Api\Storefront\YSRestResponder;
use YangSheep\Ecommerce\Shipping\YSShippingRegistry;
use YangSheep\Ecommerce\YSEcommerce;
use YangSheep\YSCartPaynow\Admin\PaynowSettings;
use YangSheep\YSCartPaynow\Services\Shipping\Adapters\YSPaynowAdapter;
use YangSheep\YSCartPaynow\Shipping\Paynow\YSPaynowShipping;
use YangSheep\YSCartPaynow\Shipping\Paynow\YSPaynowShipping711;
use YangSheep\YSCartPaynow\Shipping\Paynow\YSPaynowShippingFamily;
use YangSheep\YSCartPaynow\Shipping\Paynow\YSPaynowShippingHilife;
use YangSheep\YSCartPaynow\Shipping\Paynow\YSPaynowShippingRequester;
use YangSheep\YSCartPaynow\Shipping\Paynow\YSPaynowShippingTcat;
use YangSheep\YSCartPaynow\Shipping\Paynow\YSPaynowStoreSelector;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function init(): void {
		PaynowSettings::register();

		add_filter( 'ys_ec_provider_manifests', [ $this, 'register_manifest' ], 10, 1 );
		add_action( 'ys_ec_register_shipping_methods', [ $this, 'register_shipping_methods' ] );
		add_action( 'ys_ec_register_storefront_routes', [ $this, 'register_storefront_routes' ] );
		add_action( 'rest_api_init', [ $this, 'register_legacy_store_callback_route' ] );
		add_filter( 'ys_ec_shipping_requester', [ $this, 'register_shipping_requester' ], 10, 2 );
		add_filter( 'ys_ec_before_create_shipment', [ $this, 'add_tcat_temperature_code' ], 10, 3 );
		add_filter( 'ys_ec_shipping_carrier_adapter', [ $this, 'register_carrier_adapter' ], 10, 2 );
		add_filter( 'ys_ec_shipping_provider_labels', [ $this, 'register_shipping_provider_label' ] );
	}

	/**
	 * @param array<int,array<string,mixed>> $manifests
	 * @return array<int,array<string,mixed>>
	 */
	public function register_manifest( array $manifests ): array {
		$manifests[] = self::manifest();

		return $manifests;
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function manifest(): array {
		static $manifest = null;

		if ( null === $manifest ) {
			$manifest = require YS_CART_PAYNOW_DIR . 'manifest.php';
		}

		return $manifest;
	}

	public function register_shipping_methods(): void {
		if ( ! class_exists( YSShippingRegistry::class ) || ! $this->is_paynow_shipping_enabled() ) {
			return;
		}

		$methods = [
			'ys_ec_paynow_ship_711'    => YSPaynowShipping711::class,
			'ys_ec_paynow_ship_family' => YSPaynowShippingFamily::class,
			'ys_ec_paynow_ship_hilife' => YSPaynowShippingHilife::class,
			'ys_ec_paynow_ship_tcat'   => YSPaynowShippingTcat::class,
		];

		foreach ( $methods as $method_id => $method_class ) {
			if ( $this->is_paynow_method_enabled( $method_id ) ) {
				YSShippingRegistry::register( new $method_class() );
			}
		}
	}

	public function register_storefront_routes( string $namespace ): void {
		if ( ! $this->is_paynow_shipping_enabled() ) {
			return;
		}

		register_rest_route(
			$namespace,
			'/stores/paynow/map-url',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'paynow_map_url' ],
				'permission_callback' => [ YSRestAuth::class, 'permission_customer_or_guest_write' ],
			]
		);
	}

	public function register_legacy_store_callback_route(): void {
		if ( ! $this->is_paynow_shipping_enabled() ) {
			return;
		}

		register_rest_route(
			'ys-ecommerce/v1',
			'/paynow/store-callback',
			[
				'methods'             => 'POST',
				'callback'            => [ YSPaynowStoreSelector::class, 'handle_store_callback' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	public static function manifest_paynow_map_url( \WP_REST_Request $request ): \WP_REST_Response {
		return self::instance()->paynow_map_url( $request );
	}

	public function paynow_map_url( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! $this->is_paynow_shipping_enabled() ) {
			return YSRestResponder::error( 'paynow_disabled', 'PayNow 物流尚未啟用。' );
		}

		$params      = YSRequestParser::params( $request );
		$shipping_id = sanitize_key( (string) ( $params['shipping_id'] ?? '' ) );

		if ( '' === $shipping_id ) {
			return YSRestResponder::error( 'missing_shipping_id', '缺少物流方式 ID。' );
		}

		if ( ! $this->is_paynow_method_enabled( $shipping_id ) ) {
			return YSRestResponder::error( 'shipping_method_disabled', 'PayNow 物流方式尚未啟用。' );
		}

		$result = YSPaynowStoreSelector::build_map_form_data( $shipping_id );

		if ( $result ) {
			return YSRestResponder::success( 'map_url_ready', '', $result );
		}

		return YSRestResponder::error( 'map_url_failed', 'PayNow API 設定尚未完成。' );
	}

	public function register_shipping_requester( $requester, $method ) {
		if ( null !== $requester ) {
			return $requester;
		}

		if ( ! $this->is_paynow_shipping_enabled() ) {
			return $requester;
		}

		if ( $method instanceof YSPaynowShipping ) {
			return new YSPaynowShippingRequester( $method );
		}

		return $requester;
	}

	/**
	 * @param array<string,mixed> $order_data
	 * @return array<string,mixed>
	 */
	public function add_tcat_temperature_code( array $order_data, int $order_id, $method ): array {
		unset( $order_id );

		if ( ! $this->is_paynow_shipping_enabled() ) {
			return $order_data;
		}

		if ( $method instanceof YSPaynowShippingTcat ) {
			$order_data['temperature_code'] = $method->get_temperature_code();
		}

		return $order_data;
	}

	public function register_carrier_adapter( $adapter, string $provider_key ) {
		if ( null !== $adapter ) {
			return $adapter;
		}

		if ( ! $this->is_paynow_shipping_enabled() ) {
			return $adapter;
		}

		if ( 'paynow' === $provider_key ) {
			return new YSPaynowAdapter();
		}

		return $adapter;
	}

	/**
	 * @param array<string,string> $labels
	 * @return array<string,string>
	 */
	public function register_shipping_provider_label( array $labels ): array {
		if ( ! $this->is_paynow_shipping_enabled() ) {
			return $labels;
		}

		$labels['paynow'] = 'PayNow';

		return $labels;
	}

	private function is_paynow_enabled(): bool {
		if ( class_exists( '\YangSheep\Ecommerce\Core\Provider\YSProviderLifecycleState' ) ) {
			return \YangSheep\Ecommerce\Core\Provider\YSProviderLifecycleState::is_provider_enabled( 'ys_paynow', self::manifest() );
		}

		if ( ! class_exists( YSEcommerce::class ) ) {
			return false;
		}

		return '1' === (string) YSEcommerce::get_instance()->get_setting( 'paynow_enabled', '0' );
	}

	private function is_paynow_shipping_enabled(): bool {
		if ( class_exists( '\YangSheep\Ecommerce\Core\Provider\YSProviderLifecycleState' ) ) {
			return \YangSheep\Ecommerce\Core\Provider\YSProviderLifecycleState::is_capability_enabled( 'ys_paynow', 'shipping', self::manifest() );
		}

		return $this->is_paynow_enabled();
	}

	private function is_paynow_method_enabled( string $method_id ): bool {
		if ( class_exists( '\YangSheep\Ecommerce\Core\Provider\YSProviderLifecycleState' ) ) {
			return \YangSheep\Ecommerce\Core\Provider\YSProviderLifecycleState::is_method_enabled( 'shipping', $method_id, self::manifest() );
		}

		if ( ! class_exists( YSEcommerce::class ) ) {
			return false;
		}

		return $this->is_paynow_enabled()
			&& '1' === (string) YSEcommerce::get_instance()->get_setting( 'shipping_' . sanitize_key( $method_id ) . '_enabled', '0' );
	}
}
