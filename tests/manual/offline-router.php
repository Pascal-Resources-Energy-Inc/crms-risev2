<?php
// Isolated browser QA: php -S 127.0.0.1:8089 -t public tests/manual/offline-router.php
// Never boots Laravel, connects to application databases, or sends notifications.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
session_start();
$price = $_SESSION['fixture_price'] ?? 40;
$receipts = $_SESSION['fixture_receipts'] ?? [];
function fixtureOfflineLoginGrant()
{
    return ['user' => ['id' => 900001, 'name' => 'Example Mega Dealer', 'role' => 'mega_dealer', 'email' => 'example@example.invalid'],
        'identifiers' => ['example@example.invalid', '09999999999'],
        'credential_version' => hash('sha256', 'synthetic-account-password-version'), 'expires_at' => date(DATE_ATOM, time() + 30 * 86400)];
}
if ($path === '/auth-fixture') {
    $view = file_get_contents(__DIR__ . '/../../resources/views/auth/login.blade.php');
    $start = strpos($view, '// Online sign-in prepares only');
    $end = strpos($view, '// End online sign-in preparation.', $start);
    $handler = substr($view, $start, $end - $start);
    $handler = str_replace("@json(asset('offline/home.html'))", json_encode('/offline/home.html'), $handler);
    $handler = str_replace('{{ route("home") }}', '/offline/home.html', $handler);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Offline sign-in test</title><body><h1>Prepare example offline sign-in</h1><p>Synthetic account only. No application database is used.</p><p>Example email: example@example.invalid<br>Example phone: 09999999999<br>Example password: Example-password-9281</p><form id="loginForm" action="/fixture/authenticate" method="post"><input id="selectedRoleInput" name="selected_role" type="hidden" value="dealer"><p><label>Email or phone <input id="email" name="email" value="example@example.invalid" autocomplete="off"></label></p><p><label>Password <input id="password" name="password" type="password" autocomplete="off"></label></p><button id="signinButton" type="submit">Sign in and prepare offline access</button></form><p id="offlineSetupStatus" role="status">Offline sign-in has not been prepared.</p><script>window.OfflineDataConfig={headless:true};</script><script defer src="/offline/auth.js"></script><script defer src="/offline/data.js"></script><script src="/inside_css/assets/libs/sweetalert2/sweetalert2.all.min.js"></script><script>document.addEventListener("DOMContentLoaded",function(){' . $handler . '});</script></body></html>';
    return;
}
if ($path === '/fixture/authenticate') {
    header('Content-Type: application/json'); header('Cache-Control: no-store');
    if (!in_array($_POST['email'] ?? '', ['example@example.invalid', '09999999999'], true) ||
        ($_POST['password'] ?? '') !== 'Example-password-9281' ||
        !in_array($_POST['selected_role'] ?? '', ['dealer', 'mega_dealer'], true)) {
        http_response_code(422); echo json_encode(['success' => false, 'message' => 'Invalid example credentials.']); return;
    }
    $grant = fixtureOfflineLoginGrant();
    echo json_encode(['success' => true, 'redirect' => '/offline/home.html', 'user' => $grant['user'], 'offline_login' => $grant]); return;
}
if ($path === '/history-fixture') {
    // Reproduce older saved rows whose completed sync result omitted records.
    $rows = [];
    foreach (['order', 'client'] as $index => $type) {
        $key = '00000000-0000-4000-8000-' . ($type === 'order' ? '000000000101' : '000000000102');
        $record = ['id' => 1, 'product_id' => 21, 'transaction_id' => $type === 'order'
            ? 'PRORD-0001' : 'TRX-' . time() . '-' . rand(1000, 9999), 'status' => $type === 'order' ? 'Pending' : 'Completed'];
        $draft = ['user_id' => 900001, 'idempotency_key' => $key, 'type' => $type, 'ad_id' => $type === 'order' ? 801 : null,
            'client_id' => $type === 'client' ? 701 : null, 'payment_method' => 'cash', 'delivery_type' => 'pickup',
            'items' => [['product_id' => 21, 'name' => '330g LPG Refill', 'quantity' => 1, 'unit_price' => $type === 'order' ? $price : 70]],
            'snapshot_saved_at' => date(DATE_ATOM)];
        $_SESSION['fixture_receipts'][$key] = ['draft' => $draft, 'records' => [$record]];
        $rows[] = $draft + ['created_at' => date(DATE_ATOM), 'synced_at' => date(DATE_ATOM), 'status' => 'synced',
            'revision' => 0, 'result' => ['success' => true, 'acknowledged' => true]];
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Recovered offline history test</title><body><h1>Recover saved transaction IDs</h1><p>Synthetic synced AD and client records share numeric ID 1. Their local results have no transaction IDs.</p><button id="prepareHistory">Open recovered synced history</button><p id="result" role="status"></p><script>window.OfflineDataConfig={user:{id:900001,name:"Example Mega Dealer",role:"mega_dealer",email:"example@example.invalid"}}</script><script src="/offline/data.js"></script><script>const recoveredRows=' . json_encode($rows) . ';
    document.getElementById("prepareHistory").onclick=async()=>{try{
      const result=await OfflineData.updateAll();if(result.dataError||result.filesError)throw Error(result.dataError||result.filesError);
      await new Promise((resolve,reject)=>{const request=indexedDB.open("GazLiteOffline",1);request.onerror=()=>reject(request.error);request.onsuccess=()=>{
        const db=request.result,tx=db.transaction("outbox","readwrite");tx.oncomplete=()=>{db.close();resolve()};tx.onerror=tx.onabort=()=>{db.close();reject(tx.error)};
        recoveredRows.forEach(row=>tx.objectStore("outbox").put(row));
      }});location.href="/offline/transaction.html";
    }catch(error){document.getElementById("result").textContent=error.message}};</script></body></html>';
    return;
}
if ($path === '/offline-fixture') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Offline order test</title><body><h1>Example Mega Dealer</h1><p>Synthetic data only. The assigned AD has zero stock.</p><button id="prepare">Open saved offline app</button><p id="result" role="status"></p><p><a href="/fixture/raise-price">Change example order price</a></p><link rel="stylesheet" href="/offline/data.css"><script>window.OfflineDataConfig={user:{id:900001,name:"Example Mega Dealer",role:"mega_dealer",email:"example@example.invalid"}}</script><script src="/offline/data.js"></script><script>document.getElementById("prepare").onclick=async()=>{try{const r=await OfflineData.updateAll();if(r.dataError||r.filesError)throw new Error(r.dataError||r.filesError);location.href="/offline/products.html"}catch(e){document.getElementById("result").textContent=e.message}}</script></body></html>';
    return;
}
if ($path === '/fixture/raise-price') {
    $_SESSION['fixture_price'] = $price + 5;
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><title>Example price changed</title><p>Example order price changed.</p><a href="/offline/products.html">Return to Products</a>';
    return;
}
if ($path === '/api/offline/snapshot') {
    $orders = []; $transactions = []; $sold = 0;
    foreach ($receipts as $key => $receipt) foreach ($receipt['records'] as $record) {
        $draft = $receipt['draft'];
        $item = $draft['items'][0];
        $entry = $record + ['item' => '330g LPG Refill', 'qty' => $item['quantity'], 'price' => $item['unit_price'],
            'total_amount' => $item['quantity'] * $item['unit_price'], 'created_at' => date(DATE_ATOM), 'payment_method' => $draft['payment_method']];
        if ($draft['type'] === 'order') $orders[] = $entry;
        else { $transactions[] = $entry; $sold += $item['quantity']; }
    }
    header('Content-Type: application/json'); header('Cache-Control: no-store');
    echo json_encode([
        'schema_version' => 1, 'saved_at' => date(DATE_ATOM),
        'user' => ['id' => 900001, 'name' => 'Example Mega Dealer', 'role' => 'mega_dealer', 'email' => 'example@example.invalid'],
        'offline_login' => fixtureOfflineLoginGrant(),
        'profile' => ['id' => 1001, 'user_id' => 900001, 'name' => 'Example Mega Dealer', 'dealer_reference' => 'EXAMPLE-001', 'sales_territory' => 'Example territory', 'mfi' => 'Example MFI', 'number' => '00000000000'],
        'points' => 200, 'settings' => ['can_order' => true, 'can_transact' => true, 'show_customer_full_name' => false],
        'products' => [['id' => 21, 'product_id' => 21, 'name' => '330g LPG Refill', 'description' => 'Example refill product', 'sku' => 'EXAMPLE-REFILL',
            'price' => 70, 'order_price' => $price, 'ad_id' => 801, 'ad_user_id' => 1801, 'dealer_stock' => max(0, 8 - $sold), 'ad_stock' => 0, 'client_allowed' => true, 'image' => null]],
        'customers' => [['id' => 701, 'user_id' => 1701, 'display_name' => '**** SAMPLE', 'name' => '**** SAMPLE', 'mfi' => 'Example MFI', 'number' => '******00000']],
        'distributors' => [['id' => 801, 'user_id' => 1801, 'name' => 'Example Area Distributor', 'sales_territories' => ['Example territory'], 'distance' => 2.5]],
        'transactions' => $transactions, 'orders' => $orders, 'acknowledged_orders' => array_keys($receipts),
        'acknowledged_order_results' => (object) array_map(function ($receipt) { return $receipt['records']; }, $receipts), 'stoves' => [],
    ]);
    return;
}
if ($path === '/api/offline/session') {
    header('Content-Type: application/json'); header('Cache-Control: no-store');
    echo json_encode(['user_id' => 900001, 'csrf_token' => 'synthetic-test-token']); return;
}
if ($path === '/api/offline/orders') {
    header('Content-Type: application/json'); header('Cache-Control: no-store');
    $draft = json_decode(file_get_contents('php://input'), true);
    if (($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '') !== 'synthetic-test-token') {
        http_response_code(419); echo json_encode(['message' => 'Example session expired.']); return;
    }
    if (!is_array($draft) || (int) ($draft['user_id'] ?? 0) !== 900001 || !in_array($draft['type'] ?? '', ['order', 'client'], true)) {
        http_response_code(403); echo json_encode(['message' => 'Wrong example account.']); return;
    }
    $key = $draft['idempotency_key'];
    if (isset($receipts[$key])) { echo json_encode(['success' => true, 'records' => $receipts[$key]['records']]); return; }
    $records = [];
    foreach ($draft['items'] ?? [] as $item) {
        $expected = $draft['type'] === 'order' ? $price : 70;
        if ($item['product_id'] != 21 || $item['quantity'] < 1 || $item['quantity'] > 1000000 || $item['unit_price'] != $expected || ($draft['type'] === 'client' && $item['quantity'] > 8)) {
            http_response_code(422); echo json_encode(['message' => 'Example price or available stock changed. Update and review this saved order.']); return;
        }
        $nextId = ($_SESSION['fixture_last_id'] ?? 0) + 1; $_SESSION['fixture_last_id'] = $nextId;
        $records[] = ['id' => $nextId, 'transaction_id' => $draft['type'] === 'order'
            ? 'PRORD-' . str_pad($nextId, 4, '0', STR_PAD_LEFT) : 'TRX-' . time() . '-' . rand(1000, 9999),
            'product_id' => 21, 'status' => $draft['type'] === 'order' ? 'Pending' : 'Completed'];
    }
    $_SESSION['fixture_receipts'][$key] = ['draft' => $draft, 'records' => $records];
    echo json_encode(['success' => true, 'records' => $records, 'message' => 'Synthetic order saved.']); return;
}
$public = realpath(__DIR__ . '/../../public');
$file = realpath($public . $path);
// Restart the isolated fixture with OFFLINE_FIXTURE_RELEASE=2 to publish a new
// HTML and JavaScript bundle while a saved app is disconnected.
if ($file && getenv('OFFLINE_FIXTURE_RELEASE') === '2' && in_array($path, ['/offline/transaction.html', '/offline/workspace.js'], true)) {
    header('Cache-Control: no-store');
    header('Content-Type: ' . (substr($path, -3) === '.js' ? 'application/javascript' : 'text/html; charset=utf-8'));
    $source = file_get_contents($file);
    echo $path === '/offline/transaction.html'
        ? str_replace('Transaction history', 'Updated transaction history', $source)
        : str_replace('Your workspace is up to date', 'Updated workspace ready', $source);
    return;
}
if ($file && strpos($file, $public . DIRECTORY_SEPARATOR) === 0 && is_file($file) && strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'php') return false;
http_response_code(404); echo 'Test fixture route unavailable';
