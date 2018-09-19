angular.module('integraPublicApp').controller('PublicController', ['$scope', '$http', '$location', 
	function($scope, $http, $location) {
		console.log("TEST");

	}

]).controller('OrderCreateController', ['$scope', '$http', function($scope, $http)
    {
        $scope.$on('$viewContentLoaded', function()
        {
            $scope.merchants =
                [
                    {code: 'qeautoparts', name: 'Q&E Auto Parts'},
                    {code: 'europortparts', name: 'Euro Port Parts'},
                    {code: 'eocparts', name: 'EOC Parts'},
                    {code: 'eBay', name: 'eBay'},
                    {code: 'Amazon', name: 'Amazon'},
                    {code: 'iapaustralia', name: 'International Auto Parts Australia'},
                    {code: 'iapcanada', name: 'International Auto Parts Canada'},
                    {code: 'iapunitedkingdom', name: 'International Auto Parts UK'},
                    {code: 'iapfrance', name: 'International Auto Parts France'},
                    {code: 'iapbelgique', name: 'International Auto Parts Belgique'},
                    {code: 'iapbrazil', name: 'International Auto Parts Brazil'},
                    {code: 'iapdanmark', name: 'International Auto Parts Danmark'},
                    {code: 'iapdeutschland', name: 'International Auto Parts Deutschland'},
                    {code: 'iapitalia', name: 'International Auto Parts Italia'},
                    {code: 'iapnederland', name: 'International Auto Parts Nederland'},
                    {code: 'iapsverige', name: 'International Auto Parts Sverige'},
                    {code: 'iapswitzerland', name: 'International Auto Parts Switzerland'},
                    {code: 'iapespana', name: 'International Auto Parts España'},
                    {code: 'iaposterreich', name: 'International Auto Parts Österreich'}
                ];

            $scope.statusOptions =
                [
                    {id: 100, title: 'Reshipment'},
                    {id: 101, title: 'Exchange'},
                    {id: 102, title: 'Non-Delivered'}
                ];

            $scope.fulfillmentOptions =
                [
                    {id: 1, title: 'Direct'},
                    {id: 3, title: 'EOC'},
                    {id: 4, title: 'Unspecified'}   // TODO: Make it dynamic based on our warehouses
                ];

            $scope.speedOptions =   // TODO: Make it dynamic
                [
                    {id: 'Standard / Ground', title: 'Standard / Ground'},
                    {id: 'Expedited / Express', title: 'Expedited / Express'},
                    {id: 'Second Day', title: 'Second Day'},
                    {id: 'Next Day / Overnight', title: 'Next Day / Overnight'},
                    {id: 'International', title: 'International'},
                    {id: 'ePacket', title: 'ePacket'},
                    {id: 'Local Pick Up', title: "Local Pick Up"}
                ];

            $scope.savedAddessOptions = [];
            $scope.savedAddress = null;

            $scope.freshEntry = function()
            {
                $scope.link_error = '';
                $scope.submitWait = true;
                $scope.newEntry =
                {
                    record_num: '',
                    name: '',
                    merchant: $scope.merchants[0],
                    email: '',
                    address: '',
                    city: '',
                    state: '',
                    zip: '',
                    country: 'US',
                    phone: '',
                    speed: $scope.speedOptions[0],
                    agent: localStorage.agent,
                    total: '',
                    fulfillment: $scope.fulfillmentOptions[2],
                    status: $scope.statusOptions[10],
                    related_sales_id: null,
                    items:
                        [
                            {sku: '', quantity: 1, description: '', brand: '', price: null, weight: null, supplier: null},
                            {sku: '', quantity: null, description: '', brand: '', price: null, weight: null, supplier: null},
                            {sku: '', quantity: null, description: '', brand: '', price: null, weight: null, supplier: null}
                        ]
                };
            };

            $scope.linkRecordNum = function()
            {
                $scope.link_record_num = $scope.link_record_num.trim().toUpperCase();

                if (!$scope.link_record_num || !$scope.link_record_num.length)
                {
                    $scope.newEntry.related_sales_id = null;
                    $scope.link_error = '';
                    return;
                }

                $scope.link_error = 'Searching...';

                $http.get('/api/orders/search_record_num/' + $scope.link_record_num)
                    .success(function (data)
                    {
                        if (!data || !data.id)
                        {
                            $scope.newEntry.related_sales_id = null;
                            $scope.link_error = 'Order not found';
                            return;
                        }

                        $scope.newEntry.related_sales_id = data.id;
                        $scope.newEntry.email = data.email;
                        $scope.newEntry.name = data.buyer_name;
                        $scope.newEntry.address = data.street;
                        $scope.newEntry.city = data.city;
                        $scope.newEntry.state = data.state;
                        $scope.newEntry.zip = data.zip;
                        $scope.newEntry.country = data.country;
                        $scope.newEntry.phone = data.phone;

                        for (var i = 0; i < $scope.merchants.length; i++)
                        {
                            if (data.store == $scope.merchants[i].code)
                                $scope.newEntry.merchant = $scope.merchants[i];
                        }

                        $scope.link_error = '';
                    })
                    .error(function (data)
                    {
                        $scope.newEntry.related_sales_id = null;
                        $scope.link_error = 'Unable to search orders';
                    });
            };

            $scope.addItem = function()
            {
                $scope.newEntry.items.push({sku: '', quantity: 1, description: '', brand: '', price: null, weight: null, supplier: null});
            };

            $scope.deleteItem = function(index)
            {
                $scope.newEntry.items.splice(index, 1);
                $scope.validate();
            };

            $scope.validate = function()
            {
                if ($scope.newEntry.items.length == 0)
                {
                    $scope.submitWait = true;
                    return;
                }

                var notOk = false;

                for (var i = 0; i < $scope.newEntry.items.length; i++)
                {
                    if ($scope.newEntry.items[i].sku == '')
                        continue;

                    if (!$scope.newEntry.items[i].price || $scope.newEntry.items[i].price == '?')
                    {
                        notOk = true;
                        break;
                    }
                }

                $scope.submitWait = notOk;
            };

            $scope.selectSavedAddress = function()
            {
                if ($scope.savedAddress)
                {
                    $scope.newEntry.name = $scope.savedAddress.name;
                    $scope.newEntry.email = $scope.savedAddress.email;
                    $scope.newEntry.address = $scope.savedAddress.address;
                    $scope.newEntry.city = $scope.savedAddress.city;
                    $scope.newEntry.state = $scope.savedAddress.state;
                    $scope.newEntry.zip = $scope.savedAddress.zip;
                    $scope.newEntry.country = $scope.savedAddress.country;
                    $scope.newEntry.phone = $scope.savedAddress.phone;
                }
            };

            $scope.querySku = function(item)
            {
                if (item.sku.length == 0)
                {
                    item.description = '';
                    item.brand = '';
                    item.price = null;
                    item.weight = null;

                    $scope.validate();
                    return;
                }

                var supplier;

                if (item.sku.indexOf('.') >= 0)
                {
                    item['supplier'] = 2;
                    supplier = 'ssf';
                }
                else
                {
                    item['supplier'] = 1;
                    supplier = 'imc';
                }

                item['description'] = 'Loading...';
                item['brand'] = '';
                item['price'] = '';
                item['weight'] = '';

                $scope.validate();

                $http.get('/api/proxy/' + supplier + '_ajax/' + item.sku)
                    .success(function (data)
                    {
                        item['description'] = data['desc'];
                        item['brand'] = data['brand'];
                        item['price'] = data['price'];
                        item['weight'] = data['weight'];
                        $scope.validate();
                    })
                    .error(function (data)
                    {
                        item['description'] = data['desc'];
                        $scope.validate();
                    });
            };

            $scope.saveEntry = function(entry)
            {
                $scope.submitWait = true;

                localStorage.agent = entry.agent;

                $http.post('/api/orders', entry)
                    .success(function (data)
                    {
                        // TODO: Change after migration
                        window.location.replace('http://integra2.eocenterprise.com/public/#orders/view/' + data.order_id);
                    })
                    .error(function (data)
                    {
                        $scope.$popError("Error while saving the order", data);
                        $scope.submitWait = false;
                    });
            };

            $scope.freshEntry();

            $http.get('/api/saved_addresses')
                .success(function (data)
                {
                    $scope.savedAddressOptions = data;
                });
        });
    }]);