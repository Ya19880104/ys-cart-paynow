<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$artifacts = glob($root . '/artifacts/ys-cart-paynow-*.zip') ?: [];

if (!$artifacts) {
    echo "v119_release_package_contract skipped: no release zip built yet\n";
    exit(0);
}

rsort($artifacts);
$zipPath = $artifacts[0];

if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "ZipArchive extension is required to inspect {$zipPath}\n");
    exit(1);
}

$zip = new ZipArchive();
if (true !== $zip->open($zipPath)) {
    fwrite(STDERR, "Unable to open release zip: {$zipPath}\n");
    exit(1);
}

$names = [];
for ($i = 0; $i < $zip->numFiles; $i++) {
    $names[] = (string) $zip->getNameIndex($i);
}
$zip->close();

$mustHave = [
    'ys-cart-paynow/ys-cart-paynow.php',
    'ys-cart-paynow/manifest.php',
    'ys-cart-paynow/vendor/autoload.php',
    'ys-cart-paynow/vendor/yangsheep/ys-plugin-hub-client/ys-plugin-hub-client.php',
    'ys-cart-paynow/README.md',
    'ys-cart-paynow/docs/headless.md',
    'ys-cart-paynow/sdk/ys-cart-paynow-headless.js',
    'ys-cart-paynow/skills/ys-cart-paynow-headless.md',
];

foreach ($mustHave as $entry) {
    if (!in_array($entry, $names, true)) {
        fwrite(STDERR, "Release zip missing required entry: {$entry}\n");
        exit(1);
    }
}

$forbiddenPatterns = [
    '#^ys-cart-paynow/\\.git/#',
    '#^ys-cart-paynow/\\.github/#',
    '#^ys-cart-paynow/artifacts/#',
    '#^ys-cart-paynow/bin/#',
    '#^ys-cart-paynow/tests/#',
    '#^ys-cart-paynow/tmp/#',
    '#^ys-cart-paynow/node_modules/#',
    '#^ys-cart-paynow/\\.env(\\..*)?$#',
    '#\\.log$#',
    '#\\.tmp$#',
    '#^ys-cart-paynow/composer\\.(json|lock)$#',
];

foreach ($names as $entry) {
    foreach ($forbiddenPatterns as $pattern) {
        if (preg_match($pattern, $entry)) {
            fwrite(STDERR, "Release zip includes forbidden entry: {$entry}\n");
            exit(1);
        }
    }
}

echo "v119_release_package_contract passed\n";
