var integraApp = angular.module('integraApp', [
  	'ngRoute',
    'ngFileUpload',
  	'ui.bootstrap',
  	'app.controllers',
  	'app.main',
  	'app.navigation',
  	'app.smartui',
    'ngTable',
    'xeditable',
    'ui.select',
    'angular-loading-bar',
    'truncate',
    'chart.js'
]);
integraApp.factory('_', function() {
        return window._; // assumes underscore has already been loaded on the page
    }); 

integraApp.config(['$routeProvider', '$provide', '$windowProvider', function($routeProvider, $provide, $windowProvider) {
    var $window = $windowProvider.$get();
    
    var sink = 'views/noaccess.html';

    $routeProvider
        .when('/products/images',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/products/images') ? 'views/products/images.html' : sink
                }
            })
        .when('/shipping', {
            templateUrl: 'views/public/reports/shipping.html'
        })
        .when('/ebay/download_cost_weight',
            {
                redirectTo: function() {
                    window.location = "/ebay/download_cost_weight";
                }
            })
        .when('/amazon/download_cost_weight',
            {
                redirectTo: function() {
                    window.location = "/amazon/download_cost_weight";
                }
            })
        .when('/products/download_product_linking',
            {
                redirectTo: function() {
                    window.location = "/products/download_product_linking";
                }
            })
        .when('/pos',
            {
                redirectTo: function() {
                    window.open('http://pos.eocenterprise.com/','poswindow','toolbar=0,menubar=0,width=480,height=640');
                }
            })
        .when('/amazon/new_listing',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/amazon/new_listing') ? 'views/amazon/new_listing.html' : sink
                }
            })
        .when('/google_feed/update',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/google_feed/update') ? 'views/google_feed/update.html' : sink
                }
            })
        .when('/google_feed/generate',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/google_feed/generate') ? 'views/google_feed/generate.html' : sink
                }
            })
        .when('/ebay/speed_lister',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/ebay/speed_lister') ? 'views/ebay/speed_lister.html' : sink
                },
                controller: 'EbaySpeedListerController'
            })
        .when('/ebay/upgrade_template',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/ebay/upgrade_template') ? 'views/ebay/upgrade_template.html' : sink
                }
            })
        .when('/products/kits',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/products/kits') ? 'views/products/kits.html' : sink
                },
                controller: 'ProductKitController'
            })
        .when('/ebay/calc',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/ebay/calc') ? 'views/ebay/calc.html' : sink
                }
            })
        .when('/ebay/suspend',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/ebay/suspend') ? 'views/ebay/suspend.html' : sink
                }
            })
        .when('/ebay/download_monitor',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/ebay/download_monitor') ? 'views/ebay/download_monitor.html' : sink
                },
                controller: 'MonitorDownloadController'
            })
        .when('/amazon/calc',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/amazon/calc') ? 'views/amazon/calc.html' : sink
                }
            })
        .when('/amazon/monitor',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/amazon/monitor') ? 'views/amazon/monitor.html' : sink
                },
                controller: 'AmazonMonitorController'
            })
        .when('/amazon/monitor_settings',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/amazon/monitor_settings') ? 'views/amazon/monitor_settings.html' : sink
                }
            })
        .when('/products/price_calc',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/products/price_calc') ? 'views/products/price_calc.html' : sink
                }
            })
        .when('/ebay/kit_hunter',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/ebay/kit_hunter') ? 'views/ebay/kit_hunter.html' : sink
                },
                controller: 'EbayKitHunterController'
            })
        .when('/ebay/kit_hunter/:id',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/ebay/kit_hunter') ? 'views/ebay/kit_hunter_results.html' : sink
                },
                controller: 'EbayKitHunterResultsController'
            })
        .when('/ebay/monitor',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/ebay/monitor') ? 'views/ebay/monitor.html' : sink
                },
                controller: 'EbayMonitorController'
            })
        .when('/ebay-monitors/migration', {
            templateUrl: 'views/ebay/ebay_monitor_migration.html'
        })
        .when('/products/list',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/products/list') ? 'views/products/list.html' : sink
                },
                controller: 'ProductListController'
            })
        .when('/products/view/:sku',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/products/list') ? 'views/products/view.html' : sink
                },
                controller: 'ProductViewController'
            })
        .when('/products/inventory',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/products/inventory') ? 'views/products/inventory.html' : sink
                },
                controller: 'InventoryController'
            })
        .when('/products/warehouse_map',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/products/warehouse_map') ? 'views/products/warehouse_map.html' : sink
                },
                controller: 'WarehouseMapController'
            })
        .when('/products/quick_lookup',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/products/quick_lookup') ? 'views/products/quick_lookup.html' : sink
                },
                controller: 'ProductQuickLookupController'
            })
        .when('/products/search_amazon',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/products/search_amazon') ? 'views/products/search_amazon.html' : sink
                },
                controller: 'ProductSearchAmazonController'
            })
        .when('/products/new_dupe',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/products/new_dupe') ? 'views/products/new_dupe.html' : sink
                },
                controller: 'ProductNewDupeController'
            })
        .when('/products/new_stock',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/products/new_stock') ? 'views/products/new_stock.html' : sink
                },
                controller: 'ProductNewStockController'
            })
        .when('/products/new_picklist',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/products/new_picklist') ? 'views/products/new_picklist.html' : sink
                },
                controller: 'ProductNewPicklistController'
            })
        .when('/supplier_returns/create',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/supplier_returns/create') ? 'views/supplier_returns/create.html' : sink
                },
                controller: 'SupplierReturnCreateController'
            })
        .when('/supplier_returns/list',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/supplier_returns/list') ? 'views/supplier_returns/list.html' : sink
                },
                controller: 'SupplierReturnListController'
            })
        .when('/supplier_returns/view/:id',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/supplier_returns/list') ? 'views/supplier_returns/view.html' : sink
                },
                controller: 'SupplierReturnViewController'
            })
        .when('/supplier_invoices/list',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/supplier_invoices/list') ? 'views/supplier_invoices/list.html' : sink
                },
                controller: 'SupplierInvoiceListController'
            })
        .when('/supplier_invoices/view/:id',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/supplier_invoices/list') ? 'views/supplier_invoices/view.html' : sink
                },
                controller: 'SupplierInvoiceViewController'
            })
        .when('/orders/list',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/orders/list') ? 'views/orders/list.html' : sink
                }
            })
        .when('/orders/search/:keyword', {
            templateUrl: '/views/public/orders/view.html'
        })
        .when('/orders/bulk_link',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/orders/bulk_link') ? 'views/orders/bulk_link.html' : sink
                }
            })
        .when('/orders/ship_grid',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/orders/ship_grid') ? 'views/orders/ship_grid.html' : sink
                }
            })
        .when('/reports/sales/ebay_motorcycle',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/reports/sales/ebay_motorcycle') ? 'views/reports/sales.html' : sink
                }
            })
        .when('/reports/sku_weekly',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/reports/sku_weekly') ? 'views/reports/sku_weekly.html' : sink
                }
            })
        .when('/reports/sales/ebay_automotive',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/reports/sales/ebay_automotive') ? 'views/reports/sales.html' : sink
                }
            })
        .when('/reports/sales/ebay',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/reports/sales/ebay') ? 'views/reports/sales.html' : sink
                }
            })
        .when('/reports/sales/amazon',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/reports/sales/amazon') ? 'views/reports/sales.html' : sink
                }
            })
        .when('/reports/sales/europortparts',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/reports/sales/europortparts') ? 'views/reports/sales.html' : sink
                }
            })
        .when('/reports/messages',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/reports/messages') ? 'views/reports/messages.html' : sink
                }
            })
        .when('/reports/messages_table',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/reports/messages_table') ? 'views/reports/messages_table.html' : sink
                }
            })
        .when('/reports/google_feed',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/reports/google_feed') ? 'views/reports/google_feed.html' : sink
                }
            })
        .when('/reports/shipping',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/reports/shipping') ? 'views/reports/shipping.html' : sink
                }
            })
        .when('/reports/order_status',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/reports/order_status') ? 'views/reports/order_status.html' : sink
                }
            })
        .when('/reports/ebay_editing',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/reports/ebay_editing') ? 'views/reports/ebay_editing.html' : sink
                }
            })
        .when('/reports/amazon_monitor',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/reports/amazon_monitor') ? 'views/reports/amazon_monitor.html' : sink
                }
            })
        .when('/orders/view/:id',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/orders/list') ? 'views/orders/view.html' : sink
                }
            })
        .when('/orders/create',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/orders/create') ? 'views/orders/create.html' : sink
                },
                controller: 'OrderCreateController'
            })
        .when('/paypal/create',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/paypal/create') ? 'views/paypal/create.html' : sink
                },
                controller: 'PaypalInvoiceCreateController'
            })
        .when('/paypal/list',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/paypal/list') ? 'views/paypal/list.html' : sink
                },
                controller: 'PaypalInvoiceListController'
            })
        .when('/paypal/view/:id',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/paypal/list') ? 'views/paypal/view.html' : sink
                },
                controller: 'PaypalInvoiceViewController'
            })
        .when('/admin/users',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/admin/users') ? 'views/admin/users.html' : sink
                }
            })
        .when('/admin/acl',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/admin/acl') ? 'views/admin/acl.html' : sink
                }
            })
        .when('/admin/health',
            {
                templateUrl: function () {
                    return ~$window.acl.indexOf('/admin/health') ? 'views/admin/health.html' : sink
                }
            }).when('/customer-services/orders/create', {
                templateUrl: 'views/customer_services/public_create_new_order.html',
                controller: 'CustomerOrderCreateController'
            }).when('/customer-services/orders/view/:id', {
                templateUrl: 'views/customer_services/view.html'
            })
        .otherwise({
            templateUrl: 'views/coming.html'
        });

	// with this, you can use $log('Message') same as $log.info('Message');
	$provide.decorator('$log', ['$delegate',
	function($delegate) {
		// create a new function to be returned below as the $log service (instead of the $delegate)
		function logger() {
			// if $log fn is called directly, default to "info" message
			logger.info.apply(logger, arguments);
		}

		// add all the $log props into our new logger fn
		angular.extend(logger, $delegate);
		return logger;
	}]); 

}]);

integraApp.run(['$rootScope', 'editableOptions', 'uiSelectConfig', '$q', 'ngTableParams', '$http', '$location', '$timeout', function($rootScope, editableOptions, uiSelectConfig, $q, ngTableParams, $http, $location, $timeout)
{
    $rootScope.paypalUrl = paypalUrl;

    $rootScope.$popError = function(title, data, altmsg)
    {
        $rootScope.$broadcast("loading-complete");

        altmsg = altmsg || ((typeof data === 'undefined' || typeof data == 'string' || data == null) ? 'Check your Internet connection.' : data.msg);

        $.smallBox({
            title: title,
            content: altmsg,
            color: "#C46A69",
            iconSmall: "fa fa-warning swing animated",
            timeout: 4000
        });
    };

    $rootScope.$popOk = function(message)
    {
        $.smallBox({
            title: message,
            color: "#739E73",
            iconSmall: "fa fa-check",
            timeout: 4000
        });
    };

    $rootScope.$focus = function(id)
    {
        $timeout(function() { $('#' + id).focus(); $('#' + id).select(); }, 500);
    };

    $rootScope.boolFilter = function(dataOnly)
    {
        var data =
            [
                {id: 0, title: 'No'},
                {id: 1, title: 'Yes'}
            ];

        if (dataOnly) return data;
        else { var d = $q.defer(); d.resolve(data); return d; }
    };

    $rootScope.statusFilter = function(dataOnly)
    {
        var data =
            [
                {id: 0, title: 'Unspecified'},
                {id: 1, title: 'Scheduled'},
                {id: 2, title: 'Item Ordered / Waiting'},
                {id: 3, title: 'Ready for Dispatch'},
                {id: 4, title: 'Order Complete'},
                {id: 90, title: 'Cancelled'},
                {id: 91, title: 'Payment Pending'},
                {id: 92, title: 'Return Pending'},
                {id: 93, title: 'Return Complete'},
                {id: 94, title: 'Refund Pending'},
                {id: 99, title: 'Error'}
            ];

        if (dataOnly) return data;
        else { var d = $q.defer(); d.resolve(data); return d; }
    };

    $rootScope.fulfillmentFilter = function(dataOnly)
    {
        var data =
            [
                {id: 0, title: 'Unspecified'},
                {id: 1, title: 'Direct'},
                {id: 2, title: 'Pickup'},
                {id: 3, title: 'EOC'}
            ];

        if (dataOnly) return data;
        else { var d = $q.defer(); d.resolve(data); return d; }
    };

    $rootScope.paypalInvoiceStatusFilter = function(dataOnly)
    {
        var data =
            [
                {id: 'Unpaid', title: 'Unpaid'},
                {id: 'Paid', title: 'Paid'}
            ];

        if (dataOnly) return data;
        else { var d = $q.defer(); d.resolve(data); return d; }
    };

    $rootScope.returnStatusFilter = function(dataOnly)
    {
        var data =
            [
                {id: 'Pending RA', title: 'Pending RA'},
                {id: 'Processing', title: 'Processing'},
                {id: 'Partial Credit', title: 'Partial Credit'},
                {id: 'Full Credit', title: 'Full Credit'}
            ];

        if (dataOnly) return data;
        else { var d = $q.defer(); d.resolve(data); return d; }
    };

    editableOptions.theme = 'bs3';

    $rootScope.crud = function(obj, options)
    {
        if (!angular.isDefined(options.tables))
        {
            options.tables = [{}];
        }

        for (var i = 0; i < options.tables.length; i++)
        {
            if (!angular.isDefined(options.tables[i].name))
                options.tables[i].name = 'tableParams';

            if (!angular.isDefined(options.tables[i].method))
                options.tables[i].method = (angular.isDefined(options.method) ? options.method : 'GET');

            if (!angular.isDefined(options.tables[i].getUrl))
                options.tables[i].getUrl = options.getUrl;

            if (!angular.isDefined(options.tables[i].defaultSort))
                options.tables[i].defaultSort = (angular.isDefined(options.defaultSort) ? options.defaultSort : {});

            if (!angular.isDefined(options.tables[i].beforeGet))
                options.tables[i].beforeGet = (angular.isDefined(options.beforeGet) ? options.beforeGet : null);

            if (!angular.isDefined(options.tables[i].afterGet))
                options.tables[i].afterGet = (angular.isDefined(options.afterGet) ? options.afterGet : null);
        }

        obj.submitWait = false;

        if (angular.isDefined(obj.freshEntry))
            obj.freshEntry();

        for (i = 0; i < options.tables.length; i++)
        {
            obj[options.tables[i].name] = new ngTableParams(
                {
                    page: 1,
                    count: 20,
                    sorting: options.tables[i].defaultSort,
                    tz: (new Date().getTimezoneOffset())
                },
                {
                    total: 0,
                    getData: function($defer, params)
                    {
                        var getParams = params.url();
                        
                        if (options.tables[params.tableId].beforeGet)
                        {
                            if (!options.tables[params.tableId].beforeGet(getParams))
                            {
                                $defer.reject();
                                return;
                            }
                        }

                        $http({method: options.tables[params.tableId].method, url: options.tables[params.tableId].getUrl, params: getParams}).success(function(data)
                        {
                            if (options.tables[params.tableId].afterGet)
                                options.tables[params.tableId].afterGet(data);

                            params.total(data.total);
                            $defer.resolve(data.result);
                        });
                    }
                });

            obj[options.tables[i].name].tableId = i;
        }

        if (angular.isDefined(options.openUrl))
        {
            obj.openEntry = function(id)
            {
                $location.path(options.openUrl + id);
            };
        }

        obj.deleteEntry = function(id)
        {
            $http.delete(options.getUrl + id)
                .success(function (data)
                {
                    for (var i = 0; i < options.tables.length; i++)
                        obj[options.tables[i].name].reload();

                    obj.$popOk(data.msg);
                })
                .error(function (data)
                {
                    obj.$popError("Error while deleting the entry", data);
                });
        };

        obj.saveEntry = function(id, entry)
        {
            console.log(entry);
            obj.submitWait = true;
            if (id != 0) var d = $q.defer();

            if (angular.isDefined(options.beforeSave))
                options.beforeSave();

            $http.put(options.getUrl + id, entry)
                .success(function (data)
                {
                    if (id == 0)
                    {
                        for (var i = 0; i < options.tables.length; i++)
                            obj[options.tables[i].name].reload();
                    }
                    else
                    {
                        d.resolve();

                        if (options.refreshTablesAfterEdit)
                        {
                            for (var i = 0; i < options.tables.length; i++)
                                obj[options.tables[i].name].reload();
                        }
                    }

                    if (angular.isDefined(obj.freshEntry))
                        obj.freshEntry();

                    obj.submitWait = false;
                    obj.$popOk(data.msg);

                    if (angular.isDefined(options.afterSave))
                        options.afterSave(data);
                })
                .error(function (data)
                {
                    obj.$popError("Error while saving the entry", data);
                    obj.submitWait = false;
                    if (id != 0) d.resolve('');
                });

            if (id != 0) return d.promise;
            else return null;
        };
    };
}])