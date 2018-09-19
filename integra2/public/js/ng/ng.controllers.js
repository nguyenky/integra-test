angular.module('app.controllers', [])

    .controller('IntegraAppController', ['$scope', function($scope)
    {
        // main controller
    }])

    .controller('WarehouseMapController', ['$scope', '$http', '$location', function($scope, $http, $location)
    {
        $scope.selected = null;
        $scope.currentBin = null;
        $scope.data = null;
        $scope.warehouse_id = 1;

        $scope.loadForm = function()
        {
            $http.get('/api/warehouses/supplier').then(function(data)
            {
                $scope.dropdown = data.data;
                $scope.store = {};
            });
        };

        $scope.submitRegister = function(input)
        {
            $http.post('/api/warehouses/store', input).then(function(data)
            {
                if(data.data == ''){
                    $scope.error = 1;
                }
                angular.element('#registerWarehouse').modal('hide');
                $scope.warehouse_id = data.data;
                $scope.loadData();
            });
        };

        $scope.submitUpdate = function(input)
        {
            $http.put('/api/warehouses/update', input).then(function(data)
            {
                if(data.data == ''){
                    $scope.error = 1;
                }
                angular.element('#updateWarehouse').modal('hide');
                $scope.warehouse_id = data.data;
                $scope.loadData();
            });
        };

        $scope.hasEmptyBin = function(cell)
        {
            for (var i = 0; i < cell.bins.length; i++)
            {
                if (cell.bins[i].quantity == 0)
                    return true;
            }

            return false;
        };

        $scope.details = function(bin)
        {
            window.open('/#/products/view/' + bin.sku, '_blank');
        };

        $scope.addCode = function(bin)
        {
            var new_code = prompt('Enter or scan new code:');
            if (!new_code) return;

            bin.codes.push(new_code);
            $scope.saveCodes(bin);
        };

        $scope.deleteCode = function(bin, index)
        {
            bin.codes.splice(index, 1);
            $scope.saveCodes(bin);
        };

        $scope.saveCodes = function(bin)
        {
            $http.put('/api/warehouses/save_codes', {product_id: bin.product_id, codes: bin.codes})
                .success(function (data)
                {
                    bin.codes = data.codes;

                    if (data.success) $scope.$popOk('Product codes updated successfully');
                    else $scope.$popError('Error while updating product codes', null, 'Some of the codes are invalid or already assigned to other products.');
                }).error(function (data)
                {
                    $scope.$popError('Error while updating product codes');
                });
        };

        $scope.recount = function(bin)
        {
            while (true)
            {
                var new_quantity = parseInt(prompt('Enter current quantity:', bin.quantity), 10);
                if (new_quantity == null || isNaN(new_quantity)) return;

                if (new_quantity < 0 || new_quantity > 500)
                {
                    alert('Please enter a quantity between 1-500.');
                    continue;
                }
                else break;
            }

            $http.post('/api/warehouses/recount', {bin_id: bin.id, quantity: new_quantity})
                .success(function (data)
                {
                    if (data == '1')
                    {
                        bin.quantity = new_quantity;
                        $scope.$popOk('Quantity updated successfully');
                    }
                    else $scope.$popError('Error while updating quantity', null, 'An unknown error occurred.');
                }).error(function (data)
                {
                    $scope.$popError('Error while updating quantity');
                });
        };

        $scope.relocate = function(bin)
        {
            var new_bin = prompt('Enter or scan new bin:', bin.bin);
            if (!new_bin) return;

            $http.post('/api/warehouses/relocate', {bin_id: bin.id, new_bin: new_bin})
                .success(function (data)
                {
                    if (data == '1')
                    {
                        $scope.$popOk('Product relocated successfully');
                        location.reload();
                    }
                    else $scope.$popError('Error while relocating product', null, data);
                }).error(function (data)
                {
                    $scope.$popError('Error while relocating product');
                });
        };

        $scope.search = function()
        {
            var kw = $scope.keyword.toUpperCase().trim();

            for (var y = 0; y < $scope.data.rows.length; y++)
            {
                var row = $scope.data.rows[y];

                for (var x = 0; x < row.length; x++)
                {
                    var col = row[x];

                    if (kw == '')
                    {
                        col.filtered = false;
                        continue;
                    }

                    var found = false;

                    for (var z = 0; z < col.bins.length; z++)
                    {
                        var bin = col.bins[z];

                        if (bin.bin == kw || bin.sku.toUpperCase() == kw || bin.name.toUpperCase().indexOf(kw) >= 0 || bin.brand.toUpperCase().indexOf(kw) >= 0)
                        {
                            col.filtered = true;
                            found = true;
                            continue;
                        }

                        for (var c = 0; c < bin.codes.length; c++)
                        {
                            var code = bin.codes[c];

                            if (code.toUpperCase() == kw)
                            {
                                col.filtered = true;
                                found = true;
                                break;
                            }
                        }
                    }

                    if (!found) col.filtered = false;
                }
            }
        };

        $scope.loadData = function()
        {
            $scope.selected = null;

            $http.get('/api/warehouses/map/' + $scope.warehouse_id)
                .success(function (data)
                {
                    console.log(data);
                    $scope.data = data[0];
                    $scope.update = data[1];
                })
                .error(function (data)
                {
                    $scope.$popError('Error while loading warehouse map', data);
                });
        };

        $scope.$on('$viewContentLoaded', function()
        {
            $scope.loadData();
        });
        $scope.loadForm();
    }])
    .controller('CustomerOrderCreateController', ['$scope', '$http', function($scope, $http)
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
                    {id: 0, title: 'Unspecified'},
                    {id: 1, title: 'Direct'},
                    {id: 3, title: 'EOC'},

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
            $scope.selectedSupplier = null;

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
                    fulfillment: $scope.fulfillmentOptions[0],
                    status: $scope.statusOptions[0],
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
                    if ($scope.newEntry.items[i].sku == '') {
                        notOk = true;
                        break;
                    }

                }


                $scope.submitWait = notOk;
            };

            $scope.supplierSelected = function(item) {
                if(item.selectedSupplier) {
                    item.supplier = item.selectedSupplier.id;
                }

            }

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



            $scope.saveEntry = function(entry)
            {
                $scope.submitWait = true;

                localStorage.agent = entry.agent;

                console.log(entry);

                $http.post('/api/customer-services/orders', entry)
                    .success(function (data)
                    {

                        // TODO: Change after migration
                        window.location.replace('http://integra2.eocenterprise.com/#/orders/view/' + data.order_id);
                        //window.location.replace('http://integra2.eocenterprise.com/#/customer-services/orders/view/' + data.order_id);
                    })
                    .error(function (data)
                    {
                        $scope.$popError("Error while saving the order", data);
                        $scope.submitWait = false;
                    });
            };

            $scope.freshEntry();
            /*
            $http.get('/api/saved_addresses')
                .success(function (data)
                {
                    $scope.savedAddressOptions = data;
                });
            */
            $scope.getInitDataForm = function() {
                $http.get('/api/init-create-order-data').success(function(data) {
                    $scope.savedAddressOptions = data.savedAddresses;
                    $scope.suppliers = data.suppliers;
                });
            };

            $scope.getInitDataForm();
        });
    }])

    .controller('EbayMonitorMigrationController', ['$scope', 'Upload', function($scope, Upload) {
        $scope.uploadCsv = function(data) {
            Upload.upload({
                url: '/api/ebay-monitors/migrate-new-vs-our',
                data: {file: data}
            }).then(function (resp) {
                $scope.$popOk('Success ' + resp.config.data.file.name + 'uploaded. Response: ' + resp.data);
                console.log('Success ' + resp.config.data.file.name + 'uploaded. Response: ' + resp.data);

            }, function (resp) {
                console.log('Error status: ' + resp.status);
            }, function (evt) {
                console.log(evt);
                var progressPercentage = parseInt(100.0 * evt.loaded / evt.total);
                console.log('progress: ' + progressPercentage + '% ' + evt.config.data.file.name);
            });
        };
    }])

	.controller('ProductKitController', ['$scope', 'Upload', function($scope, Upload)
    {
        $scope.uploadKits = function(data) {
            Upload.upload({
                url: '/api/products/kits/upload',
                data: {file: data}
            }).then(function (resp) {
                $scope.$popOk('Success ' + resp.config.data.file.name + 'uploaded. Response: ' + resp.data);
                console.log('Success ' + resp.config.data.file.name + 'uploaded. Response: ' + resp.data);

            }, function (resp) {
                console.log('Error status: ' + resp.status);
            }, function (evt) {
                console.log(evt);
                var progressPercentage = parseInt(100.0 * evt.loaded / evt.total);
                console.log('progress: ' + progressPercentage + '% ' + evt.config.data.file.name);
            });
        };

		$scope.$on('$viewContentLoaded', function()
        {
            $scope.freshEntry = function()
            {
                $scope.newEntry =
                {
                    id: 0,
                    name: '',
                    components:
                        [
                            {sku: '', pivot: {quantity: 1}},
                            {sku: '', pivot: {quantity: null}},
                            {sku: '', pivot: {quantity: null}}
                        ]
                };
            };

            $scope.addComponent = function()
            {
                $scope.newEntry.components.push({sku: '', pivot: {quantity: 1}});
            };

            $scope.deleteComponent = function(index)
            {
                $scope.newEntry.components.splice(index, 1);
            };

            $scope.editEntry = function(entry)
            {
                $scope.newEntry = entry;
                window.scrollTo(0, 0);
            };



            $scope.crud($scope,
            {
                getUrl: '/api/products/kits/',
                refreshTablesAfterEdit: true,
                afterSave: function(data)
                {
                    if (data.sku)
                        $scope.newSku = data.sku;
                    else $scope.newSku = null;
                }
            });
        });
	}])

    .controller('ProductNewStockController', ['$scope', '$http', function($scope, $http)
    {
        $scope.$on('$viewContentLoaded', function()
        {
            $scope.invoice_loading = false;
            $scope.invoice_status = 0;
            $scope.invoice_num = null;
            $scope.invoice_items = [];

            $scope.clearInvoice = function()
            {
                $scope.invoice_num = null;
                $scope.invoice_items = [];

                localStorage.last_invoice_num = null;
                localStorage.last_invoice_items = null;

                $scope.invoice_loading = false;
                $scope.invoice_status = 0;
            };

            $scope.getLastInvoice = function()
            {
                var last_invoice_num = localStorage.last_invoice_num;
                var last_invoice_items = localStorage.last_invoice_items;

                if (!last_invoice_num || last_invoice_num == 'null' || !last_invoice_items || last_invoice_items == 'null')
                {
                    $scope.clearInvoice();
                }
                else
                {
                    $scope.invoice_num = last_invoice_num;
                    $scope.invoice_items = angular.fromJson(last_invoice_items);
                    $scope.invoice_loading = false;
                    $scope.invoice_status = 1;
                }
            };

            $scope.invoiceChanged = function()
            {
                if (!$scope.invoice_num)
                {
                    $scope.clearInvoice();
                }
                else if ($scope.invoice_num != localStorage.last_invoice_num)
                {
                    $scope.invoice_loading = true;

                    $http.get('/api/supplier_invoices/item_quantities/' + $scope.invoice_num).success(function (data)
                    {
                        $scope.invoice_loading = false;

                        localStorage.last_invoice_num = $scope.invoice_num;

                        if (!data || data.length == 0)
                        {
                            localStorage.last_invoice_items = null;
                            $scope.invoice_items = [];
                            $scope.invoice_status = -1;
                            $scope.$focus('invoice_num');
                        }
                        else
                        {
                            localStorage.last_invoice_items = angular.toJson(data);
                            $scope.invoice_items = data;
                            $scope.invoice_status = 1;
                            $scope.findExpectedQuantity();
                        }

                        console.log($scope.invoice_status);
                    })
                    .error(function (data)
                    {
                        $scope.invoice_loading = false;
                        $scope.$popError('Error while loading invoice.');
                    });
                }
            };

            $scope.noBrand = function(sku)
            {
                sku = sku.replace('/', '.');
                sku = sku.replace(/[^\w.]/g, '').trim();
                var idx = sku.indexOf('.');
                if (idx < 0) return sku;
                return sku.substring(0, idx);
            };

            $scope.findExpectedQuantity = function()
            {
                if ($scope.invoice_status != 1 || !$scope.result.match_sku || !angular.isDefined($scope.invoice_items))
                    $scope.expected_quantity = 0;

                for (var i = 0; i < $scope.invoice_items.length; i++)
                {
                    var item = $scope.invoice_items[i];

                    if (item.sku == $scope.result.match_sku || item.sku == $scope.result.match_code)
                    {
                        $scope.expected_quantity = item.quantity;
                        return;
                    }

                    if (angular.isDefined($scope.result.match_codes))
                    {
                        for (var j = 0; j < $scope.result.match_codes.length; j++)
                        {
                            if (item.sku == $scope.result.match_codes[j])
                            {
                                $scope.expected_quantity = item.quantity;
                                return;
                            }
                        }
                    }

                    // search again without brand code

                    if (item.sku == $scope.noBrand($scope.result.match_sku) || item.sku == $scope.noBrand($scope.result.match_code))
                    {
                        $scope.expected_quantity = item.quantity;
                        return;
                    }

                    if (angular.isDefined($scope.result.match_codes))
                    {
                        for (var j = 0; j < $scope.result.match_codes.length; j++)
                        {
                            if (item.sku == $scope.noBrand($scope.result.match_codes[j]))
                            {
                                $scope.expected_quantity = item.quantity;
                                return;
                            }
                        }
                    }
                }

                $scope.expected_quantity = 'not in invoice';
            };

            $scope.freshEntry = function()
            {
                $scope.entry =
                {
                    warehouse_id: 1,
                    product_id: null,
                    code: null,
                    sku: null,
                    bin: null,
                    quantity: 1
                };

                $scope.result =
                {
                    bad_code: false,
                    bad_sku: false,
                    match_code: null,
                    match_codes: null,
                    match_sku: null,
                    match_name: null,
                    match_brand: null,
                    match_bin: null
                };

                $scope.code_loading = false;
                $scope.sku_loading = false;
                $scope.submit_wait = false;
                $scope.$focus('code');
            };

            $scope.freshEntry();
            $scope.getLastInvoice();

            $scope.submitEntry = function()
            {
                $scope.submit_wait = true;

                $http.post('/api/warehouses/new_stock', $scope.entry).success(function (data)
                {
                    $scope.submit_wait = false;

                    if (angular.isDefined(data.new_quantity))
                    {
                        $scope.$popOk('Stock added successfully. New quantity: ' + data.new_quantity);
                        $scope.freshEntry();
                    }
                    else if (angular.isDefined(data.other_sku))
                    {
                        $scope.$popError('This bin is already taken', null,
                            data.other_name + ' (' + data.other_sku + ') is currently assigned to this bin.');

                        $scope.$focus('bin');
                    }
                })
                .error(function (data)
                {
                    $scope.submit_wait = false;
                    alert('Error while adding new stock. Please check your Internet connection.');
                });
            };

            $scope.enterQuantity = function()
            {
                $scope.$focus('quantity');
            };

            $scope.codeChanged = function()
            {
                // do not refresh if code is good and has not been changed
                if (!$scope.result.bad_code && $scope.result.match_code == $scope.entry.code) return;

                if (!$scope.entry.code)
                {
                    $scope.freshEntry();
                    return;
                }

                $scope.code_loading = true;
                $scope.result.match_code = $scope.entry.code;

                $http.put('/api/warehouses/upsert_sku', $scope.entry)
                    .success(function (data)
                    {
                        $scope.code_loading = false;

                        if (angular.isDefined(data.product))
                        {
                            $scope.entry.product_id = data.product.id;
                            $scope.result.match_sku = data.product.sku;
                            $scope.result.match_brand = data.product.brand;
                            $scope.result.match_name = data.product.name;
                            $scope.result.match_codes = data.product.codes;
                            $scope.result.bad_code = false;
                            $scope.result.bad_sku = false;

                            if (data.bin)
                            {
                                $scope.result.match_bin = data.bin.isle + data.bin.row + data.bin.column;
                                $scope.$focus('quantity');
                            }
                            else
                            {
                                $scope.result.match_bin = null;
                                $scope.$focus('bin');
                            }
                        }
                        else
                        {
                            $scope.entry.product_id = null;
                            $scope.result.match_sku = null;
                            $scope.result.match_brand = null;
                            $scope.result.match_name = null;
                            $scope.result.match_bin = null;
                            $scope.result.match_codes = null;

                            $scope.result.bad_code = true;
                            $scope.result.bad_sku = false;
                            $scope.entry.sku = null;

                            $scope.$focus('sku');
                        }

                        $scope.findExpectedQuantity();
                    })
                    .error(function (data)
                    {
                        $scope.$popError('Error while searching product.');

                        $scope.code_loading = false;

                        $scope.entry.product_id = null;
                        $scope.result.match_code = null;
                        $scope.result.match_codes = null;
                        $scope.result.match_sku = null;
                        $scope.result.match_brand = null;
                        $scope.result.match_name = null;
                        $scope.result.match_bin = null;

                        $scope.findExpectedQuantity();

                        $scope.$focus('code');
                    });
            };

            $scope.skuChanged = function()
            {
                // do not refresh if sku is good and has not been changed
                if (!$scope.result.bad_sku && $scope.result.match_sku == $scope.entry.sku) return;

                if (!$scope.entry.sku) return;

                $scope.sku_loading = true;
                $scope.result.match_code = $scope.entry.code;

                $http.put('/api/warehouses/upsert_sku', $scope.entry)
                    .success(function (data)
                    {
                        $scope.sku_loading = false;

                        if (angular.isDefined(data.product))
                        {
                            $scope.entry.product_id = data.product.id;
                            $scope.result.match_sku = data.product.sku;
                            $scope.result.match_brand = data.product.brand;
                            $scope.result.match_name = data.product.name;
                            $scope.result.match_codes = data.product.codes;
                            $scope.result.bad_code = true;
                            $scope.result.bad_sku = false;

                            if (data.bin)
                            {
                                $scope.result.match_bin = data.bin.isle + data.bin.row + data.bin.column;
                                $scope.$focus('quantity');
                            }
                            else
                            {
                                $scope.result.match_bin = null;
                                $scope.$focus('bin');
                            }
                        }
                        else
                        {
                            $scope.entry.product_id = null;
                            $scope.result.match_sku = null;
                            $scope.result.match_brand = null;
                            $scope.result.match_name = null;
                            $scope.result.match_bin = null;
                            $scope.result.match_codes = null;

                            $scope.result.bad_code = true;
                            $scope.result.bad_sku = true;
                            $scope.$focus('sku');
                        }

                        $scope.findExpectedQuantity();
                    })
                    .error(function (data)
                    {
                        $scope.$popError('Error while searching product.');

                        $scope.sku_loading = false;

                        $scope.entry.product_id = null;
                        $scope.result.match_code = null;
                        $scope.result.match_codes = null;
                        $scope.result.match_sku = null;
                        $scope.result.match_brand = null;
                        $scope.result.match_name = null;
                        $scope.result.match_bin = null;

                        $scope.findExpectedQuantity();

                        $scope.$focus('sku');
                    });
            };
        });
    }])

    .controller('ProductNewPicklistController', ['$scope', '$http', function($scope, $http)
    {
        $scope.$on('$viewContentLoaded', function()
        {
            $scope.unfinished = [];

            $scope.freshEntry = function()
            {
                $scope.list =
                {
                    warehouse_id: 1,
                    number: null,
                    items: []
                };

                $scope.current = -1;
                $scope.done = 0;
                $scope.loading = false;
                $scope.has_list = false;
                $scope.submitted = false;
            };

            $scope.freshEntry();

            $http.get('/api/warehouses/unfinished_picklist', {warehouse_id: $scope.list.warehouse_id}).success(function (data)
            {
                $scope.unfinished = data;
            });

            $scope.resumePicklist = function(id)
            {
                $scope.freshEntry();
                $scope.loading = true;

                $http.post('/api/warehouses/resume_picklist/' + id, {warehouse_id: $scope.list.warehouse_id}).success(function (data)
                {
                    $scope.list = data;

                    if ($scope.list.items.length > 0)
                        $scope.current = 0;
                    else $scope.current = -1;

                    $scope.has_list = true;
                    $scope.submitted = false;
                    $scope.loading = false;
                }).error(function (data)
                {
                    alert('Error while resuming picklist. Please check your Internet connection.');
                    $scope.freshEntry();
                });
            };

            $scope.newPicklist = function(warn)
            {
                if (warn && !confirm('This will clear the current picklist. Make sure you have saved or written down the picklist number for shipping.'))
                    return;

                $scope.freshEntry();
                $scope.loading = true;

                $http.post('/api/warehouses/new_picklist', {warehouse_id: $scope.list.warehouse_id}).success(function (data)
                {
                    $scope.list = data;

                    if ($scope.list.items.length > 0)
                        $scope.current = 0;
                    else $scope.current = -1;

                    $scope.has_list = true;
                    $scope.submitted = false;
                    $scope.loading = false;
                }).error(function (data)
                {
                    alert('Error while generating picklist. Please check your Internet connection.');
                    $scope.freshEntry();
                });
            };

            $scope.countDone = function()
            {
                var done = 0;

                for (var i = 0; i < $scope.list.items.length; i++)
                {
                    if ($scope.list.items[i].status != 0)
                        done++;
                }

                $scope.done = done;
            };

            $scope.findNext = function()
            {
                $scope.countDone();

                for (var i = 0; i < $scope.list.items.length; i++)
                {
                    if ($scope.list.items[i].status == 0)
                    {
                        return i;
                    }
                }

                return -1;
            };

            $scope.skipItem = function()
            {
                var reason = prompt('Why are you skipping this item?');
                if (!reason) return;

                $scope.list.items[$scope.current].status = -1;
                $scope.list.items[$scope.current].reason = reason;

                var next = $scope.findNext();
                if (next >= 0)
                {
                    $scope.current = next;
                    return;
                }
            };

            $scope.addItem = function()
            {
                $scope.list.items[$scope.current].status = 1;
                $scope.list.items[$scope.current].reason = null;

                var next = $scope.findNext();
                if (next >= 0)
                {
                    $scope.current = next;
                    return;
                }
            };

            $scope.allDone = function()
            {
                if (!$scope.has_list || !$scope.list || !$scope.list.items || !$scope.list.items.length)
                {
                    $scope.freshEntry();
                    return;
                }

                var next = $scope.findNext();
                if (next >= 0)
                {
                    $scope.current = next;
                    return;
                }

                $http.post('/api/warehouses/confirm_picklist', $scope.list).success(function (data)
                {
                    $scope.loading = false;
                    $scope.submitted = true;
                }).error(function (data)
                {
                    alert('Error while submitting picklist. Please check your Internet connection.');
                    $scope.loading = false;
                    $scope.submitted = false;
                });
            };
        });
    }])

    .controller('ProductNewDupeController', ['$scope', '$http', function($scope, $http)
    {
        $scope.newEntry =
        {
            supplier: 0,
            mpn: null
        };

        $scope.valid_mpn = false;
        $scope.img_mpn = null;

        $scope.$on('$viewContentLoaded', function()
        {
            $scope.$focus('mpn');
        });

        $scope.saveEntry = function()
        {
            $http.post('/api/products/new_dupe', $scope.newEntry)
                .success(function (data)
                {
                    if (data.length > 0)
                    {
                        $scope.dupe_sku = data;
                    }
                    else $scope.$popError('Unable to duplicate product');
                }).error(function (data)
                {
                    $scope.$popError('Unable to duplicate product');
                });
        };

        $scope.search = function()
        {
            $scope.valid_mpn = false;

            if ($scope.newEntry.mpn.length == 0)
            {
                $scope.master_name = '';
                $scope.img_mpn = null;
                return;
            }

            var supplier;

            if ($scope.newEntry.mpn.indexOf('PU') == 0)
            {
                $scope.newEntry.supplier = 7;
                supplier = 'pss';
            }
            else if ($scope.newEntry.mpn.indexOf('WP') == 0)
            {
                $scope.newEntry.supplier = 8;
                supplier = 'pss';
            }
            else if ($scope.newEntry.mpn.indexOf('TR') == 0)
            {
                $scope.newEntry.supplier = 9;
                supplier = 'pss';
            }
            else if ($scope.newEntry.mpn.indexOf('.') >= 0)
            {
                $scope.newEntry.supplier = 2;
                supplier = 'ssf';
            }
            else
            {
                $scope.newEntry.supplier = 1;
                supplier = 'imc';
            }

            $scope.master_name = 'Searching...';

            if (supplier == 'pss')
            {
                $http.get('/api/products/pss/' + $scope.newEntry.mpn)
                    .success(function (data)
                    {
                        if (data['title'].length > 0)
                        {
                            $scope.master_name = data['title'];
                            $scope.img_mpn = $scope.newEntry.mpn;
                            $scope.valid_mpn = true;
                        }
                        else
                        {
                            $scope.$popError('Invalid MPN');
                            $scope.img_mpn = null;
                            $scope.master_name = '';
                            $scope.valid_mpn = false;
                        }
                    })
                    .error(function (data)
                    {
                        $scope.$popError('Unable to find MPN');
                        $scope.img_mpn = null;
                        $scope.master_name = '';
                        $scope.valid_mpn = false;
                    });
            }
            else
            {
                $http.get('/api/proxy/' + supplier + '_ajax/' + $scope.newEntry.mpn)
                    .success(function (data) {
                        if (data['brand'].length > 0) {
                            $scope.master_name = data['brand'] + ' ' + data['desc'];
                            $scope.img_mpn = $scope.newEntry.mpn;
                            $scope.valid_mpn = true;
                        }
                        else {
                            $scope.$popError('Invalid MPN');
                            $scope.img_mpn = null;
                            $scope.master_name = '';
                            $scope.valid_mpn = false;
                        }
                    })
                    .error(function (data) {
                        $scope.$popError('Unable to find MPN');
                        $scope.img_mpn = null;
                        $scope.master_name = '';
                        $scope.valid_mpn = false;
                    });
            }
        };
    }])

    .controller('OrderCreateController', ['$scope', '$http', function($scope, $http)
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

            $scope.fulfillmentOptions =
                [
                    {id: 1, title: 'Direct'},
                    {id: 3, title: 'EOC'}   // TODO: Make it dynamic based on our warehouses
                ];

            $scope.speedOptions =   // TODO: Make it dynamic
                [
                    {id: 'Standard / Ground', title: 'Standard / Ground'},
                    {id: 'Expedited / Express', title: 'Expedited / Express'},
                    {id: 'Second Day', title: 'Second Day'},
                    {id: 'Next Day / Overnight', title: 'Next Day / Overnight'},
                    {id: 'International', title: 'International'},
                    {id: 'ePacket', title: 'ePacket'}
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
                    fulfillment: $scope.fulfillmentOptions[0],
                    status: $scope.statusOptions[0],
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
                        window.location.replace('http://integra2.eocenterprise.com/#/orders/view/' + data.order_id);
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
    }])

    .controller('ShipmentCreateLabelController', ['$scope', '$http', '$routeParams', function($scope, $http, $routeParams)
    {
        $scope.newEntry = { country: 'US' };

        $scope.$on('$viewContentLoaded', function()
        {
            $scope.mailClassOptions = function()
            {
                if ($scope.newEntry.country == 'US')
                    return [
                        {id: 'First', title: 'First-Class Mail'},
                        {id: 'Priority', title: 'Priority Mail'},
                        {id: 'PriorityExpress', title: 'Priority Mail Express'}
                    ];
                else return [
                    {id: 'FirstClassMailInternational', title: 'First-Class Mail International'},
                    {id: 'PriorityMailInternational', title: 'Priority Mail International'},
                    {id: 'PriorityMailExpressInternational', title: 'Priority Mail Express International'}
                ];
            }

            $scope.freshEntry = function()
            {
                $scope.submitWait = true;
                $scope.newEntry =
                {
                    name: '',
                    address1: '',
                    address2: '',
                    city: '',
                    state: '',
                    zip: '',
                    country: 'US',
                    mailClass: $scope.mailClassOptions()[0],
                    requestedShipping: '',
                    expedited: false,
                    material: '',
                    items: []
                };

                $scope.addItem();
            };

            $scope.addItem = function()
            {
                $scope.newEntry.items.push(
                    {
                        description: '',
                        quantity: 1,
                        weight: null,
                        value: 10,
                        image: null
                    });
            }

            $scope.saveEntry = function(entry)
            {
                $scope.submitWait = true;

                $http.post('/api/shipments', entry)
                    .success(function (data)
                    {
                        // TODO: Change after migration
                        window.location.replace('http://integra2.eocenterprise.com/#/orders/view/' + data.order_id);
                    })
                    .error(function (data)
                    {
                        $scope.$popError("Error while creating the label", data);
                        $scope.submitWait = false;
                    });
            };

            $scope.freshEntry();

            if ($routeParams.id)
            {
                $http.get('/api/shipments/create/' + $routeParams.id).success(function (data)
                {
                    $scope.newEntry = data;
                    $scope.mailClass = $scope.mailClassOptions()[0];
                });
            }
        });
    }])

    .controller('PaypalInvoiceCreateController', ['$scope', '$http', '$location', function($scope, $http, $location)
    {
        $scope.merchants =
            [
                {code: 'qeautoparts', name: 'Q&E Auto Parts'},
                {code: 'need4autoparts', name: 'Need 4 Auto Parts'},
                {code: 'eocparts', name: 'EOC Parts'},
                {code: 'europortparts', name: 'Euro Port Parts'},
                {code: 'b2cautoparts', name: 'B2C Auto Parts'},
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

        $scope.fulfillmentOptions =
            [
                {id: 1, title: 'Direct'},
                {id: 3, title: 'EOC'}   // TODO: Make it dynamic based on our warehouses
            ];

        $scope.speedOptions =   // TODO: Make it dynamic
            [
                {id: 'Standard / Ground', title: 'Standard / Ground'},
                {id: 'Expedited / Express', title: 'Expedited / Express'},
                {id: 'Second Day', title: 'Second Day'},
                {id: 'Next Day / Overnight', title: 'Next Day / Overnight'},
                {id: 'International', title: 'International'},
                {id: 'ePacket', title: 'ePacket'}
            ];

        $scope.freshEntry = function()
        {
            $scope.submitWait = true;
            $scope.newEntry =
            {
                email: '',
                merchant: $scope.merchants[0],
                shipping_speed: $scope.speedOptions[0],
                fulfillment: $scope.fulfillmentOptions[0],
                agent: localStorage.agent,
                remarks: '',
                related_record_num: '',
                related_sales_id: null,
                shipping_cost: 0,
                misc_item: '',
                misc_amount: null,
                items:
                    [
                        {quantity: 1, sku: '', description: '', unit_price: null, weight: null, supplier: null, supplier_cost: null, loading: false},
                        {quantity: null, sku: '', description: '', unit_price: null, weight: null, supplier: null, supplier_cost: null, loading: false},
                        {quantity: null, sku: '', description: '', unit_price: null, weight: null, supplier: null, supplier_cost: null, loading: false}
                    ]
            };
        };

        $scope.findOrder = function()
        {
            $scope.newEntry.related_sales_id = 0;
            $scope.submitWait = true;

            $http.get('/api/paypal/order/' + $scope.newEntry.related_record_num)
                .success(function (data)
                {
                    if (data)
                    {
                        $scope.newEntry.related_sales_id = data.id;
                        $scope.newEntry.misc_amount = -data.total;
                        $scope.newEntry.misc_item = 'Previous purchase credit';
                    }
                    else $scope.$popError("Invalid related order number", null, 'Please check your input');
                    $scope.validate();
                })
                .error(function (data)
                {
                    $scope.$popError("Invalid related order number");
                });
        };

        $scope.addItem = function()
        {
            $scope.newEntry.items.push({quantity: 1, sku: '', description: '', unit_price: null, weight: null, supplier: null, supplier_cost: null, loading: false});
        };

        $scope.deleteItem = function(index)
        {
            $scope.newEntry.items.splice(index, 1);
            $scope.validate();
        };

        $scope.validate = function()
        {
            if ($scope.newEntry.related_record_num.length > 0 && !$scope.newEntry.related_sales_id)
            {
                $scope.submitWait = true;
                return;
            }

            $scope.newEntry.total = 0;

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

                if ($scope.newEntry.items[i].unit_price == null)
                {
                    notOk = true;
                    continue;
                }

                $scope.newEntry.total += ($scope.newEntry.items[i].unit_price * $scope.newEntry.items[i].quantity);
            }

            $scope.newEntry.total += $scope.newEntry.shipping_cost + $scope.newEntry.misc_amount;
            $scope.submitWait = notOk;
        };

        $scope.querySku = function(item)
        {
            if (item.sku.length == 0)
            {
                $scope.validate();
                return;
            }

            item['description'] = 'Loading...';
            item['unit_price'] = null;
            item['weight'] = null;
            item['supplier'] = null;
            item['supplier_cost'] = null;
            item['loading'] = true;

            $scope.validate();

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

            $http.get('/api/proxy/' + supplier + '_ajax/' + item.sku)
                .success(function (data)
                {
                    item['weight'] = data['weight'];
                    item['supplier_cost'] = data['price'];
                    item['description'] = data['desc'];
                    item['loading'] = false;
                    $scope.validate();
                })
                .error(function (data)
                {
                    item['description'] = data['desc'];
                    item['loading'] = false;
                    $scope.validate();
                });
        };

        $scope.saveEntry = function(entry)
        {
            $scope.submitWait = true;

            localStorage.agent = entry.agent;

            $http.post('/api/paypal', entry)
                .success(function (data)
                {
                    $location.path('/paypal/view/' + data);
                })
                .error(function (data)
                {
                    $scope.$popError("Error while saving the invoice", data);
                    $scope.submitWait = false;
                });
        };

        $scope.$on('$viewContentLoaded', function()
        {
            $scope.freshEntry();
        });
    }])

    .controller('PaypalInvoiceViewController', ['$scope', '$http', '$routeParams', function($scope, $http, $routeParams)
    {
        $scope.$on('$viewContentLoaded', function()
        {
            $http.get('/api/paypal/' + $routeParams.id)
                .success(function (data)
                {
                    $scope.entry = data;
                });
        });
    }])

    .controller('PaypalInvoiceListController', ['$scope', '$http', function($scope)
    {
        $scope.$on('$viewContentLoaded', function()
        {
            $scope.crud($scope,
                {
                    getUrl: '/api/paypal/',
                    openUrl: '/paypal/view/',
                    defaultSort: {invoice_date: 'desc'}
                });
        });
    }])

    .controller('SupplierReturnListController', ['$scope', '$http', function($scope)
    {
        $scope.$on('$viewContentLoaded', function()
        {
            $scope.crud($scope,
                {
                    getUrl: '/api/supplier_returns/',
                    openUrl: '/supplier_returns/view/',
                    defaultSort: {return_date: 'desc'}
                });
        });
    }])

    .controller('SupplierReturnViewController', ['$scope', '$http', '$routeParams', function($scope, $http, $routeParams)
    {
        $scope.$on('$viewContentLoaded', function()
        {
            $http.get('/api/supplier_returns/' + $routeParams.id)
                .success(function (data)
                {
                    $scope.entry = data;
                });
        });
    }])

    .controller('SupplierInvoiceListController', ['$scope', '$http', function($scope)
    {
        $scope.$on('$viewContentLoaded', function()
        {
            $scope.crud($scope,
                {
                    getUrl: '/api/supplier_invoices/',
                    openUrl: '/supplier_invoices/view/',
                    defaultSort: {order_date: 'desc'}
                });
        });
    }])

    .controller('SupplierInvoiceViewController', ['$scope', '$http', '$routeParams', function($scope, $http, $routeParams)
    {
        $scope.$on('$viewContentLoaded', function()
        {
            $http.get('/api/supplier_invoices/' + $routeParams.id)
                .success(function (data)
                {
                    $scope.entry = data;
                });
        });
    }])

    .controller('ProductListController', ['$scope', '$http', function($scope)
    {
        $scope.$on('$viewContentLoaded', function()
        {
            $scope.crud($scope,
                {
                    getUrl: '/api/products/',
                    openUrl: '/products/view/'
                });
        });
    }])

    .controller('ProductViewController', ['$scope', '$http', '$routeParams', function($scope, $http, $routeParams)
    {
        $scope.deleteImage = function(index)
        {
            if (!confirm('Are you sure you want to delete this product image?'))
                return;

            var name = $scope.product.images[index];
            $scope.product.images.splice(index, 1);
            $http.delete('/api/products/images/' + name);
        };

        $scope.setGlobalAttrib = function(name, value)
        {
            for (var i = 0; i < $scope.product.attribs.length; i++)
            {
                if ($scope.product.attribs[i].name == name)
                {
                    $scope.product.attribs[i].values[0].value = value;
                    return;
                }
            }
        };

        $scope.syncAttribs = function()
        {
            $scope.submitWait = true;
            $scope.attribSyncing = true;

            $http.get('/api/proxy/' + $scope.supplierName + '_ajax/' + $scope.product.sku)
                .success(function (data)
                {
                    $scope.submitWait = false;
                    $scope.attribSyncing = false;

                    if (data.length == 0 || data['price'] == '?')
                    {
                        $scope.$popError("Unable to extract product data from supplier");
                        return;
                    }

                    if (data['desc'] && data['desc'].length > 0)
                        $scope.setGlobalAttrib('Name', data['desc']);

                    if (data['brand'] && data['brand'].length > 0)
                        $scope.setGlobalAttrib('Brand', data['brand']);

                    if (data['price'] && data['price'].length > 0)
                        $scope.setGlobalAttrib('Item Cost', data['price']);

                    if (data['core'] && data['core'].length > 0)
                        $scope.setGlobalAttrib('Core Price', data['core']);

                    if (data['weight'] && data['weight'].length > 0)
                        $scope.setGlobalAttrib('Weight', data['weight']);
                })
                .error(function (data)
                {
                    $scope.$popError("Error while connecting to supplier");
                    $scope.submitWait = false;
                    $scope.attribSyncing = false;
                });
        };

        $scope.inputClass = function(a)
        {
            var a2 = a.name.toLowerCase();
            if (a2.indexOf('price') >= 0 || a2.indexOf('cost') >= 0 || a2.indexOf('weight') >= 0) return 'short-edit';
            else return '';
        };

        $scope.openSales = function(id)
        {
            window.open('http://integra2.eocenterprise.com/#/orders/view/' + id, '_blank');
        };

        $scope.loadCompat = function()
        {
            $scope.crud($scope,
                {
                    getUrl: '/api/products/compatibilities/' + $scope.product.sku
                });
        };

        $scope.loadSales = function()
        {
            $scope.crud($scope,
                {
                    getUrl: '/api/products/sales/' + $scope.product.sku,
                    tables:
                        [
                            {
                                name: 'tableParamsSales'
                            }
                        ]
                });
        };

        $scope.saveProduct = function()
        {
            $scope.submitWait = true;

            var postData =
            {
                sku: $scope.product.sku,
                attribs: []
            };

            for (var i = 0; i < $scope.product.attribs.length; i++)
            {
                var a =
                {
                    id: $scope.product.attribs[i].attrib_id,
                    values: []
                };

                for (var j = 0; j < $scope.product.attribs[i].values.length; j++)
                {
                    if ($scope.product.attribs[i].values[j].store_id != 0 && !$scope.product.attribs[i].values[j].override)
                        continue;

                    a.values.push({
                        store: $scope.product.attribs[i].values[j].store_id,
                        value: $scope.product.attribs[i].values[j].value
                    });
                }

                postData.attribs.push(a);
            }

            $http.put('/api/products/' + $scope.product.entity_id, postData)
                .success(function (data)
                {
                    $scope.$popOk('Product updated successfully');
                    $scope.submitWait = false;
                })
                .error(function (data)
                {
                    $scope.$popError("Error while saving the product", data);
                    $scope.submitWait = false;
                });
        };

        $scope.$on('$viewContentLoaded', function()
        {
            $http.get('/api/products/' + $routeParams.sku)
                .success(function (data)
                {
                    $scope.product = data;

                    if ($scope.product.sku.indexOf('EW') === 0)
                    {
                        $scope.canSyncAttrib = false;
                    }
                    else if ($scope.product.sku.indexOf('EK') === 0)
                    {
                        $scope.canSyncAttrib = false;
                    }
                    else if ($scope.product.sku.indexOf('EDP') === 0)
                    {
                        $scope.canSyncAttrib = false;
                    }
                    else if ($scope.product.sku.indexOf('.') >= 0)
                    {
                        $scope.canSyncAttrib = true;
                        $scope.supplier = 2;
                        $scope.supplierName = 'ssf';
                    }
                    else
                    {
                        $scope.canSyncAttrib = true;
                        $scope.supplier = 1;
                        $scope.supplierName = 'imc';
                    }
                });
        });
    }])

    .controller('ProductQuickLookupController', ['$scope', '$http','_', function($scope, $http,_)
    {
        $scope.searching = false;
        $scope.loading = false;
        $scope.search = function()
        {
            var fd = new FormData();
            if($scope.file){
                fd.append('file', $scope.file);
                fd.append('remarks', $scope.remarks);
            }else{
                fd.append('file', null);
            }
            if($scope.mpn){
                fd.append('mpn', $scope.mpn);
            }else{
                fd.append('mpn', null);
            }
            $scope.searching = true;
            $http({
                    method: 'POST',
                    url: '/api/products/lookup',
                    data:fd,
                    headers: {'Content-Type': undefined},
                    transformRequest: angular.identity   
                }).then(function successCallback(response) {
                    $scope.searching = false;
                    if (response.data == '' || response.data.length == 0)
                        if($scope.file){
                            $scope.$popOk('Upload file success !!', null, 'Please try a different MPN.');
                        }else{
                            $scope.$popError('No matching products', null, 'Please try a different MPN.');
                        }
                        
                    else
                    {
                        for (var i = 0; i < response.data.length; i++) {
                            response.data[i].quantity = 0;
                            Object.getOwnPropertyNames(response.data[i]).forEach(function (prop) {
                                if (prop.indexOf('site_') !== 0) return;
                                response.data[i].quantity += response.data[i][prop];
                            });
                        }
                    }

                    $scope.data = response.data;
                    focus('search');

                }, function errorCallback(response) {
                    // called asynchronously if an error occurs
                    // or server returns response with an error status.
                });
        };

        $scope.showAlt = function(alt)
        {
            if (Array.isArray(alt)) return alt.join(', ');
            else return alt;
        };

        $scope.$on('$viewContentLoaded', function()
        {
            $scope.$focus('search');
        });

        $scope.progress = [];
        $scope.getProgress = function(){
            $http({
                    method: 'GET',
                    url: '/api/products/lookup-progress',  
                }).then(function successCallback(response) {
                    if(response.status == 200){
                        $scope.progress = response.data;
                    }

                }, function errorCallback(response) {
                    // called asynchronously if an error occurs
                    // or server returns response with an error status.
                });
        }
        $scope.getProgress();
        $scope.deleteLookup = function(id){
            $scope.loading = true;
            $http({
                    method: 'GET',
                    url: '/api/products/delete-lookup-progress/'+id,  
                }).then(function successCallback(response) {
                    console.log(response);
                    if(response.status == 200){
                        var index = _.findIndex($scope.progress, function (val) {
                            return val.id == id;
                        });
                        if(index > -1){
                            $scope.progress.splice(index, 1);
                        }
                        $scope.loading = false;
                    }
                }, function errorCallback(response) {
                });
        }
    }])

    .controller('ProductSearchAmazonController', ['$scope', '$http', function($scope, $http)
    {
        $scope.searching = false;

        $scope.search = function()
        {
            $scope.searching = true;

            $http.get('/api/products/amazon/search?pages=2&keywords=' + encodeURIComponent($scope.mpn))
                .success(function (data)
                {
                    $scope.searching = false;
                    $scope.data = data;

                    if (data == '' || data.length == 0)
                        $scope.$popError('No matching products', null, 'Please try a different MPN.');

                    focus('search');
                });
        };

        $scope.listByASIN = function(d)
        {
            var tmp = prompt('Enter SKU');
            if (!tmp || tmp.length == 0) return;
            var sku = tmp.toUpperCase().trim();

            if (sku.indexOf('EDP') == 0)
            {
                alert('The SKU cannot start with "EDP". Please enter the original SKU.');
                return;
            }

            tmp = prompt('Enter Price');
            if (!tmp || tmp.length == 0) return;
            var price = tmp;

            tmp = prompt('Enter Quantity Available');
            if (!tmp || tmp.length == 0) return;
            var quantity = tmp;

            $http.post('/api/products/amazon/list_by_asin', {asin: d.asin, sku: sku, price: price, quantity: quantity})
                .success(function (data)
                {
                    d.queue_status = 0;
                    $scope.$popOk('Item has been added to the listing queue');
                })
                .error(function (data)
                {
                    $scope.$popError("Error while queueing the item for listing", data);
                });
        };

        $scope.$on('$viewContentLoaded', function()
        {
            $scope.$focus('search');
        });
    }])

    .controller('InventoryController', ['$scope', function($scope)
    {
    }])

    .controller('MonitorDownloadController', ['$scope', function($scope)
    {
    }])

    .controller('SupplierReturnCreateController', ['$scope', '$http', '$location', function($scope, $http, $location)
    {
        $scope.$on('$viewContentLoaded', function()
        {
            $scope.reasonOptions =
                [
                    {id: 3, title: 'New - Customer Canceled'},
                    {id: 2, title: 'New - Catalog Error'},
                    {id: 4, title: 'New - Ordered in Error'},
                    {id: 7, title: 'New - Received Wrong Part'},
                    {id: 5, title: 'New - Pricing Issue'},
                    {id: 1, title: 'New - Brand Issue'},
                    {id: 25, title: 'Defective - Doesn\'t Charge'},
                    {id: 19, title: 'Defective - Doesn\'t Crank'},
                    {id: 23, title: 'Defective - Doesn\'t Pump'},
                    {id: 36, title: 'Defective - Intermittent'},
                    {id: 15, title: 'Defective - Leaking'},
                    {id: 34, title: 'Defective - Machined incorrectly'},
                    {id: 40, title: 'Defective - Mis-Labeled'},
                    {id: 18, title: 'Defective - Mis-fire'},
                    {id: 21, title: 'Defective - Missing Gaskets/Hardware'},
                    {id: 16, title: 'Defective - Noisy'},
                    {id: 41, title: 'Defective - Received Used Part'},
                    {id: 29, title: 'Defective - Seized'},
                    {id: 30, title: 'Defective - Shipping Damage'},
                    {id: 44, title: 'Defective - Triggered Codes'},
                    {id: 31, title: 'Defective - Warped'},
                    {id: 32, title: 'Defective - Wont Hold Pressure'},
                    {id: 39, title: 'Defective - Wrong Dimensions'}
                ];

            $scope.crud($scope,
                {
                    getUrl: '/api/supplier_returns/create'
                });

            $scope.freshEntry = function()
            {
                $scope.basket = [];
            }

            $scope.saveEntry = function()
            {
                $scope.submitWait = true;

                $http.post('/api/supplier_returns', $scope.basket)
                    .success(function (data)
                    {
                        if (data == '0')
                            $location.path('/supplier_returns/list');
                        else
                            $location.path('/supplier_returns/view/' + data);
                    })
                    .error(function (data)
                    {
                        $scope.$popError("Error while saving the return request", data);
                        $scope.submitWait = false;
                    });
            };

            $scope.addItem = function(item)
            {
                item.added = true;
                item.quantity = item.max_quantity;
                item.reason = $scope.reasonOptions[0];

                var existing = false;

                for (var i = 0; i < $scope.basket.length; i++)
                {
                    if ($scope.basket[i].item_id == item.item_id)
                    {
                        existing = true;
                        break;
                    }
                }

                if (!existing)
                    $scope.basket.push(item);
            };

            $scope.deleteItem = function(index)
            {
                $scope.basket[index].added = false;
                $scope.basket.splice(index, 1);
            };

            $scope.freshEntry();
        });
    }])

    .controller('EbayKitHunterController', ['$scope', '$http', '$interval', function($scope, $http, $interval)
    {
        $scope.$on('$viewContentLoaded', function()
        {
            $scope.freshEntry = function()
            {
                $scope.newEntry =
                {
                    id: 0,
                    job_type: 1,
                    versions: 1,
                    components: [],
                    addons: []
                };
            };

            $scope.currentPartType = '';
            $scope.lastPartType = '';
            $scope.partTypes = [];
            $scope.makes = [];

            $http.get('/api/ebay/list_makes')
                .success(function (data) {
                    $scope.makes = data;
                });

            $scope.getPartTypes = function()
            {
                // only check for part types when changed, every 3 seconds
                if ($scope.lastPartType != $scope.currentPartType)
                {
                    if ($scope.currentPartType && $scope.currentPartType.length >= 3) {
                        $http.get('/api/ebay/part_types/' + encodeURIComponent($scope.currentPartType))
                            .success(function (data) {
                                $scope.partTypes = data;
                            });
                    }

                    $scope.lastPartType = $scope.currentPartType;
                }
            };

            $interval($scope.getPartTypes, 3000);

            $scope.addComponent = function()
            {
                $scope.newEntry.components.push($scope.currentPartType);
                $scope.currentPartType = null;
            };

            $scope.addComponent2 = function()
            {
                $scope.newEntry.components.push($scope.currentQtyBase + '~' + $scope.currentMPNs);
                $scope.currentQtyBase = null;
                $scope.currentMPNs = null;
            };

            $scope.formatComponent = function(component)
            {
                if ($scope.newEntry.job_type == 1)
                    return component;
                else if ($scope.newEntry.job_type == 2)
                {
                    var fields = component.split('~');
                    return fields[0] + 'x ' + fields[1];
                }
            };

            $scope.changeJobType = function()
            {
                $scope.newEntry.components = [];
            }

            $scope.deleteComponent = function(index)
            {
                $scope.newEntry.components.splice(index, 1);
            };

            $scope.addAddon = function()
            {
                if (!$scope.currentSupplier || !$scope.currentMPN || $scope.currentMPN.length == 0) return;

                $scope.newEntry.addons.push({supplier: $scope.currentSupplier, mpn: $scope.currentMPN, qty: $scope.currentQty});
                $scope.currentMPN = null;
                $scope.currentSupplier = null;
                $scope.currentQty = null;
            };

            $scope.deleteAddon = function(index)
            {
                $scope.newEntry.addons.splice(index, 1);
            };

            $scope.crud($scope,
                {
                    getUrl: '/api/ebay/kit_hunter/',
                    openUrl: '/ebay/kit_hunter/',
                    refreshTablesAfterEdit: true
                });
        });
    }])

    .controller('EbayKitHunterResultsController', ['$scope', '$http', '$routeParams', function($scope, $http, $routeParams)
    {
        $scope.curPage = 1;
        $scope.results = {
            pages: 1,
            suppliers: []
        };

        $scope.getNumber = function(num)
        {
            return new Array(num);
        };

        $scope.loadPage = function(page)
        {
            $http.get('/api/ebay/kit_hunter/' + $routeParams.id + '/' + (page || '1'))
                .success(function (data) {
                    $scope.results = data;
                });
        };

        $scope.$on('$viewContentLoaded', function()
        {
            $scope.loadPage(1);

            $scope.deleteKit = function(supplier, index)
            {
                if (!confirm('Are you sure you want to delete this kit?'))
                    return;

                $http.delete('/api/ebay/kit_hunter/' + supplier.kits[index].sku);
                supplier.kits.splice(index, 1);
            };

            $scope.publishKit = function(kit)
            {
                if (!confirm('Are you sure you want to publish this kit to eBay?'))
                    return;

                kit.publish_status = 1;
                $http.put('/api/ebay/kit_hunter/' + kit.sku + '/publish');
            };
        });
    }])

    .controller('EbayMonitorController', ['$scope', '$http', function($scope, $http)
    {
        $scope.curPage = 1;
        $scope.results = {
            pages: 1,
            keywords: null
        };

        $scope.getNumber = function(num)
        {
            return new Array(num);
        };

        $scope.loadPage = function(page, search)
        {
            $http.get('/api/ebay/monitor/' + (page || '1') + '?search=' + (search || ''))
                .success(function (data) {
                    $scope.results = data;
                });
        };

        $scope.$on('$viewContentLoaded', function()
        {
            $scope.loadPage(1, '');

            $scope.unmonitor = function(kw, index)
            {
                if (!confirm('Are you sure you want to stop monitoring this listing?'))
                    return;

                $http.delete('/api/ebay/monitor/' + kw.items[index].id);
                kw.items.splice(index, 1);

                $scope.check_changed(kw);
            };

            $scope.ackmonitor = function(kw, index)
            {
                $http.put('/api/ebay/monitor/' + kw.items[index].id + '/ack');
                kw.items[index].prev_title = kw.items[index].cur_title;
                kw.items[index].prev_price = kw.items[index].cur_price;
                kw.items[index].prev_sold = kw.items[index].cur_sold;
                kw.items[index].sold_change = 0;
                kw.items[index].days = 0;
                kw.items[index].changed = false;
                kw.items[index].started_selling = false;
                kw.items[index].below_min = false;

                $scope.check_changed(kw);
            };

            $scope.check_changed = function(kw)
            {
                var changed = false;
                var i;
                for (i = 0; i < kw.items.length; i++)
                {
                    if (kw.items[i].changed)
                    {
                        changed = true;
                        break;
                    }
                }
                kw.changed = changed;
            }
        });
    }])

    .controller('AmazonMonitorController', ['$scope', '$http', function($scope, $http)
    {
        $scope.curPage = 1;
        $scope.results = {
            pages: 1,
            asins: null
        };

        $scope.getNumber = function(num)
        {
            return new Array(num);
        };

        $scope.loadPage = function(page, search)
        {
            $http.get('/api/amazon/monitor/' + (page || '1') + '?search=' + (search || ''))
                .success(function (data) {
                    $scope.results = data;
                });
        };

        $scope.$on('$viewContentLoaded', function()
        {
            $scope.loadPage(1, '');

            $scope.ackmonitor = function(kw, index)
            {
                $http.put('/api/amazon/monitor/' + kw.items[index].id + '/ack');
                kw.items[index].into_buybox = false;
                kw.items[index].outof_buybox = false;
                kw.items[index].below_min = false;

                $scope.check_changed(kw);
            };

            $scope.check_changed = function(kw)
            {
                var into_buybox = false;
                var outof_buybox = false;
                var below_min = false;
                var i;
                for (i = 0; i < kw.items.length; i++)
                {
                    if (kw.items[i].into_buybox) into_buybox = true;
                    if (kw.items[i].outof_buybox) outof_buybox = true;
                    if (kw.items[i].below_min) below_min = true;
                }
                kw.into_buybox = into_buybox;
                kw.outof_buybox = outof_buybox;
                kw.below_min = below_min;
            };
        });
    }])

    .controller('EbaySpeedListerController', ['$scope', '$http', function($scope, $http)
    {
        $scope.mpn = '';
        $scope.partBrand = '';
        $scope.partName = '';
        $scope.qtyRequired = '';
        $scope.validMpn = false;
        $scope.versions = [];
        $scope.results = null;
        $scope.submitWait = false;

        $scope.saveEntry = function()
        {
            $scope.submitWait = true;

            $http.post('/api/ebay/generate_versions', {mpn: $scope.mpn, versions: $scope.versions})
                .success(function (data)
                {
                    $scope.results = data;
                    $scope.submitWait = false;
                })
                .error(function (data)
                {
                    $scope.$popError('Error while generating versions');
                    $scope.submitWait = false;
                });
        };

        $scope.deleteVersion = function(index)
        {
            $scope.versions.splice(index, 1);
        };

        $scope.addVersion = function()
        {
            $scope.versions.push({num: $scope.currentNum, qty: $scope.currentQty, price: $scope.currentPrice});
            $scope.currentNum = '';
            $scope.currentQty = '';
            $scope.currentPrice = '';
        };

        $scope.deleteResult = function(index)
        {
            if (!confirm('Are you sure you want to delete this version?'))
                return;

            $scope.results.splice(index, 1);
        };

        $scope.publishResult = function(version)
        {
            if (!confirm('Are you sure you want to publish this version to eBay?'))
                return;

            version.queued = 1;

            $http.post('/api/ebay/publish', version)
                .success(function (data)
                {
                    if (data.item_id && data.item_id.length > 0)
                    {
                        version.item_id = data.item_id;
                    }
                    else
                    {
                        $scope.$popError('Error while publishing version', null, data.error);
                        version.queued = 2;
                    }
                })
                .error(function (data)
                {
                    $scope.$popError('Error while publishing version');
                    version.queued = 2;
                });
        };

        $scope.getEbayUrl = function(version)
        {
            return 'http://www.ebay.com/itm/' + version.item_id;
        };

        $scope.queryMpn = function()
        {
            if ($scope.mpn.length == 0)
            {
                $scope.partBrand = '';
                $scope.partName = '';
                $scope.qtyRequired = '';
                $scope.validMpn = false;
                return;
            }

            var supplier;

            if ($scope.mpn.indexOf('.') >= 0)
                supplier = 'ssf';
            else
                supplier = 'imc';

            $scope.partBrand = 'Loading...';
            $scope.partName = '';
            $scope.qtyRequired = '';
            $scope.validMpn = false;

            $http.get('/api/proxy/' + supplier + '_ajax/' + $scope.mpn)
                .success(function (data)
                {
                    $scope.partBrand = data['brand'];
                    $scope.partName = data['desc'];
                    $scope.validMpn = true;
                })
                .error(function (data)
                {
                    $scope.partBrand = 'Item not found';
                    $scope.partName = '';
                    $scope.qtyRequired = '';
                    $scope.validMpn = false;
                });

            $http.get('/api/products/qty_required/' + $scope.mpn)
                .success(function (data) {
                    $scope.qtyRequired = data;
                });
        };
    }])
;
