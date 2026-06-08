<?php
/**
 * PayNow headless docs and SDK must match the REST handler payload contract.
 */

declare(strict_types=1);

$root   = dirname(__DIR__, 2);
$plugin = (string) file_get_contents($root . '/src/Plugin.php');
$sdk    = (string) file_get_contents($root . '/sdk/ys-cart-paynow-headless.js');
$docs   = (string) file_get_contents($root . '/docs/headless.md');
$skill  = (string) file_get_contents($root . '/skills/ys-cart-paynow-headless.md');
$readme = (string) file_get_contents($root . '/README.md');

$fail = 0;
$check = static function (string $label, bool $ok) use (&$fail): void {
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$ok) {
        $fail++;
    }
};

$check(
    'REST handler reads canonical shipping_id',
    str_contains($plugin, "\$params['shipping_id']")
);

$check(
    'SDK posts shipping_id to map route',
    str_contains($sdk, 'shipping_id: shippingMethod')
        && !str_contains($sdk, 'shipping_method: shippingMethod')
);

$check(
    'Headless docs publish shipping_id payload',
    str_contains($docs, '"shipping_id": "ys_ec_paynow_ship_711"')
        && !str_contains($docs, '"shipping_method": "ys_ec_paynow_ship_711"')
);

$check(
    'Skill instructs agents to use shipping_id',
    str_contains($skill, 'shipping_id')
        && str_contains($skill, 'do not use `shipping_method`')
);

$check(
    'README documents shipping_id payload and callback boundary',
    str_contains($readme, '"shipping_id": "ys_ec_paynow_ship_711"')
        && str_contains($readme, 'provider-facing')
);

echo "v121_headless_payload_contract FAIL={$fail}" . PHP_EOL;
exit($fail > 0 ? 1 : 0);
