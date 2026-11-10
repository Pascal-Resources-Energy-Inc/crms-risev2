<?php

use App\MegaDealer;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\HomeController;
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
use Illuminate\Validation\Factory;
use Illuminate\Validation\DatabasePresenceVerifier;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Isolated in-memory databases; never connects to application data. */
class MegaDealerSupplierTest extends TestCase
{
    private $db;
    private $context;
    private $mailer;

    protected function setUp()
    {
        $app = new Container; Container::setInstance($app); Facade::clearResolvedInstances(); Facade::setFacadeApplication($app);
        $app->instance('config', new Repository(['database.default' => 'default']));
        $this->db = new Capsule($app);
        foreach (['default', 'dms'] as $connection) $this->db->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], $connection);
        $this->db->getDatabaseManager()->setDefaultConnection('default'); $this->db->setAsGlobal(); $this->db->bootEloquent();
        $app->instance('db', $this->db->getDatabaseManager());
        $app->bind('db.schema', function () { return $this->db->connection()->getSchemaBuilder(); });
        $validator = new Factory(new Translator(new ArrayLoader, 'en'), $app);
        $validator->setPresenceVerifier(new DatabasePresenceVerifier($this->db->getDatabaseManager()));
        $app->instance('validator', $validator);
        $app->instance(\Illuminate\Contracts\Routing\ResponseFactory::class, new MegaSupplierResponseFactory);
        $this->mailer = new MegaSupplierTestMailer;
        $app->instance('mailer', $this->mailer);
        $schema = $this->db->connection()->getSchemaBuilder();
        $schema->create('users', function ($t) { $t->increments('id'); $t->string('name'); $t->string('email'); $t->string('role'); });
        foreach (['dealers', 'mega_dealers', 'center_chiefs'] as $table) $schema->create($table, function ($t) {
            $t->increments('id'); $t->integer('user_id'); $t->string('name'); $t->string('area'); $t->string('status');
            $t->string('number')->nullable(); $t->string('address')->nullable(); $t->float('latitude')->nullable(); $t->float('longitude')->nullable(); $t->softDeletes();
        });
        // Deployed order_details has no product_id; all stock tests exercise SKU/name fallback.
        $schema->create('order_details', function ($t) {
            $t->increments('id'); $t->integer('dealer_id'); $t->integer('ad_id')->nullable(); $t->string('transaction_id')->nullable();
            $t->string('item'); $t->string('sku')->nullable(); $t->integer('qty'); $t->decimal('price', 12, 2)->default(0);
            $t->decimal('points_dealer', 12, 2)->default(0); $t->string('item_description')->nullable(); $t->string('ad_address')->nullable();
            $t->string('date')->nullable(); $t->integer('created_by')->nullable(); $t->string('payment_method')->nullable(); $t->string('delivery_type')->nullable(); $t->decimal('delivery_fee', 12, 2)->default(0);
            $t->string('status'); $t->string('remarks')->nullable(); $t->timestamp('completed_at')->nullable(); $t->timestamps();
        });
        require_once __DIR__ . '/../../database/migrations/2026_10_28_000002_add_mega_dealer_supplier_to_order_details.php';
        (new AddMegaDealerSupplierToOrderDetails)->up();
        $schema->create('transaction_details', function ($t) {
            $t->increments('id'); $t->integer('dealer_id'); $t->string('item'); $t->string('sku')->nullable(); $t->integer('qty'); $t->string('status');
            $t->string('transaction_id')->nullable(); $t->string('item_description')->nullable(); $t->decimal('price', 12, 2)->default(0);
            $t->integer('client_id')->nullable(); $t->string('client_address')->nullable(); $t->boolean('is_guest')->default(false);
            $t->integer('created_by')->nullable(); $t->string('date')->nullable(); $t->string('payment_method')->nullable();
            $t->decimal('points_dealer', 12, 2)->default(0); $t->decimal('points_client', 12, 2)->default(0); $t->timestamps();
        });
        $dms = $this->db->connection('dms')->getSchemaBuilder();
        $dms->create('users', function ($t) { $t->increments('id'); $t->string('name'); $t->string('email'); $t->string('role'); });
        $dms->create('area_distributors', function ($t) { $t->increments('id'); $t->integer('user_id'); $t->string('name'); $t->string('address')->nullable(); $t->string('number')->nullable(); $t->float('latitude')->nullable(); $t->float('longitude')->nullable(); $t->softDeletes(); });
        $dms->create('ad_areas', function ($t) { $t->increments('id'); $t->integer('ad_id'); $t->string('area_name'); $t->string('user_role'); $t->softDeletes(); });
        $dms->create('products', function ($t) {
            $t->increments('id'); $t->integer('ad_user_id'); $t->string('product_name'); $t->string('sku'); $t->string('status');
            $t->string('description')->nullable(); $t->float('dealer_points')->default(1);
            $t->decimal('price', 12, 2); $t->decimal('dealer_price', 12, 2); $t->decimal('mega_dealer_price', 12, 2); $t->decimal('srp_price', 12, 2);
        });
        foreach ([42 => 'Dealer', 43 => 'Mega Dealer', 44 => 'center_chief', 45 => 'mega_dealer', 46 => 'Mega Dealer', 47 => 'Mega Dealer', 48 => 'Dealer', 49 => 'Dealer', 50 => 'Admin', 51 => 'Client'] as $id => $role) {
            $this->db->table('users')->insert(['id' => $id, 'name' => 'Example ' . $id, 'email' => $id . '@example.test', 'role' => $role]);
        }
        $this->db->table('dealers')->insert(['id' => 142, 'user_id' => 42, 'name' => 'Buyer', 'area' => ' North Zone ', 'status' => 'Active']);
        $this->db->table('center_chiefs')->insert(['id' => 144, 'user_id' => 44, 'name' => 'Chief', 'area' => 'North Zone', 'status' => 'Active']);
        foreach ([200 => [43, 'north  zone', 'Active', null], 201 => [45, 'South', 'Active', null], 202 => [46, 'North Zone', 'Inactive', null], 203 => [47, 'North Zone', 'Active', '2026-10-01'], 204 => [48, 'North Zone', 'Active', null]] as $id => $profile) {
            $this->db->table('mega_dealers')->insert(['id' => $id, 'user_id' => $profile[0], 'name' => 'Mega ' . $id, 'area' => $profile[1], 'status' => $profile[2], 'deleted_at' => $profile[3]]);
        }
        $this->db->connection('dms')->table('area_distributors')->insert(['id' => 200, 'user_id' => 900, 'name' => 'North AD']);
        $this->db->connection('dms')->table('users')->insert(['id' => 900, 'name' => 'North AD User', 'email' => 'ad@example.test', 'role' => 'Area Distributor']);
        $this->db->connection('dms')->table('ad_areas')->insert(['ad_id' => 200, 'area_name' => 'NORTH ZONE', 'user_role' => 'Area Distributor']);
        $this->db->connection('dms')->table('products')->insert(['id' => 10, 'ad_user_id' => 900, 'product_name' => '330g Refill', 'sku' => 'REFILL', 'status' => 'Activate', 'price' => 70, 'dealer_price' => 50, 'mega_dealer_price' => 45, 'srp_price' => 75]);
        $this->context = new OfflinePortalContext;
    }

    protected function tearDown()
    {
        foreach (['default', 'dms'] as $connection) $this->db->getDatabaseManager()->disconnect($connection);
        Facade::clearResolvedInstances(); Facade::setFacadeApplication(null); Container::setInstance(null);
    }

    private function order($overrides = [])
    {
        return OrderDetail::forceCreate(array_replace(['dealer_id' => 42, 'mega_dealer_id' => 200, 'ad_id' => null,
            'transaction_id' => 'PRORD-TEST', 'item' => '330g Refill', 'sku' => 'REFILL', 'qty' => 4, 'price' => 50, 'status' => 'Pending'], $overrides));
    }

    private function supplierStock()
    {
        $this->order(['dealer_id' => 43, 'mega_dealer_id' => null, 'ad_id' => 200, 'qty' => 10, 'status' => 'Completed']);
        $this->db->table('transaction_details')->insert(['dealer_id' => 43, 'item' => '330g Refill', 'sku' => 'REFILL', 'qty' => 3, 'status' => 'Completed']);
        $this->order(['dealer_id' => 49, 'qty' => 2, 'status' => 'Completed']);
        $this->order(['dealer_id' => 49, 'qty' => 20, 'status' => 'Pending']);
        $this->order(['dealer_id' => 49, 'qty' => 20, 'status' => 'Cancelled']);
    }

    private function submitOnlineOrder($overrides = [], $actorId = 42)
    {
        Container::getInstance()->instance(\Illuminate\Contracts\Auth\Factory::class, new MegaSupplierTestAuth(User::find($actorId)));
        $request = MegaSupplierControllerRequest::create('/ad-transactions/store', 'POST', array_replace([
            'item_id' => 10, 'qty' => 3, 'supplier_type' => 'mega_dealer', 'mega_dealer_id' => 200,
            'payment_method' => 'cash', 'delivery_type' => 'pickup', 'delivery_fee' => 0,
        ], $overrides), [], [], ['HTTP_ACCEPT' => 'application/json']);
        return (new TransactionController)->adStore($request);
    }

    public function test_online_controller_saves_dealer_order_to_mega_dealer_with_actual_reference_and_correct_recipient()
    {
        $response = $this->submitOnlineOrder(['price' => 1]);
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $result = $response->getData(true); $order = OrderDetail::find($result['id']);
        $this->assertTrue($result['success']); $this->assertSame('Pending', $order->status);
        $this->assertNull($order->ad_id); $this->assertSame(200, (int) $order->mega_dealer_id);
        $this->assertSame(42, (int) $order->dealer_id); $this->assertEquals(50, $order->price);
        $this->assertSame('PRORD-' . str_pad((string) $order->id, 4, '0', STR_PAD_LEFT), $result['transaction_id']);
        $this->assertSame($result['transaction_id'], $order->transaction_id);
        $this->assertCount(1, $this->mailer->sent); $this->assertSame('43@example.test', $this->mailer->sent[0]['to']);
        $this->assertInstanceOf(MegaDealer::class, $this->mailer->sent[0]['mail']->ad);
        $this->assertSame(200, (int) $this->mailer->sent[0]['mail']->ad->id);
    }

    public function test_online_controller_rejects_foreign_area_wrong_role_and_ambiguous_suppliers_before_saving()
    {
        foreach ([[['mega_dealer_id' => 201], 42], [[], 43], [['area_distributor_id' => 200], 42]] as $case) {
            $response = $this->submitOnlineOrder($case[0], $case[1]);
            $this->assertSame(422, $response->getStatusCode(), $response->getContent());
            $this->assertFalse($response->getData(true)['success']);
        }
        $this->assertSame(0, OrderDetail::count()); $this->assertCount(0, $this->mailer->sent);
    }

    public function test_online_order_stays_successful_if_supplier_email_fails_after_saving()
    {
        Container::getInstance()->instance('log', new \Psr\Log\NullLogger);
        $this->mailer->fail = true;
        $response = $this->submitOnlineOrder(); $result = $response->getData(true);
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $this->assertTrue($result['success']); $this->assertNotEmpty($result['warning']);
        $this->assertSame(1, OrderDetail::count()); $this->assertCount(1, $this->mailer->sent);
        $this->assertSame($result['transaction_id'], OrderDetail::first()->transaction_id);
        $this->assertSame('Pending', OrderDetail::first()->status);
    }

    public function test_online_controller_keeps_the_ad_with_a_colliding_profile_id_separate()
    {
        $response = $this->submitOnlineOrder(['supplier_type' => 'area_distributor', 'mega_dealer_id' => null, 'area_distributor_id' => 200]);
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $order = OrderDetail::find($response->getData(true)['id']);
        $this->assertSame(200, (int) $order->ad_id); $this->assertNull($order->mega_dealer_id);
        $this->assertEquals(50, $order->price);
        $this->assertSame('ad@example.test', $this->mailer->sent[0]['to']);
        $this->assertInstanceOf(\App\AreaDistributor::class, $this->mailer->sent[0]['mail']->ad);
    }

    public function test_online_client_sales_cannot_resell_stock_already_supplied_to_a_dealer()
    {
        $this->supplierStock();
        $this->assertSame(5, $this->context->dealerStock(Product::find(10), 43));
        Container::getInstance()->instance(\Illuminate\Contracts\Auth\Factory::class, new MegaSupplierTestAuth(User::find(43)));
        $controller = new TransactionController;
        foreach ([6 => 'Pending', 5 => 'Completed'] as $quantity => $status) {
            $request = MegaSupplierControllerRequest::create('/transactions/store', 'POST', [
                'item_id' => 10, 'qty' => $quantity, 'customer_id' => 'guest', 'payment_method' => 'cash', 'delivery_type' => 'pickup',
            ], [], [], ['HTTP_ACCEPT' => 'application/json']);
            $response = $controller->store($request);
            $this->assertSame(200, $response->getStatusCode(), $response->getContent());
            $result = $response->getData(true);
            $this->assertTrue($result['success']); $this->assertSame($status, $result['status']);
            $sale = $this->db->table('transaction_details')->find($result['id']);
            $this->assertSame($status, $sale->status); $this->assertEquals(70, $sale->price);
            $this->assertNull($sale->client_id); $this->assertEquals(1, $sale->is_guest);
            $this->assertSame($result['transaction_id'], $sale->transaction_id);
            $this->assertSame(1, preg_match('/^TRX-\d+-[1-9]\d{3}$/', $sale->transaction_id));
            $this->assertSame($status === 'Completed' ? 0 : 5, $this->context->dealerStock(Product::find(10), 43));
        }
        $this->assertSame(0, $this->context->dealerStock(Product::find(10), 43));
        $this->assertSame(3, $this->db->table('transaction_details')->where('dealer_id', 43)->count());
    }

    public function test_home_pending_sales_use_remaining_mega_dealer_stock_after_wholesale_deliveries()
    {
        $this->supplierStock();
        $incoming = OrderDetail::where('dealer_id', 43)->where('status', 'Completed')->first();
        $ids = [];
        foreach ([6, 5] as $quantity) $ids[] = $this->db->table('transaction_details')->insertGetId([
            'dealer_id' => 43, 'item' => '330g Refill', 'sku' => 'REFILL', 'qty' => $quantity,
            'status' => 'Pending', 'created_at' => '2026-10-28 01:00:00',
        ]);
        $method = new ReflectionMethod(HomeController::class, 'completePendingClientTransactionsForOrder');
        $method->setAccessible(true); $home = new HomeController;
        $this->assertSame(1, $method->invoke($home, $incoming));
        $this->assertSame('Pending', $this->db->table('transaction_details')->find($ids[0])->status);
        $this->assertSame('Completed', $this->db->table('transaction_details')->find($ids[1])->status);
        $this->assertSame(0, $this->context->dealerStock(Product::find(10), 43));
        $this->assertSame(0, $method->invoke($home, $incoming));
    }

    public function test_only_active_same_area_mega_dealers_are_available_to_a_dealer()
    {
        $this->assertSame([200], $this->context->megaDealers(User::find(42))->pluck('id')->all());
        foreach ([43, 44, 50, 51] as $id) $this->assertCount(0, $this->context->megaDealers(User::find($id)));
        $this->db->table('dealers')->where('user_id', 42)->update(['area' => '']);
        $this->assertCount(0, $this->context->megaDealers(User::find(42)));
    }

    public function test_typed_suppliers_keep_colliding_ad_and_mega_dealer_profile_ids_separate()
    {
        $suppliers = $this->context->suppliers(User::find(42));
        $this->assertSame(['area_distributor:200', 'mega_dealer:200'], $suppliers->pluck('id')->all());
        $this->assertInstanceOf(MegaDealer::class, $this->context->resolveOrderSupplier(User::find(42), 'mega_dealer', 200));
        $this->assertInstanceOf(\App\AreaDistributor::class, $this->context->resolveOrderSupplier(User::find(42), 'area_distributor', 200));
        $this->assertNull($this->context->resolveOrderSupplier(User::find(42), 'mega_dealer', 201));
        $this->assertNull($this->context->resolveOrderSupplier(User::find(43), 'mega_dealer', 200));
        $this->assertFalse(isset($suppliers->last()['model']));
    }

    public function test_mega_dealer_catalog_uses_assigned_ad_products_and_dealer_order_prices()
    {
        $dealer = User::find(42); $supplier = $this->context->resolveOrderSupplier($dealer, 'mega_dealer', 200);
        $products = $this->context->supplierProducts($dealer, $supplier);
        $this->assertSame([10], $products->pluck('id')->all());
        $this->assertSame(50.0, $this->context->orderPrice($products->first(), $dealer));
        $this->assertSame(900, (int) $products->first()->ad_user_id);
        $this->assertCount(0, $this->context->supplierProducts($dealer, MegaDealer::find(201)));
    }

    public function test_mega_dealer_stock_subtracts_completed_outgoing_orders_but_not_pending_or_cancelled_requests()
    {
        $this->supplierStock(); $product = Product::find(10);
        $this->assertSame(5, $this->context->dealerStock($product, 43));
        [$dealer] = $this->context->stockMaps(collect([$product]), User::find(43));
        $this->assertSame(5, $dealer[10]);
    }

    public function test_dealer_buyer_can_mark_own_mega_dealer_order_completed_once()
    {
        $this->supplierStock();
        $order = $this->order();
        $otherOrder = $this->order(['transaction_id' => 'PRORD-OTHER']);
        (new MegaDealerOrderService($this->context))->fulfil($order->id, User::find(43), 'Sent to Dealer');
        $this->assertSame('For Verification', $order->fresh()->status);
        $this->assertNull($order->fresh()->completed_at);
        $this->assertSame(0, $this->context->dealerStock(Product::find(10), 42));
        Container::getInstance()->instance(\Illuminate\Contracts\Auth\Factory::class, new MegaSupplierTestAuth(User::find(42)));
        $controller = new HomeController;
        $request = Request::create('/ad-orders/' . $order->id . '/complete', 'POST', ['remarks' => 'Received from Mega Dealer']);

        $response = $controller->markCompleted($request, $order->id);

        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $this->assertTrue($response->getData(true)['success']);
        $this->assertSame('Completed', $response->getData(true)['status']);
        $this->assertSame('Completed', $order->fresh()->status);
        $this->assertSame('Received from Mega Dealer', $order->fresh()->remarks);
        $this->assertNotNull($order->fresh()->completed_at);
        $this->assertSame('Pending', $otherOrder->fresh()->status);
        $this->assertSame(1, $this->context->dealerStock(Product::find(10), 43));
        $this->assertSame(4, $this->context->dealerStock(Product::find(10), 42));

        $repeat = $controller->markCompleted(Request::create('/ad-orders/' . $order->id . '/complete', 'POST', ['remarks' => 'Repeated click']), $order->id);
        $this->assertSame(200, $repeat->getStatusCode(), $repeat->getContent());
        $this->assertTrue($repeat->getData(true)['success']);
        $this->assertSame('Received from Mega Dealer', $order->fresh()->remarks);
        $this->assertSame(1, $this->context->dealerStock(Product::find(10), 43));
        $this->assertSame(4, $this->context->dealerStock(Product::find(10), 42));
        $this->assertSame('Pending', $otherOrder->fresh()->status);
    }

    public function test_dealer_completion_endpoint_rejects_another_buyers_mega_dealer_order()
    {
        $this->supplierStock(); $order = $this->order();
        Container::getInstance()->instance(\Illuminate\Contracts\Auth\Factory::class, new MegaSupplierTestAuth(User::find(49)));
        $response = (new HomeController)->markCompleted(Request::create('/ad-orders/' . $order->id . '/complete', 'POST', ['remarks' => 'Not my order']), $order->id);
        $this->assertSame(403, $response->getStatusCode(), $response->getContent());
        $this->assertFalse($response->getData(true)['success']);
        $this->assertSame('Pending', $order->fresh()->status);
        $this->assertNull($order->fresh()->completed_at);
        $this->assertSame(5, $this->context->dealerStock(Product::find(10), 43));
    }

    public function test_dealer_completion_endpoint_requires_supplier_verification_before_completion()
    {
        $order = $this->order();
        Container::getInstance()->instance(\Illuminate\Contracts\Auth\Factory::class, new MegaSupplierTestAuth(User::find(42)));
        $response = (new HomeController)->markCompleted(Request::create('/ad-orders/' . $order->id . '/complete', 'POST', ['remarks' => 'Receive order']), $order->id);
        $this->assertSame(409, $response->getStatusCode(), $response->getContent());
        $this->assertFalse($response->getData(true)['success']);
        $this->assertSame('Pending', $order->fresh()->status);
        $this->assertNull($order->fresh()->completed_at);
        $this->assertNull($order->fresh()->remarks);
        $this->assertSame(0, $this->context->dealerStock(Product::find(10), 42));
    }

    public function test_cancelling_one_mega_dealer_card_does_not_cancel_same_time_sibling_orders()
    {
        $created = \Carbon\Carbon::now()->subMinutes(10)->format('Y-m-d H:i:s');
        $selected = $this->order(['transaction_id' => 'PRORD-SELECTED', 'created_at' => $created]);
        $sibling = $this->order(['transaction_id' => 'PRORD-SIBLING', 'created_at' => $created]);
        $otherSupplier = $this->order(['mega_dealer_id' => 201, 'created_at' => $created]);
        $adOrder = $this->order(['mega_dealer_id' => null, 'ad_id' => 200, 'created_at' => $created]);
        Container::getInstance()->instance(\Illuminate\Contracts\Auth\Factory::class, new MegaSupplierTestAuth(User::find(42)));

        $response = (new HomeController)->cancelPendingOrder(Request::create('/ad-orders/' . $selected->id . '/cancel', 'POST', ['remarks' => 'Cancel this item only']), $selected->id);

        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $this->assertTrue($response->getData(true)['success']);
        $this->assertSame('Cancelled', $selected->fresh()->status);
        $this->assertSame('Cancelled: Cancel this item only', $selected->fresh()->remarks);
        foreach ([$sibling, $otherSupplier, $adOrder] as $untouched) {
            $this->assertSame('Pending', $untouched->fresh()->status);
            $this->assertNull($untouched->fresh()->remarks);
        }
    }

    public function test_ad_cancellation_still_cancels_the_same_time_group_without_touching_mega_dealer_orders()
    {
        $created = \Carbon\Carbon::now()->subMinutes(10)->format('Y-m-d H:i:s');
        $selected = $this->order(['mega_dealer_id' => null, 'ad_id' => 200, 'created_at' => $created]);
        $sibling = $this->order(['mega_dealer_id' => null, 'ad_id' => 200, 'created_at' => $created]);
        $megaOrder = $this->order(['created_at' => $created]);
        $otherAd = $this->order(['mega_dealer_id' => null, 'ad_id' => 999, 'created_at' => $created]);
        Container::getInstance()->instance(\Illuminate\Contracts\Auth\Factory::class, new MegaSupplierTestAuth(User::find(42)));

        $response = (new HomeController)->cancelPendingOrder(Request::create('/ad-orders/' . $selected->id . '/cancel', 'POST', ['remarks' => 'Cancel AD order']), $selected->id);

        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $this->assertTrue($response->getData(true)['success']);
        foreach ([$selected, $sibling] as $cancelled) {
            $this->assertSame('Cancelled', $cancelled->fresh()->status);
            $this->assertSame('Cancelled: Cancel AD order', $cancelled->fresh()->remarks);
        }
        $this->assertSame('Pending', $megaOrder->fresh()->status);
        $this->assertSame('Pending', $otherAd->fresh()->status);
    }

    public function test_stale_pending_cancel_cannot_report_success_after_supplier_dispatches_order()
    {
        $this->supplierStock();
        $order = $this->order(['created_at' => \Carbon\Carbon::now()->subMinutes(10)]);
        Container::getInstance()->instance(\Illuminate\Contracts\Auth\Factory::class, new MegaSupplierTestAuth(User::find(42)));
        $previousDispatcher = OrderDetail::getEventDispatcher();
        $dispatcher = new \Illuminate\Events\Dispatcher(Container::getInstance());
        $dispatched = false;
        $dispatcher->listen('eloquent.retrieved: ' . OrderDetail::class, function ($loaded) use ($order, &$dispatched) {
            if ($dispatched || (int) $loaded->id !== (int) $order->id) return;
            // Simulate the supplier committing between the first read and the
            // locked pending-only query; keep the buyer's hydrated row stale.
            $dispatched = true;
            $this->db->table('order_details')->where('id', $order->id)->update(['status' => 'For Verification']);
        });
        OrderDetail::setEventDispatcher($dispatcher);
        try {
            $response = (new HomeController)->cancelPendingOrder(Request::create('/ad-orders/' . $order->id . '/cancel', 'POST'), $order->id);
        } finally {
            if ($previousDispatcher) OrderDetail::setEventDispatcher($previousDispatcher);
            else OrderDetail::unsetEventDispatcher();
        }
        $this->assertTrue($dispatched);
        $this->assertSame(409, $response->getStatusCode(), $response->getContent());
        $this->assertFalse($response->getData(true)['success']);
        $this->assertSame('For Verification', $order->fresh()->status);
        $this->assertSame(1, $this->context->dealerStock(Product::find(10), 43));
        $this->assertSame(0, $this->context->dealerStock(Product::find(10), 42));
    }

    public function test_fulfilment_reserves_real_stock_and_receipt_credits_buyer_without_double_deduction()
    {
        $this->supplierStock(); $order = $this->order(); $service = new MegaDealerOrderService($this->context);
        [$sent, $changed] = $service->fulfil($order->id, User::find(43), 'Delivered to Dealer');
        $this->assertTrue($changed); $this->assertSame('For Verification', $sent->status);
        $this->assertNull($sent->ad_id); $this->assertSame(200, (int) $sent->mega_dealer_id);
        $this->assertSame('Mega 200', $sent->supplier_name); $this->assertSame('mega_dealer', $sent->supplier_type);
        $this->assertNull($sent->completed_at);
        $this->assertSame('Delivered to Dealer', $sent->remarks);
        $this->assertSame(1, $this->context->dealerStock(Product::find(10), 43));
        $this->assertSame(0, $this->context->dealerStock(Product::find(10), 42));
        [$stocks] = $this->context->stockMaps(collect([Product::find(10)]), User::find(43));
        $this->assertSame(1, $stocks[10]);
        [$sentAgain, $sentChanged] = $service->fulfil($order->id, User::find(43), 'Repeated fulfilment');
        $this->assertFalse($sentChanged); $this->assertSame('Delivered to Dealer', $sentAgain->remarks);
        [$completed, $received] = $service->complete($order->id, User::find(42), 'Received and verified');
        $this->assertTrue($received); $this->assertSame('Completed', $completed->status);
        $this->assertNotNull($completed->completed_at);
        $this->assertSame(1, $this->context->dealerStock(Product::find(10), 43));
        $this->assertSame(4, $this->context->dealerStock(Product::find(10), 42));
        [, $changedAgain] = $service->complete($order->id, User::find(42), 'Repeated request');
        $this->assertFalse($changedAgain); $this->assertSame(1, $this->context->dealerStock(Product::find(10), 43));
        [, $sentAfterCompletion] = $service->fulfil($order->id, User::find(43), 'Late supplier replay');
        $this->assertFalse($sentAfterCompletion); $this->assertSame(4, $this->context->dealerStock(Product::find(10), 42));
    }

    public function test_fulfilment_is_blocked_without_supplier_stock()
    {
        $order = $this->order();
        try { (new MegaDealerOrderService($this->context))->fulfil($order->id, User::find(43), 'Send order'); $this->fail('Expected stock rejection'); }
        catch (ValidationException $error) { $this->assertArrayHasKey('stock', $error->errors()); }
        $this->assertSame('Pending', $order->fresh()->status);
        $this->assertSame(0, $this->context->dealerStock(Product::find(10), 42));
    }

    public function test_foreign_accounts_cannot_complete_an_order()
    {
        $order = $this->order(); $this->expectException(HttpException::class); $this->expectExceptionCode(0);
        (new MegaDealerOrderService($this->context))->complete($order->id, User::find(49), 'Unauthorized');
    }

    public function test_cancelled_orders_cannot_be_completed()
    {
        $order = $this->order(['status' => 'Cancelled']);
        try { (new MegaDealerOrderService($this->context))->complete($order->id, User::find(42), 'Retry'); $this->fail('Expected status conflict'); }
        catch (HttpException $error) { $this->assertSame(409, $error->getStatusCode()); }
    }

    public function test_buyer_cannot_fulfil_and_supplier_cannot_confirm_dealer_receipt()
    {
        $this->supplierStock(); $order = $this->order(); $service = new MegaDealerOrderService($this->context);
        try { $service->fulfil($order->id, User::find(42), 'Bypass dispatch'); $this->fail('Expected supplier authorization.'); }
        catch (HttpException $error) { $this->assertSame(403, $error->getStatusCode()); }
        $service->fulfil($order->id, User::find(43), 'Dispatched');
        try { $service->complete($order->id, User::find(43), 'Bypass receipt'); $this->fail('Expected buyer authorization.'); }
        catch (HttpException $error) { $this->assertSame(403, $error->getStatusCode()); }
        $this->assertSame('For Verification', $order->fresh()->status);
        $this->assertSame(0, $this->context->dealerStock(Product::find(10), 42));
    }

    public function test_verification_reservation_cannot_be_resold_to_mega_dealer_clients()
    {
        $this->supplierStock(); $order = $this->order();
        (new MegaDealerOrderService($this->context))->fulfil($order->id, User::find(43), 'Awaiting buyer receipt');
        Container::getInstance()->instance(\Illuminate\Contracts\Auth\Factory::class, new MegaSupplierTestAuth(User::find(43)));
        $request = MegaSupplierControllerRequest::create('/transactions/store', 'POST', [
            'item_id' => 10, 'qty' => 2, 'customer_id' => 'guest', 'payment_method' => 'cash', 'delivery_type' => 'pickup',
        ], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $response = (new TransactionController)->store($request);
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $this->assertSame('Pending', $response->getData(true)['status']);
        $this->assertSame(1, $this->context->dealerStock(Product::find(10), 43));
        $this->assertSame(0, $this->context->dealerStock(Product::find(10), 42));
    }

    public function test_home_pending_client_sales_cannot_consume_stock_reserved_for_dealer_verification()
    {
        $this->supplierStock(); $order = $this->order();
        (new MegaDealerOrderService($this->context))->fulfil($order->id, User::find(43), 'Awaiting verification');
        $incoming = OrderDetail::where('dealer_id', 43)->where('status', 'Completed')->first();
        $pending = $this->db->table('transaction_details')->insertGetId([
            'dealer_id' => 43, 'item' => '330g Refill', 'sku' => 'REFILL', 'qty' => 2,
            'status' => 'Pending', 'created_at' => '2026-10-28 01:00:00',
        ]);
        $method = new ReflectionMethod(HomeController::class, 'completePendingClientTransactionsForOrder');
        $method->setAccessible(true);
        $this->assertSame(0, $method->invoke(new HomeController, $incoming));
        $this->assertSame('Pending', $this->db->table('transaction_details')->find($pending)->status);
        $this->assertSame(1, $this->context->dealerStock(Product::find(10), 43));
    }

    public function test_dealer_cannot_cancel_a_fulfilled_order_awaiting_verification()
    {
        $this->supplierStock(); $order = $this->order();
        (new MegaDealerOrderService($this->context))->fulfil($order->id, User::find(43), 'Dispatched');
        Container::getInstance()->instance(\Illuminate\Contracts\Auth\Factory::class, new MegaSupplierTestAuth(User::find(42)));
        $response = (new HomeController)->cancelPendingOrder(Request::create('/ad-orders/' . $order->id . '/cancel', 'POST'), $order->id);
        $this->assertSame(409, $response->getStatusCode(), $response->getContent());
        $this->assertSame('For Verification', $order->fresh()->status);
        $this->assertSame(1, $this->context->dealerStock(Product::find(10), 43));
    }

    public function test_migration_is_repeatable_and_preserves_existing_ad_supplier()
    {
        $order = $this->order(['mega_dealer_id' => null, 'ad_id' => 200]);
        (new AddMegaDealerSupplierToOrderDetails)->up();
        $this->assertSame('area_distributor', $order->fresh()->supplier_type);
        $this->assertSame('North AD', $order->fresh()->supplier_name);
        $this->assertSame(200, (int) $order->fresh()->ad_id);
    }
}

class MegaSupplierControllerRequest extends Request
{
    public function validate(array $rules)
    {
        return Container::getInstance()->make('validator')->make($this->all(), $rules)->validate();
    }
}

class MegaSupplierResponseFactory
{
    public function json($data = [], $status = 200, array $headers = [], $options = 0)
    {
        return new JsonResponse($data, $status, $headers, $options);
    }
}

class MegaSupplierTestAuth
{
    private $user;

    public function __construct($user) { $this->user = $user; }
    public function user() { return $this->user; }
    public function id() { return optional($this->user)->id; }
}

class MegaSupplierTestMailer
{
    public $sent = [];
    public $fail = false;
    private $recipient;

    public function to($recipient) { $this->recipient = $recipient; return $this; }
    public function send($mail)
    {
        $this->sent[] = ['to' => $this->recipient, 'mail' => $mail];
        if ($this->fail) throw new RuntimeException('Synthetic supplier email outage');
    }
}
