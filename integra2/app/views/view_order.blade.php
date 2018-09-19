<!DOCTYPE html>
<html lang="en-us" class="no-js">
    <head>
        <meta charset="utf-8">
        <title>Invoice</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

        <link rel="stylesheet" type="text/css" href="/css/bootstrap.min.css">
        <link rel="stylesheet" type="text/css" href="/css/font-awesome.min.css">
        <link rel="stylesheet" type="text/css" href="/css/smartadmin-production.min.css">
        <link rel="stylesheet" type="text/css" href="/css/smartadmin-skins.min.css">
        <link rel="stylesheet" type="text/css" href="/css/integra.css">
        <link rel="stylesheet" href="http://fonts.googleapis.com/css?family=Open+Sans:400italic,700italic,300,400,700">
    </head>
    <body>
        <div id="content" role="content">
            <div class="row">
                <article class="col-xs-12 col-sm-8 col-md-6">
                    <div class="jarviswidget jarviswidget-color-teal">
                        <header>
                            <span class="widget-icon"> <i class="fa fa-edit"></i> </span>
                            <h2>Order Details</h2>
                        </header>
                        <div>
                            <div class="widget-body no-padding">

                                <table class="table table-hover">
                                    <tbody>
                                    <tr>
                                        <th>Store</th>
                                        <td>
                                            {{{ $entry['store']'] }}}
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Record Number</th>
                                        <td>
                                            {{{ $entry['record_num'] }}}
                                        </td>
                                    </tr>
                                    @if ($entry['parent_order'])
                                    <tr>
                                        <th>Parent Order</th>
                                        <td>
                                            {{{ $entry['parent_order'] }}}
                                        </td>
                                    </tr>
                                    @endif
                                    @if (count($entry['sub_orders'])) 
                                    <tr>
                                        <th>Suborders</th>
                                        <td>
                                            @foreach ($entry['sub_orders'] as $subOrder)
                                            <div>

                                                {{{ $subOrder.split('~')[1] }}}
                                                
                                            </div>
                                            @endforeach
                                        </td>
                                    </tr>
                                    @endif
                                    <tr>
                                        <th>Order Date</th>
                                        <td>
                                            {{{ entry['order_date'] }}}
                                        </td>
                                    </tr>
                                    <tr ng-show="entry.internal_id && entry.record_num != entry.internal_id">
                                        <th>Store Order Number</th>
                                        <td>
                                            {{{ entry['internal_id'] }}}
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Customer's Name</th>
                                        <td>
                                            {{{ $entry['buyer_name'].trim() }}}
                                            <!-- <div ng-show="entry.buyer_id && (entry.buyer_name.trim() != entry.buyer_id.trim())">
                                                {{{ $entry.buyer_id.trim() }}}
                                            </div> -->
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Email</th>
                                        <td>
                                            {{{ $entry['email'] }}}
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Street</th>
                                        <td>{{{ $entry['street'] }}}</td>
                                    </tr>
                                    <tr>
                                        <th>City</th>
                                        <td>{{{ $entry['city'] }}}</td>
                                    </tr>
                                    <tr>
                                        <th>State</th>
                                        <td>{{{ $entry['state'] }}}</td>
                                    </tr>
                                    <tr>
                                        <th>Country</th>
                                        <td>{{{ $entry['country'] }}}</td>
                                    </tr>
                                    <tr>
                                        <th>Zip</th>
                                        <td>{{{ $entry['zip'] }}}</td>
                                    </tr>
                                    <tr>
                                        <th>Phone</th>
                                        <td>{{{ $entry['phone'] }}}</td>
                                    </tr>
                                    <tr>
                                        <th>Requested Shipping</th>
                                        <td>
                                            <i class="fa fa-flash" ng-show="entry['speed=='Next Day / Overnight'"></i>
                                            <i class="fa fa-flash" ng-show="entry['speed=='Second Day'"></i>
                                            {{{ $entry['speed'] }}}
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Label</th>
                                        <td>
                                            {{{ $entry['label_date'] }}}
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Fulfillment</th>
                                        <td>
                                            <div class="btn-group" dropdown keyboard-nav="true">

                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Status</th>
                                        <td>
                                            {{{ $entry['status'] }}}
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Tracking</th>
                                        <td>
                                            @if ($entry['tracking'])
                                            <span>{{{ $entry['tracking_num'] }}} - {{{ $entry['carrier'] }}}</span>
                                            @endif
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="col-xs-12 col-sm-8 col-md-6">
                    <div class="row">
                        <article class="col-xs-12">
                            <div class="jarviswidget jarviswidget-color-teal">
                                <header>
                                    <span class="widget-icon"> <i class="fa fa-cubes"></i> </span>
                                    <h2>Shipping Components - {{{ $entry['weight_str'] }}}</h2>
                                    
                                </header>
                                <div>
                                    <div class="widget-body">
                                        <div class="ship-components" ng-repeat="component in entry['components">
                                            <div class="media">
                                                <img class="pull-left media-object ship-components-tn" src="http://catalog.eocenterprise.com/img/{{{ $component['sku']|nodash }}}/cl1-tneb"/>
                                                <div class="media-body">
                                                    <h4 class="media-heading">{{{ $component['qty'] }}}x {{{ $component['sku'] }}}</h4>
                                                    <div class="ship-components-text">{{{ $component['brand'] }}}</div>
                                                    <div class="ship-components-text">{{{ $component['name'] }}}</div>
                                                    <div class="ship-components-text" style="font-weight:bold;">{{{ $component['supplier'] }}}</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>

                    <div class="row">
                        <article class="col-xs-12">
                            <div class="jarviswidget jarviswidget-color-teal">
                                <header>
                                    <span class="widget-icon"> <i class="fa fa-truck"></i> </span>
                                    <h2>Supplier Orders</h2>

                                </header>
                                <div>
                                    <div class="widget-body no-padding">
                                        <table class="table table-hover">
                                            <thead>
                                            <tr>
                                                <th class="text-center">Warehouse</th>
                                                <th class="text-center">Order Number</th>
                                                <th class="text-center">Tracking Number</th>
                                                <th class="text-center">ETD</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <tr ng-repeat="source in entry['sources">
                                                <td class="text-center">{{{ $source['supplier'] }}}</td>
                                                <td class="text-center">{{{ $source['order_id'] }}}</td>
                                                <td class="text-center">{{{ $source['tracking_num'] }}}</td>
                                                <td class="text-center">{{{ $source['etd'] }}}</td>
                                            </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
                </article>
            </div>

            <div class="row">
                <article class="col-xs-12">
                    <div class="jarviswidget jarviswidget-color-teal">
                        <header>
                            <span class="widget-icon"> <i class="fa fa-list"></i> </span>
                            <h2>Items</h2>
                        </header>
                        <div>
                            <div class="widget-body no-padding">
                                <table class="table table-hover">
                                    <thead>
                                    <tr>
                                        <th class="text-center">Quantity</th>
                                        <th>SKU</th>
                                        <th>Description</th>
                                        <th class="text-right">Unit Price</th>
                                        <th class="text-right">Subtotal</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr ng-repeat="item in entry['items">
                                        <td class="text-center">{{{ $item['quantity'] }}}</td>
                                        <td>
                                            {{{ $item['sku'] }}}
                                            <div ng-show="item['ebay_item_id">
                                                <i>eBay: {{{ $item['ebay_item_id'] }}}</i>
                                            </div>
                                            <div ng-show="item['amazon_asin">
                                                <i>Amazon: {{{ $item['amazon_asin'] }}}</i>
                                            </div>
                                        </td>
                                        <td>{{{ $item['description'] }}}</td>
                                        <td class="text-right">{{{ $item['unit_price | number:2 }}}</td>
                                        <td class="text-right">{{{ $item['quantity * item['unit_price) | number:2 }}}</td>
                                    </tr>
                                    </tbody>
                                    <tfoot>
                                    <tr>
                                        <th colspan="4" class="text-right">Subtotal</td>
                                        <td class="text-right">{{{ $entry['subtotal }}}</td>
                                    </tr>
                                    <tr>
                                        <th colspan="4" class="text-right">Shipping</td>
                                        <td class="text-right">{{{ $entry['shipping'] }}}</td>
                                    </tr>
                                    <tr class="info">
                                        <th colspan="4" class="text-right">GRAND TOTAL</td>
                                        <th class="text-right">{{{ $entry['total'] }}}</td>
                                    </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </article>
            </div>

            <div class="row">
                <article class="col-xs-12">
                    <div class="jarviswidget jarviswidget-color-teal">
                        <header>
                            <span class="widget-icon"> <i class="fa fa-pencil-square-o"></i> </span>
                            <h2>Order History</h2>
                        </header>
                        <div>
                            <div class="widget-body">
                                @if (count($entry['history']))
                                <div>
                                    <table class="table table-condensed table-bordered table-hover">
                                        <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Entered By</th>
                                            <th>Remarks</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <tr ng-repeat="log in entry['history">
                                            <td>{{{ $log['ts'] }}}</td>
                                            <td>{{{ $log['email'] }}}</td>
                                            <td class="wrap"><p ></p></td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                                @endif
                                
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </body>
</html>
