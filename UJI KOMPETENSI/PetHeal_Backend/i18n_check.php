<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$views = [
    'admin/medicines/index.blade.php',
    'admin/medicines/edit.blade.php',
    'admin/medicines/disposals.blade.php',
    'admin/medicines/partials/disposal-modal.blade.php',
    'admin/medicines/partials/delete-all-modal.blade.php',
    'admin/dashboard.blade.php',
];

$missing = [];

foreach ($views as $view) {
    $src = file_get_contents(__DIR__ . '/resources/views/' . $view);

    if (preg_match_all("/__\('([a-z0-9_]+\.[a-z0-9_]+)'/i", $src, $m)) {
        foreach (array_unique($m[1]) as $key) {
            [$file, $item] = explode('.', $key, 2);

            if (__($key) === $key) {
                $missing[$view][] = $key;
            }
        }
    }
}

if (!$missing) {
    echo "ALL KEYS PRESENT\n";
    exit(0);
}

foreach ($missing as $view => $keys) {
    echo $view . ":\n  - " . implode("\n  - ", $keys) . "\n";
}
