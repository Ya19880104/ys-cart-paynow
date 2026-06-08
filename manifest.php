<?php
/**
 * PayNow provider manifest for YS CART.
 *
 * @package YangSheep\YSCartPaynow
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

return [
	'id'                 => 'ys_paynow',
	'name'               => 'PayNow 物流',
	'description'        => 'PayNow 超商取貨與宅配物流整合。',
	'version'            => YS_CART_PAYNOW_VERSION,
	'contract_version'   => 1,
	'plugin_file'        => YS_CART_PAYNOW_BASENAME,
	'icon'               => 'dashicons-store',
	'documentation_url'  => 'https://www.paynow.com.tw/',
	'legacy_setting_key' => 'paynow_enabled',
	'domains'            => [ 'shipping' ],
	'capabilities'       => [
		'shipping' => [
			'methods'             => [
				[
					'id'             => 'ys_ec_paynow_ship_711',
					'label'          => '7-ELEVEN 超商取貨',
					'provider_label' => 'PayNow',
					'class'          => \YangSheep\YSCartPaynow\Shipping\Paynow\YSPaynowShipping711::class,
					'shipping_type'  => 'cvs',
					'store_selector' => \YangSheep\YSCartPaynow\Shipping\Paynow\YSPaynowStoreSelector::class,
					'supports'       => [ 'cod' => false, 'tracking' => true ],
				],
				[
					'id'             => 'ys_ec_paynow_ship_family',
					'label'          => '全家超商取貨',
					'provider_label' => 'PayNow',
					'class'          => \YangSheep\YSCartPaynow\Shipping\Paynow\YSPaynowShippingFamily::class,
					'shipping_type'  => 'cvs',
					'store_selector' => \YangSheep\YSCartPaynow\Shipping\Paynow\YSPaynowStoreSelector::class,
					'supports'       => [ 'cod' => false, 'tracking' => true ],
				],
				[
					'id'             => 'ys_ec_paynow_ship_hilife',
					'label'          => '萊爾富超商取貨',
					'provider_label' => 'PayNow',
					'class'          => \YangSheep\YSCartPaynow\Shipping\Paynow\YSPaynowShippingHilife::class,
					'shipping_type'  => 'cvs',
					'store_selector' => \YangSheep\YSCartPaynow\Shipping\Paynow\YSPaynowStoreSelector::class,
					'supports'       => [ 'cod' => false, 'tracking' => true ],
				],
				[
					'id'             => 'ys_ec_paynow_ship_tcat',
					'label'          => '黑貓宅配',
					'provider_label' => 'PayNow',
					'class'          => \YangSheep\YSCartPaynow\Shipping\Paynow\YSPaynowShippingTcat::class,
					'shipping_type'  => 'home',
					'supports'       => [ 'cod' => false, 'tracking' => true, 'temperature_control' => true ],
				],
			],
			'supported_countries' => [ 'TW' ],
			'shipping_requester'  => \YangSheep\YSCartPaynow\Shipping\Paynow\YSPaynowShippingRequester::class,
			'carrier_adapter'     => \YangSheep\YSCartPaynow\Services\Shipping\Adapters\YSPaynowAdapter::class,
		],
	],
	'admin_page'         => [
		'slug'                => 'ys-provider-paynow',
		'title'               => 'PayNow 物流設定',
		'render_callback'     => [ \YangSheep\YSCartPaynow\Admin\PaynowSettings::class, 'render_page' ],
		'capability_required' => 'manage_options',
		'icon'                => 'dashicons-store',
	],
	'callback_routes'    => [
		'store_map_url'  => [
			'namespace'           => 'ys-ecommerce-headless/v1',
			'route'               => '/stores/paynow/map-url',
			'methods'             => [ 'POST' ],
			'callback'            => [ \YangSheep\YSCartPaynow\Plugin::class, 'manifest_paynow_map_url' ],
			'permission_callback' => [ \YangSheep\Ecommerce\Api\Storefront\YSRestAuth::class, 'permission_customer_or_guest_write' ],
			'signature_scheme'    => 'none',
		],
		'store_callback' => [
			'namespace'           => 'ys-ecommerce/v1',
			'route'               => '/paynow/store-callback',
			'methods'             => [ 'POST' ],
			'callback'            => [ \YangSheep\YSCartPaynow\Shipping\Paynow\YSPaynowStoreSelector::class, 'handle_store_callback' ],
			'permission_callback' => [ \YangSheep\YSCartPaynow\Plugin::class, 'store_callback_permission' ],
			'signature_scheme'    => 'paynow_aes_hash',
			'bypass_nonce'        => true,
		],
	],
	'allowed_hosts'      => [ 'www.paynow.com.tw', 'test.paynow.com.tw' ],
	'health_check'       => [
		'callback'      => null,
		'cache_ttl'     => 3600,
		'failure_codes' => [ 'missing_credentials', 'invalid_credentials', 'network_unreachable' ],
	],
];
