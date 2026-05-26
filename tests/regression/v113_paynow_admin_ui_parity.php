<?php
/**
 * v1.1.4 PayNow admin architecture regression.
 */

declare(strict_types=1);

$root     = dirname(__DIR__, 2);
$admin    = (string) file_get_contents($root . '/src/Admin/PaynowSettings.php');
$plugin   = (string) file_get_contents($root . '/ys-cart-paynow.php');
$manifest = (string) file_get_contents($root . '/manifest.php');

$pass = 0;
$fail = 0;

function v113_check(string $label, bool $ok): void {
	global $pass, $fail;
	if ($ok) {
		++$pass;
		echo "[PASS] {$label}\n";
		return;
	}
	++$fail;
	echo "[FAIL] {$label}\n";
}

preg_match('/Version:\s*([0-9.]+)/', $plugin, $header_version);
preg_match("/YS_CART_PAYNOW_VERSION', '([0-9.]+)'/", $plugin, $constant_version);

v113_check(
	'Plugin version remains at least 1.1.4',
	isset($header_version[1], $constant_version[1])
		&& version_compare($header_version[1], '1.1.4', '>=')
		&& version_compare($constant_version[1], '1.1.4', '>=')
);

v113_check(
	'PayNow admin uses YS admin shell and wide page stack',
	false !== strpos($admin, "YSAdminApp::open( 'PayNow 物流設定', '金物流 / PayNow' )")
		&& false !== strpos($admin, 'ysca-page-root ysca-page-root--wide ysca-stack-md')
);

v113_check(
	'PayNow admin exposes provider/API/method status cards',
	false !== strpos($admin, '供應商狀態')
		&& false !== strpos($admin, 'API 模式')
		&& false !== strpos($admin, 'API 憑證')
		&& false !== strpos($admin, '物流方法')
);

v113_check(
	'PayNow admin saves sender fields required by YSShippingHandler',
	false !== strpos($admin, 'shipping_paynow_sender_name')
		&& false !== strpos($admin, 'shipping_paynow_sender_phone')
		&& false !== strpos($admin, 'shipping_paynow_sender_zipcode')
		&& false !== strpos($admin, 'shipping_paynow_sender_address')
);

foreach ([
	'ys_ec_paynow_ship_711',
	'ys_ec_paynow_ship_family',
	'ys_ec_paynow_ship_hilife',
	'ys_ec_paynow_ship_tcat',
] as $method_id) {
	v113_check("PayNow admin documents registered method {$method_id}", false !== strpos($admin, $method_id));
}

v113_check(
	'PayNow admin does not own provider enable toggle',
	false === strpos($admin, "update_setting( 'paynow_enabled'")
		&& false === strpos($admin, 'name="paynow_enabled"')
);

v113_check(
	'PayNow admin does not own shipping-method enabled toggles',
	false === strpos($admin, "'shipping_' . \$method_id . '_enabled'")
		&& false === strpos($admin, 'shipping_enabled[]')
);

v113_check(
	'PayNow admin routes logistics enablement back to core ShippingAdmin',
	false !== strpos($admin, "admin.php?page=ys-ec-shipping")
		&& false !== strpos($admin, '前往物流設定')
);

v113_check(
	'PayNow admin documents headless map and callback routes',
	false !== strpos($admin, '/wp-json/ys-ecommerce-headless/v1/stores/paynow/map-url')
		&& false !== strpos($admin, "rest_url( 'ys-ecommerce/v1/paynow/store-callback' )")
		&& false !== strpos($admin, 'sdk/ys-cart-paynow-headless.js')
		&& false !== strpos($admin, 'skills/ys-cart-paynow-headless.md')
);

v113_check(
	'PayNow admin uses shared CTA/button/form primitives',
	false !== strpos($admin, 'ysca-switch-label--trailing')
		&& false !== strpos($admin, 'ysca-btn ysca-btn--primary')
		&& false !== strpos($admin, 'ysca-field-row')
		&& false !== strpos($admin, 'ysca-settings-table')
);

v113_check(
	'PayNow manifest is localized and manifest-first',
	false !== strpos($manifest, "'name'               => 'PayNow 物流'")
		&& false !== strpos($manifest, "'slug'                => 'ys-provider-paynow'")
		&& false !== strpos($manifest, "'domains'            => [ 'shipping' ]")
);

echo "v1.1.4 PayNow admin architecture regression: PASS={$pass} FAIL={$fail}\n";
exit($fail > 0 ? 1 : 0);
