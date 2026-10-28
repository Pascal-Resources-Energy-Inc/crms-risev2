<?php

use App\Http\Controllers\OfflineOrderController;
use App\Support\OfflinePortalContext;
use App\Support\OfflineSnapshotData;
use App\Support\OrderReference;
use App\User;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Fully isolated SQLite fixtures. Never boots the application or its databases. */
class OfflineOrderSyncTest extends TestCase
{
    private $db;
    private $context;
    private $controller;

    protected function setUp()
    {
        $app = new Container;
        Container::setInstance($app);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);
        $app->instance('config', new Repository(['database.default' => 'default', 'app.crms_admin_url' => 'https://admin.example.test']));
        $this->db = new Capsule($app);
        $this->db->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'default');
        $this->db->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'dms');
        $this->db->getDatabaseManager()->setDefaultConnection('default');
        $this->db->setAsGlobal();
        $this->db->bootEloquent();
        $app->instance('db', $this->db->getDatabaseManager());
        $app->bind('db.schema', function () { return $this->db->connection()->getSchemaBuilder(); });
        $app->instance('validator', new Factory(new Translator(new ArrayLoader, 'en'), $app));
        $this->schema();
        $this->seed();
        $this->context = new OfflinePortalContext;
        $this->controller = new IsolatedOfflineOrderController;
    }

    protected function tearDown()
    {
        $this->db->getDatabaseManager()->disconnect('default');
        $this->db->getDatabaseManager()->disconnect('dms');
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
    }

    private function schema()
    {
        $schema = $this->db->connection()->getSchemaBuilder();
        $schema->create('users', function ($t) { $t->increments('id'); $t->string('name'); $t->string('email'); $t->string('role'); $t->string('password')->nullable(); });
        foreach (['dealers', 'mega_dealers', 'center_chiefs', 'clients'] as $name) {
            $schema->create($name, function ($t) {
                $t->increments('id'); $t->integer('user_id'); $t->string('name'); $t->string('mfi')->nullable(); $t->string('area')->nullable();
                $t->string('address')->nullable(); $t->string('number')->nullable(); $t->integer('serial_number')->nullable();
                $t->decimal('latitude', 10, 6)->nullable(); $t->decimal('longitude', 10, 6)->nullable(); $t->timestamps();
            });
        }
        $schema->create('stoves', function ($t) { $t->increments('id'); $t->string('serial_number')->nullable(); });
        foreach (['order_details', 'transaction_details'] as $name) {
            $schema->create($name, function ($t) {
                $t->increments('id'); $t->integer('dealer_id'); $t->integer('product_id')->nullable(); $t->string('transaction_id');
                $t->string('item'); $t->string('item_description')->nullable(); $t->string('sku')->nullable(); $t->integer('qty');
                $t->decimal('price', 12, 2); $t->string('status'); $t->integer('created_by')->nullable(); $t->string('date')->nullable();
                $t->integer('ad_id')->nullable(); $t->string('ad_address')->nullable(); $t->integer('client_id')->nullable(); $t->string('client_address')->nullable();
                $t->decimal('points_dealer', 12, 2)->default(0); $t->decimal('points_client', 12, 2)->default(0);
                $t->string('payment_method')->nullable(); $t->string('delivery_type')->nullable(); $t->decimal('delivery_fee', 12, 2)->default(0); $t->boolean('is_guest')->default(false); $t->timestamps();
            });
        }
        $schema->create('redeemed_histories', function ($t) { $t->increments('id'); $t->integer('user_id'); $t->decimal('points_amount', 12, 2); });
        require_once __DIR__ . '/../../database/migrations/2026_10_28_000001_create_offline_order_receipts_table.php';
        (new CreateOfflineOrderReceiptsTable)->up();
        $dms = $this->db->connection('dms')->getSchemaBuilder();
        $dms->create('area_distributors', function ($t) { $t->increments('id'); $t->integer('user_id'); $t->string('name'); $t->string('address')->nullable(); $t->string('number')->nullable(); $t->float('latitude')->nullable(); $t->float('longitude')->nullable(); $t->softDeletes(); });
        $dms->create('ad_areas', function ($t) { $t->increments('id'); $t->integer('ad_id'); $t->string('area_name'); $t->string('user_role'); $t->softDeletes(); });
        $dms->create('mfis', function ($t) { $t->increments('id'); $t->string('name'); $t->integer('show_customer_full_name'); });
        $dms->create('products', function ($t) {
            $t->increments('id'); $t->integer('ad_user_id'); $t->string('product_name'); $t->string('description')->nullable(); $t->string('sku')->nullable();
            $t->string('status'); $t->decimal('price', 12, 2); $t->decimal('dealer_price', 12, 2); $t->decimal('mega_dealer_price', 12, 2); $t->decimal('srp_price', 12, 2);
            $t->float('dealer_points')->default(1); $t->float('customer_points')->default(2); $t->string('product_image')->nullable(); $t->boolean('client_allowed')->nullable();
        });
        $dms->create('inventory_transfers', function ($t) { $t->increments('id'); $t->integer('product_id'); $t->integer('ad_user_id'); $t->string('item_name'); $t->string('movement_type'); $t->integer('qty'); $t->string('from_area')->nullable(); $t->string('to_area')->nullable(); $t->softDeletes(); });
    }

    private function seed()
    {
        foreach ([42 => ['Dealer', 'dealers'], 43 => ['Mega Dealer', 'mega_dealers'], 44 => ['center_chief', 'center_chiefs']] as $id => $type) {
            $this->db->table('users')->insert(['id' => $id, 'name' => $type[0] . ' Example', 'email' => 'example' . $id . '@example.test', 'role' => $type[0], 'password' => 'NEVER_EXPORT_PASSWORD']);
            $this->db->table($type[1])->insert(['id' => $id + 1000, 'user_id' => $id, 'name' => 'Example Profile', 'mfi' => 'MFI One', 'area' => 'North', 'address' => 'Example road', 'latitude' => 14, 'longitude' => 121]);
            $this->db->table('order_details')->insert(['dealer_id' => $id, 'product_id' => 10, 'transaction_id' => 'STOCK-' . $id, 'item' => 'Refill', 'qty' => 8, 'price' => 50, 'status' => 'Completed', 'ad_id' => 200]);
        }
        $this->db->table('users')->insert(['id' => 45, 'name' => 'Client Example', 'email' => 'client@example.test', 'role' => 'Client']);
        $this->db->table('clients')->insert([
            ['id' => 100, 'user_id' => 45, 'name' => 'PRIVATE FIRST SURNAME', 'mfi' => ' mfi one ', 'number' => '09123456789', 'address' => 'Customer address'],
            ['id' => 101, 'user_id' => 46, 'name' => 'OTHER MFI PERSON', 'mfi' => 'Other MFI', 'number' => '09999999999', 'address' => 'Other private address'],
        ]);
        $dms = $this->db->connection('dms');
        $dms->table('area_distributors')->insert([['id' => 200, 'user_id' => 900, 'name' => 'North AD'], ['id' => 201, 'user_id' => 901, 'name' => 'South AD']]);
        $dms->table('ad_areas')->insert([['ad_id' => 200, 'area_name' => ' north ', 'user_role' => 'Area Distributor'], ['ad_id' => 201, 'area_name' => 'South', 'user_role' => 'Area Distributor']]);
        $dms->table('mfis')->insert(['name' => 'MFI One', 'show_customer_full_name' => 0]);
        foreach ([10 => [900, 'Refill', 70, 50, 45, 75], 11 => [900, 'Cylinder', 100, 80, 70, 110], 12 => [901, 'Unassigned', 100, 80, 70, 110]] as $id => $p) {
            $dms->table('products')->insert(['id' => $id, 'ad_user_id' => $p[0], 'product_name' => $p[1], 'sku' => 'SKU-' . $id,
                'status' => 'Activate', 'price' => $p[2], 'dealer_price' => $p[3], 'mega_dealer_price' => $p[4], 'srp_price' => $p[5]]);
        }
    }

    private function payload($overrides = [])
    {
        return array_replace(['user_id' => 42, 'idempotency_key' => '00000000-0000-4000-8000-000000000001', 'type' => 'order', 'ad_id' => 200,
            'items' => [['product_id' => 10, 'quantity' => 30, 'unit_price' => 50]], 'payment_method' => 'cash', 'delivery_type' => 'pickup', 'snapshot_saved_at' => '2026-10-28T00:00:00Z'], $overrides);
    }

    private function send($payload = null, $userId = 42)
    {
        $request = Request::create('/api/offline/orders', 'POST', $payload ?: $this->payload());
        $request->setUserResolver(function () use ($userId) { return $userId ? User::find($userId) : null; });
        return $this->controller->store($request, $this->context)->getData(true);
    }

    public function test_zero_stock_ad_order_is_accepted_as_pending_without_stock_or_payment_fabrication()
    {
        $result = $this->send();
        $this->assertTrue($result['success']);
        $this->assertSame('Pending', $result['records'][0]['status']);
        $saved = $this->db->table('order_details')->where('status', 'Pending')->first();
        $this->assertEquals(30, $saved->qty); $this->assertEquals(50, $saved->price); $this->assertEquals(200, $saved->ad_id);
        $this->assertSame(0, $this->db->connection('dms')->table('inventory_transfers')->count());
        $this->assertSame(0, $this->db->table('transaction_details')->count());
        $this->assertSame(1, $this->controller->notifications);
    }

    public function test_retry_replays_exact_committed_order_without_duplicate_or_notification()
    {
        $first = $this->send(); $second = $this->send();
        $this->assertSame($first, $second);
        $this->assertSame(1, $this->db->table('order_details')->where('status', 'Pending')->count());
        $this->assertSame(1, $this->db->table('offline_order_receipts')->count());
        $this->assertSame(1, $this->controller->notifications);
    }

    public function test_synced_ad_order_uses_the_next_database_id_and_keeps_its_uuid_only_for_retries()
    {
        $previous = (int) $this->db->table('order_details')->max('id');
        $result = $this->send();
        $reference = 'PRORD-' . str_pad((string) ($previous + 1), 4, '0', STR_PAD_LEFT);
        $this->assertSame($reference, $result['records'][0]['transaction_id']);
        $this->assertSame($reference, $this->db->table('order_details')->find($result['records'][0]['id'])->transaction_id);
        $this->assertSame($this->payload()['idempotency_key'], $this->db->table('offline_order_receipts')->first()->idempotency_key);
        $this->assertSame($result, $this->send());
    }

    public function test_multiple_items_and_other_accounts_receive_consecutive_order_ids()
    {
        $firstId = (int) $this->db->table('order_details')->max('id') + 1;
        $first = $this->send($this->payload(['items' => [
            ['product_id' => 10, 'quantity' => 2, 'unit_price' => 50],
            ['product_id' => 11, 'quantity' => 2, 'unit_price' => 80],
        ]]));
        $other = $this->send($this->payload(['user_id' => 43, 'idempotency_key' => '00000000-0000-4000-8000-000000000099',
            'items' => [['product_id' => 10, 'quantity' => 1, 'unit_price' => 45]]]), 43);
        $this->assertSame([$firstId, $firstId + 1, $firstId + 2], array_column(array_merge($first['records'], $other['records']), 'id'));
        foreach (array_merge($first['records'], $other['records']) as $record) {
            $this->assertSame('PRORD-' . str_pad((string) $record['id'], 4, '0', STR_PAD_LEFT), $record['transaction_id']);
        }
    }

    public function test_database_allocated_id_is_used_after_deleted_high_ids_and_legacy_uuid_references()
    {
        $historical = (array) $this->db->table('order_details')->first();
        $historical['id'] = 10005;
        $historical['transaction_id'] = 'PRORD-OFF-00000000-0000-4000-8000-000000000050-1';
        $this->db->table('order_details')->insert($historical);
        $this->db->table('order_details')->where('id', 10005)->delete();
        $result = $this->send();
        $this->assertSame(10006, $result['records'][0]['id']);
        $this->assertSame('PRORD-10006', $result['records'][0]['transaction_id']);
    }

    public function test_online_order_reference_helper_and_offline_orders_share_the_database_sequence()
    {
        $online = new \App\OrderDetail;
        $online->dealer_id = 42; $online->product_id = 10; $online->item = 'Refill';
        $online->qty = 1; $online->price = 50; $online->status = 'Pending';
        OrderReference::save($online);
        $result = $this->send();
        $this->assertSame('PRORD-' . str_pad((string) $online->id, 4, '0', STR_PAD_LEFT), $online->transaction_id);
        $this->assertSame((int) $online->id + 1, $result['records'][0]['id']);
        $this->assertSame('PRORD-' . str_pad((string) ($online->id + 1), 4, '0', STR_PAD_LEFT), $result['records'][0]['transaction_id']);
    }

    public function test_failure_to_finalize_an_order_reference_rolls_back_the_order_and_receipt()
    {
        $this->db->connection()->statement("CREATE TRIGGER reject_order_reference BEFORE UPDATE OF transaction_id ON order_details BEGIN SELECT RAISE(ABORT, 'Synthetic reference failure'); END");
        try { $this->send(); $this->fail('Expected reference write failure'); }
        catch (\Illuminate\Database\QueryException $error) { $this->assertContains('Synthetic reference failure', $error->getMessage()); }
        $this->assertSame(0, $this->db->table('order_details')->where('status', 'Pending')->count());
        $this->assertSame(0, $this->db->table('offline_order_receipts')->count());
        $this->assertSame(0, $this->controller->notifications);
    }

    public function test_same_reference_cannot_be_reused_with_changed_items()
    {
        $this->send();
        try { $this->send($this->payload(['items' => [['product_id' => 10, 'quantity' => 31, 'unit_price' => 50]]])); $this->fail('Expected conflict'); }
        catch (HttpException $error) { $this->assertSame(409, $error->getStatusCode()); }
        $this->assertSame(1, $this->db->table('order_details')->where('status', 'Pending')->count());
    }

    public function test_a_bad_second_item_rolls_back_the_entire_order_and_receipt()
    {
        try { $this->send($this->payload(['items' => [['product_id' => 10, 'quantity' => 30, 'unit_price' => 50], ['product_id' => 11, 'quantity' => 2, 'unit_price' => 1]]])); $this->fail('Expected validation'); }
        catch (ValidationException $error) { $this->assertArrayHasKey('items', $error->errors()); }
        $this->assertSame(0, $this->db->table('order_details')->where('status', 'Pending')->count());
        $this->assertSame(0, $this->db->table('offline_order_receipts')->count());
        $this->assertSame(0, $this->controller->notifications);
    }

    public function test_unassigned_ad_and_product_are_rejected_even_for_backorders()
    {
        foreach ([$this->payload(['ad_id' => 201]), $this->payload(['items' => [['product_id' => 12, 'quantity' => 1, 'unit_price' => 80]]])] as $payload) {
            try { $this->send($payload); $this->fail('Expected validation'); }
            catch (ValidationException $error) { $this->assertNotEmpty($error->errors()); }
        }
        $this->assertSame(0, $this->db->table('offline_order_receipts')->count());
    }

    public function test_role_prices_are_enforced_for_mega_dealer_and_center_chief()
    {
        foreach ([43 => 45, 44 => 75] as $id => $price) {
            $result = $this->send($this->payload(['user_id' => $id, 'idempotency_key' => '00000000-0000-4000-8000-0000000000' . $id,
                'items' => [['product_id' => 10, 'quantity' => 2, 'unit_price' => $price]]]), $id);
            $record = $this->db->table('order_details')->find($result['records'][0]['id']);
            $this->assertEquals($price, $record->price); $this->assertSame('Pending', $record->status);
        }
    }

    public function test_client_sale_uses_regular_price_and_reduces_available_dealer_stock()
    {
        $result = $this->send($this->payload(['type' => 'client', 'client_id' => 100, 'items' => [['product_id' => 10, 'quantity' => 2, 'unit_price' => 70]]]));
        $this->assertSame('Completed', $result['records'][0]['status']);
        $this->assertEquals(70, $this->db->table('transaction_details')->first()->price);
        $this->assertEquals(6, $this->context->dealerStock(\App\Product::find(10), 42));
    }

    public function test_synced_client_transaction_receives_a_server_timestamp_and_four_digit_reference()
    {
        $before = time();
        $result = $this->send($this->payload(['type' => 'client', 'client_id' => 100, 'transaction_id' => 'UNTRUSTED-REFERENCE',
            'items' => [['product_id' => 10, 'quantity' => 2, 'unit_price' => 70]]]));
        $reference = $result['records'][0]['transaction_id'];
        $this->assertSame(1, preg_match('/^TRX-(\d+)-([1-9]\d{3})$/', $reference, $parts));
        $this->assertGreaterThanOrEqual($before, (int) $parts[1]);
        $this->assertLessThanOrEqual(time(), (int) $parts[1]);
        $this->assertSame($reference, $this->db->table('transaction_details')->first()->transaction_id);
        $this->assertFalse(strpos($reference, $this->payload()['idempotency_key']));
    }

    public function test_client_sync_retry_preserves_the_generated_reference_stock_and_history()
    {
        $payload = $this->payload(['type' => 'client', 'client_id' => 100,
            'items' => [['product_id' => 10, 'quantity' => 2, 'unit_price' => 70]]]);
        $first = $this->send($payload); $retry = $this->send($payload);
        $this->assertSame($first, $retry);
        $this->assertSame(1, $this->db->table('transaction_details')->count());
        $this->assertSame(1, $this->db->table('offline_order_receipts')->count());
        $this->assertEquals(6, $this->context->dealerStock(\App\Product::find(10), 42));
        $snapshot = (new OfflineSnapshotData($this->context))->build(User::find(42));
        $this->assertSame($first['records'][0]['transaction_id'], $snapshot['transactions'][0]['transaction_id']);
        $results = (array) $snapshot['acknowledged_order_results'];
        $this->assertEquals($first['records'], $results[$payload['idempotency_key']]);
    }

    public function test_client_sale_cannot_spend_zero_or_insufficient_dealer_stock()
    {
        foreach ([[10, 9, 70], [11, 1, 100]] as $item) {
            try { $this->send($this->payload(['type' => 'client', 'client_id' => 100, 'items' => [['product_id' => $item[0], 'quantity' => $item[1], 'unit_price' => $item[2]]]])); $this->fail('Expected stock validation'); }
            catch (ValidationException $error) { $this->assertArrayHasKey('items', $error->errors()); }
        }
        $this->assertSame(0, $this->db->table('transaction_details')->count());
    }

    public function test_customer_from_another_mfi_is_rejected()
    {
        $this->expectException(ValidationException::class);
        $this->send($this->payload(['type' => 'client', 'client_id' => 101, 'items' => [['product_id' => 10, 'quantity' => 1, 'unit_price' => 70]]]));
    }

    public function test_email_failure_does_not_undo_or_duplicate_a_saved_order()
    {
        $this->controller->failNotification = true;
        // Bind a quiet logger rather than any application logging service.
        Container::getInstance()->instance('log', new \Psr\Log\NullLogger);
        $result = $this->send();
        $this->assertArrayHasKey('warning', $result);
        $replay = $this->send();
        $this->assertTrue($replay['success']);
        $this->assertSame(1, $this->controller->notifications);
        $this->assertSame(1, $this->db->table('order_details')->where('status', 'Pending')->count());
    }

    public function test_unauthenticated_and_other_account_submissions_fail()
    {
        foreach ([0 => 401, 43 => 403] as $id => $status) {
            try { $this->send($this->payload(), $id); $this->fail('Expected access denial'); }
            catch (HttpException $error) { $this->assertSame($status, $error->getStatusCode()); }
        }
    }

    public function test_snapshot_is_scoped_to_mfi_territory_and_omits_private_credentials()
    {
        $snapshot = (new OfflineSnapshotData($this->context))->build(User::find(42));
        $this->assertSame([10, 11], array_column($snapshot['products'], 'id'));
        $this->assertSame([200], array_column($snapshot['distributors'], 'id'));
        $this->assertSame([100], array_column($snapshot['customers'], 'id'));
        $this->assertSame('************* SURNAME', $snapshot['customers'][0]['name']);
        $this->assertSame('******56789', $snapshot['customers'][0]['number']);
        $this->assertSame(0, $snapshot['products'][0]['ad_stock']);
        $this->assertSame(8, $snapshot['products'][0]['dealer_stock']);
        $json = json_encode($snapshot);
        $this->assertFalse(strpos($json, 'NEVER_EXPORT_PASSWORD'));
        $this->assertFalse(strpos($json, 'OTHER MFI PERSON'));
        $this->assertFalse(strpos($json, 'PRIVATE FIRST'));
    }

    public function test_snapshot_respects_mfi_full_name_setting_and_role_prices()
    {
        $this->db->connection('dms')->table('mfis')->update(['show_customer_full_name' => 1]);
        foreach ([42 => 50, 43 => 45, 44 => 75] as $id => $price) {
            $snapshot = (new OfflineSnapshotData($this->context))->build(User::find($id));
            $this->assertSame('PRIVATE FIRST SURNAME', $snapshot['customers'][0]['name']);
            $this->assertEquals($price, $snapshot['products'][0]['order_price']);
            $this->assertEquals($id === 44 ? 75 : 70, $snapshot['products'][0]['price']);
        }
    }

    public function test_missing_territory_or_mfi_never_expands_access()
    {
        $this->db->table('dealers')->where('user_id', 42)->update(['area' => '', 'mfi' => '']);
        $snapshot = (new OfflineSnapshotData($this->context))->build(User::find(42));
        $this->assertSame([], $snapshot['products']); $this->assertSame([], $snapshot['distributors']); $this->assertSame([], $snapshot['customers']);
    }

    public function test_client_snapshot_cannot_see_dealer_products_customers_or_orders()
    {
        $snapshot = (new OfflineSnapshotData($this->context))->build(User::find(45));
        $this->assertFalse($snapshot['settings']['can_order']);
        $this->assertSame([], $snapshot['products']); $this->assertSame([], $snapshot['customers']); $this->assertSame([], $snapshot['orders']);
    }

    public function test_snapshot_points_exclude_uncompleted_sales_and_include_redemptions()
    {
        foreach (['Completed' => 12, 'Pending' => 100, 'Cancelled' => 200] as $status => $points) {
            $this->db->table('transaction_details')->insert(['dealer_id' => 42, 'product_id' => 10, 'transaction_id' => $status,
                'item' => 'Refill', 'qty' => 1, 'price' => 70, 'status' => $status, 'points_dealer' => $points, 'client_id' => 100]);
        }
        $this->db->table('redeemed_histories')->insert(['user_id' => 42, 'points_amount' => -5]);
        $snapshot = (new OfflineSnapshotData($this->context))->build(User::find(42));
        $this->assertSame(7.0, $snapshot['points']);
    }

    public function test_acknowledgements_are_account_scoped_and_survive_older_history_pages()
    {
        $first = $this->send();
        $other = $this->payload(['user_id' => 43, 'idempotency_key' => '00000000-0000-4000-8000-000000000002',
            'items' => [['product_id' => 10, 'quantity' => 2, 'unit_price' => 45]]]);
        $this->send($other, 43);
        $rows = [];
        for ($index = 0; $index < 501; $index++) $rows[] = ['dealer_id' => 42, 'product_id' => 10,
            'transaction_id' => 'NEWER-' . $index, 'item' => 'Refill', 'qty' => 1, 'price' => 50, 'status' => 'Pending'];
        foreach (array_chunk($rows, 50) as $chunk) $this->db->table('order_details')->insert($chunk);
        $snapshot = (new OfflineSnapshotData($this->context))->build(User::find(42));
        $this->assertCount(500, $snapshot['orders']);
        $this->assertSame([$this->payload()['idempotency_key']], $snapshot['acknowledged_orders']);
        $results = (array) $snapshot['acknowledged_order_results'];
        $this->assertSame([$this->payload()['idempotency_key']], array_keys($results));
        $this->assertEquals($first['records'], $results[$this->payload()['idempotency_key']]);
        $this->assertSame('PRORD-0004', $results[$this->payload()['idempotency_key']][0]['transaction_id']);
    }

    public function test_acknowledged_result_map_only_exports_safe_record_fields_for_the_authenticated_account()
    {
        $first = $this->send();
        $otherKey = '00000000-0000-4000-8000-000000000002';
        $this->send($this->payload(['user_id' => 43, 'idempotency_key' => $otherKey,
            'items' => [['product_id' => 10, 'quantity' => 2, 'unit_price' => 45]]]), 43);
        $first['records'][0]['private_customer_detail'] = 'DO_NOT_EXPORT';
        $first['request_hash'] = 'DO_NOT_EXPORT';
        $this->db->table('offline_order_receipts')->where('idempotency_key', $this->payload()['idempotency_key'])
            ->update(['response_json' => json_encode($first)]);
        $snapshot = (new OfflineSnapshotData($this->context))->build(User::find(42));
        $results = (array) $snapshot['acknowledged_order_results'];
        $this->assertArrayNotHasKey($otherKey, $results);
        $record = $results[$this->payload()['idempotency_key']][0];
        $this->assertSame(['id', 'product_id', 'transaction_id', 'status'], array_keys($record));
        $this->assertSame('PRORD-0004', $record['transaction_id']);
        $this->assertFalse(strpos(json_encode($results), 'DO_NOT_EXPORT'));
        $client = (new OfflineSnapshotData($this->context))->build(User::find(45));
        $this->assertSame([], (array) $client['acknowledged_order_results']);
        $this->assertSame('{}', json_encode($client['acknowledged_order_results']));
    }

    public function test_invalid_historical_receipt_json_does_not_break_the_snapshot_or_expose_record_data()
    {
        $this->send();
        $this->db->table('offline_order_receipts')->where('idempotency_key', $this->payload()['idempotency_key'])
            ->update(['response_json' => '{invalid']);
        $snapshot = (new OfflineSnapshotData($this->context))->build(User::find(42));
        $this->assertSame([$this->payload()['idempotency_key']], $snapshot['acknowledged_orders']);
        $this->assertSame([], (array) $snapshot['acknowledged_order_results']);
    }

    public function test_reapplying_or_rolling_back_the_setup_keeps_existing_receipts()
    {
        $this->send();
        $migration = new CreateOfflineOrderReceiptsTable;
        $migration->up(); $migration->down();
        $this->assertSame(1, $this->db->table('offline_order_receipts')->count());
        $this->assertTrue($this->send()['success']);
        $this->assertSame(1, $this->controller->notifications);
    }

    public function test_legacy_transaction_schema_without_product_or_status_columns_still_saves_and_refreshes()
    {
        $schema = $this->db->connection()->getSchemaBuilder();
        $schema->drop('transaction_details');
        $schema->create('transaction_details', function ($t) {
            $t->increments('id'); $t->integer('dealer_id'); $t->string('transaction_id'); $t->string('item');
            $t->string('item_description')->nullable(); $t->integer('qty'); $t->decimal('price', 12, 2);
            $t->integer('client_id'); $t->string('client_address')->nullable(); $t->integer('created_by'); $t->string('date');
            $t->decimal('points_dealer', 12, 2)->default(0); $t->decimal('points_client', 12, 2)->default(0);
            $t->string('payment_method'); $t->timestamps();
        });
        $result = $this->send($this->payload(['type' => 'client', 'client_id' => 100, 'items' => [['product_id' => 10, 'quantity' => 2, 'unit_price' => 70]]]));
        $this->assertSame('Completed', $result['records'][0]['status']);
        $this->assertEquals(6, $this->context->dealerStock(\App\Product::find(10), 42));
        $snapshot = (new OfflineSnapshotData($this->context))->build(User::find(42));
        $this->assertCount(1, $snapshot['transactions']);
        $this->assertSame('Completed', $snapshot['transactions'][0]['status']);
        $this->assertSame([$this->payload()['idempotency_key']], $snapshot['acknowledged_orders']);
    }
}

class IsolatedOfflineOrderController extends OfflineOrderController
{
    public $notifications = 0;
    public $failNotification = false;

    protected function notifyDistributor($order, $ad, $product, $user)
    {
        $this->notifications++;
        if ($this->failNotification) throw new RuntimeException('Synthetic mail failure');
    }
}
