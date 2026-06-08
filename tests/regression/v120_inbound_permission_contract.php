<?php
/**
 * PayNow inbound callback route must use route-level YS CART inbound guards.
 */

declare(strict_types=1);

$root     = dirname(__DIR__, 2);
$main     = (string) file_get_contents($root . '/ys-cart-paynow.php');
$plugin   = (string) file_get_contents($root . '/src/Plugin.php');
$manifest = (string) file_get_contents($root . '/manifest.php');

$fail = 0;
$check = static function (string $label, bool $ok) use (&$fail): void {
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (! $ok) {
        $fail++;
    }
};

preg_match('/Version:\s*([0-9.]+)/', $main, $version_match);
preg_match("/YS_CART_PAYNOW_VERSION', '([0-9.]+)'/", $main, $constant_match);

$check('plugin version bumped to 1.1.9 and header/constant match', '1.1.9' === ($version_match[1] ?? '') && '1.1.9' === ($constant_match[1] ?? ''));
$check('store callback imports YSInboundPermission', str_contains($plugin, 'use YangSheep\\Ecommerce\\Security\\YSInboundPermission;'));
$check('plugin exposes store_callback_permission', str_contains($plugin, 'store_callback_permission'));
$check('runtime callback no longer uses __return_true', ! str_contains($plugin, "'permission_callback' => '__return_true'"));
$check('manifest no longer uses __return_true', ! str_contains($manifest, "'permission_callback' => '__return_true'"));
$check('manifest declares store callback permission callback', str_contains($manifest, "[ \\YangSheep\\YSCartPaynow\\Plugin::class, 'store_callback_permission' ]"));

echo "v120_inbound_permission_contract FAIL={$fail}" . PHP_EOL;
exit($fail > 0 ? 1 : 0);
