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
	data.addColumn('number', 'Sales');
	data.addRows([
<?php
unset($rows);
$q=<<<EOQ
	SELECT DATE_FORMAT( order_date,  '%m/%d' ) AS MONTH , COUNT(*) AS SALES
	FROM sales
	WHERE DATE(order_date) >= DATE_SUB(NOW( ), INTERVAL 30 DAY ) 
	AND store = 'eBay'
	GROUP BY MONTH 
	ORDER BY order_date
EOQ;
$res = mysql_query($q);
while ($row = mysql_fetch_row($res)) $rows[] = "['${row[0]}', ${row[1]}]";
if (!empty($rows))
{
	array_pop($rows);
	echo implode(',', $rows);
}
?>
	]);
	var chart = new google.visualization.LineChart(document.getElementById('ebay_daily'));
	chart.draw(data, { title: '1 Month Daily Sales Count', hAxis: { textPosition: 'none' }, legend: 'none' });
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	var data = new google.visualization.DataTable();
	data.addColumn('string', 'Date');
	data.addColumn('number', 'Sales');
	data.addRows([
<?php
unset($rows);
$q=<<<EOQ
	SELECT STR_TO_DATE(CONCAT(YEAR(order_date), WEEKOFYEAR(order_date)-1, ' Monday'), '%X%V %W') AS WEEK, COUNT(*) AS SALES
	FROM sales
	WHERE DATE(order_date) >= DATE_SUB( CAST( DATE_FORMAT( NOW( ) ,  '%Y-%m-01' ) AS DATE ) , INTERVAL 24 WEEK ) 
	AND store = 'eBay'
	GROUP BY WEEK
	ORDER BY order_date
EOQ;
$res = mysql_query($q);
while ($row = mysql_fetch_row($res)) $rows[] = "['${row[0]}', ${row[1]}]";
if (!empty($rows))
{
	array_pop($rows);
	echo implode(',', $rows);
}
?>
	]);
	var chart = new google.visualization.LineChart(document.getElementById('ebay_weekly'));
	chart.draw(data, { title: '6 Months Weekly Sales Count', hAxis: { textPosition: 'none' }, legend: 'none' });
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	var data = new google.visualization.DataTable();
	data.addColumn('string', 'Date');
	data.addColumn('number', 'Sales');
	data.addRows([
<?php
unset($rows);
$q=<<<EOQ
	SELECT DATE_FORMAT( order_date,  '%b %Y' ) AS MONTH , COUNT(*) AS SALES
	FROM sales
	WHERE DATE(order_date) >= DATE_SUB( CAST( DATE_FORMAT( NOW( ) ,  '%Y-%m-01' ) AS DATE ) , INTERVAL 24 MONTH ) 
	AND store = 'eBay'
	GROUP BY MONTH 
	ORDER BY order_date
EOQ;
$res = mysql_query($q);
while ($row = mysql_fetch_row($res)) $rows[] = "['${row[0]}', ${row[1]}]";
if (!empty($rows))
{
	array_pop($rows);
	echo implode(',', $rows);
}
?>
	]);
	var chart = new google.visualization.LineChart(document.getElementById('ebay_monthly'));
	chart.draw(data, { title: '2 Years Monthly Sales Count', hAxis: { textPosition: 'none' }, legend: 'none' });
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
}
</script>
<style>.chart { width: 500px; height: 120px }</style>
</head>
<body>
<div class='chart' id='ebay_daily'></div>
<div class='chart' id='ebay_weekly'></div>
<div class='chart' id='ebay_monthly'></div>
</body>
</html>
