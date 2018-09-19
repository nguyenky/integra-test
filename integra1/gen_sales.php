<?php

require_once('system/config.php');
require_once('system/utils.php');
require_once('system/acl.php');

$user = Login('sales');

if (!empty($_REQUEST['from']) && !empty($_REQUEST['to']))
{
    mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
    mysql_select_db(DB_SCHEMA);

    $q = <<<EOQ
    SELECT id, store, agent, record_num, order_date, total_paid, email, city, state, country, zip, speed, fulfilment_desc, status_desc, supplier_cost, weight, supplier, shipping_cost, listing_fee, stamps_cost, stamps_material, stamps_service, stamps_weight, direct_supplier, direct_order_id, direct_subtotal, direct_core, direct_shipping, direct_total, invoice_num, invoice_total, store_item_id, sku, quantity, unit_price, line_total
    FROM v_sales
WHERE order_date >= '%s'
AND order_date <= '%s 23:59:59'
EOQ;
    $rows = mysql_query(query($q, $_REQUEST['from'], $_REQUEST['to']));

    if (empty($rows) || mysql_num_rows($rows) == 0)
        $error = 'Invalid date range format or no sales data for date range provided.';
    else
    {
        header("Content-type: text/csv");
        header("Content-Disposition: attachment; filename=sales.csv");
        header("Pragma: no-cache");
        header("Expires: 0");
        echo "id, store, agent, record_num, order_date, total_paid, email, city, state, country, zip, speed, fulfilment_desc, status_desc, supplier_cost, weight, supplier, shipping_cost, listing_fee, stamps_cost, stamps_material, stamps_service, stamps_weight, direct_supplier, direct_order_id, direct_subtotal, direct_core, direct_shipping, direct_total, invoice_num, invoice_total, store_item_id, sku, quantity, unit_price, line_total\r\n";
        while ($row = mysql_fetch_row($rows))
        {
            $cols = [];
            foreach ($row as $r)
                $cols[] = '"' . trim(str_replace("\n", '', str_replace("\r", '', str_replace("\t", '', $r)))) . '"';
            echo implode(',', $cols) . "\r\n";
        }
        mysql_close();
        exit;
    }
}

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
    <title>Download Sales Data</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <link rel="stylesheet" href="css/jquery-ui.css"/>
    <style>
        body
        {
            font-family: tahoma, verdana;
        }
    </style>
</head>
<body>
<center>
    <h2>Download Sales Data</h2>
    <br/>
    <form method="GET">
        From Date: <input class="datepicker" type="text" name="from" placeholder="YYYY-MM-DD" required/>
        <br/>
        <br/>
        To Date: <input class="datepicker" type="text" name="to" placeholder="YYYY-MM-DD" required/>
        <br/>
        <br/>
        <input type="submit" value="Submit" />
    </form>
    <br/>
    <b><?=$error?></b>
</center>
<script type="application/javascript" src="js/jquery.min.js"></script>
<script type="application/javascript" src="js/jquery-ui.min.js"></script>

<script>
    $(document).ready(function()
    {
        $( ".datepicker").datepicker({"dateFormat": "yy-mm-dd"});
    });
</script>
</body>
</html>