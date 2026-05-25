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

		add_action( 'ys_ec_register_shipping_methods', [ $this, 'register_shipping_methods' ] );
		add_filter( 'ys_ec_providers', [ $this, 'register_provider' ] );
		add_action( 'ys_ec_admin_payment_menus', [ $this, 'register_admin_menu' ], 10, 2 );
		add_action( 'ys_ec_register_storefront_routes', [ $this, 'register_storefront_routes' ] );
		add_action( 'rest_api_init', [ $this, 'register_legacy_store_callback_route' ] );
		add_filter( 'ys_ec_shipping_requester', [ $this, 'register_shipping_requester' ], 10, 2 );
		add_filter( 'ys_ec_before_create_shipment', [ $this, 'add_tcat_temperature_code' ], 10, 3 );
		add_filter( 'ys_ec_shipping_carrier_adapter', [ $this, 'register_carrier_adapter' ], 10, 2 );
		add_filter( 'ys_ec_shipping_provider_labels', [ $this, 'register_shipping_provider_label' ] );
	}

	public function register_shipping_methods(): void {
		if ( ! class_exists( YSShippingRegistry::class ) ) {
			return;
		}

		if ( ! $this->is_paynow_enabled() ) {
			return;
		}

		YSShippingRegistry::register( new YSPaynowShipping711() );
		YSShippingRegistry::register( new YSPaynowShippingFamily() );
		YSShippingRegistry::register( new YSPaynowShippingHilife() );
		YSShippingRegistry::register( new YSPaynowShippingTcat() );
	}

	/**
	 * @param array<string,array<string,mixed>> $providers
	 * @return array<string,array<string,mixed>>
	 */
	public function register_provider( array $providers ): array {
		$providers['paynow'] = [
			'name'        => 'PayNow 立吉富',
			'icon'        => 'dashicons-store',
			'description' => 'PayNow 超商取貨與黑貓宅配物流。',
			'payment'     => [],
			'shipping'    => [ '7-ELEVEN', '全家', '萊爾富', '黑貓宅配' ],
			'setting_key' => 'paynow_enabled',
			'admin_url'   => admin_url( 'admin.php?page=ys-ec-paynow' ),
		];

		return $providers;
	}

	public function register_admin_menu( string $parent_slug, string $capability ): void {
		add_submenu_page(
			$parent_slug,
			'PayNow 設定',
			'PayNow',
			$capability,
			'ys-ec-paynow',
			[ PaynowSettings::class, 'render_page' ]
		);
	}

	public function register_storefront_routes( string $namespace ): void {
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

	public function paynow_map_url( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! $this->is_paynow_enabled() ) {
			return YSRestResponder::error( 'paynow_disabled', 'PayNow logistics is disabled.' );
		}

		$params      = YSRequestParser::params( $request );
		$shipping_id = sanitize_text_field( $params['shipping_id'] ?? '' );

		if ( '' === $shipping_id ) {
			return YSRestResponder::error( 'missing_shipping_id', 'Missing shipping method ID.' );
		}

		$result = YSPaynowStoreSelector::build_map_form_data( $shipping_id );

		if ( $result ) {
			return YSRestResponder::success( 'map_url_ready', '', $result );
		}

		return YSRestResponder::error( 'map_url_failed', 'PayNow API settings are incomplete.' );
	}

	public function register_shipping_requester( $requester, $method ) {
		if ( null !== $requester ) {
			return $requester;
		}

		if ( ! $this->is_paynow_enabled() ) {
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
	public function add_tcat_temperature_code( array $order_data, $method, int $order_id ): array {
		unset( $order_id );

		if ( ! $this->is_paynow_enabled() ) {
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

		if ( ! $this->is_paynow_enabled() ) {
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
		$labels['paynow'] = 'PayNow';

		return $labels;
	}

	private function is_paynow_enabled(): bool {
		if ( ! class_exists( YSEcommerce::class ) ) {
			return false;
		}

		return '1' === (string) YSEcommerce::get_instance()->get_setting( 'paynow_enabled', '0' );
	}
}
