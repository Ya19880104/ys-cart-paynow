<?php
/**
 * v1.1.6+ PayNow provider enablement guard regression.
 */

declare(strict_types=1);

$root     = dirname(__DIR__, 2);
$plugin   = (string) file_get_contents($root . '/ys-cart-paynow.php');
$runtime  = (string) file_get_contents($root . '/src/Plugin.php');
$settings = (string) file_get_contents($root . '/src/Admin/PaynowSettings.php');
$manifest = (string) file_get_contents($root . '/manifest.php');
$shipping = (string) file_get_contents($root . '/src/Shipping/Paynow/YSPaynowShipping.php');
$store    = (string) file_get_contents($root . '/src/Shipping/Paynow/YSPaynowStoreSelector.php');
$readme   = (string) file_get_contents($root . '/README.md');

$pass = 0;
$fail = 0;

function v115_check(string $label, bool $ok): void {
	global $pass, $fail;
	if ($ok) {
		++$pass;
		echo "[PASS] {$label}\n";
		return;
	}
	++$fail;
	echo "[FAIL] {$label}\n";
}

v115_check(
	'Plugin version remains at least 1.1.6',
	preg_match('/Version:\s*([0-9.]+)/', $plugin, $version_match)
		&& preg_match("/YS_CART_PAYNOW_VERSION', '([0-9.]+)'/", $plugin, $constant_match)
		&& version_compare((string) ($version_match[1] ?? ''), '1.1.6', '>=')
		&& version_compare((string) ($constant_match[1] ?? ''), '1.1.6', '>=')
);

v115_check(
	'Provider card is manifest-first for install/configuration entry',
	false !== strpos($runtime, "add_filter( 'ys_ec_provider_manifests'")
		&& false !== strpos($runtime, 'public static function manifest()')
		&& false !== strpos($manifest, "'id'                 => 'ys_paynow'")
		&& false !== strpos($manifest, "'legacy_setting_key' => 'paynow_enabled'")
		&& false !== strpos($manifest, "'slug'                => 'ys-provider-paynow'")
		&& false === strpos($runtime, "add_filter( 'ys_ec_providers'")
		&& false === strpos($runtime, 'ys_ec_admin_payment_menus')
);

v115_check(
	'Shipping registration is fail-closed by lifecycle capability and method state',
		false !== strpos($runtime, 'private function is_paynow_shipping_enabled(): bool')
		&& false !== strpos($runtime, "is_capability_enabled( 'ys_paynow', 'shipping'")
		&& false !== strpos($runtime, 'private function is_paynow_method_enabled( string $method_id ): bool')
		&& false !== strpos($runtime, "is_method_enabled( 'shipping', \$method_id, self::manifest()")
		&& false !== strpos($runtime, 'if ( $this->is_paynow_method_enabled( $method_id ) )')
		&& false !== strpos($runtime, 'YSShippingRegistry::register( new $method_class() )')
);

v115_check(
	'PayNow runtime adapters do not activate while provider is disabled',
	preg_match('/public function register_shipping_requester\(.*?if \( ! \$this->has_enabled_paynow_shipping_methods\(\) \) \{.*?return \$requester;/s', $runtime) === 1
		&& preg_match('/public function register_carrier_adapter\(.*?if \( ! \$this->has_enabled_paynow_shipping_methods\(\) \) \{.*?return \$adapter;/s', $runtime) === 1
		&& preg_match('/public function paynow_map_url\(.*?if \( ! \$this->has_enabled_paynow_shipping_methods\(\) \) \{.*?paynow_disabled/s', $runtime) === 1
);

v115_check(
	'PayNow shipping classes and store selector enforce lifecycle method state',
	false !== strpos($shipping, "YSProviderLifecycleState::is_method_enabled( 'shipping', \$this->id, Plugin::manifest()")
		&& false !== strpos($store, "YSProviderLifecycleState::is_method_enabled( 'shipping', \$shipping_id, Plugin::manifest()")
);

v115_check(
	'PayNow settings page uses Chinese YS CART copy',
	false !== strpos($settings, "YSAdminApp::open( 'PayNow 物流設定', '金物流 / PayNow' )")
		&& false !== strpos($settings, '供應商狀態')
		&& false !== strpos($settings, 'API 基本設定')
		&& false !== strpos($settings, '儲存 PayNow 設定')
);

v115_check(
	'Legacy English labels are not rendered in PayNow settings page',
	false === strpos($settings, 'Enable PayNow logistics')
		&& false === strpos($settings, 'Sandbox mode')
		&& false === strpos($settings, 'Save PayNow settings')
		&& false === strpos($settings, 'Configure shipping methods')
);

v115_check(
	'PayNow settings page does not own provider or shipping-method enable toggles',
	false === strpos($settings, "update_setting( 'paynow_enabled'")
		&& false === strpos($settings, 'name="paynow_enabled"')
		&& false === strpos($settings, "update_setting( 'shipping_' . \$method_id . '_enabled'")
		&& false === strpos($settings, 'shipping_enabled[]')
);

v115_check(
	'PayNow settings page uses provider slug and lifecycle state',
	false !== strpos($settings, "admin.php?page=ys-provider-paynow&updated=1")
		&& false !== strpos($settings, 'private static function is_provider_enabled(): bool')
		&& false !== strpos($settings, "'ys_paynow'")
);

v115_check(
	'PayNow manifest and README point to provider lifecycle admin slug',
	false !== strpos($manifest, "'title'               => 'PayNow 物流設定'")
		&& false !== strpos($readme, 'ys-provider-paynow')
		&& false === strpos($readme, 'ys-ec-paynow')
);

echo "v1.1.6+ PayNow enabled guard regression: PASS={$pass} FAIL={$fail}\n";
exit($fail > 0 ? 1 : 0);
