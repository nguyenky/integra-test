<?php

require_once('../system/config.php');
session_start();
$user = $_SESSION['user'];
if (empty($user)) exit;
mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

?>

<html>
<head>
<script type='text/javascript' src='http://www.google.com/jsapi'></script>
<script type='text/javascript'>
google.load('visualization', '1', {'packages':['corechart']});
google.setOnLoadCallback(drawChart);
function drawChart()
{
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	var data = new google.visualization.DataTable();
	data.addColumn('string', 'Date');
	data.addColumn('number', 'Warehouse 1');
	data.addColumn('number', 'Warehouse 2');
	data.addColumn('number', 'Warehouse 3');
	data.addRows([
<?php
unset($rows);
$q=<<<EOQ
	SELECT DATE_FORMAT( s.order_date,  '%b %Y' ) AS MONTH , SUM(IF(ds.supplier = 1, 1, 0)) AS IMC , SUM(IF(ds.supplier = 2, 1, 0)) AS SSF , SUM(IF(ds.supplier = 3, 1, 0)) AS ESI
	FROM direct_shipments ds INNER JOIN sales s ON ds.sales_id = s.id
	WHERE DATE(s.order_date) >= DATE_SUB( CAST( DATE_FORMAT( NOW( ) ,  '%Y-%m-01' ) AS DATE ) , INTERVAL 24 MONTH ) 
	GROUP BY MONTH 
	ORDER BY s.order_date
EOQ;
$res = mysql_query($q);
while ($row = mysql_fetch_row($res)) $rows[] = "['${row[0]}', ${row[1]}, ${row[2]}, ${row[3]}]";
if (!empty($rows))
{
	array_pop($rows);
	echo implode(',', $rows);
}
?>
	]);
	var chart = new google.visualization.ColumnChart(document.getElementById('supplier_monthly'));
	chart.draw(data, { isStacked: true, title: '2 Years Monthly Shipment Count Per Supplier', legend: 'none' });
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	var data = new google.visualization.DataTable();
	data.addColumn('string', 'Date');
	data.addColumn('number', 'eBay');
	data.addColumn('number', 'Amazon');
	data.addColumn('number', 'Manual');
	data.addRows([
<?php
unset($rows);
$q=<<<EOQ
	SELECT DATE_FORMAT( order_date,  '%b %Y' ) AS MONTH , SUM(IF(store = 'eBay', 1, 0)) AS EBAY , SUM(IF(store = 'Amazon', 1, 0)) AS AMAZON , SUM(IF(store = 'Manual', 1, 0)) AS MANUAL
	FROM sales
	WHERE DATE(order_date) >= DATE_SUB( CAST( DATE_FORMAT( NOW( ) ,  '%Y-%m-01' ) AS DATE ) , INTERVAL 24 MONTH ) 
	GROUP BY MONTH 
	ORDER BY order_date
EOQ;
$res = mysql_query($q);
while ($row = mysql_fetch_row($res)) $rows[] = "['${row[0]}', ${row[1]}, ${row[2]}, ${row[3]}]";
if (!empty($rows))
{
	array_pop($rows);
	echo implode(',', $rows);
}
?>
	]);
	var chart = new google.visualization.ColumnChart(document.getElementById('channel_monthly'));
	chart.draw(data, { isStacked: true, title: '2 Years Monthly Sales Count Per Channel', legend: 'none' });
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
}
</script>
<style>.chart { width: 500px; height: 200px }</style>
</head>
<body>
<div class='chart' id='supplier_monthly'></div>
<div class='chart' id='channel_monthly'></div>
</body>
</html>
