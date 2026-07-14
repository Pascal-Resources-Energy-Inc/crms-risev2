<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', 'HomeController@index')->name('home');
Route::get('/dashboard', 'HomeController@index')->name('dashboard');
Route::get('/home', 'HomeController@index')->name('home');
Route::get('/notification', 'NotificationController@index')->name('notification');
Route::post('/notifications/{id}/mark-viewed', 'NotificationController@markAsViewed')->name('notifications.markViewed');
Route::post('/notifications/mark-all-read', 'NotificationController@markAllAsRead')->name('notifications.markAllRead');
Route::get('/notifications/{id}', 'NotificationController@show')->name('notifications.show');
Route::get('voucher', 'VoucherController@index')->name('voucher');
// Route::post('/ad-orders/{id}/complete', 'HomeController@markCompleted')
//     ->name('ad.orders.complete');
Route::post('/ad-orders/{id}/complete', ['uses' => 'HomeController@markCompleted'
]);
Route::post('/ad-orders/{id}/cancel', ['uses' => 'HomeController@cancelPendingOrder'
]);

Route::get('/points-history', 'PointsHistoryController@index')->name('points.history');


Route::get('/chat', function () {
    return view('chat');
})->name('chat');

Auth::routes();

Route::get('user-profile','UserController@view');

Route::get('history','HistoryController@index');
Route::get('account','AccountController@index');
Route::patch('account','AccountController@update')->name('account.update');

Route::get('/storelocation', 'HomeController@storelocation')->name('storelocation');
Route::get('/api/locations-map', 'HomeController@getLocationsForMap')->name('locations.map');
Route::get('/api/location-details/{id}/{type}', 'HomeController@getLocationDetails')->name('location.details');
Route::get('/home/monthly-data', 'HomeController@getMonthlyDataAjax')->name('home.monthly-data');
Route::get('/home/chart-data', 'HomeController@getChartDataAjax')->name('home.chart-data');

Route::get('/products', 'ProductController@index')->name('products.index');
Route::get('/products/stocks', 'ProductController@stockSummary')->name('products.stocks');
Route::get('/stock-inventory', 'ProductController@dealerStockInventory')->name('dealer.stock.inventory');
Route::get('/stock-inventory/{product}/transactions', 'ProductController@dealerStockTransactions')->name('dealer.stock.transactions');
Route::post('/stock-inventory/request', 'DealerStockRequestController@store')->name('dealer.stock.requests.store');
// Route::get('/admin/stock-requests', 'DealerStockRequestController@adminIndex')->name('admin.stock.requests');
// Route::post('/admin/stock-requests/{stockRequest}/approve', 'DealerStockRequestController@approve')->name('admin.stock.requests.approve');
// Route::post('/admin/stock-requests/{stockRequest}/reject', 'DealerStockRequestController@reject')->name('admin.stock.requests.reject');

Route::get('/about', 'HomeController@about')->name('about');

Route::get('/cart', 'CartController@index')->name('cart');
Route::get('/place_order', 'TransactionController@index')->name('place_order');
// Route::post('/transactions/store', 'TransactionController@store')->name('transactions.store');
Route::get('get-user/{id}', 'CustomerController@getUser');

// Client transactions
Route::post('/transactions/store', 'TransactionController@store')->name('transactions.store');

// AD transactions
Route::post('/ad-transactions/store', 'TransactionController@adStore')->name('ad.transactions.store');


Route::get('/transactions','TransactionController@index')->name('transactions');
Route::delete('/transactions/{id}', 'TransactionController@destroy')->name('transactions.destroy');
Route::post('/transactions/bulk-delete', 'TransactionController@bulkDelete')->name('transactions.bulkDelete');
Route::post('/store-transaction','TransactionController@store')->name('new-transaction');
Route::post('/store-transaction-admin','TransactionController@storeAdmin')->name('new-transaction');

Route::get('/redeem', 'RedeemedHistoryController@index')->middleware('auth')->name('redeem');
Route::post('/redeem-reward', 'RedeemedHistoryController@redeemReward')->middleware('auth')->name('redeem.reward');

Route::get('/redemption-success', function () { return view('redemption-success'); });

// Voucher Routes
Route::get('/voucher', 'VoucherController@index')->name('voucher');

// Settlement Routes
Route::get('/settlement', 'SettlementController@index')->name('settlement');
Route::get('/settlement/{id}', 'SettlementController@index')->name('settlement.index');
Route::post('/settlement/{id}/submit', 'SettlementController@submit')->name('settlement.submit');

// Reward Routes
Route::get('/rewards', 'RewardController@index')->name('rewards');
Route::post('/rewards', 'RewardController@store')->name('rewards.store');


// User Sync Route for offline mode
Route::get('/api/get-users', 'UserController@getUsers')->name('api.users');
Route::get('/api/get-dealers', 'DealerController@getDealers')->name('api.dealers');
Route::get('/api/get-clients', 'ClientController@getClients')->name('api.clients');
Route::get('/api/get-transactions', 'TransactionController@getTransactions');
Route::get('/api/get-stoves', 'StoveController@getStoves');
Route::get('/api/get-items', 'ItemController@getItems');

Route::post('/api/transactions/store', 'TransactionController@storeApi');

Route::get('/api/test-password-exists', 'UserController@testPasswordExists');



