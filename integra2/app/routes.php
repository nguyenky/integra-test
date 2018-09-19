<?php
Route::get('/public/reports', 'HomeController@reports');
Route::get('/public', 'HomeController@orders');
Route::get('/', 'UserController@login');
// Route::get('/',function(){
//     phpinfo();
// });
Route::get('/users/login', 'UserController@login');
Route::get('/users/logout', 'UserController@logout');
Route::get('/users/nav', 'UserController@nav');

Route::get('/ebay/download_cost_weight', 'EbayController@downloadCostWeight');
Route::get('/amazon/download_cost_weight', 'AmazonController@downloadCostWeight');
Route::get('/products/download_product_linking', 'ProductController@downloadProductLinking');

Route::post('/paypal_ipn', 'PaypalIpnController@processIpn');
Route::get('/paypal_ipn', 'PaypalIpnController@processIpn');

Route::get('/orders/order_invoice/{orderId}', 'OrderController@orderInvoice');
Route::get('/orders/return_invoice/{orderId}', 'OrderController@returnInvoice');


Route::group(array('prefix' => 'api'), function()
{
    Route::get('orders/search_by_email', 'OrderController@searchByEmail');
    Route::get('orders/search/{keyword}', 'OrderController@getSearchDetailOrder');

    Route::put('health/settings', 'HealthController@saveSettings');
    Route::get('health/settings', 'HealthController@loadSettings');

    Route::get('health/ebay_monitor', 'HealthController@ebayMonitor');
    Route::get('health/amazon_scraper', 'HealthController@amazonScraper');
    Route::get('health/ebay_inventory', 'HealthController@ebayInventory');
    Route::get('health/imc_bulk_order_morning', 'HealthController@imcBulkOrderMorning');
    Route::get('health/imc_bulk_order_noon', 'HealthController@imcBulkOrderNoon');
    Route::get('health/ssf_bulk_order', 'HealthController@ssfBulkOrder');
    Route::get('health/ebay_api_call_counters', 'HealthController@ebay_api_call_counters');

    Route::get('health/ebay_api_call_counters/detail', 'HealthController@countersDetail');
    
    Route::post('ebay/raw_preview_v2', 'EbayController@rawPreviewV2');
    Route::get('ebay/preview_v2/{id}', 'EbayController@previewV2');
    Route::post('ebay/upgrade_template/{id}', 'EbayController@upgradeTemplate');
    Route::get('ebay/graph', 'EbayController@graph');
    Route::post('ebay/reports', 'EbayController@reports');
    Route::post('ebay-monitors/migrate-new-vs-our', 'EbayController@migrateNewVsOur');
    Route::get('amazon/graph', 'AmazonController@graph');

    Route::get('messages/graph', 'MessageController@graph');
    Route::get('messages/table', 'MessageController@table');
    Route::get('messages/replies', 'MessageController@replies');
    Route::get('messages/flagged', 'MessageController@flagged');
    Route::get('messages/compositions', 'MessageController@compositions');

    Route::get('google_feed/graph', 'GoogleFeedController@graph');
    Route::post('google_feed/upload', 'GoogleFeedController@upload');
    Route::put('google_feed/update/{id}', 'GoogleFeedController@update');
    Route::post('google_feed/update_multiple', 'GoogleFeedController@updateMultiple');
    Route::delete('google_feed/destroy/{id}', 'GoogleFeedController@destroy');
    Route::post('google_feed/destroy_multiple', 'GoogleFeedController@destroyMultiple');
    Route::get('google_feed/search/{mpn}', 'GoogleFeedController@search');
    Route::post('google_feed/generate', 'GoogleFeedController@generate');

    Route::get('acl', 'UserController@listAcl');
    Route::put('acl', 'UserController@updateAcl');

    Route::get('users', 'UserController@listUsers');
    Route::put('users/{id}', 'UserController@updateUser');
    Route::delete('users/{id}', 'UserController@destroyUser');

    Route::post('reports/sku_weekly', 'OrderController@skuWeeklyQueue');
    Route::get('reports/sku_weekly', 'OrderController@skuWeeklyList');

    Route::get('orders/history/{orderId}', 'OrderController@history');
    Route::post('orders/history/{orderId}', 'OrderController@addHistory');
    Route::post('orders/search', 'OrderController@search');
    Route::post('orders/searchInDetail', 'OrderController@searchInOrderDetail');
    Route::post('orders/search_ship_list', 'OrderController@searchShipList');
    Route::post('orders/download_ship_list', 'OrderController@downloadShipList');
    Route::post('orders', 'OrderController@store');
    Route::put('orders/fulfilment', 'OrderController@updateFulfilment');
    Route::put('orders/status', 'OrderController@updateStatus');
    Route::put('orders/etd', 'OrderController@updateEtd');
    Route::put('orders/bulk_etd', 'OrderController@bulkEtd');
    Route::put('orders/bulk_dispatch', 'OrderController@bulkDispatch');
    Route::post('customer-services/orders', 'OrderController@customerServiceCreateOrder');

    Route::get('orders/search_record_num/{recordNum}', 'OrderController@searchRecordNum');
    Route::get('orders/sales_graph/{store}', 'OrderController@salesGraph');
    Route::get('orders/ship_graph', 'OrderController@shipGraph');
    Route::get('orders/realtimeShip', 'OrderController@getActualShipGraph');
    Route::get('orders/status_graph', 'OrderController@statusGraph');
    Route::get('orders/status_graph_orders', 'OrderController@statusGraphOrders');
    Route::put('orders/link_supplier_order', 'OrderController@linkSupplierOrder');
    Route::post('orders/download_list', 'OrderController@downloadList');
    Route::post('orders/update_tracking', 'OrderController@updateTracking');
    Route::post('orders/send_email', 'OrderController@sendEmail');
    Route::post('orders/validate', function() { return file_get_contents("http://integra.eocenterprise.com/validate.php?sales_id=" . Input::get('id') . '&user=' . Cookie::get('user')); });
    Route::get('orders/{id}', 'OrderController@show');

    Route::get('shipments/create/{id}', 'ShipmentController@create');

    Route::get('products/kits', 'ProductController@listKits');
    Route::post('products/kits/upload', 'ProductController@uploadKits');
    Route::put('products/kits/{id}', 'ProductController@updateKit');
    Route::delete('products/kits/{id}', 'ProductController@destroyKit');
    Route::get('products/skus/search', 'ProductController@getSkus');
    Route::get('products/sale-records/search', 'OrderController@getSaleRecords');
    Route::get('products/asin/search', 'OrderController@getItemIDAsin');

    Route::get('amazon/monitor', 'AmazonController@listMonitor');
    Route::get('amazon/monitor/{page}', 'AmazonController@listMonitor');
    Route::put('amazon/monitor/{id}/ack', 'AmazonController@ackMonitor');
    Route::post('amazon/change_price/{asin}', 'AmazonController@changePrice');

    Route::get('ebay/monitor', 'EbayController@listMonitor');
    Route::get('ebay/monitor/{page}', 'EbayController@listMonitor');
    Route::put('ebay/monitor/{id}/ack', 'EbayController@ackMonitor');
    Route::delete('ebay/monitor/{id}', 'EbayController@unmonitor');
    Route::get('ebay/download_monitor', 'EbayController@downloadMonitor');
    Route::post('ebay/upload_monitor', 'EbayController@uploadMonitor');

    Route::get('ebay/part_types/{keyword}', 'EbayController@searchPartType');
    Route::get('ebay/list_makes', 'EbayController@listMakes');
    Route::get('ebay/kit_hunter', 'EbayController@listKitHunter');
    Route::get('ebay/kit_hunter/{id}/{page}', 'EbayController@viewKitHunter');
    Route::delete('ebay/kit_hunter/{sku}', 'EbayController@deleteKit');
    Route::put('ebay/kit_hunter/{sku}/publish', 'EbayController@publishKit');
    Route::put('ebay/kit_hunter/0', 'EbayController@queueKitHunter');

    Route::get('products/download_inventory', 'ProductController@downloadInventory');
    Route::get('products/download_inventory2', 'ProductController@downloadInventory2');
    Route::post('products/upload_inventory', 'ProductController@uploadInventory');
    Route::post('orders/upload_bulk_link', 'OrderController@uploadBulkLink');
    Route::delete('products/images/{name}', 'ProductController@deleteImage');
    Route::post('products/images', 'ProductController@storeImage');
    Route::post('products/brand_image', 'ProductController@storeBrandImage');
    Route::put('products/images/set_primary', 'ProductController@setPrimaryImage');
    Route::get('products/images/{mpn}', 'ProductController@listImages');
    Route::get('products/lookup/{mpn}', 'ProductController@lookup');
    Route::POST('products/lookup', 'ProductController@lookupWithFile');
    Route::get('products/lookup-progress', 'ProductController@lookupProgress');
    Route::get('products/delete-lookup-progress/{id}', 'ProductController@deleteProductLookup');
    Route::put('products/calc', 'ProductController@calc');
    Route::post('products/new_dupe', 'ProductController@dupeProduct');
    Route::get('products/amazon/search', 'ProductController@amazonSearch');
    Route::get('amazon/queue_list', 'AmazonController@queueList');
    Route::post('products/amazon/list_by_asin', 'ProductController@amazonListByAsin');
    Route::get('products/export_price/{mpn}', 'ProductController@getExportPrice');
    Route::get('amazon/monitor_settings/{q}', 'AmazonController@monitorSettings');
    Route::put('amazon/monitor_settings/{asin}', 'AmazonController@updateMonitor');

    Route::put('warehouses/upsert_sku', 'WarehouseController@upsertSku');
    Route::get('products/compatibilities/{sku}', 'ProductController@compatibilities');
    Route::get('products/sales/{sku}', 'ProductController@sales');
    Route::get('products/qty_required/{sku}', 'ProductController@qtyRequired');
    Route::get('products/pss/{sku}', 'ProductController@pssInfo');
    Route::get('products/dedupe/{sku}', 'ProductController@dedupe');
    Route::get('products/expand_kit/{sku}', 'ProductController@expandKit');
    Route::get('init-create-order-data', 'OrderController@initDataForm');

    Route::get('saved_addresses', 'SavedAddressController@index');
    Route::get('supplier_invoices/item_quantities/{invoiceNum}', 'SupplierInvoiceController@itemQuantities');

    Route::get('warehouses/map/{id}', 'WarehouseController@map');
    Route::post('warehouses/recount', 'WarehouseController@recount');
    Route::post('warehouses/relocate', 'WarehouseController@relocate');
    Route::put('warehouses/save_codes', 'WarehouseController@saveCodes');
    Route::post('warehouses/new_stock', 'WarehouseController@newStock');
    Route::post('warehouses/new_picklist', 'WarehouseController@newPicklist');
    Route::post('warehouses/confirm_picklist', 'WarehouseController@confirmPicklist');
    Route::get('warehouses/unfinished_picklist', 'WarehouseController@unfinishedPicklist');
    Route::post('warehouses/resume_picklist/{id}', 'WarehouseController@resumePicklist');
    Route::get('warehouses/find_bin/{id}/{code}', 'WarehouseController@findBin');
    Route::get('warehouses/supplier', 'WarehouseController@getSupplier');
    Route::post('warehouses/store', 'WarehouseController@store');
    Route::put('warehouses/update', 'WarehouseController@update');

    Route::post('ebay/generate_versions', 'EbayController@generateVersions');
    Route::post('ebay/publish', 'EbayController@publish');
    Route::get('ebay/suspend', 'EbayController@suspendList');
    Route::post('ebay/suspend/{id}', 'EbayController@suspend');
    Route::post('ebay/resume/{id}', 'EbayController@resume');

    Route::get('paypal/order/{num}', 'PaypalInvoiceController@findOrder');

    Route::resource('paypal', 'PaypalInvoiceController');
    Route::resource('products', 'ProductController');
    Route::resource('supplier_invoices', 'SupplierInvoiceController');
    Route::resource('supplier_returns', 'SupplierReturnController');

    Route::get('proxy/imc_ajax/{sku}', function($sku) { return file_get_contents("http://integra.eocenterprise.com/imc_ajax.php?sku={$sku}"); });
    Route::get('proxy/ssf_ajax/{sku}', function($sku) { return file_get_contents("http://integra.eocenterprise.com/ssf_ajax.php?sku={$sku}"); });
});

App::missing(function($exception)
{
    return Redirect::to('/');
});


Route::get('test-command',function(){


    
    // EbayQuickLookupCsvfile::truncate();
    // EbayQuickLookupPending::truncate();
    // dd('sssss');
    // $data = [
    // 'ebay_service_name'=>'demo'
    // ];
    // // EbayApiCallCounter::create(['ebay_service_name'=>'demo']);
    // dd(EbayQuickLookupPending::create(['mpn'=>'test','status'=>0,'csv_id'=>1]));
    // Artisan::call('quick-lookup');
});

Route::get('test-lookup','ProductController@lookupV2');

