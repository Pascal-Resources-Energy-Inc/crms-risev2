<?php
// Isolated UI fixture. Never boots Laravel, touches application databases or sends mail.
session_start();
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$user = ['id' => 900002, 'role' => 'Dealer', 'name' => 'Example Dealer', 'email' => 'dealer@example.invalid'];
$receipts = $_SESSION['supplier_receipts'] ?? [];
if ($path === '/supplier-fixture') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Mega Dealer supplier test</title><body><h1>Same-area supplier test</h1><p>Synthetic Dealer with an AD, app Mega Dealer and DMS Mega Dealer. All suppliers deliberately share profile ID 801.</p><button id="prepare">Open Dealer workspace</button><p id="result" role="status"></p><script>window.OfflineDataConfig={user:' . json_encode($user) . '}</script><script src="/offline/data.js"></script><script>document.getElementById("prepare").onclick=async()=>{try{const r=await OfflineData.updateAll();if(r.dataError||r.filesError)throw Error(r.dataError||r.filesError);location.href="/offline/products.html"}catch(e){document.getElementById("result").textContent=e.message}}</script></body></html>';
    return;
}
if ($path === '/api/offline/session') {
    header('Content-Type: application/json'); header('Cache-Control: no-store');
    echo json_encode(['user_id' => $user['id'], 'csrf_token' => 'synthetic-supplier-token']); return;
}
if ($path === '/api/offline/snapshot') {
    $orders = [];
    foreach ($receipts as $receipt) foreach ($receipt['records'] as $record) {
        $draft = $receipt['draft']; $item = $draft['items'][0];
        $orders[] = $record + ['item' => '330g LPG Refill', 'qty' => $item['quantity'], 'price' => $item['unit_price'],
            'total_amount' => $item['quantity'] * $item['unit_price'], 'created_at' => date(DATE_ATOM),
            'payment_method' => 'cash', 'ad_id' => $draft['ad_id'] ?? null, 'mega_dealer_id' => $draft['mega_dealer_id'] ?? null,
            'supplier_type' => $draft['supplier_type'] ?? 'area_distributor',
                'supplier_source' => ($draft['supplier_type'] ?? '') === 'mega_dealer' ? ($draft['supplier_source'] ?? 'admin_crms') : 'dms_prei',
                'supplier_name' => ($draft['supplier_type'] ?? '') === 'mega_dealer'
                    ? (($draft['supplier_source'] ?? 'admin_crms') === 'dms_prei' ? 'Example DMS Mega Dealer' : 'Example Mega Dealer')
                    : 'Example Area Distributor'];
    }
    header('Content-Type: application/json'); header('Cache-Control: no-store');
    echo json_encode(['schema_version' => 1, 'saved_at' => date(DATE_ATOM), 'user' => $user,
        'profile' => ['name' => $user['name'], 'area' => 'Example territory', 'sales_territory' => 'Example territory', 'mfi' => 'Example MFI'],
        'settings' => ['can_order' => true, 'can_transact' => true], 'points' => 200, 'customers' => [],
        'products' => [['id' => 21, 'name' => '330g LPG Refill', 'description' => 'Synthetic supplier catalog', 'sku' => 'EXAMPLE-REFILL',
            'price' => 70, 'order_price' => 40, 'ad_id' => 801, 'ad_user_id' => 1801, 'dealer_stock' => 0, 'ad_stock' => 8, 'client_allowed' => true, 'image' => null]],
        'distributors' => [
            ['id' => 801, 'user_id' => 1801, 'supplier_type' => 'area_distributor', 'key' => 'area_distributor:801', 'name' => 'Example Area Distributor', 'sales_territories' => ['Example territory'], 'distance' => 2.5],
            ['id' => 801, 'user_id' => 1802, 'supplier_type' => 'mega_dealer', 'key' => 'mega_dealer:801', 'name' => 'Example Mega Dealer', 'sales_territories' => ['Example territory'], 'distance' => 1.2,
                'product_ids' => [21], 'stock_by_product' => [21 => 3]],
            ['id' => 801, 'user_id' => 1802, 'supplier_type' => 'mega_dealer', 'supplier_source' => 'dms_prei',
                'key' => 'mega_dealer:dms_prei:801', 'name' => 'Example DMS Mega Dealer', 'sales_territories' => ['Example territory'], 'distance' => 1.5,
                'product_ids' => [21], 'stock_by_product' => [21 => 0]],
        ], 'transactions' => [], 'orders' => $orders, 'stoves' => [], 'acknowledged_orders' => array_keys($receipts),
        'acknowledged_order_results' => (object) array_map(function ($receipt) { return $receipt['records']; }, $receipts)]);
    return;
}
if ($path === '/api/offline/orders') {
    header('Content-Type: application/json'); header('Cache-Control: no-store');
    $draft = json_decode(file_get_contents('php://input'), true);
    $mega = ($draft['supplier_type'] ?? '') === 'mega_dealer';
    if (($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '') !== 'synthetic-supplier-token' || ($draft['user_id'] ?? null) !== 900002) {
        http_response_code(403); echo json_encode(['message' => 'Invalid synthetic account.']); return;
    }
    if ($draft['type'] !== 'order' || ($mega ? ($draft['mega_dealer_id'] ?? null) != 801 || !empty($draft['ad_id']) : ($draft['ad_id'] ?? null) != 801)) {
        http_response_code(422); echo json_encode(['message' => 'Choose an assigned synthetic supplier.']); return;
    }
    if ($mega && !in_array($draft['supplier_source'] ?? 'admin_crms', ['admin_crms', 'dms_prei'], true)) {
        http_response_code(422); echo json_encode(['message' => 'Invalid synthetic supplier source.']); return;
    }
    $key = $draft['idempotency_key'];
    if (isset($receipts[$key])) { echo json_encode(['success' => true, 'records' => $receipts[$key]['records']]); return; }
    foreach ($draft['items'] as $item) if ($item['product_id'] != 21 || $item['unit_price'] != 40 || $item['quantity'] < 1) {
        http_response_code(422); echo json_encode(['message' => 'Invalid synthetic product.']); return;
    }
    $nextId = count($receipts) + 1;
    $records = [['id' => $nextId, 'product_id' => 21, 'transaction_id' => 'PRORD-' . str_pad($nextId, 4, '0', STR_PAD_LEFT), 'status' => 'Pending']];
    $_SESSION['supplier_receipts'][$key] = ['draft' => $draft, 'records' => $records];
    echo json_encode(['success' => true, 'records' => $records]); return;
}
$public = realpath(__DIR__ . '/../../public'); $file = realpath($public . $path);
if ($file && strpos($file, $public . DIRECTORY_SEPARATOR) === 0 && is_file($file) && strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'php') return false;
http_response_code(404); echo 'Synthetic supplier fixture route unavailable.';
