<?php

use App\Http\Controllers\ReceivedDealerOrderController;
use App\OrderDetail;
use App\Support\MegaDealerOrderService;
use App\Support\OfflinePortalContext;
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

/** Every query runs against isolated SQLite memory databases, never application data. */
class ReceivedDealerOrderTableTest extends TestCase
{
    private $db;
    private $context;

    protected function setUp()
    {
        $app = new Container; Container::setInstance($app);
        Facade::clearResolvedInstances(); Facade::setFacadeApplication($app);
        $app->instance('config', new Repository(['database.default' => 'default']));
        $this->db = new Capsule($app);
        foreach (['default', 'dms'] as $connection) {
            $this->db->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], $connection);
        }
        $this->db->getDatabaseManager()->setDefaultConnection('default');
        $this->db->setAsGlobal(); $this->db->bootEloquent();
        $app->instance('db', $this->db->getDatabaseManager());
        $app->bind('db.schema', function () { return $this->db->connection()->getSchemaBuilder(); });
        $app->instance('validator', new Factory(new Translator(new ArrayLoader, 'en'), $app));
        $schema = $this->db->connection()->getSchemaBuilder();
        $schema->create('users', function ($t) {
            $t->increments('id'); $t->string('name'); $t->string('role');
        });
        $schema->create('mega_dealers', function ($t) {
            $t->increments('id'); $t->integer('user_id'); $t->string('name'); $t->string('area');
        });
        // Deployed orders have no product_id. Deployed sales have neither SKU nor status.
        $schema->create('order_details', function ($t) {
            $t->increments('id'); $t->integer('dealer_id'); $t->integer('ad_id')->nullable(); $t->integer('mega_dealer_id')->nullable();
            $t->string('transaction_id'); $t->string('item'); $t->string('sku')->nullable(); $t->integer('qty');
            $t->decimal('price', 12, 2); $t->string('status'); $t->string('payment_method')->nullable();
            $t->string('delivery_type')->nullable(); $t->string('remarks')->nullable(); $t->timestamp('completed_at')->nullable(); $t->timestamps();
        });
        $schema->create('transaction_details', function ($t) {
            $t->increments('id'); $t->integer('dealer_id'); $t->string('item'); $t->integer('qty'); $t->softDeletes();
        });
        $dms = $this->db->connection('dms')->getSchemaBuilder();
        $dms->create('area_distributors', function ($t) {
            $t->increments('id'); $t->integer('user_id'); $t->string('name'); $t->softDeletes();
        });
        $dms->create('ad_areas', function ($t) {
            $t->increments('id'); $t->integer('ad_id'); $t->string('area_name'); $t->string('user_role'); $t->softDeletes();
        });
        $dms->create('products', function ($t) {
            $t->increments('id'); $t->integer('ad_user_id'); $t->string('product_name'); $t->string('sku'); $t->string('status');
        });
        foreach ([42 => ['Alice Dealer', 'Dealer'], 43 => ['North Mega', 'Mega Dealer'],
            45 => ['Other Mega', 'Mega Dealer'], 49 => ['Bob Buyer', 'Dealer'], 50 => ['No Profile', 'Mega Dealer']] as $id => $user) {
            $this->db->table('users')->insert(['id' => $id, 'name' => $user[0], 'role' => $user[1]]);
        }
        foreach ([200 => [43, 'North Zone'], 201 => [45, 'South']] as $id => $profile) {
            $this->db->table('mega_dealers')->insert(['id' => $id, 'user_id' => $profile[0], 'name' => 'Mega ' . $id, 'area' => $profile[1]]);
        }
        $this->db->connection('dms')->table('area_distributors')->insert(['id' => 200, 'user_id' => 900, 'name' => 'North AD']);
        $this->db->connection('dms')->table('ad_areas')->insert(['ad_id' => 200, 'area_name' => 'North Zone', 'user_role' => 'Area Distributor']);
        foreach ([10 => ['330g Refill', 'REFILL'], 11 => ['330g Crate', 'CRATE']] as $id => $product) {
            $this->db->connection('dms')->table('products')->insert(['id' => $id, 'ad_user_id' => 900,
                'product_name' => $product[0], 'sku' => $product[1], 'status' => 'Activate']);
        }
        $this->context = new OfflinePortalContext;
    }

    protected function tearDown()
    {
        foreach (['default', 'dms'] as $connection) $this->db->getDatabaseManager()->disconnect($connection);
        Facade::clearResolvedInstances(); Facade::setFacadeApplication(null); Container::setInstance(null);
    }

    private function order(array $values = [])
    {
        return OrderDetail::forceCreate(array_replace(['dealer_id' => 42, 'ad_id' => null, 'mega_dealer_id' => 200,
            'transaction_id' => 'PRORD-TEST', 'item' => '330g Refill', 'sku' => 'REFILL', 'qty' => 4, 'price' => 50,
            'status' => 'Pending', 'payment_method' => 'cash', 'delivery_type' => 'pickup', 'created_at' => '2026-10-28 08:30:00'], $values));
    }

    private function request(array $parameters = [], $actorId = 43)
    {
        $request = Request::create('/api/mega-dealer/orders/received', 'GET', $parameters, [], [], ['HTTP_ACCEPT' => 'application/json']);
        $actor = $actorId === null ? null : User::find($actorId);
        $request->setUserResolver(function () use ($actor) { return $actor; });
        return (new ReceivedDealerOrderController)->index($request, $this->context);
    }

    private function table(array $parameters = [])
    {
        $response = $this->request($parameters);
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        return $response->getData(true);
    }

    private function fulfilRequest($orderId, array $parameters = [], $actorId = 43)
    {
        $request = Request::create('/mega-dealer/orders/' . $orderId . '/fulfil', 'POST',
            array_replace(['remarks' => 'Dispatched for Dealer verification'], $parameters), [], [], ['HTTP_ACCEPT' => 'application/json']);
        $actor = $actorId === null ? null : User::find($actorId);
        $request->setUserResolver(function () use ($actor) { return $actor; });
        return (new ReceivedDealerOrderController)->fulfil($request, $orderId, $this->context);
    }

    private function stock($quantity = 12)
    {
        $this->order(['dealer_id' => 43, 'mega_dealer_id' => null, 'ad_id' => 200, 'qty' => $quantity, 'status' => 'Completed']);
        $this->db->table('transaction_details')->insert(['dealer_id' => 43, 'item' => '330g Refill', 'qty' => 3]);
        $this->order(['dealer_id' => 49, 'qty' => 4, 'status' => 'Completed']);
    }

    public function test_expired_sessions_wrong_roles_and_missing_profiles_return_json_errors()
    {
        foreach ([[null, 401], [42, 403], [50, 403]] as $case) {
            $response = $this->request(['draw' => '<script>'], $case[0]);
            $this->assertSame($case[1], $response->getStatusCode());
            $result = $response->getData(true);
            $this->assertSame(0, $result['draw']); $this->assertSame([], $result['data']);
            $this->assertNotEmpty($result['message']);
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
    }

    public function test_pagination_counts_and_search_never_include_other_suppliers_or_ad_orders()
    {
        $ids = [];
        for ($i = 0; $i < 25; $i++) $ids[] = $this->order(['transaction_id' => 'PRORD-' . $i])->id;
        $this->order(['mega_dealer_id' => 201, 'transaction_id' => 'SECRET-MEGA']);
        $this->order(['mega_dealer_id' => null, 'ad_id' => 200, 'transaction_id' => 'SECRET-AD']);
        $this->order(['ad_id' => 200, 'transaction_id' => 'SECRET-AMBIGUOUS']);
        $result = $this->table(['draw' => '7', 'start' => 10, 'length' => 10, 'order' => [['column' => 0, 'dir' => 'asc']]]);
        $this->assertSame(7, $result['draw']); $this->assertSame(25, $result['recordsTotal']);
        $this->assertSame(25, $result['recordsFiltered']); $this->assertCount(10, $result['data']);
        $this->assertSame(array_slice($ids, 10, 10), array_column($result['data'], 'id'));
        $result = $this->table(['search' => ['value' => 'SECRET']]);
        $this->assertSame(25, $result['recordsTotal']); $this->assertSame(0, $result['recordsFiltered']); $this->assertSame([], $result['data']);
    }

    public function test_server_search_and_status_filter_cover_displayed_fields()
    {
        $match = $this->order(['transaction_id' => 'PRORD-unique', 'payment_method' => 'bank_transfer', 'delivery_type' => 'delivery']);
        $this->order(['dealer_id' => 49, 'status' => 'Cancelled', 'item' => '330g Crate', 'sku' => 'CRATE']);
        foreach (['Alice', 'PRORD-unique', 'Refill', 'REFILL', 'bank_transfer', 'delivery', 'Pending'] as $search) {
            $result = $this->table(['search' => ['value' => $search]]);
            $this->assertSame(2, $result['recordsTotal']); $this->assertSame(1, $result['recordsFiltered']);
            $this->assertSame($match->id, $result['data'][0]['id']);
        }
        $result = $this->table(['status' => 'Cancelled', 'search' => ['value' => 'Bob']]);
        $this->assertSame(1, $result['recordsFiltered']); $this->assertSame('Cancelled', $result['data'][0]['status']);
        $this->assertSame('Bank Transfer', $this->table(['status' => 'Pending'])['data'][0]['payment_label']);
        $this->assertSame(422, $this->request(['status' => 'unknown'])->getStatusCode());
    }

    public function test_sorting_is_allowlisted_and_totals_are_sorted_numerically()
    {
        $small = $this->order(['qty' => 2, 'price' => 9]); $large = $this->order(['qty' => 3, 'price' => 100]);
        $result = $this->table(['order' => [['column' => 6, 'dir' => 'asc']]]);
        $this->assertSame([$small->id, $large->id], array_column($result['data'], 'id'));
        $this->assertEquals([18, 300], array_column($result['data'], 'total_amount'));
        $result = $this->table(['order' => [['column' => 0, 'dir' => 'asc']], 'columns' => [['name' => 'quantity']]]);
        $this->assertSame([$small->id, $large->id], array_column($result['data'], 'id'));
        $result = $this->table(['draw' => '8<script>', 'order' => [['column' => 0, 'dir' => 'desc; drop table users']],
            'columns' => [['name' => 'id; drop table users']]]);
        $this->assertSame(0, $result['draw']); $this->assertSame([$large->id, $small->id], array_column($result['data'], 'id'));
        $this->assertSame(5, User::count());
    }

    public function test_real_stock_uses_legacy_names_and_disables_short_zero_invalid_and_unknown_products()
    {
        $this->stock();
        $available = $this->order(['sku' => 'OLD-SKU']);
        $short = $this->order(['qty' => 6]);
        $zero = $this->order(['item' => '330g Crate', 'sku' => 'CRATE', 'qty' => 1]);
        $unknown = $this->order(['item' => 'Retired Product', 'sku' => 'OLD', 'qty' => 1]);
        $invalid = $this->order(['qty' => 0]);
        $cancelled = $this->order(['status' => 'Cancelled']);
        $rows = collect($this->table(['length' => 100])['data'])->keyBy('id');
        $this->assertSame(5, $rows[$available->id]['available_stock']); $this->assertTrue($rows[$available->id]['can_fulfil']);
        foreach ([$short->id => 'insufficient', $zero->id => 'out_of_stock', $unknown->id => 'unavailable', $invalid->id => 'unavailable'] as $id => $state) {
            $this->assertSame($state, $rows[$id]['stock_state']); $this->assertFalse($rows[$id]['can_fulfil']);
            $this->assertNotEmpty($rows[$id]['stock_message']);
        }
        $this->assertNull($rows[$unknown->id]['available_stock']); $this->assertSame(0, $rows[$zero->id]['available_stock']);
        $this->assertFalse($rows[$cancelled->id]['can_fulfil']);
    }

    public function test_stock_queries_are_batched_and_page_length_is_capped_in_sql()
    {
        for ($i = 0; $i < 110; $i++) $this->order();
        $connection = $this->db->connection(); $connection->enableQueryLog();
        $this->table(['length' => 1]); $singleCount = count($connection->getQueryLog()); $connection->flushQueryLog();
        $result = $this->table(['length' => 99999]); $queries = $connection->getQueryLog();
        $this->assertCount(100, $result['data']); $this->assertSame(110, $result['recordsTotal']);
        $this->assertSame($singleCount, count($queries), 'Stock must not run a query per row.');
        $paged = array_values(array_filter($queries, function ($query) { return strpos($query['query'], 'received_dealers') !== false && strpos($query['query'], 'limit') !== false; }));
        $this->assertCount(1, $paged); $this->assertStringContainsString('limit 100', $paged[0]['query']);
        $this->assertCount(10, $this->table(['length' => -1, 'start' => -100])['data']);
    }

    public function test_stale_table_state_cannot_fulfil_an_order_after_stock_is_consumed()
    {
        $this->stock(); $order = $this->order();
        $rows = collect($this->table(['status' => 'Pending'])['data'])->keyBy('id');
        $this->assertTrue($rows[$order->id]['can_fulfil']);
        $this->db->table('transaction_details')->insert(['dealer_id' => 43, 'item' => '330g Refill', 'qty' => 5]);
        try {
            (new MegaDealerOrderService($this->context))->fulfil($order->id, User::find(43), 'Fulfil from stale table');
            $this->fail('Expected stock to be checked again on completion.');
        } catch (ValidationException $error) { $this->assertArrayHasKey('stock', $error->errors()); }
        $this->assertSame('Pending', $order->fresh()->status);
        $row = collect($this->table(['status' => 'Pending'])['data'])->keyBy('id')[$order->id];
        $this->assertSame(0, $row['available_stock']); $this->assertFalse($row['can_fulfil']);
    }

    public function test_optional_sales_status_excludes_pending_client_transactions_from_available_stock()
    {
        $this->db->connection()->getSchemaBuilder()->table('transaction_details', function ($t) {
            $t->string('status')->default('Completed'); $t->string('sku')->nullable();
        });
        $this->stock(); $order = $this->order();
        $this->db->table('transaction_details')->insert(['dealer_id' => 43, 'item' => '330g Refill',
            'sku' => 'REFILL', 'qty' => 100, 'status' => 'Pending']);
        $row = collect($this->table(['status' => 'Pending'])['data'])->keyBy('id')[$order->id];
        $this->assertSame(5, $row['available_stock']); $this->assertTrue($row['can_fulfil']);
    }

    public function test_fulfilment_rejects_nonpositive_and_excessive_quantities_without_changing_orders()
    {
        $this->stock(); $service = new MegaDealerOrderService($this->context);
        foreach ([0, -1, 6] as $quantity) {
            $order = $this->order(['qty' => $quantity]);
            try { $service->fulfil($order->id, User::find(43), 'Invalid fulfilment'); $this->fail('Expected stock rejection.'); }
            catch (ValidationException $error) { $this->assertArrayHasKey('stock', $error->errors()); }
            $this->assertSame('Pending', $order->fresh()->status);
        }
    }

    public function test_competing_pending_orders_cannot_overdraw_reserved_stock_and_fulfilment_replay_is_safe()
    {
        $this->stock(); $first = $this->order(); $second = $this->order();
        $service = new MegaDealerOrderService($this->context);
        list($sent, $changed) = $service->fulfil($first->id, User::find(43), 'First fulfilment');
        $this->assertTrue($changed); $this->assertSame('For Verification', $sent->status);
        $this->assertNull($sent->completed_at);
        $this->assertSame(0, $this->context->dealerStock(\App\Product::find(10), 42));
        try { $service->fulfil($second->id, User::find(43), 'Second fulfilment'); $this->fail('Expected insufficient stock.'); }
        catch (ValidationException $error) { $this->assertArrayHasKey('stock', $error->errors()); }
        list(, $replayed) = $service->fulfil($first->id, User::find(43), 'Repeat');
        $this->assertFalse($replayed); $this->assertSame('Pending', $second->fresh()->status);
        $rows = collect($this->table(['length' => 100])['data'])->keyBy('id');
        $this->assertSame(1, $rows[$second->id]['available_stock']); $this->assertFalse($rows[$second->id]['can_fulfil']);
        $this->assertFalse($rows[$first->id]['can_fulfil']);
        $this->assertSame('For Verification', $rows[$first->id]['status']);
        $verified = $this->table(['status' => 'For Verification']);
        $this->assertSame(1, $verified['recordsFiltered']);
        $this->assertSame($first->id, $verified['data'][0]['id']);
    }

    public function test_fulfil_endpoint_returns_verification_status_and_replays_without_restocking()
    {
        $this->stock(); $order = $this->order();
        $response = $this->fulfilRequest($order->id);
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $result = $response->getData(true);
        $this->assertTrue($result['success']); $this->assertSame('For Verification', $result['status']);
        $this->assertSame('For Verification', $order->fresh()->status);
        $this->assertSame('Dispatched for Dealer verification', $order->fresh()->remarks);
        $this->assertNull($order->fresh()->completed_at);
        $again = $this->fulfilRequest($order->id, ['remarks' => 'Duplicate submit']);
        $this->assertSame(200, $again->getStatusCode(), $again->getContent());
        $this->assertSame('For Verification', $again->getData(true)['status']);
        $this->assertSame('Dispatched for Dealer verification', $order->fresh()->remarks);
        $row = $this->table(['status' => 'For Verification'])['data'][0];
        $this->assertFalse($row['can_fulfil']); $this->assertSame(1, $row['available_stock']);
    }

    public function test_fulfil_endpoint_rejects_missing_sessions_buyers_foreign_suppliers_and_bad_remarks()
    {
        $this->stock(); $order = $this->order();
        foreach ([[null, 401], [42, 403], [45, 403], [50, 403]] as $case) {
            $response = $this->fulfilRequest($order->id, [], $case[0]);
            $this->assertSame($case[1], $response->getStatusCode(), $response->getContent());
            $this->assertFalse($response->getData(true)['success']);
        }
        foreach (['', '   ', str_repeat('x', 256), str_repeat('x', 501)] as $remarks) {
            $response = $this->fulfilRequest($order->id, ['remarks' => $remarks]);
            $this->assertSame(422, $response->getStatusCode(), $response->getContent());
            $this->assertFalse($response->getData(true)['success']);
        }
        $this->assertSame('Pending', $order->fresh()->status);
        $this->assertNull($order->fresh()->remarks);
    }

    public function test_fulfil_endpoint_rejects_no_stock_without_moving_order_to_verification()
    {
        $order = $this->order();
        $response = $this->fulfilRequest($order->id);
        $this->assertSame(422, $response->getStatusCode(), $response->getContent());
        $this->assertFalse($response->getData(true)['success']);
        $this->assertArrayHasKey('stock', $response->getData(true)['errors']);
        $this->assertSame('Pending', $order->fresh()->status);
        $this->assertNull($order->fresh()->completed_at);
    }
}
