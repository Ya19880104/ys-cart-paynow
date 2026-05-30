<?php
/**
 * v1.1.7 shipping filter contract.
 *
 * Core calls:
 *   apply_filters( 'ys_ec_before_create_shipment', $order_data, $order_id, $method )
 *
 * PayNow must not fatal when another provider, such as PayUni, creates a label.
 */

$root = dirname( __DIR__, 2 );
$file = $root . '/src/Plugin.php';
$src  = file_get_contents( $file );

$pass = 0;
$fail = 0;

function ys_paynow_v117_assert( bool $condition, string $message ): void {
	global $pass, $fail;
	if ( $condition ) {
		$pass++;
		echo "PASS: {$message}\n";
		return;
	}
	$fail++;
	echo "FAIL: {$message}\n";
}

ys_paynow_v117_assert(
	(bool) preg_match( '/add_filter\(\s*[\'"]ys_ec_before_create_shipment[\'"]\s*,\s*\[\s*\$this\s*,\s*[\'"]add_tcat_temperature_code[\'"]\s*\]\s*,\s*10\s*,\s*3\s*\)/', $src ),
	'PayNow registers ys_ec_before_create_shipment with three accepted args.'
);

ys_paynow_v117_assert(
	strpos( $src, 'public function add_tcat_temperature_code( array $order_data, int $order_id, $method ): array' ) !== false,
	'PayNow callback signature matches core order: order_data, order_id, method.'
);

ys_paynow_v117_assert(
	strpos( $src, 'if ( $method instanceof YSPaynowShippingTcat )' ) !== false,
	'Temperature code injection is scoped to PayNow TCAT methods only.'
);

echo "v117_shipping_filter_contract: PASS={$pass} FAIL={$fail}\n";
exit( $fail > 0 ? 1 : 0 );
