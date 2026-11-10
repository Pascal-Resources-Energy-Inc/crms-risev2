<?php
// Synthetic UI fixture only. Does not boot Laravel, touch application data or send mail.
session_start();
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$public = realpath(__DIR__ . '/../../public');
if ($path === '/received-orders-fixture') {
    $_SESSION['received_fixture_stock'] = [8, 0, 1];
    $_SESSION['received_fixture_completed'] = [];
    $_SESSION['received_fixture_verification'] = [22 => true];
    $fixture = $public . '/__codex-received-orders-table.html';
    if (!is_file($fixture)) { http_response_code(404); echo 'Render the synthetic received orders fixture first.'; return; }
    header('Content-Type: text/html; charset=utf-8'); readfile($fixture); return;
}
if ($path === '/api/mega-dealer/orders/received') {
    // Deliberate delay makes the real DataTables processing indicator visible.
    usleep(220000);
    header('Content-Type: application/json'); header('Cache-Control: no-store');
    if (($_GET['search']['value'] ?? '') === 'SIMULATE-LOAD-FAILURE') {
        http_response_code(503); echo json_encode(['message' => 'Synthetic connection failure. Clear the test search and refresh stock.']); return;
    }
    $rows = [];
    $products = ['330g Gaz Lite Crate', '330g LPG Refill', '330g Gaz Lite Cylinder'];
    $stock = $_SESSION['received_fixture_stock'] ?? [8, 0, 1];
    $completed = $_SESSION['received_fixture_completed'] ?? [];
    $verification = $_SESSION['received_fixture_verification'] ?? [22 => true];
    for ($id = 1; $id <= 34; $id++) {
        $product = ($id - 1) % 3;
        $quantity = $product === 0 ? 2 : 48;
        $available = $stock[$product];
        $status = isset($completed[$id]) || $id > 25 ? 'Completed' :
            (isset($verification[$id]) ? 'For Verification' : ($id === 25 ? 'Cancelled' : 'Pending'));
        $state = $available <= 0 ? 'out_of_stock' : ($available < $quantity ? 'insufficient' : 'available');
        $message = $state === 'out_of_stock' ? 'No stock available.' : ($state === 'insufficient' ? 'Need ' . ($quantity - $available) . ' more to fulfil.' : 'Enough stock to fulfil.');
        if ($status === 'For Verification') $message = $quantity . ' units reserved for this order. Awaiting Dealer verification.';
        if ($id === 7) { $available = null; $state = 'unavailable'; $message = 'Product is no longer in your assigned catalog.'; }
        $created = strtotime('2026-10-28T16:24:00+08:00') - $id * 60;
        $rows[] = ['id' => $id, 'dealer_name' => $id === 10 ? 'Example Dealer With A Very Long Store Name For Mobile Layout Testing' : 'Example Dealer ' . str_pad($id, 2, '0', STR_PAD_LEFT),
            'transaction_id' => 'PRORD-' . str_pad(100 - $id, 4, '0', STR_PAD_LEFT), 'product_name' => $products[$product], 'sku' => 'SAMPLE-' . ($product + 1),
            'quantity' => $quantity, 'available_stock' => $available, 'stock_state' => $state, 'stock_message' => $message,
            'can_fulfil' => $status === 'Pending' && $state === 'available',
            'payment_method' => $id % 2 ? 'cash' : 'gcash', 'payment_label' => $id % 2 ? 'Cash' : 'GCash',
            'delivery_type' => $id % 2 ? 'pickup' : 'delivery', 'delivery_label' => $id % 2 ? 'Pickup' : 'Delivery',
            'total_amount' => $quantity * [400, 62, 80][$product], 'status' => $status,
            'created_at' => date(DATE_ATOM, $created), 'created_at_display' => date('M d, Y · h:i A', $created)];
    }
    $total = count($rows);
    $search = strtolower(trim($_GET['search']['value'] ?? ''));
    $filter = $_GET['status'] ?? '';
    $rows = array_values(array_filter($rows, function ($row) use ($search, $filter) {
        return (!$filter || $row['status'] === $filter) && (!$search || strpos(strtolower(implode(' ', [$row['dealer_name'], $row['transaction_id'], $row['product_name'], $row['sku'], $row['payment_label'], $row['delivery_label'], $row['status']])), $search) !== false);
    }));
    $filtered = count($rows);
    $index = (int) ($_GET['order'][0]['column'] ?? 0);
    $sort = $_GET['columns'][$index]['name'] ?? 'created_at';
    if (!in_array($sort, ['dealer_name', 'transaction_id', 'product_name', 'quantity', 'payment_method', 'delivery_type', 'total_amount', 'status', 'created_at'], true)) $sort = 'created_at';
    $direction = ($_GET['order'][0]['dir'] ?? 'desc') === 'asc' ? 1 : -1;
    usort($rows, function ($a, $b) use ($sort, $direction) { return (($a[$sort] <=> $b[$sort]) ?: ($a['id'] <=> $b['id'])) * $direction; });
    $rows = array_slice($rows, max(0, (int) ($_GET['start'] ?? 0)), min(100, max(1, (int) ($_GET['length'] ?? 10))));
    echo json_encode(['draw' => (int) ($_GET['draw'] ?? 0), 'recordsTotal' => $total, 'recordsFiltered' => $filtered, 'data' => $rows]); return;
}
$fulfil = preg_match('#^/mega-dealer/orders/(\d+)/fulfil$#', $path, $matches);
if ($fulfil || preg_match('#^/ad-orders/(\d+)/complete$#', $path, $matches)) {
    header('Content-Type: application/json'); header('Cache-Control: no-store');
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int) $matches[1]; $product = ($id - 1) % 3;
    $stock = $_SESSION['received_fixture_stock'] ?? [8, 0, 1];
    $quantity = $product === 0 ? 2 : 48;
    if (($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '') !== 'synthetic-received-token') { http_response_code(419); echo json_encode(['message' => 'Invalid fixture token.']); return; }
    if (!trim($input['remarks'] ?? '') || mb_strlen($input['remarks']) > 255) { http_response_code(422); echo json_encode(['message' => 'Enter remarks of up to 255 characters.']); return; }
    if ($id < 1 || $id > 34) { http_response_code(404); echo json_encode(['message' => 'Synthetic order unavailable.']); return; }
    if ($id === 25) { http_response_code(409); echo json_encode(['message' => 'Cancelled orders cannot be fulfilled.']); return; }
    if (!empty($_SESSION['received_fixture_completed'][$id]) || $id > 25) {
        echo json_encode(['success' => true, 'status' => 'Completed', 'message' => 'The Dealer already confirmed receipt.']); return;
    }
    $verification = $_SESSION['received_fixture_verification'] ?? [22 => true];
    if (!$fulfil) {
        if (empty($verification[$id])) { http_response_code(409); echo json_encode(['message' => 'The Mega Dealer must submit fulfilment for verification first.']); return; }
        $_SESSION['received_fixture_completed'][$id] = true;
        echo json_encode(['success' => true, 'status' => 'Completed', 'message' => 'Synthetic receipt confirmed by Dealer.']); return;
    }
    if (!empty($verification[$id])) {
        echo json_encode(['success' => true, 'status' => 'For Verification', 'message' => 'The order is already awaiting Dealer verification.']); return;
    }
    // Order 4 intentionally loses stock between display and fulfilment.
    if ($id === 4) { $stock[$product] = 0; $_SESSION['received_fixture_stock'] = $stock; }
    if ($stock[$product] < $quantity) { http_response_code(422); echo json_encode(['message' => 'The Mega Dealer does not have enough available stock to complete this order.']); return; }
    if ($id === 7) { http_response_code(422); echo json_encode(['message' => 'This product is no longer in the assigned catalog.']); return; }
    $stock[$product] -= $quantity;
    $_SESSION['received_fixture_stock'] = $stock;
    $_SESSION['received_fixture_verification'] = $verification + [$id => true];
    echo json_encode(['success' => true, 'status' => 'For Verification', 'message' => 'Synthetic order submitted for Dealer verification.']); return;
}
$file = realpath($public . $path);
if ($file && strpos($file, $public . DIRECTORY_SEPARATOR) === 0 && is_file($file) && strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'php') return false;
http_response_code(404); echo 'Synthetic received orders fixture route unavailable.';
