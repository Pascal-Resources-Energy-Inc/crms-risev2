<?php
// Render the real partial for the isolated manual router, without running view composers.
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$app->make('url')->forceRootUrl('http://127.0.0.1:8097');
$partial = $app->make('view.engine.resolver')->resolve('blade')->get(
    resource_path('views/partials/mega_dealer_orders.blade.php'), ['__env' => $app->make('view')]
);
$html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
    . '<meta name="csrf-token" content="synthetic-received-token"><title>Received orders UI — synthetic test</title>'
    . '<link rel="stylesheet" href="/inside_css/assets/libs/sweetalert2/sweetalert2.min.css">'
    . '<link rel="stylesheet" href="/inside_css/assets/css/received-orders.css">'
    . '<style>body{margin:0;padding:24px 16px;background:#f6f8fb;font:14px Arial,sans-serif}button,input,select{font:inherit}.fixture-note{max-width:1600px;margin:auto;color:#64748b;font-size:12px}@media(max-width:600px){body{padding:12px 8px}}</style>'
    . '</head><body><p class="fixture-note">UI preview · synthetic orders only</p>' . $partial
    . '<script src="/login_css/js/jquery-3.3.1.min.js"></script>'
    . '<script src="/design/assets/libs/datatables.net/js/jquery.dataTables.min.js"></script>'
    . '<script src="/inside_css/assets/libs/sweetalert2/sweetalert2.all.min.js"></script>'
    . '<script src="/inside_css/assets/js/received-orders.js"></script></body></html>';
$path = public_path('__codex-received-orders-table.html');
$handle = fopen($path, 'x');
if (!$handle) { fwrite(STDERR, "Fixture already exists; inspect it before regenerating.\n"); exit(1); }
fwrite($handle, $html); fclose($handle);
echo "Rendered synthetic received orders fixture.\n";
