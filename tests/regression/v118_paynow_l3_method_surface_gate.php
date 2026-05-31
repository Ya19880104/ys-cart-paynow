<?php
/**
 * v1.1.8 PayNow L3 method-surface gate regression.
 */

declare(strict_types=1);

$root    = dirname( __DIR__, 2 );
$plugin  = (string) file_get_contents( $root . '/ys-cart-paynow.php' );
$runtime = (string) file_get_contents( $root . '/src/Plugin.php' );

$pass = 0;
$fail = 0;

function v118_check( string $label, bool $ok ): void {
	global $pass, $fail;
	if ( $ok ) {
		++$pass;
		echo "[PASS] {$label}\n";
		return;
	}

	++$fail;
	echo "[FAIL] {$label}\n";
}

preg_match( '/Version:\s*([0-9.]+)/', $plugin, $version_match );
preg_match( "/YS_CART_PAYNOW_VERSION', '([0-9.]+)'/", $plugin, $constant_match );
$version = (string) ( $version_match[1] ?? '' );

v118_check(
	'Plugin version bumped to 1.1.8+ and header/constant match',
	version_compare( $version, '1.1.8', '>=' )
		&& $version === (string) ( $constant_match[1] ?? '' )
);

v118_check(
	'PayNow declares one canonical method id set for L3 gating',
	str_contains( $runtime, 'private const SHIPPING_METHOD_IDS' )
		&& str_contains( $runtime, "'ys_ec_paynow_ship_711'" )
		&& str_contains( $runtime, "'ys_ec_paynow_ship_family'" )
		&& str_contains( $runtime, "'ys_ec_paynow_ship_hilife'" )
		&& str_contains( $runtime, "'ys_ec_paynow_ship_tcat'" )
);

v118_check(
	'L3 helper requires provider shipping capability and at least one method',
	str_contains( $runtime, 'private function has_enabled_paynow_shipping_methods(): bool' )
		&& str_contains( $runtime, 'if ( ! $this->is_paynow_shipping_enabled() )' )
		&& str_contains( $runtime, 'foreach ( self::SHIPPING_METHOD_IDS as $method_id )' )
		&& str_contains( $runtime, '$this->is_paynow_method_enabled( $method_id )' )
);

foreach (
	[
		'register_shipping_methods'             => 'return;',
		'register_storefront_routes'            => 'return;',
		'register_legacy_store_callback_route'  => 'return;',
		'register_shipping_requester'           => 'return $requester;',
		'add_tcat_temperature_code'             => 'return $order_data;',
		'register_carrier_adapter'              => 'return $adapter;',
		'register_shipping_provider_label'      => 'return $labels;',
	] as $method => $return
) {
	v118_check(
		"{$method} is gated by at least one enabled PayNow method",
		preg_match( '/public function ' . preg_quote( $method, '/' ) . '\(.*?if \( ! \$this->has_enabled_paynow_shipping_methods\(\) \) \{.*?' . preg_quote( $return, '/' ) . '/s', $runtime ) === 1
	);
}

v118_check(
	'PayNow map URL endpoint itself stays fail-closed when all methods are off',
	preg_match( '/public function paynow_map_url\(.*?if \( ! \$this->has_enabled_paynow_shipping_methods\(\) \) \{.*?paynow_disabled/s', $runtime ) === 1
);

v118_check(
	'No runtime surface uses provider capability alone after L3 gate',
	substr_count( $runtime, 'has_enabled_paynow_shipping_methods()' ) >= 8
		&& substr_count( $runtime, 'if ( ! $this->is_paynow_shipping_enabled() )' ) === 1
);

echo "v1.1.8 PayNow L3 method-surface gate regression: PASS={$pass} FAIL={$fail}\n";
exit( $fail > 0 ? 1 : 0 );
