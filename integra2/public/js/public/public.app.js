var integraPublicApp = angular.module('integraPublicApp', [
  	'ngRoute',
    'ngFileUpload',
    'ui.bootstrap',
    'app.main',
    'app.navigation',
    'app.smartui',
    'angular-loading-bar',
    'truncate',
    'chart.js'
]);

integraPublicApp.config(['$routeProvider', '$provide', '$windowProvider', function($routeProvider, $provide, $windowProvider) {
    $routeProvider.when('/shipping', {
      templateUrl: '/views/public/reports/shipping.html'
    }).when('/orders/create', {
        templateUrl: '/views/public/orders/public_create_new_order.html',
        controller: 'OrderCreateController'
    }).when('/orders/view/:id', {
        templateUrl: '/views/public/orders/view.html'
    });
}]);

integraPublicApp.run(['$rootScope', '$location', '$http', '$interval', 
    function($rootScope, $location, $http, $interval) {
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
    }
]);