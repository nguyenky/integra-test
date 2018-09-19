<!DOCTYPE html>
<html lang="en-us" class="no-js">
<head>
    <meta charset="utf-8">
    <title>Return</title>
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
    <div class="pull-left">
        <img src="http://catalog.eocenterprise.com/skin/frontend/default/ma_carstore/images/logo-{{{ $order['store'] }}}.gif" height="32">
        <address>
            <br>
            <strong>{{{ $order['our_name'] }}}</strong>
            <br>
            {{{ $order['our_street'] }}}
            <br>
            {{{ $order['our_city'] }}}, {{{ $order['our_state'] }}}
            <br>
            {{{ $order['our_zip'] }}} USA
            <br>
            <i class="fa fa-phone"></i> {{{ $order['phone'] }}}
        </address>
    </div>
    <div class="pull-right">
        <h1 class="font-400">return</h1>
    </div>
    <div class="clearfix"></div>
    <br/>
    <div class="pull-left">
        <h4 class="semi-bold">{{{ $order['buyer_name'] }}}</h4>
        <address>
            {{{ $order['street'] }}}
            <br>
            {{{ $order['city'] }}}, {{{ $order['state'] }}}
            <br>
            {{{ $order['zip'] }}} {{{ $order['country'] }}}
            <br>
            <i class="fa fa-phone"></i> {{{ $order['phone'] }}}
        </address>
    </div>

    <div class="pull-right">
        <div>
            <div>
                <strong>INVOICE NO :</strong>
                <span class="pull-right">{{{ $order['record_num'] }}}</span>
            </div>
        </div>
        <div>
            <div>
                <strong>INVOICE DATE :</strong>
                <span class="pull-right">{{{ explode(' ', $order['order_date'])[0] }}}</span>
            </div>
        </div>
        <br>
        <div class="well well-sm  bg-color-darken txt-color-white no-border">
            <div class="fa-lg">
                Total Due :
                <span class="pull-right"> &nbsp; {{{ number_format($order['total'], 2) }}} {{{ $order['currency'] }}}</span>
            </div>
        </div>
        <br>
        <br>
    </div>

    <div class="clearfix"></div>

    <table class="table table-hover">
        <thead>
        <tr>
            <th class="text-center">SKU</th>
            <th class="text-left">DESCRIPTION</th>
            <th class="text-center">QUANTITY</th>
            <th class="text-right">UNIT PRICE</th>
            <th class="text-right">SUBTOTAL ({{$order['currency']}})</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($lines as $line)
            <tr>
                <td class="text-left"><strong>{{{ $line['sku_noprefix'] }}}</strong></td>
                <td class="text-left">{{{ $line['description'] }}}</td>
                <td class="text-center">{{{ number_format($line['quantity'], 0) }}}</td>
                <td class="text-right">{{{ number_format($line['unit_price'], 2) }}}</td>
                <td class="text-right">{{{ number_format($line['total'], 2) }}}</td>
            </tr>
            @if (isset($line['components']))
                @foreach ($line['components'] as $component)
                <tr>
                    <td class="text-left"><strong>{{{ $component['sku'] }}}</strong></td>
                    <td class="text-left">&nbsp;&nbsp;&nbsp;*&nbsp;{{{ $component['description'] }}}</td>
                    <td class="text-center">{{{ number_format($component['quantity'], 0) }}}</td>
                    <td class="text-right"></td>
                    <td class="text-right"></td>
                </tr>
                @endforeach
            @endif
        @endforeach
        @if ($order['shipping_handling'])
            <tr>
                <td class="text-left" colspan="4"><strong>SHIPPING &amp; HANDLING</strong></td>
                <td class="text-right"><strong>{{{ number_format($order['shipping_handling'], 2) }}}</strong></td>
            </tr>
        @endif
        </tbody>
    </table>

    <br>

    <div class="invoice-footer">
        <div class="pull-left">
            <strong>I hereby receive the above items:</strong>
            <br>
            <br>
            <br>
            ____________________________________________________
        </div>
        <div class="invoice-sum-total pull-right">
            <h3><strong>Total: <span class="text-success">{{{ number_format($order['total'], 2) }}} {{{ $order['currency'] }}}</span></strong></h3>
        </div>
    </div>
</div>
</body>
</html>
