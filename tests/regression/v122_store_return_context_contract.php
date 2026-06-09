<?php
/**
 * PayNow store-map flow must preserve caller return context for one-page checkout.
 */

$root = dirname( __DIR__, 2 );
$pass = 0;
$fail = 0;
$bad  = [];

$read = static function ( string $path ) use ( $root ): string {
	$full = $root . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $path );
	return is_file( $full ) ? (string) file_get_contents( $full ) : '';
};

$check = static function ( string $name, bool $ok ) use ( &$pass, &$fail, &$bad ): void {
	if ( $ok ) {
		$pass++;
		echo "  PASS  {$name}\n";
		return;
	}
	$fail++;
	$bad[] = $name;
	echo "  FAIL  {$name}\n";
};

$main     = $read( 'ys-cart-paynow.php' );
$plugin   = $read( 'src/Plugin.php' );
$selector = $read( 'src/Shipping/Paynow/YSPaynowStoreSelector.php' );

$check(
	'version bumped for store return context fix',
	strpos( $main, 'Version: 1.1.10' ) !== false
		&& strpos( $main, "define( 'YS_CART_PAYNOW_VERSION', '1.1.10' )" ) !== false
);

$check(
	'map route accepts return_url/cart_scope and passes them to selector',
	strpos( $plugin, "\$cart_scope  = self::sanitize_cart_scope" ) !== false
		&& strpos( $plugin, "\$return_url  = esc_url_raw" ) !== false
		&& preg_match( '/build_map_form_data\(\s*\$shipping_id,\s*\$cart_scope,\s*\$return_url\s*\)/s', $plugin ) === 1
);

$check(
	'selector stores sanitized return context in map transient and store payload',
	strpos( $selector, "string \$cart_scope = 'default'" ) !== false
		&& strpos( $selector, "string \$return_url = ''" ) !== false
		&& strpos( $selector, "'cart_scope'  => \$cart_scope" ) !== false
		&& strpos( $selector, "'return_url'   => \$return_url" ) !== false
		&& strpos( $selector, "\$store_info['return_url']" ) !== false
		&& strpos( $selector, 'sanitize_return_url' ) !== false
);

$check(
	'callback redirects to caller page when there is no opener',
	strpos( $selector, 'window.location.replace' ) !== false
		&& strpos( $selector, '$checkout_url' ) !== false
);

if ( $fail > 0 ) {
	echo "\nPayNow store return context contract failed:\n";
	foreach ( $bad as $item ) {
		echo " - {$item}\n";
	}
	exit( 1 );
}

echo "\nPayNow store return context contract passed ({$pass} checks).\n";
