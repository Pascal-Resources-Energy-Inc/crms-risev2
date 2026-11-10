<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\OfflineOrderController;
use App\Http\Controllers\ReceivedDealerOrderController;
use App\Http\Controllers\TransactionController;
use App\OrderDetail;
use App\Product;
use App\Support\MegaDealerOrderService;
use App\Support\OfflinePortalContext;
use App\User;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\DatabasePresenceVerifier;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Source collisions are exercised against two isolated in-memory databases. */
class DmsMegaDealerSupplierTest extends TestCase
{
    private $db;
    private $context;
    private $mailer;

    protected function setUp()
    {
        $app = new Container; Container::setInstance($app);
        Facade::clearResolvedInstances(); Facade::setFacadeApplication($app);
        $app->instance('config', new Repository(['database' => ['default' => 'default']]));
        $this->db = new Capsule($app);
        foreach (['default', 'dms'] as $connection) $this->db->addConnection([
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ], $connection);
        $this->db->getDatabaseManager()->setDefaultConnection('default');
        $this->db->setAsGlobal(); $this->db->bootEloquent();
        $app->instance('db', $this->db->getDatabaseManager());
        $app->bind('db.schema', function () { return $this->db->connection()->getSchemaBuilder(); });
        $validator = new Factory(new Translator(new ArrayLoader, 'en'), $app);
        $validator->setPresenceVerifier(new DatabasePresenceVerifier($this->db->getDatabaseManager()));
        $app->instance('validator', $validator);
        $app->instance(\Illuminate\Contracts\Routing\ResponseFactory::class, new DmsSupplierResponseFactory);
        $this->mailer = new DmsSupplierTestMailer;
        $app->instance('mailer', $this->mailer);
        $app->instance('log', new \Psr\Log\NullLogger);
        foreach (['default', 'dms'] as $connection) {
            $schema = $this->db->connection($connection)->getSchemaBuilder();
            $schema->create('users', function ($t) {
                $t->increments('id'); $t->string('name'); $t->string('email'); $t->string('role');
                $t->string('status')->default('Active'); $t->softDeletes();
            });
            foreach (['dealers', 'mega_dealers', 'center_chiefs'] as $table) $schema->create($table, function ($t) {
                $t->increments('id'); $t->integer('user_id'); $t->string('name'); $t->string('area');
                $t->string('status')->default('Active'); $t->string('number')->nullable(); $t->string('address')->nullable();
                $t->float('latitude')->nullable(); $t->float('longitude')->nullable(); $t->softDeletes();
            });
            $schema->create('order_details', function ($t) {
                $t->increments('id'); $t->integer('dealer_id'); $t->integer('ad_id')->nullable();
                $t->integer('mega_dealer_id')->nullable(); $t->string('mega_dealer_source')->nullable();
                $t->string('transaction_id')->nullable(); $t->string('item'); $t->string('sku')->nullable();
                $t->integer('qty'); $t->decimal('price', 12, 2)->default(0); $t->string('status')->nullable();
                $t->decimal('points_dealer', 12, 2)->default(0); $t->string('item_description')->nullable();
                $t->string('ad_address')->nullable(); $t->string('date')->nullable(); $t->integer('created_by')->nullable();
                $t->string('payment_method')->nullable(); $t->string('delivery_type')->nullable(); $t->decimal('delivery_fee', 12, 2)->default(0);
                $t->string('remarks')->nullable(); $t->timestamp('completed_at')->nullable(); $t->softDeletes(); $t->timestamps();
            });
            $schema->create('transaction_details', function ($t) {
                $t->increments('id'); $t->integer('dealer_id'); $t->string('item'); $t->string('sku')->nullable();
                $t->integer('qty'); $t->string('status')->nullable(); $t->softDeletes(); $t->timestamps();
            });
        }
        require_once __DIR__ . '/../../database/migrations/2026_10_28_000001_create_offline_order_receipts_table.php';
        (new CreateOfflineOrderReceiptsTable)->up();
        $dms = $this->db->connection('dms')->getSchemaBuilder();
        $dms->create('area_distributors', function ($t) {
            $t->increments('id'); $t->integer('user_id'); $t->string('name'); $t->string('address')->nullable();
            $t->string('number')->nullable(); $t->float('latitude')->nullable(); $t->float('longitude')->nullable(); $t->softDeletes();
        });
        $dms->create('ad_areas', function ($t) {
            $t->increments('id'); $t->integer('ad_id'); $t->string('area_name'); $t->string('user_role'); $t->softDeletes();
        });
        $dms->create('products', function ($t) {
            $t->increments('id'); $t->integer('ad_user_id'); $t->string('product_name'); $t->string('sku'); $t->string('status');
            $t->string('description')->nullable(); $t->float('dealer_points')->default(1);
            $t->decimal('price', 12, 2); $t->decimal('dealer_price', 12, 2); $t->decimal('mega_dealer_price', 12, 2); $t->decimal('srp_price', 12, 2);
        });
        $this->seed();
        $this->context = new OfflinePortalContext;
    }

    private function seed()
    {
        foreach ([42 => 'Dealer', 43 => 'Mega Dealer', 44 => 'center_chief', 49 => 'Dealer', 50 => 'Admin'] as $id => $role) {
            $this->db->table('users')->insert(['id' => $id, 'name' => 'Primary ' . $id, 'email' => 'primary' . $id . '@example.test', 'role' => $role]);
        }
        $this->db->table('dealers')->insert(['id' => 142, 'user_id' => 42, 'name' => 'Dealer buyer', 'area' => ' North Zone ']);
        $this->db->table('center_chiefs')->insert(['id' => 144, 'user_id' => 44, 'name' => 'Center Chief', 'area' => 'North Zone']);
        foreach ([200, 208] as $id) $this->db->table('mega_dealers')->insert([
            'id' => $id, 'user_id' => 43, 'name' => 'Primary Mega ' . $id, 'area' => 'north zone',
        ]);
        $dms = $this->db->connection('dms');
        foreach ([42 => 'Mega Dealer', 43 => 'mega_dealer', 45 => 'Mega Dealer', 46 => 'Mega Dealer',
            47 => 'Mega Dealer', 48 => 'Dealer', 49 => 'Mega Dealer', 52 => 'Mega Dealer', 900 => 'Area Distributor', 901 => 'Area Distributor'] as $id => $role) {
            $dms->table('users')->insert(['id' => $id, 'name' => 'DMS ' . $id, 'email' => 'dms' . $id . '@example.test',
                'role' => $role, 'status' => $id === 45 ? 'Inactive' : 'Active', 'deleted_at' => $id === 52 ? '2026-10-01' : null]);
        }
        foreach ([200 => [43, ' NORTH  ZONE ', 'Active', null], 201 => [42, 'North Zone', 'Activate', null],
            202 => [46, 'North Zone', 'Inactive', null], 203 => [47, 'North Zone', 'Active', '2026-10-01'],
            204 => [48, 'North Zone', 'Active', null], 205 => [45, 'North Zone', 'Active', null],
            206 => [49, 'South', 'Active', null], 207 => [52, 'North Zone', 'Active', null]] as $id => $values) {
            $dms->table('mega_dealers')->insert(['id' => $id, 'user_id' => $values[0], 'name' => 'DMS Mega ' . $id,
                'area' => $values[1], 'status' => $values[2], 'deleted_at' => $values[3]]);
        }
        foreach ([200 => 900, 201 => 901] as $id => $userId) $dms->table('area_distributors')->insert([
            'id' => $id, 'user_id' => $userId, 'name' => 'AD ' . $id,
        ]);
        foreach ([200 => 'NORTH ZONE', 201 => 'South'] as $id => $area) $dms->table('ad_areas')->insert([
            'ad_id' => $id, 'area_name' => $area, 'user_role' => 'Area Distributor',
        ]);
        foreach ([10 => [900, '330g Refill', 'REFILL'], 12 => [901, 'Unassigned product', 'OTHER']] as $id => $values) {
            $dms->table('products')->insert(['id' => $id, 'ad_user_id' => $values[0], 'product_name' => $values[1], 'sku' => $values[2],
                'status' => 'Activate', 'price' => 70, 'dealer_price' => 50, 'mega_dealer_price' => 45, 'srp_price' => 75]);
        }
    }

    protected function tearDown()
    {
        foreach (['default', 'dms'] as $connection) $this->db->getDatabaseManager()->disconnect($connection);
        Facade::clearResolvedInstances(); Facade::setFacadeApplication(null); Container::setInstance(null);
    }

    private function order(array $values = [])
    {
        return OrderDetail::forceCreate(array_replace(['dealer_id' => 42, 'ad_id' => null, 'mega_dealer_id' => 200,
            'mega_dealer_source' => 'dms_prei', 'transaction_id' => 'PRORD-TEST', 'item' => '330g Refill',
            'sku' => 'REFILL', 'qty' => 3, 'price' => 50, 'status' => 'Pending'], $values));
    }

    private function dmsSupplier($id = 200)
    {
        return $this->context->resolveOrderSupplier(User::find(42), 'mega_dealer', $id, 'dms_prei');
    }

    private function stock()
    {
        $this->order(['dealer_id' => 43, 'mega_dealer_id' => null, 'mega_dealer_source' => null, 'ad_id' => 200, 'qty' => 10, 'status' => 'Completed']);
        $this->db->table('transaction_details')->insert(['dealer_id' => 43, 'item' => '330g Refill', 'sku' => 'REFILL', 'qty' => 3, 'status' => 'Completed']);
        $this->order(['dealer_id' => 49, 'mega_dealer_source' => null, 'qty' => 2, 'status' => 'Completed']);
        $dms = $this->db->connection('dms');
        $dms->table('order_details')->insert(['dealer_id' => 43, 'item' => '330g Refill', 'sku' => 'REFILL', 'qty' => 20, 'status' => 'Completed']);
        $dms->table('order_details')->insert(['dealer_id' => 43, 'item' => '330g Refill', 'sku' => 'REFILL', 'qty' => 100, 'status' => 'Pending']);
        $dms->table('transaction_details')->insert(['dealer_id' => 43, 'item' => '330g Refill', 'sku' => 'REFILL', 'qty' => 4, 'status' => 'Completed']);
        $dms->table('transaction_details')->insert(['dealer_id' => 43, 'item' => '330g Refill', 'sku' => 'REFILL', 'qty' => 100, 'status' => 'Pending']);
        $this->order(['dealer_id' => 49, 'qty' => 3, 'status' => 'For Verification']);
        $this->order(['dealer_id' => 49, 'qty' => 2, 'status' => 'Completed']);
    }

    private function online(array $values = [])
    {
        Container::getInstance()->instance(\Illuminate\Contracts\Auth\Factory::class, new DmsSupplierTestAuth(User::find(42)));
        $request = DmsSupplierControllerRequest::create('/ad-transactions/store', 'POST', array_replace([
            'item_id' => 10, 'qty' => 3, 'supplier_type' => 'mega_dealer', 'supplier_source' => 'dms_prei',
            'mega_dealer_id' => 200, 'payment_method' => 'cash', 'delivery_type' => 'pickup', 'delivery_fee' => 0,
        ], $values), [], [], ['HTTP_ACCEPT' => 'application/json']);
        return (new TransactionController)->adStore($request);
    }

    private function offline(array $values = [])
    {
        $request = Request::create('/api/offline/orders', 'POST', array_replace([
            'user_id' => 42, 'idempotency_key' => '00000000-0000-4000-8000-000000000001', 'type' => 'order',
            'supplier_type' => 'mega_dealer', 'supplier_source' => 'dms_prei', 'mega_dealer_id' => 200, 'ad_id' => null,
            'items' => [['product_id' => 10, 'quantity' => 3, 'unit_price' => 50]],
            'payment_method' => 'cash', 'delivery_type' => 'pickup', 'snapshot_saved_at' => '2026-10-29T00:00:00Z',
        ], $values));
        $request->setUserResolver(function () { return User::find(42); });
        return (new OfflineOrderController)->store($request, $this->context);
    }

    public function test_same_area_dealer_receives_distinct_primary_and_dms_supplier_keys()
    {
        $suppliers = $this->context->suppliers(User::find(42));
        $keys = $suppliers->pluck('key')->all();
        $this->assertContains('mega_dealer:200', $keys);
        $this->assertContains('mega_dealer:dms_prei:200', $keys);
        $this->assertContains('mega_dealer:dms_prei:201', $keys);
        $this->assertContains('area_distributor:200', $keys);
        $dms = $suppliers->firstWhere('key', 'mega_dealer:dms_prei:200');
        $this->assertSame('dms_prei', $dms['supplier_source']);
        $this->assertSame('DMS Mega 200', $dms['name']);
        $this->assertSame(43, $dms['user_id']);
        foreach ([202, 203, 204, 205, 206, 207] as $id) $this->assertNotContains('mega_dealer:dms_prei:' . $id, $keys);
        foreach ([43, 44, 50] as $id) $this->assertCount(0, $this->context->megaDealers(User::find($id)));
        $this->db->table('dealers')->where('user_id', 42)->update(['area' => '']);
        $this->assertCount(0, $this->context->megaDealers(User::find(42)));
    }

    public function test_legacy_primary_supplier_selection_survives_when_dms_profile_table_is_unavailable()
    {
        $this->db->connection('dms')->getSchemaBuilder()->drop('mega_dealers');
        $keys = $this->context->suppliers(User::find(42))->pluck('key')->all();
        $this->assertContains('mega_dealer:200', $keys);
        $this->assertNotContains('mega_dealer:dms_prei:200', $keys);
        $this->assertSame('Primary Mega 200', $this->context->resolveOrderSupplier(User::find(42), 'mega_dealer', 200)->name);
        $this->assertNull($this->dmsSupplier());
    }

    public function test_source_resolver_cannot_fall_back_to_a_colliding_primary_profile_or_user()
    {
        $primary = $this->context->resolveOrderSupplier(User::find(42), 'mega_dealer', 200);
        $dms = $this->dmsSupplier();
        $this->assertSame('Primary Mega 200', $primary->name);
        $this->assertSame('DMS Mega 200', $dms->name);
        $this->assertSame('dms', $dms->getConnectionName());
        $this->assertSame('dms43@example.test', $dms->user->email);
        $this->assertNull($this->dmsSupplier(208));
        $this->assertNull($this->context->resolveOrderSupplier(User::find(42), 'mega_dealer', 200, 'other'));
        $this->assertSame([10], $this->context->supplierProducts(User::find(42), $dms)->pluck('id')->all());
    }

    public function test_dms_stock_uses_its_own_ledgers_and_source_scoped_reservations()
    {
        $this->stock(); $product = Product::find(10);
        $this->assertSame(5, $this->context->dealerStock($product, 43));
        $this->assertSame(11, $this->context->supplierStock($product, $this->dmsSupplier()));
        $this->order(['dealer_id' => 42, 'mega_dealer_id' => null, 'mega_dealer_source' => null, 'ad_id' => 200, 'qty' => 99, 'status' => 'Completed']);
        $this->assertSame(0, $this->context->supplierStock($product, $this->dmsSupplier(201)));
        $this->assertSame([10], $this->context->supplierProducts(User::find(42), $this->dmsSupplier())->pluck('id')->all());
    }

    public function test_deleted_dms_ledger_rows_cannot_change_available_supplier_stock()
    {
        $this->stock(); $dms = $this->db->connection('dms');
        foreach (['order_details', 'transaction_details'] as $table) $dms->table($table)->insert([
            'dealer_id' => 43, 'item' => '330g Refill', 'sku' => 'REFILL', 'qty' => 100,
            'status' => 'Completed', 'deleted_at' => '2026-10-01',
        ]);
        $this->assertSame(11, $this->context->supplierStock(Product::find(10), $this->dmsSupplier()));
    }

    public function test_dms_legacy_sales_with_no_status_are_deducted_without_including_pending_or_foreign_rows()
    {
        $this->stock(); $dms = $this->db->connection('dms');
        foreach ([null, '', '   '] as $status) $dms->table('transaction_details')->insert([
            'dealer_id' => 43, 'item' => '330g Refill', 'sku' => 'REFILL', 'qty' => 1, 'status' => $status,
        ]);
        $this->assertSame(8, $this->context->supplierStock(Product::find(10), $this->dmsSupplier()));
        foreach (['Pending', 'Cancelled'] as $status) $dms->table('transaction_details')->insert([
            'dealer_id' => 43, 'item' => '330g Refill', 'sku' => 'REFILL', 'qty' => 100, 'status' => $status,
        ]);
        $dms->table('transaction_details')->insert([
            'dealer_id' => 42, 'item' => '330g Refill', 'sku' => 'REFILL', 'qty' => 100, 'status' => null,
        ]);
        $dms->table('transaction_details')->insert([
            'dealer_id' => 43, 'item' => '330g Refill', 'sku' => 'REFILL', 'qty' => 100, 'status' => null, 'deleted_at' => '2026-10-01',
        ]);
        foreach ([null, ''] as $status) $dms->table('order_details')->insert([
            'dealer_id' => 43, 'item' => '330g Refill', 'sku' => 'REFILL', 'qty' => 100, 'status' => $status,
        ]);
        $this->assertSame(8, $this->context->supplierStock(Product::find(10), $this->dmsSupplier()));
        $this->assertSame(5, $this->context->dealerStock(Product::find(10), 43));
    }

    public function test_source_migration_is_repeatable_and_keeps_legacy_primary_identity()
    {
        $this->db->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'legacy_source_test');
        $this->db->getDatabaseManager()->setDefaultConnection('legacy_source_test');
        try {
            $connection = $this->db->getDatabaseManager()->connection('legacy_source_test');
            $schema = $connection->getSchemaBuilder();
            $schema->create('order_details', function ($t) {
                $t->increments('id'); $t->integer('mega_dealer_id')->nullable(); $t->string('transaction_id');
            });
            $connection->table('order_details')->insert(['mega_dealer_id' => 200, 'transaction_id' => 'PRORD-LEGACY']);
            require_once __DIR__ . '/../../database/migrations/2026_10_29_000001_add_mega_dealer_source_to_order_details.php';
            (new AddMegaDealerSourceToOrderDetails)->up();
            (new AddMegaDealerSourceToOrderDetails)->up();
            $this->assertTrue($schema->hasColumn('order_details', 'mega_dealer_source'));
            $legacy = OrderDetail::on('legacy_source_test')->first();
            $this->assertNull($legacy->mega_dealer_source);
            $this->assertSame('admin_crms', $legacy->supplier_source);
            $this->assertSame('PRORD-LEGACY', $legacy->transaction_id);
            $this->assertSame(1, OrderDetail::on('legacy_source_test')->count());
        } finally {
            $this->db->getDatabaseManager()->setDefaultConnection('default');
            $this->db->getDatabaseManager()->disconnect('legacy_source_test');
        }
    }

    public function test_online_dms_order_persists_source_relation_reference_and_dms_notification_recipient()
    {
        $response = $this->online();
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $order = OrderDetail::find($response->getData(true)['id']);
        $this->assertSame('dms_prei', $order->mega_dealer_source);
        $this->assertSame(200, (int) $order->mega_dealer_id); $this->assertNull($order->ad_id);
        $this->assertSame('DMS Mega 200', $order->supplier_name);
        $this->assertSame('dms', $order->supplier->getConnectionName());
        $this->assertSame('Pending', $order->status); $this->assertEquals(50, $order->price);
        $this->assertSame(1, preg_match('/^PRORD-\d+$/', $order->transaction_id));
        $this->assertSame('dms43@example.test', $this->mailer->sent[0]['to']);
    }

    public function test_explicit_dms_source_cannot_save_a_primary_only_supplier_or_unassigned_product()
    {
        foreach ([['mega_dealer_id' => 208], ['item_id' => 12], ['supplier_source' => 'untrusted']] as $values) {
            $response = $this->online($values);
            $this->assertSame(422, $response->getStatusCode(), $response->getContent());
        }
        $this->assertSame(0, OrderDetail::count());
    }

    public function test_primary_and_dms_ids_remain_distinct_in_received_orders_and_cancellation()
    {
        $created = \Carbon\Carbon::now()->subMinutes(5);
        $dms = $this->order(['created_at' => $created]);
        $primary = $this->order(['mega_dealer_source' => null, 'created_at' => $created]);
        $request = Request::create('/api/mega-dealer/orders/received', 'GET');
        $request->setUserResolver(function () { return User::find(43); });
        $data = (new ReceivedDealerOrderController)->index($request, $this->context)->getData(true);
        $this->assertSame(1, $data['recordsTotal']);
        $this->assertSame([$primary->id], array_column($data['data'], 'id'));
        Container::getInstance()->instance(\Illuminate\Contracts\Auth\Factory::class, new DmsSupplierTestAuth(User::find(42)));
        $response = (new HomeController)->cancelPendingOrder(Request::create('/ad-orders/' . $dms->id . '/cancel', 'POST', ['remarks' => 'Cancel DMS request']), $dms->id);
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $this->assertSame('Cancelled', $dms->fresh()->status);
        $this->assertSame('Pending', $primary->fresh()->status);
    }

    public function test_primary_mega_dealer_cannot_fulfil_a_dms_supplier_with_colliding_ids()
    {
        $this->stock(); $order = $this->order();
        try {
            (new MegaDealerOrderService($this->context))->fulfil($order->id, User::find(43), 'I am not the DMS supplier');
            $this->fail('A primary Mega Dealer cannot act as an unrelated DMS account.');
        } catch (HttpException $error) { $this->assertSame(403, $error->getStatusCode()); }
        $this->assertSame('Pending', $order->fresh()->status);
    }

    public function test_admin_fulfilment_and_dealer_verification_use_dms_stock_once()
    {
        $this->stock(); $order = $this->order(); $service = new MegaDealerOrderService($this->context);
        $request = Request::create('/mega-dealer/orders/' . $order->id . '/fulfil', 'POST', ['remarks' => 'DMS supplier dispatched']);
        $request->setUserResolver(function () { return User::find(50); });
        $response = (new ReceivedDealerOrderController)->fulfil($request, $order->id, $this->context);
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $this->assertTrue($response->getData(true)['success']);
        $this->assertSame('For Verification', $response->getData(true)['status']);
        $this->assertSame('For Verification', $order->fresh()->status);
        $this->assertSame(8, $this->context->supplierStock(Product::find(10), $this->dmsSupplier()));
        $this->assertSame(0, $this->context->dealerStock(Product::find(10), 42));
        [$completed, $changed] = $service->complete($order->id, User::find(42), 'Received from DMS Mega Dealer');
        $this->assertTrue($changed); $this->assertSame('Completed', $completed->status);
        $this->assertSame(8, $this->context->supplierStock(Product::find(10), $this->dmsSupplier()));
        $this->assertSame(5, $this->context->dealerStock(Product::find(10), 43));
        $this->assertSame(3, $this->context->dealerStock(Product::find(10), 42));
        [, $changedAgain] = $service->complete($order->id, User::find(42), 'Replay');
        $this->assertFalse($changedAgain);
        $this->assertSame(3, $this->context->dealerStock(Product::find(10), 42));
    }

    public function test_dms_fulfilment_cannot_use_stock_owned_by_a_colliding_primary_user()
    {
        $this->order(['dealer_id' => 43, 'mega_dealer_id' => null, 'mega_dealer_source' => null,
            'ad_id' => 200, 'qty' => 100, 'status' => 'Completed']);
        $order = $this->order();
        try {
            (new MegaDealerOrderService($this->context))->fulfil($order->id, User::find(50), 'No DMS stock exists');
            $this->fail('DMS fulfilment must not spend primary stock with the same numeric user ID.');
        } catch (ValidationException $error) { $this->assertArrayHasKey('stock', $error->errors()); }
        $this->assertSame('Pending', $order->fresh()->status);
        $this->assertSame(100, $this->context->dealerStock(Product::find(10), 43));
        $this->assertSame(0, $this->context->supplierStock(Product::find(10), $this->dmsSupplier()));
    }

    public function test_offline_dms_order_replays_with_its_original_source_and_rejects_source_reuse()
    {
        $first = $this->offline()->getData(true);
        $again = $this->offline()->getData(true);
        $this->assertTrue($first['success']); $this->assertSame($first['records'], $again['records']);
        $this->assertSame(1, OrderDetail::count());
        $order = OrderDetail::first();
        $this->assertSame('dms_prei', $order->mega_dealer_source);
        $this->assertSame('DMS Mega 200', $order->supplier_name);
        try {
            $this->offline(['supplier_source' => 'admin_crms']);
            $this->fail('A saved source cannot be replaced by a colliding primary supplier.');
        } catch (HttpException $error) { $this->assertSame(409, $error->getStatusCode()); }
        $this->assertSame(1, OrderDetail::count());
    }

    public function test_legacy_offline_primary_order_replays_when_default_source_becomes_explicit()
    {
        $first = $this->offline(['supplier_source' => null])->getData(true);
        $again = $this->offline(['supplier_source' => 'admin_crms'])->getData(true);
        $this->assertTrue($first['success']); $this->assertSame($first['records'], $again['records']);
        $this->assertSame(1, OrderDetail::count());
        $this->assertSame('Primary Mega 200', OrderDetail::first()->supplier_name);
    }
}

class DmsSupplierControllerRequest extends Request
{
    public function validate(array $rules)
    {
        return Container::getInstance()->make('validator')->make($this->all(), $rules)->validate();
    }
}

class DmsSupplierResponseFactory
{
    public function json($data = [], $status = 200, array $headers = [], $options = 0)
    {
        return new JsonResponse($data, $status, $headers, $options);
    }
}

class DmsSupplierTestAuth
{
    private $user;
    public function __construct($user) { $this->user = $user; }
    public function user() { return $this->user; }
    public function id() { return optional($this->user)->id; }
}

class DmsSupplierTestMailer
{
    public $sent = [];
    private $recipient;
    public function to($recipient) { $this->recipient = $recipient; return $this; }
    public function send($mail) { $this->sent[] = ['to' => $this->recipient, 'mail' => $mail]; }
}
