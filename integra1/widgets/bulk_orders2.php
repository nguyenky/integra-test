<?php

require_once('../system/config.php');
session_start();
$user = $_SESSION['user'];
if (empty($user)) exit;

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<title>Integra :: Bulk Orders</title>
<link rel="stylesheet" type="text/css" href="../css/bootstrap.min.css" /> 
<style>
	table
	{
		margin: 10px;
		width: 700px !important;
	}
    .nowrap
    {
        white-space: nowrap !important;
    }
</style>
</head>
<body>
<table class="table table-bordered table-condensed">
	<thead>
		<tr>
			<th>Date</th>
            <th>Supplier</th>
			<th>Order ID</th>
            <th>Tracking</th>
            <th>ETA</th>
            <th>Delivered</th>
			<th>Orders</th>
			<th>Incomplete/Cancelled</th>
		</tr>
	</thead>
	<tbody>
<?

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

$q = <<<EOQ
SELECT ds.order_id, COUNT(*), SUM(IF(o.status != 4 AND o.status != 90, 1, 0)), DATE_FORMAT(ds.order_date, '%m-%d'), ds.tracking_num, DATE_FORMAT(etd, '%m-%d'), CONCAT('W', ds.supplier), is_delivered
FROM direct_shipments ds, sales o, direct_shipments_sales dss
WHERE ds.order_id = dss.order_id
AND o.fulfilment = 3
AND o.id = dss.sales_id
AND DATE(ds.order_date) > DATE_SUB(CURDATE(), INTERVAL 10 DAY)
GROUP BY 1
ORDER BY ds.order_date DESC
EOQ;

$res = mysql_query($q);

while ($row = mysql_fetch_row($res))
{
	echo '<tr class="';
	echo ($row[2] > 0 ? 'danger' : 'success');
	echo '">';
	echo '<td class="nowrap">' . htmlentities($row[3]) . '</td>';
    echo '<td class="text-center">' . htmlentities($row[6]) . '</td>';
	echo '<td><a target="_blank" href="/bulk_orders.php?order_id=' . $row[0] . '">' . htmlentities($row[0]) . '</a></td>';
    echo '<td>' . htmlentities($row[4]) . '</td>';
    echo '<td class="nowrap">' . htmlentities($row[5]) . '</td>';
    echo '<td class="text-center"><input type="checkbox" order_id="' . $row[0] . '" class="check_delivered" ' . (empty($row[7]) ? '' : 'checked') . '/></td>';
    echo '<td class="text-right">' . htmlentities($row[1]) . '</td>';
	echo '<td class="text-right">' . htmlentities($row[2]) . '</td>';
	echo "</tr>\n";
}

mysql_close();
?>
</tbody>
</table>
<script src="/js/jquery.min.js"></script>
<script>
    $(document).ready(function()
    {
        $('.check_delivered').change(function()
        {
            $.ajax('/mark_delivered.php?order_id=' + $(this).attr('order_id') + '&delivered=' + ($(this).is(':checked') ? '1' : '0')).fail(function()
            {
                alert('There was an error while marking the order as delivered.\nPlease check your internet connection.');
            });
        });
    });
</script>
</body>
</html>