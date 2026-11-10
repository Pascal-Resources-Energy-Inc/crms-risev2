<?php
// Render the actual buyer order card and completion handler with synthetic data only.
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$user = new App\User(['name' => 'Example Dealer', 'role' => 'Dealer']);
$app->instance(Illuminate\Contracts\Auth\Factory::class, new class($user) {
    private $user;
    public function __construct($user) { $this->user = $user; }
    public function user() { return $this->user; }
});
$source = file_get_contents(resource_path('views/home.blade.php'));
$start = strpos($source, '@forelse($ad_transactions as $transaction)');
$end = strpos($source, "@if(auth()->user()->usesMegaDealerPricing())", $start);
$card = substr($source, $start, $end - $start);
$card = substr($card, 0, strrpos($card, '@endforelse') + strlen('@endforelse'));
$ad_transactions = collect([['id' => 1, 'transaction_id' => 'PRORD-0099', 'supplier_type' => 'mega_dealer',
    'ad' => (object) ['name' => 'Example Mega Dealer', 'number' => '09000000000', 'address' => 'Example sales area', 'area' => 'Example territory'],
    'status' => 'For Verification', 'created_at' => Carbon\Carbon::now(), 'subtotal' => 800, 'price_total' => 800,
    'delivery_fee' => 0, 'payment_method' => 'cash', 'delivery_type' => 'pickup',
    'items' => [['name' => '330g Gaz Lite Crate', 'qty' => 2, 'price' => 400, 'total' => 800]]]]);
$__env = $app->make('view');
$compiled = $app->make('blade.compiler')->compileString($card);
foreach (['Pending', 'For Verification', 'Completed', 'Cancelled'] as $fixtureStatus) {
    $ad_transactions[0] = array_replace($ad_transactions[0], ['status' => $fixtureStatus]);
    ob_start(); eval('?>' . $compiled); $candidate = ob_get_clean();
    if ((strpos($candidate, 'complete-btn') !== false) !== ($fixtureStatus === 'For Verification')) {
        throw new RuntimeException('Unexpected Dealer completion action for status ' . $fixtureStatus);
    }
    if ($fixtureStatus === 'For Verification') $rendered = $candidate;
}
if (strpos($rendered, 'complete-btn') === false || strpos($rendered, 'PRORD-0099') === false) {
    throw new RuntimeException('Dealer completion action or order reference was not rendered.');
}
$scriptStart = strpos($source, "        document.querySelectorAll('.complete-btn')");
$scriptEnd = strpos($source, "        document.querySelectorAll('.cancel-order-btn')", $scriptStart);
$script = substr($source, $scriptStart, $scriptEnd - $scriptStart);
$html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
    . '<meta name="csrf-token" content="synthetic-received-token"><title>Dealer completion — sample order</title>'
    . '<link rel="stylesheet" href="/inside_css/assets/css/bootstrap.min.css">'
    . '<link rel="stylesheet" href="/inside_css/assets/libs/sweetalert2/sweetalert2.min.css">'
    . '<style>body{margin:0;padding:24px;background:#f6f8fb;font:14px Arial,sans-serif}.preview{max-width:760px;margin:auto}.order-actions{display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end}.order-actions button{min-height:44px}.cancel-window-note{color:#64748b;font-size:12px}@media(max-width:576px){body{padding:12px}.order-transaction-layout{flex-direction:column;gap:18px}.order-transaction-layout>.text-end{width:100%}.order-actions button{flex:1}}</style>'
    . '</head><body><main class="preview"><p class="text-muted small">Dealer account · sample order</p><h1 class="h4 mb-3">Order Transactions</h1>' . $rendered . '</main>'
    . '<script src="/inside_css/assets/libs/sweetalert2/sweetalert2.all.min.js"></script>'
    . '<script>document.addEventListener("DOMContentLoaded",function(){const baseUrl=location.origin;' . $script . '});</script></body></html>';
$handle = fopen(public_path('__codex-dealer-completion.html'), 'x');
if (!$handle) throw new RuntimeException('Inspect the existing synthetic preview before regenerating.');
fwrite($handle, $html); fclose($handle);
echo "Verified Dealer actions for all four statuses; rendered For Verification with Mark Completed.\n";
